<?php

namespace App\Http\Controllers\Api\Admin\System;

use App\Http\Controllers\Controller;
use App\Services\System\DatabaseBackupService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Respaldo de la base de datos (rol administrador): mismo export que
 * Dashboard\DatabaseBackupController (Blade, sesion web), servido aqui via
 * token Bearer para el boton "Descargar respaldo" en frontend-urban.
 */
class BackupController extends Controller
{
    public function __construct(private readonly DatabaseBackupService $backups) {}

    public function download(): BinaryFileResponse
    {
        $zipPath = $this->backups->createZip();

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(true);
    }
}
