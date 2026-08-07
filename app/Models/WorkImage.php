<?php

namespace App\Models;

use Database\Factories\WorkImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Imagen o video individual que pertenece a un trabajo (Work) del
 * portafolio.
 */
class WorkImage extends Model
{
    /** @use HasFactory<WorkImageFactory> */
    use HasFactory;

    protected $fillable = [
        'image',
        'type',      // 'image' | 'video'
        'mime_type',
    ];

    // Trata como imagen si el tipo no está definido (dato legado sin `type`).
    public function isVideo(): bool
    {
        return ($this->type ?? 'image') === 'video';
    }

    // Trabajo al que pertenece este archivo.
    public function work()
    {
        return $this->belongsTo(Work::class);
    }
}
