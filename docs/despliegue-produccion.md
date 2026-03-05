# Despliegue a producción (Laravel 12 Barbería)

## 1) Variables de entorno mínimas

Configura en `.env` de producción:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://tu-dominio.com`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `QUEUE_CONNECTION=database` (o `redis`)
- `CACHE_STORE=database` (o `redis`)
- `SESSION_DRIVER=database`
- `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`
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

## 6) Checklist rápido

- [ ] HTTPS activo
- [ ] `APP_DEBUG=false`
- [ ] Cola en ejecución (Supervisor/systemd)
- [ ] Cron de scheduler activo
- [ ] Mail configurado
- [ ] Backups de BD y archivos públicos (`storage/app/public/comprobantes`)
