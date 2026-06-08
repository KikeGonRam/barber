<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use MongoDB\Laravel\Eloquent\Model;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

class Activity extends Model implements ActivityContract
{
    protected $connection = 'mongodb';
    protected $collection = 'activity_log';
    protected $guarded = [];

    protected $casts = [
        'properties' => 'collection',
    ];

    public function subject(): MorphTo
    {
        if (config('activitylog.subject_returns_soft_deleted_models')) {
            return $this->morphTo()->withTrashed();
        }

        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function getExtraProperty(string $propertyName, mixed $defaultValue = null): mixed
    {
        return Arr::get($this->properties->toArray(), $propertyName, $defaultValue);
    }

    public function changes(): Collection
    {
        if (! $this->properties instanceof Collection) {
            return new Collection();
        }

        return collect(array_filter($this->properties->only(['attributes', 'old'])->toArray()));
    }

    public function scopeInLog(Builder $query, ...$logNames): Builder
    {
        if (is_array($logNames[0] ?? null)) {
            $logNames = $logNames[0];
        }

        return $query->whereIn('log_name', $logNames);
    }

    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    public function scopeForCauser(Builder $query, Model $causer): Builder
    {
        return $query
            ->where('causer_type', $causer->getMorphClass())
            ->where('causer_id', $causer->getKey());
    }

    public function scopeCausedBy(Builder $query, Model $causer): Builder
    {
        return $this->scopeForCauser($query, $causer);
    }

    public function scopeForEvent(Builder $query, string $event): Builder
    {
        return $query->where('event', $event);
    }

    public function scopeForBatch(Builder $query, string $batchUuid): Builder
    {
        return $query->where('batch_uuid', $batchUuid);
    }
}
