<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // Imágenes públicas (productos, portafolio, avatares, fotos). Con
        // UPLOADS_BUCKET va a un bucket S3 de lectura pública; sin él, queda en
        // disco local como siempre.
        'public' => env('UPLOADS_BUCKET') ? [
            'driver' => 's3',
            'bucket' => env('UPLOADS_BUCKET'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'url' => env('UPLOADS_URL', 'https://'.env('UPLOADS_BUCKET').'.s3.'.env('AWS_DEFAULT_REGION', 'us-east-1').'.amazonaws.com'),
            'throw' => false,
            'report' => false,
        ] : [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Comprobantes de transferencia y recibos PDF (datos de pago de
        // clientes). Con RECEIPTS_BUCKET va a un bucket S3 PRIVADO y se sirve solo
        // con URLs firmadas temporales (ver App\Support\ReceiptStorage). Sin
        // él, usa la misma carpeta local que 'public' para no perder archivos
        // existentes en desarrollo. Las credenciales salen del rol de la tarea ECS.
        'receipts' => env('RECEIPTS_BUCKET') ? [
            'driver' => 's3',
            'bucket' => env('RECEIPTS_BUCKET'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'throw' => false,
            'report' => false,
        ] : [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
