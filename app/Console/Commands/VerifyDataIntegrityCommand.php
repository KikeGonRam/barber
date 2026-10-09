<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Connection as MongoConnection;

/**
 * Revisión de integridad referencial de MongoDB (solo lectura). Mongo no tiene llaves foráneas: nada impide que una
 * cita apunte a un cliente borrado. Este comando cuenta esas referencias rotas por colección y campo, y falla
 * (código 1) si se pasa de --max-orphans, para poder usarlo en el CI o en un cron. Nunca modifica datos: limpiar
 * citas activas huérfanas es trabajo de `data:cancel-orphans`.
 */
class VerifyDataIntegrityCommand extends Command
{
    protected $signature = 'data:verify-integrity {--max-orphans=0 : Huérfanos tolerados en total antes de fallar}';

    protected $description = 'Cuenta referencias rotas entre colecciones (solo lectura)';

    /** colección => [campo => colección destino] */
    private const RELATIONS = [
        'appointments' => ['client_id' => 'clients', 'barber_id' => 'barbers', 'service_id' => 'services'],
        'payments' => ['appointment_id' => 'appointments'],
        'loyalty_transactions' => ['client_id' => 'clients'],
        'client_memberships' => ['client_id' => 'clients'],
        'orders' => ['client_id' => 'clients'],
        'clients' => ['user_id' => 'users'],
        'barbers' => ['user_id' => 'users'],
        'mobile_api_tokens' => ['user_id' => 'users'],
        'work_images' => ['work_id' => 'works'],
        'comments' => ['work_id' => 'works'],
        'reactions' => ['work_id' => 'works'],
    ];

    public function handle(): int
    {
        /** @var MongoConnection $connection */
        $connection = DB::connection('mongodb');
        $db = $connection->getMongoDB();

        /** @var array<string, array<string, true>> $ids */
        $ids = [];
        $idsOf = function (string $collection) use ($db, &$ids): array {
            if (! isset($ids[$collection])) {
                $ids[$collection] = [];
                foreach ($db->selectCollection($collection)->find([], ['projection' => ['_id' => 1]]) as $doc) {
                    $ids[$collection][(string) $doc['_id']] = true;
                }
            }

            return $ids[$collection];
        };

        $rows = [];
        $total = 0;
        foreach (self::RELATIONS as $collection => $fields) {
            foreach ($fields as $field => $target) {
                $known = $idsOf($target);
                $docs = 0;
                $orphans = 0;
                foreach ($db->selectCollection($collection)->find([], ['projection' => [$field => 1]]) as $doc) {
                    $docs++;
                    $value = $doc[$field] ?? null;
                    if ($value !== null && $value !== '' && ! isset($known[(string) $value])) {
                        $orphans++;
                    }
                }
                $total += $orphans;
                $rows[] = ["{$collection}.{$field}", $target, $docs, $orphans];
            }
        }

        $this->table(['Referencia', 'Apunta a', 'Documentos', 'Huérfanos'], $rows);
        $max = (int) $this->option('max-orphans');
        $this->line("Huérfanos en total: {$total} (tolerados: {$max}). Solo lectura: no se modificó nada.");

        return $total > $max ? self::FAILURE : self::SUCCESS;
    }
}
