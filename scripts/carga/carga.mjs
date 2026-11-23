#!/usr/bin/env node
/*
 * Pruebas de carga de la API de UrbanBlade (HT-20 / T055-T056). Node 20+ sin dependencias: la carga es de tasa
 * abierta (peticiones por segundo programadas, no «usuarios que esperan»), así una API lenta no baja la presión.
 *
 *   node scripts/carga/carga.mjs <escenario> [--base URL] [--salida archivo.json]
 *
 * Escenarios contra STAGING (solo endpoints públicos; nunca credenciales reales):
 *   catalogo         barbershop/services/barbers/products, rampa de 2 a 15 req/s en 3 min (límite: 20 req/s por IP)
 *   disponibilidad   horarios de un barbero a 0.4 req/s durante 60 s (límite: 30 por minuto por IP)
 *   rafaga-limite    35 consultas de horarios seguidas: deben llegar 429 después de la 30
 *   login-limite     7 intentos con un correo inventado: 401/422 y luego 429 (límite: 5 por minuto)
 *
 * Escenarios contra la DEMOSTRACIÓN LOCAL (scripts/demo/levantar-demo.ps1, http://127.0.0.1:8001):
 *   demo-sesion      cliente demo: catálogo, horarios y «mis citas» con sesión, rampa de 2 a 10 req/s en 2 min
 *   demo-panel       administrador demo: panel, citas, pagos y reportes, 3 req/s durante 60 s
 *   demo-reserva-concurrente  20 reservas simultáneas del mismo horario: debe crearse UNA sola cita
 * Requieren UB_DEMO_CLAVE (la contraseña con la que se sembraron las cuentas demo-*@urbanblade.test).
 */
import { writeFileSync } from 'node:fs'

const args = process.argv.slice(2)
const escenario = args[0]
const opt = (k, d) => { const i = args.indexOf(k); return i >= 0 ? args[i + 1] : d }
const STAGING = 'https://api.urbanblade.com.mx/api/v1'
const DEMO = 'http://127.0.0.1:8001/api/v1'

const sleep = ms => new Promise(r => setTimeout(r, ms))
const pct = (arr, p) => { if (!arr.length) return null; const s = [...arr].sort((a, b) => a - b); return Math.round(s[Math.min(s.length - 1, Math.ceil(p / 100 * s.length) - 1)]) }

async function req(base, { method = 'GET', path, body, token }) {
  const t0 = performance.now()
  try {
    const r = await fetch(base + path, {
      method,
      headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}), ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: body ? JSON.stringify(body) : undefined,
      signal: AbortSignal.timeout(15000),
    })
    const text = await r.text()
    let json = null
    try { json = JSON.parse(text) } catch { /* no JSON */ }
    return { status: r.status, ms: performance.now() - t0, json }
  } catch (e) {
    return { status: e.name === 'TimeoutError' ? 'timeout' : 'error', ms: performance.now() - t0, json: null }
  }
}

/**
 * Freno de seguridad: el 23-nov-2026 una rampa a 15 req/s tumbó la API de staging (ECS reemplazó la tarea por
 * fallar la comprobación de salud). Si en las últimas 40 respuestas más del 5 % fallan (5xx o sin respuesta) o el
 * p95 pasa de 3 s, la prueba deja de enviar peticiones. Se desactiva con --sin-freno (solo en local).
 */
const freno = {
  activado: null,
  revisar(resultados) {
    if (this.activado || args.includes('--sin-freno') || resultados.length < 40) return
    const ultimas = resultados.slice(-40)
    const fallos = ultimas.filter(r => typeof r.status !== 'number' || r.status >= 500).length / ultimas.length
    const p95 = pct(ultimas.map(r => r.ms), 95)
    if (fallos > 0.05 || p95 > 3000) {
      this.activado = { segundo: Math.round(ultimas.at(-1).t), fallos: +(fallos * 100).toFixed(1), p95 }
      console.error(`FRENO: se detiene la carga en el segundo ${this.activado.segundo} (fallos ${this.activado.fallos} %, p95 ${p95} ms).`)
    }
  },
}

/** Tasa abierta: `etapas` = [{ segundos, rps }] con rampa lineal entre el rps anterior y el de la etapa. */
async function cargar(base, etapas, elegir) {
  // --sin-freno solo vale contra la demostración local: contra staging (o cualquier otro host) no se permite.
  if (args.includes('--sin-freno') && !['127.0.0.1', 'localhost', '::1', '[::1]'].includes(new URL(base).hostname)) {
    console.error('--sin-freno solo se permite contra una base local (127.0.0.1 o localhost).')
    process.exit(2)
  }
  const resultados = []
  const vuelo = []
  const t0 = performance.now()
  let previo = etapas[0].rps
  for (const { segundos, rps } of etapas) {
    const inicio = performance.now()
    let enviadas = 0
    while ((performance.now() - inicio) / 1000 < segundos) {
      const trans = (performance.now() - inicio) / 1000
      // Peticiones acumuladas que tocan hasta ahora = integral de la tasa (rampa lineal de `previo` a `rps`).
      // Antes se usaba trans × tasa actual, que adelantaba envíos: la rampa «a 15 req/s» llegó a ~22 req/s.
      const debidas = previo * trans + (rps - previo) * trans * trans / (2 * segundos)
      if (freno.activado) break
      if (enviadas < debidas) {
        const r = elegir()
        vuelo.push(req(base, r).then(x => { resultados.push({ ...x, nombre: r.nombre, t: (performance.now() - t0) / 1000 }); freno.revisar(resultados) }))
        enviadas++
      } else {
        await sleep(5)
      }
    }
    previo = rps
    if (freno.activado) break
  }
  await Promise.all(vuelo)
  return { resultados, duracion: (performance.now() - t0) / 1000 }
}

function resumen(titulo, { resultados, duracion }, extra = {}) {
  const porNombre = {}
  for (const r of resultados) (porNombre[r.nombre] ||= []).push(r)
  const fila = (lista) => {
    const ok = lista.filter(r => typeof r.status === 'number' && r.status < 400)
    const estados = {}
    for (const r of lista) estados[r.status] = (estados[r.status] || 0) + 1
    const ms = ok.map(r => r.ms)
    return { peticiones: lista.length, estados, p50: pct(ms, 50), p95: pct(ms, 95), p99: pct(ms, 99), max: ms.length ? Math.round(Math.max(...ms)) : null }
  }
  const out = {
    escenario: titulo,
    fecha: new Date().toISOString(),
    duracion_s: Math.round(duracion),
    rps_logrados: +(resultados.length / duracion).toFixed(2),
    total: fila(resultados),
    por_endpoint: Object.fromEntries(Object.entries(porNombre).map(([k, v]) => [k, fila(v)])),
    ...extra,
    freno: freno.activado,
  }
  console.log(JSON.stringify(out, null, 2))
  const salida = opt('--salida')
  if (salida) writeFileSync(salida, JSON.stringify(out, null, 2))
  return out
}

async function idsCatalogo(base, token) {
  const barbers = (await req(base, { path: '/barbers', token })).json?.data ?? []
  const services = (await req(base, { path: '/services', token })).json?.data ?? []
  if (!barbers.length || !services.length) throw new Error('El catálogo no devolvió barberos o servicios.')
  return { barber: barbers[0].id, service: services[0].id }
}

const enDias = (n) => new Date(Date.now() + n * 86400000).toISOString().slice(0, 10)

async function loginDemo(base, email) {
  const clave = process.env.UB_DEMO_CLAVE
  if (!clave) throw new Error('Define UB_DEMO_CLAVE con la contraseña de las cuentas de demostración.')
  const r = await req(base, { method: 'POST', path: '/auth/login', body: { email, password: clave, device_name: 'carga', plataforma: 'web' } })
  if (!r.json?.token) throw new Error(`No se pudo iniciar sesión con ${email} (HTTP ${r.status}).`)
  return r.json.token
}

const escenarios = {
  async catalogo() {
    const base = opt('--base', STAGING)
    const rutas = [['barbershop', '/barbershop'], ['services', '/services'], ['barbers', '/barbers'], ['products', '/products']]
    let i = 0
    const r = await cargar(base, [{ segundos: 30, rps: 2 }, { segundos: 60, rps: 8 }, { segundos: 90, rps: 15 }], () => {
      const [nombre, path] = rutas[i++ % rutas.length]
      return { nombre, path }
    })
    return resumen('catalogo', r)
  },
  async disponibilidad() {
    const base = opt('--base', STAGING)
    const { barber, service } = await idsCatalogo(base)
    const path = `/availability/slots?barber_id=${barber}&service_id=${service}&date=${enDias(1)}`
    return resumen('disponibilidad', await cargar(base, [{ segundos: 60, rps: 0.4 }], () => ({ nombre: 'slots', path })))
  },
  async 'rafaga-limite'() {
    const base = opt('--base', STAGING)
    const { barber, service } = await idsCatalogo(base)
    const path = `/availability/slots?barber_id=${barber}&service_id=${service}&date=${enDias(1)}`
    const resultados = []
    const t0 = performance.now()
    for (let n = 1; n <= 35; n++) resultados.push({ ...(await req(base, { path })), nombre: 'slots', n })
    const primer429 = resultados.find(r => r.status === 429)?.n ?? null
    return resumen('rafaga-limite', { resultados, duracion: (performance.now() - t0) / 1000 }, { primer_429_en_la_peticion: primer429 })
  },
  async 'login-limite'() {
    const base = opt('--base', STAGING)
    const email = `carga-limite-${Date.now()}@urbanblade.test`
    const resultados = []
    const t0 = performance.now()
    for (let n = 1; n <= 7; n++) resultados.push({ ...(await req(base, { method: 'POST', path: '/auth/login', body: { email, password: 'no-es-una-clave-real', device_name: 'carga', plataforma: 'web' } })), nombre: 'login', n })
    const primer429 = resultados.find(r => r.status === 429)?.n ?? null
    return resumen('login-limite', { resultados, duracion: (performance.now() - t0) / 1000 }, { primer_429_en_la_peticion: primer429 })
  },
  async 'demo-sesion'() {
    const base = opt('--base', DEMO)
    const token = await loginDemo(base, 'demo-cliente@urbanblade.test')
    const { barber, service } = await idsCatalogo(base, token)
    const rutas = [['services', '/services'], ['barbers', '/barbers'], ['slots', `/availability/slots?barber_id=${barber}&service_id=${service}&date=${enDias(1)}`], ['mis-citas', '/appointments'], ['perfil', '/auth/me']]
    let i = 0
    const r = await cargar(base, [{ segundos: 30, rps: 2 }, { segundos: 90, rps: 10 }], () => { const [nombre, path] = rutas[i++ % rutas.length]; return { nombre, path, token } })
    return resumen('demo-sesion', r)
  },
  async 'demo-panel'() {
    const base = opt('--base', DEMO)
    const token = await loginDemo(base, 'demo-administrador@urbanblade.test')
    const rutas = [['dashboard', '/dashboard'], ['citas', '/appointments'], ['pagos', '/payments'], ['reportes-ingresos', '/admin/reports/revenue?period=month']]
    let i = 0
    const r = await cargar(base, [{ segundos: 60, rps: 3 }], () => { const [nombre, path] = rutas[i++ % rutas.length]; return { nombre, path, token } })
    return resumen('demo-panel', r)
  },
  async 'demo-reserva-concurrente'() {
    const base = opt('--base', DEMO)
    const token = await loginDemo(base, 'demo-cliente@urbanblade.test')
    const { barber, service } = await idsCatalogo(base, token)
    const fecha = enDias(9)
    const slots = (await req(base, { path: `/availability/slots?barber_id=${barber}&service_id=${service}&date=${fecha}`, token })).json?.slots ?? []
    const libre = slots.find(s => s.available !== false) ?? slots[0]
    const hora = (typeof libre === 'string' ? libre : libre?.time ?? libre?.hora ?? libre?.start)?.slice(0, 5)
    if (!hora) throw new Error('No hay horarios libres para la prueba.')
    const cuerpo = { barber_id: barber, service_id: service, fecha, hora_inicio: hora }
    const t0 = performance.now()
    const resultados = (await Promise.all(Array.from({ length: 20 }, () => req(base, { method: 'POST', path: '/appointments', body: cuerpo, token })))).map(r => ({ ...r, nombre: 'reservar' }))
    const creadas = resultados.filter(r => r.status === 201 || r.status === 200)
    // Limpieza: el cliente cancela lo creado con su propio endpoint (DELETE), y se comprueba que funcionó.
    const limpieza = { canceladas: 0, fallidas: 0 }
    for (const c of creadas) {
      const id = c.json?.data?.id ?? c.json?.appointment?.id ?? c.json?.id
      const r = id ? await req(base, { method: 'DELETE', path: `/appointments/${id}`, token }) : { status: 'sin-id' }
      if (r.status === 200 || r.status === 204) limpieza.canceladas++
      else limpieza.fallidas++
    }
    if (limpieza.fallidas) console.error(`AVISO: ${limpieza.fallidas} cita(s) de prueba no se pudieron cancelar; revísalas en la demostración.`)
    return resumen('demo-reserva-concurrente', { resultados, duracion: (performance.now() - t0) / 1000 }, { horario: `${fecha} ${hora}`, citas_creadas: creadas.length, limpieza })
  },
}

if (!escenarios[escenario]) {
  console.error(`Escenarios: ${Object.keys(escenarios).join(', ')}`)
  process.exit(2)
}
await escenarios[escenario]()
