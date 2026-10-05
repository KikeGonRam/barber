# Cómo clonar los proyectos UrbanBlade

UrbanBlade son **cuatro repositorios independientes**, todos trabajando sobre la misma
rama: **`main`**. No hay ramas de funcionalidad.

> **Corregido el 2026-10-02.** La versión anterior de esta guía mandaba clonar
> `feature/mongodb-migration` (barber) y `urbanblade-analytics` (spark), y decía que no
> se usara `main`. Las tres afirmaciones eran falsas: esas ramas se retiraron y ya no
> existen ni localmente. Si seguiste esa guía, clonaste una rama equivocada.

---

## Los cuatro proyectos

| Proyecto | Repositorio | Carpeta local | Qué es |
|---|---|---|---|
| **barber** | `https://github.com/KikeGonRam/barber.git` | `barber` | Backend: API JSON en Laravel 13 + MongoDB |
| **frontend-urban** | `https://github.com/KikeGonRam/frontend_Urbanblade.git` | `frontend-urban` | Web: Nuxt 4 + Vue 3 (el producto real) |
| **spark** | `https://github.com/KikeGonRam/spark.git` | `spark` | Analítica: PySpark + Streamlit (proyecto escolar) |
| **UrbanBladeMobile** | `https://github.com/al140605/UrbanBladeMobile.git` | `UrbanBladeMobile` | Android: Kotlin + Jetpack Compose |

## Clonar todo de una vez

```bash
git clone https://github.com/KikeGonRam/barber.git barber
git clone https://github.com/KikeGonRam/frontend_Urbanblade.git frontend-urban
git clone https://github.com/KikeGonRam/spark.git spark
git clone https://github.com/al140605/UrbanBladeMobile.git UrbanBladeMobile
```

Cada repo se clona en `main`, la rama de integración protegida; el trabajo se hace en
ramas propias que entran por PR (ver `git-commit-conventions`). Para verificar:

```bash
cd barber && git branch --show-current        # main
cd ../spark && git branch --show-current      # main
```

## 1. barber (backend Laravel)

```powershell
cd barber
.\setup.ps1
```

El script se detiene pidiendo el archivo `.env` real — pídelo al responsable del
proyecto (contiene credenciales de MongoDB Atlas, nunca se sube a git) y colócalo en
`barber\.env` usando `.env.example` como referencia. Vuelve a correr `.\setup.ps1` para
levantar los contenedores (necesita **Docker Desktop** abierto).

Documentación completa:
[`barber/docs/DOCUMENTACION_TECNICA.md`](DOCUMENTACION_TECNICA.md).

> Para correr pruebas: `.\test.ps1`, **nunca** `php artisan test` directamente. La
> razón está explicada en `barber/AGENTS.md`: bare `php artisan test` apunta a la base
> de Atlas compartida y ya borró datos reales una vez.

## 2. frontend-urban (web Nuxt)

Necesita el backend arriba en `http://127.0.0.1:8000`.

```powershell
cd frontend-urban
npm install --legacy-peer-deps
npm run dev
```

Queda en `http://localhost:3000`. `--legacy-peer-deps` es necesario por un bug conocido
de npm con el grafo de peer-dependencies de Nuxt 4.

Documentación completa: [`frontend-urban/README.md`](../../frontend-urban/README.md).

## 3. spark (analítica)

```powershell
cd spark
.\setup-spark.ps1
```

El script instala/valida WSL2, Java, Miniconda y el entorno `spark_env`, y se detiene
pidiendo el `.env` real (usa `.env.example` como plantilla). Vuelve a correr
`.\setup-spark.ps1` para validar la conexión a MongoDB Atlas.

Documentación completa: [`spark/unidades/README.md`](../../spark/unidades/README.md) y
[`spark/unidades/COMANDOS.txt`](../../spark/unidades/COMANDOS.txt).

> En `spark` el usuario de Atlas es de **solo lectura**: los scripts leen `barber_db` y
> los derivados se escriben aparte, en `urbanblade_analytics`. Ver
> [`PLAN_ENDURECIMIENTO_ATLAS.md`](PLAN_ENDURECIMIENTO_ATLAS.md).

## 4. UrbanBladeMobile (Android)

No tiene script de arranque: se abre con **Android Studio** (→ Open → la carpeta
`UrbanBladeMobile`) y se espera la sincronización de Gradle.

Antes de ejecutar necesitas `local.properties` con `GOOGLE_CLIENT_ID` y, para pagos con
tarjeta, `STRIPE_PUBLISHABLE_KEY` (claves públicas; nunca la secreta). El backend debe
estar arriba y, en celular físico, correr `adb reverse tcp:8000 tcp:8000`.

Documentación completa: [`UrbanBladeMobile/README.md`](../../UrbanBladeMobile/README.md).

---

## Verificar que quedaste en la rama correcta

```bash
cd barber && git branch --show-current
```

Debe imprimir **`main`** en los cuatro repos. Si ves `feature/mongodb-migration`,
`urbanblade-analytics` o cualquier otra cosa, estás en una rama retirada:

```bash
git fetch origin
git checkout main
git pull origin main
```

## Sobre los archivos `.env`

Los cuatro proyectos comparten la misma base de datos (MongoDB Atlas). El `.env` de
cada uno lo entrega el responsable del proyecto — **nunca se comparte por chat ni se
sube a ningún repositorio**. Las credenciales comprometidas y su rotación están
documentadas en [`PLAN_ROTACION_CREDENCIALES.md`](PLAN_ROTACION_CREDENCIALES.md).

Si nunca has usado Git ni Docker, empieza mejor por
[`COMO_DESCARGAR_LOS_PROYECTOS.txt`](COMO_DESCARGAR_LOS_PROYECTOS.txt), que explica
todo desde cero.
