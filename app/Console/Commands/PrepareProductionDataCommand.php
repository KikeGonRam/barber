<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\Database;
use MongoDB\Laravel\Connection as MongoConnection;

/**
 * Deja la base lista para producción: borra el movimiento de prueba (citas, pagos, pedidos, lealtad, bitácora…) y la
 * cuenta de prueba indicada, y conserva usuarios, roles, catálogo y portafolio. Es destructivo e irreversible, así
 * que por defecto SOLO muestra qué borraría; para ejecutarlo exige --apply y --confirm-backup (haber tomado antes un
 * `mongodump`). No se ejecuta solo: no está en el scheduler.
 */
class PrepareProductionDataCommand extends Command
{
    protected $signature = 'data:prepare-production
        {--test-user=Test de UrbanBlade : Nombre exacto de la cuenta de prueba a borrar (junto con su perfil y sus registros)}
        {--apply : Borra de verdad; sin esta opción solo muestra lo que haría}
        {--confirm-backup : Confirma que ya se tomó un mongodump reciente (obligatoria con --apply)}';

    protected $description = 'Borra el movimiento y la cuenta de prueba para preparar producción (simulacro por defecto)';

    /** Colecciones de movimiento: se vacían por completo. */
    private const PURGE = [
        'appointments', 'payments', 'orders', 'loyalty_transactions', 'client_memberships', 'membership_invoices',
        'gift_cards', 'client_packages', 'referrals', 'raffle_results', 'cash_closes', 'waitlists', 'chat_messages',
        'barber_reviews', 'activities', 'database_notifications', 'no_show_fees', 'inventory_movements',
    ];

    /** colección => campo con el id del usuario de prueba (o de su perfil) */
    private const USER_SCOPED = [
        'mobile_api_tokens' => 'user_id',
        'push_subscriptions' => 'user_id',
        'comments' => 'user_id',
        'reactions' => 'user_id',
        'saved_works' => 'user_id',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        if ($apply && ! $this->option('confirm-backup')) {
            $this->error('--apply exige --confirm-backup: toma antes un mongodump y confírmalo.');

            return self::FAILURE;
        }

        /** @var MongoConnection $connection */
        $connection = DB::connection('mongodb');
        $db = $connection->getDatabase();

        $plan = [];
        foreach (self::PURGE as $collection) {
            $plan[] = [$collection, 'todo el movimiento', [], $db->selectCollection($collection)->countDocuments([])];
        }

        $user = $db->selectCollection('users')->findOne(['name' => (string) $this->option('test-user')]);
        if ($user !== null) {
            $userId = (string) $user['_id'];
            foreach (['clients', 'barbers'] as $profile) {
                $doc = $db->selectCollection($profile)->findOne(['user_id' => $userId]);
                if ($doc !== null) {
                    $plan[] = [$profile, 'perfil de la cuenta de prueba', ['_id' => $doc['_id']], 1];
                }
            }
            foreach (self::USER_SCOPED as $collection => $field) {
                $filter = [$field => $userId];
                $plan[] = [$collection, 'registros de la cuenta de prueba', $filter, $db->selectCollection($collection)->countDocuments($filter)];
            }
            $plan[] = ['users', 'cuenta de prueba', ['_id' => new ObjectId($userId)], 1];
        } else {
            $this->warn('No se encontró la cuenta de prueba "'.$this->option('test-user').'": solo se limpia el movimiento.');
        }

        $this->table(['Colección', 'Alcance', 'Documentos a borrar'], array_map(fn ($r) => [$r[0], $r[1], $r[3]], $plan));
        $total = array_sum(array_column($plan, 3));

        if (! $apply) {
            $this->info("Se borrarían {$total} documentos. Nada cambió: agrega --apply --confirm-backup para hacerlo.");

            return self::SUCCESS;
        }

        $this->resetLoyaltyCounters($db);
        foreach ($plan as [$collection, , $filter]) {
            $db->selectCollection($collection)->deleteMany($filter);
        }
        $this->info("Borrados {$total} documentos y reiniciados los contadores de lealtad de los clientes.");

        return self::SUCCESS;
    }

    /** Los clientes que se conservan quedan como nuevos: sus puntos y citas venían del movimiento que se borró. */
    private function resetLoyaltyCounters(Database $db): void
    {
        $db->selectCollection('clients')->updateMany([], ['$set' => ['puntos' => 0, 'total_citas' => 0, 'nivel' => 'nuevo']]);
    }
}
