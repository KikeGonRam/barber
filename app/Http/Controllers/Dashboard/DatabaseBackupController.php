<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use MongoDB\BSON\Document;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class DatabaseBackupController extends Controller
{
    /**
     * Exports every MongoDB collection as MongoDB Extended JSON (one file per
     * collection, restorable with `mongoimport --jsonArray`) and ships them
     * zipped. No external binary (mongodump) required — the container doesn't
     * ship the MongoDB Database Tools, so this stays pure-PHP via ext-mongodb.
     */
    public function download(): BinaryFileResponse
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

        return response()->download($zipPath, "backup-{$stamp}.zip")->deleteFileAfterSend(true);
    }
}
