# Despliegue a producción (Laravel 12 Barbería)

## 1) Variables de entorno mínimas

Configura en `.env` de producción:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://tu-dominio.com`
- `DB_CONNECTION=mongodb`, `MONGODB_URI` (cadena de conexión de Atlas o del clúster de producción), `MONGO_DATABASE`
- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `SESSION_DRIVER=database`
- `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`
- IA Gemini:
  - `GEMINI_API_KEY`
  - `GEMINI_MODEL` (ejemplo: `gemini-1.5-pro`)
  - `GEMINI_BASE_URL` (opcional si se usa endpoint custom)
- Opcional Twilio:
  - `TWILIO_SID`
  - `TWILIO_AUTH_TOKEN`
  - `TWILIO_FROM`
  - `TWILIO_WHATSAPP_FROM`

## 2) Comandos de despliegue

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm ci
npm run build
php artisan optimize
```

Si no quieres datos demo en producción, omite `php artisan db:seed --force` o ajusta el `DatabaseSeeder`.

## 3) Scheduler (recordatorios automáticos)

Agregar en `crontab` del servidor (usuario del web server):

```cron
* * * * * cd /ruta/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

El comando programado `appointments:send-reminders` corre cada 10 minutos desde `routes/console.php`.

## 4) Queue worker

### Opción A: Supervisor

Archivo ejemplo `/etc/supervisor/conf.d/barberia-worker.conf`:

```ini
[program:barberia-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /ruta/proyecto/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/ruta/proyecto/storage/logs/worker.log
stopwaitsecs=3600
```

Luego:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start barberia-worker:*
```

### Opción B: systemd (alternativa)

Servicio ejemplo `/etc/systemd/system/barberia-queue.service`:

```ini
[Unit]
Description=Barberia Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /ruta/proyecto/artisan queue:work --sleep=3 --tries=3 --max-time=3600
WorkingDirectory=/ruta/proyecto

[Install]
WantedBy=multi-user.target
```

Comandos:

```bash
sudo systemctl daemon-reload
sudo systemctl enable barberia-queue
sudo systemctl start barberia-queue
```

## 5) Comprobaciones post-deploy

- `php artisan about`
- `php artisan migrate:status`
- `php artisan queue:monitor` (si aplica)
- `php artisan schedule:list`
- Revisar logs: `storage/logs/laravel.log`
- Validar rutas de chatbot:
  - `php artisan route:list | grep chatbot`

## 6) Validaciones funcionales del chatbot

Probar en ambiente productivo con un usuario autenticado:

1. `POST /chatbot/query` con preguntas de servicio y agenda.
2. `GET /chatbot/history` para confirmar persistencia de contexto.
3. `GET /chatbot/profile` para verificar perfil conversacional.
4. `GET /chatbot/learning-stats` para comprobar aprendizaje.
5. `POST /chatbot/train-history` para entrenamiento incremental.

Si hay errores 500, verificar:

- Credenciales de `GEMINI_API_KEY`.
- Conectividad saliente a APIs externas.
- Cache de Laravel (`php artisan config:clear && php artisan cache:clear`).

## 7) Checklist rápido

- [ ] HTTPS activo
- [ ] `APP_DEBUG=false`
- [ ] Cola en ejecución (Supervisor/systemd)
- [ ] Cron de scheduler activo
- [ ] Mail configurado
- [ ] Backups de BD y archivos públicos (`storage/app/public/comprobantes`)
- [ ] Variables Gemini configuradas
- [ ] Endpoints chatbot accesibles en producción
