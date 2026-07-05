<?php

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    // Writes go through a single bulkWrite per batch instead of one round trip
    // per document — with hundreds/thousands of rows against an Atlas M0
    // cluster, per-document updateQuietly() calls were slow enough to blow
    // past the driver's socket timeout mid-migration.
    private const BATCH_SIZE = 500;

    public function up(): void
    {
        $this->populateBarberSlugs();
        $this->populateServiceSlugs();
        $this->populateClientSlugs();
        $this->populateAppointmentCodes();
    }

    private function populateBarberSlugs(): void
    {
        $existing = Barber::whereNotNull('slug')->pluck('slug')->flip()->toArray();

        $this->bulkUpdate(Barber::whereNull('slug'), function (Barber $barber) use (&$existing) {
            $slug = $this->uniqueSlug(Str::slug($barber->nombre ?? 'barbero'), $existing);

            return ['slug' => $slug];
        });
    }

    private function populateServiceSlugs(): void
    {
        $existing = Service::whereNotNull('slug')->pluck('slug')->flip()->toArray();

        $this->bulkUpdate(Service::whereNull('slug'), function (Service $service) use (&$existing) {
            $slug = $this->uniqueSlug(Str::slug($service->nombre ?? 'servicio'), $existing);

            return ['slug' => $slug];
        });
    }

    private function populateClientSlugs(): void
    {
        $existing  = Client::whereNotNull('slug')->pluck('slug')->flip()->toArray();
        $userNames = User::all()->pluck('name', 'id')->mapWithKeys(fn ($name, $id) => [(string) $id => $name]);

        $this->bulkUpdate(Client::whereNull('slug'), function (Client $client) use (&$existing, $userNames) {
            $name = $userNames->get((string) $client->user_id) ?? 'cliente';
            $slug = $this->uniqueSlug(Str::slug($name), $existing);

            return ['slug' => $slug];
        });
    }

    private function populateAppointmentCodes(): void
    {
        $existing = Appointment::withTrashed()->whereNotNull('code')->pluck('code')->flip()->toArray();

        $this->bulkUpdate(Appointment::withTrashed()->whereNull('code'), function (Appointment $appt) use (&$existing) {
            do {
                $code = Str::lower(Str::random(8));
            } while (isset($existing[$code]));

            $existing[$code] = true;

            return ['code' => $code];
        });
    }

    // Flushes each chunk as a single bulkWrite instead of one round trip per document.
    private function bulkUpdate($query, callable $fieldsFor): void
    {
        $query->chunkById(self::BATCH_SIZE, function ($models) use ($fieldsFor): void {
            $ops = [];

            foreach ($models as $model) {
                // laravel-mongodb renames `_id` to `id` on hydration and
                // getIdAttribute() stringifies it, so read the raw attribute
                // to get the actual BSON ObjectId for the bulkWrite filter.
                $ops[] = [
                    'updateOne' => [
                        ['_id' => $model->getAttributes()['id']],
                        ['$set' => $fieldsFor($model)],
                    ],
                ];
            }

            $models->first()::query()->raw()->bulkWrite($ops);
        });
    }

    private function uniqueSlug(string $base, array &$existing): string
    {
        if (empty($base)) {
            $base = 'item';
        }

        $slug = $base;
        $i    = 2;

        while (isset($existing[$slug])) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        $existing[$slug] = true;

        return $slug;
    }

    public function down(): void
    {
        // slug/code fields are additive — no rollback needed
    }
};
