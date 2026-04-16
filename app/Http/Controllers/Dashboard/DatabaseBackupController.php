<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;
use RuntimeException;

class DatabaseBackupController extends Controller
{
    public function download(): BinaryFileResponse
    {
        $connectionName = config('database.default');
        $databaseConfig = config("database.connections.{$connectionName}");

        abort_unless(($databaseConfig['driver'] ?? null) === 'mysql', 500, 'El backup solo está soportado para MySQL.');

        $backupDirectory = storage_path('app/backups');
        File::ensureDirectoryExists($backupDirectory);

        $fileName = sprintf('backup-%s.sql', now()->format('Y-m-d_His'));
        $backupPath = $backupDirectory.DIRECTORY_SEPARATOR.$fileName;

        $command = [
            'mysqldump',
            '--host='.$databaseConfig['host'],
            '--port='.$databaseConfig['port'],
            '--user='.$databaseConfig['username'],
            '--password='.$databaseConfig['password'],
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--databases',
            $databaseConfig['database'],
        ];

        $process = new Process($command);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('No se pudo generar el backup de la base de datos.');
        }

        File::put($backupPath, $process->getOutput());

        return response()->download($backupPath, $fileName)->deleteFileAfterSend(true);
    }
}
