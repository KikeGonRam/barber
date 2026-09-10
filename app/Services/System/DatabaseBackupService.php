<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use MongoDB\BSON\Document;
use RuntimeException;
use ZipArchive;

/**
 * Exporta cada colección de MongoDB como Extended JSON (un archivo por
 * colección, restaurable con `mongoimport --jsonArray`) y las empaqueta en
 * un zip. Sin binario externo (mongodump): el contenedor no trae las
 * MongoDB Database Tools, así que esto queda puro-PHP via ext-mongodb.
 * Compartido por Dashboard\DatabaseBackupController (Blade, sesión web) y
 * Api\Admin\System\BackupController (API, token Bearer -- frontend-urban).
 */
class DatabaseBackupService
{
    /**
     * Genera el zip y devuelve su ruta absoluta. El llamador es responsable
     * de servirlo y borrarlo (p.ej. ->deleteFileAfterSend(true)).
     */
    public function createZip(): string
    {
        $mongoDb = DB::connection('mongodb')->getDatabase();

        $backupDirectory = storage_path('app/backups');
        File::ensureDirectoryExists($backupDirectory);

        $stamp = now()->format('Y-m-d_His');
        $workDir = $backupDirectory.DIRECTORY_SEPARATOR."backup-{$stamp}";
        File::ensureDirectoryExists($workDir);

        try {
            foreach ($mongoDb->listCollectionNames() as $collectionName) {
                $documents = [];
                foreach ($mongoDb->selectCollection($collectionName)->find() as $document) {
                    $documents[] = Document::fromPHP($document)->toRelaxedExtendedJSON();
                }

                File::put(
                    $workDir.DIRECTORY_SEPARATOR."{$collectionName}.json",
                    '['.implode(',', $documents).']'
                );
            }

            $zipPath = $backupDirectory.DIRECTORY_SEPARATOR."backup-{$stamp}.zip";
            $zip = new ZipArchive;

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo crear el archivo zip de backup.');
            }

            foreach (File::files($workDir) as $file) {
                $zip->addFile($file->getPathname(), $file->getFilename());
            }

            $zip->close();
        } finally {
            File::deleteDirectory($workDir);
        }

        return $zipPath;
    }
}
