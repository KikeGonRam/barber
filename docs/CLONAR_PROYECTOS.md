# Cómo clonar los proyectos UrbanBlade

UrbanBlade reúne repositorios independientes que integran sus cambios en **`main`**
mediante ramas de trabajo y pull requests. El alcance de desarrollo actual incluye
backend, frontend web y Android; `spark` se conserva como proyecto de analítica.

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
Copy-Item .env.example .env
```

Configura conexiones locales aisladas siguiendo el [README](../README.md).
No copies credenciales operativas como requisito de una primera instalación.

`setup.ps1` es una alternativa de bootstrap que instala dependencias y ejecuta
migraciones con la configuración seleccionada. Revísalo antes de usarlo y confirma
el destino de datos; no forma parte de una actualización documental ni de CI.

Documentación completa:
[`barber/docs/DOCUMENTACION_TECNICA.md`](DOCUMENTACION_TECNICA.md).

> Para correr pruebas: `.\test.ps1`, **nunca** `php artisan test` directamente. La
> razón está explicada en `barber/AGENTS.md`: bare `php artisan test` apunta a la base
> de Atlas compartida y ya borró datos reales una vez.

## 2. frontend-urban (web Nuxt)

Necesita el backend arriba en `http://127.0.0.1:8000`.

```powershell
cd frontend-urban
npm ci --legacy-peer-deps
Copy-Item .env.example .env
npm run dev
```

Queda en `http://localhost:3000`. `--legacy-peer-deps` es necesario por un bug conocido
de npm con el grafo de peer-dependencies de Nuxt 4.

Documentación completa: [README del frontend](https://github.com/KikeGonRam/frontend_Urbanblade/blob/main/README.md).

## 3. spark (analítica)

```powershell
cd spark
.\setup-spark.ps1
```

El script instala/valida WSL2, Java, Miniconda y el entorno `spark_env`, y se detiene
pidiendo el `.env` real (usa `.env.example` como plantilla). Vuelve a correr
`.\setup-spark.ps1` para validar la conexión a MongoDB Atlas.

Documentación completa: [guía de unidades de spark](https://github.com/KikeGonRam/spark/blob/main/unidades/README.md) y
[comandos de spark](https://github.com/KikeGonRam/spark/blob/main/unidades/COMANDOS.txt).

> En `spark` el usuario de Atlas es de **solo lectura**: los scripts leen `barber_db` y
> los derivados se escriben aparte, en `urbanblade_analytics`. Ver
> [`PLAN_ENDURECIMIENTO_ATLAS.md`](PLAN_ENDURECIMIENTO_ATLAS.md).

## 4. UrbanBladeMobile (Android)

No tiene script de arranque: se abre con **Android Studio** (→ Open → la carpeta
`UrbanBladeMobile`) y se espera la sincronización de Gradle.

Antes de ejecutar necesitas `local.properties` con `GOOGLE_CLIENT_ID` y, para pagos con
tarjeta, `STRIPE_PUBLISHABLE_KEY` (claves públicas; nunca la secreta). El backend debe
estar arriba y, en celular físico, correr `adb reverse tcp:8000 tcp:8000`.

Documentación completa: [README de Android](https://github.com/al140605/UrbanBladeMobile/blob/main/README.md).

---

## Verificar que quedaste en la rama correcta

```bash
cd barber && git branch --show-current
```

Un clon nuevo parte de la rama predeterminada del repositorio. Durante el trabajo
es normal estar en una rama propia: su nombre no indica que esté retirada.
Para consultar la referencia de integración sin cambiar de rama ni alterar el
árbol de trabajo:

```bash
git fetch origin
git log -1 --oneline origin/main
```

## Sobre los archivos `.env`

El backend administra las conexiones a MongoDB. Web y Android consumen la API;
no necesitan credenciales directas de Atlas. `spark` tiene su configuración de
lectura y analítica. Los archivos `.env` reales no se publican ni se comparten en
issues o mensajes públicos. Los procedimientos de rotación están en
[PLAN_ROTACION_CREDENCIALES.md](PLAN_ROTACION_CREDENCIALES.md).

Si nunca has usado Git ni Docker, empieza mejor por
[`COMO_DESCARGAR_LOS_PROYECTOS.txt`](COMO_DESCARGAR_LOS_PROYECTOS.txt), que explica
todo desde cero.
