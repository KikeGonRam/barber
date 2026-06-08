<?php

namespace App\Models;

use Database\Factories\WorkImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class WorkImage extends Model
{
    /** @use HasFactory<WorkImageFactory> */
    use HasFactory;

    protected $fillable = [
        'image',
    ];

    public function work()
    {
        return $this->belongsTo(Work::class);
    }
}
