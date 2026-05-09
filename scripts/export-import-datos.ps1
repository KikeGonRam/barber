# Exportar toda la base de datos a SQL
$fecha = Get-Date -Format yyyyMMdd
$backupFile = "export_${fecha}.sql"
docker compose exec mysql mysqldump -u root -p$env:DB_ROOT_PASSWORD laravel > $backupFile
Write-Host "Backup completo exportado a $backupFile"

# Exportar solo la tabla appointments a SQL
$tablaFile = "appointments_${fecha}.sql"
docker compose exec mysql mysqldump -u root -p$env:DB_ROOT_PASSWORD laravel appointments > $tablaFile
Write-Host "Tabla appointments exportada a $tablaFile"

# Exportar appointments a CSV (dentro del contenedor)
$csvFile = "/tmp/appointments_${fecha}.csv"
docker compose exec mysql mysql -u root -p$env:DB_ROOT_PASSWORD -e "SELECT * FROM appointments INTO OUTFILE '$csvFile' FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';" laravel
# Copia el CSV a tu máquina local
docker compose cp barber-mysql:$csvFile ./appointments_${fecha}.csv
Write-Host "Tabla appointments exportada a appointments_${fecha}.csv"

# Importar un SQL a la base de datos
# (Cambia 'archivo.sql' por el nombre de tu archivo)
# docker compose exec -T mysql mysql -u root -p$env:DB_ROOT_PASSWORD laravel < archivo.sql

# Importar un CSV a la tabla appointments
# (Cambia 'archivo.csv' por el nombre de tu archivo)
# docker compose cp archivo.csv barber-mysql:/tmp/archivo.csv
# docker compose exec mysql mysql -u root -p$env:DB_ROOT_PASSWORD -e "LOAD DATA INFILE '/tmp/archivo.csv' INTO TABLE appointments FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';" laravel
