# Preparar un entorno local de UrbanBlade

Backend, frontend y Android son repositorios independientes.
Spark se conserva como proyecto académico de analítica.
Cada componente tiene su `main`; el trabajo se realiza en ramas y PR.

## Repositorios

| Componente | Repositorio |
| --- | --- |
| API Laravel | [barber](https://github.com/KikeGonRam/barber) |
| Web Nuxt | [frontend_Urbanblade](https://github.com/KikeGonRam/frontend_Urbanblade) |
| Android | [UrbanBladeMobile](https://github.com/al140605/UrbanBladeMobile) |
| Analítica académica | [spark](https://github.com/KikeGonRam/spark) |

No es necesario clonar ni arrancar los cuatro para actualizar documentación.

## Backend aislado

Requisitos: Git, Docker con Compose y Node.js/npm para los recursos de Laravel.
Usa las versiones declaradas por el repositorio, no las de una guía antigua.

```powershell
git clone https://github.com/KikeGonRam/barber.git barber
cd barber
Copy-Item .env.example .env
Copy-Item .env.development.example .env.development
```

Revisa primero [la plantilla](../.env.development.example), el
[Compose base](../docker-compose.yml) y el
[overlay de desarrollo](../docker-compose.development.yml).
El overlay define `mongo-dev`/rsdev y la base `urbanblade_dev`, separados
de `mongo-test`/barber_db_test. No necesita las credenciales operativas de Atlas.

Configura una APP_KEY exclusiva de desarrollo en .env.development antes de
arrancar; el overlay hereda otras variables de .env. Mantén ambos archivos
ignorados y no copies la clave de producción.

Con el entorno local verificado:

```powershell
docker compose --env-file .env.development -f docker-compose.yml -f docker-compose.development.yml up -d --build
npm ci
npm run build
docker compose --env-file .env.development -f docker-compose.yml -f docker-compose.development.yml ps
```

El Compose base carga también configuración de la aplicación: verifica sus
`env_file` y requisitos locales. No sobrescribas un `.env` ya existente ni
copies el de producción. Si falta una variable, obtén un valor de desarrollo
adecuado en lugar de apuntar a la base real.

La API local está en el puerto 8000; Mailpit, en 8025.
`setup.ps1` es otra herramienta de bootstrap y puede ejecutar migraciones.
No se ejecuta para revisar documentación ni sobre datos operativos.

## Frontend

Desde una carpeta hermana:

```powershell
git clone https://github.com/KikeGonRam/frontend_Urbanblade.git frontend-urban
cd frontend-urban
npm ci --legacy-peer-deps
Copy-Item .env.example .env
npm run dev
```

La aplicación usa el puerto 3000 y consume la API local con `/api/v1`.
Todo valor `NUXT_PUBLIC_*` se expone al cliente.
La guía vigente está en el
[README del frontend](https://github.com/KikeGonRam/frontend_Urbanblade/blob/main/README.md).

## Android y analítica

Sigue las instrucciones de sus repositorios: dependen de sus herramientas,
contratos y entornos. No copies una receta de WSL/Gradle antigua del backend
como si fuera la configuración actual de esos componentes.

## Pruebas y colaboración

Backend: exclusivamente `.\test.ps1`, con montaje y Mongo de pruebas comprobados.
Consulta [PRUEBAS.md](PRUEBAS.md).
Para cuentas y permisos, [ACCESOS.md](ACCESOS.md).

Antes de trabajar: `git status --short`, `git fetch origin` y una rama desde
`origin/main`. Conserva cambios ajenos y revisa el diff antes de publicar.
Un push o merge puede activar integraciones externas; incluso una rama de
documentación necesita revisar ese alcance.
