<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Reporte de un comentario del muro hecho por otro usuario (política de contenido de
 * usuarios de Google Play: reportar y moderar). Un usuario reporta un comentario una sola vez.
 *
 * @property string $id
 * @property string $comment_id
 * @property string $work_id
 * @property string $reporter_id
 * @property string $reason
 * @property string|null $details
 * @property string $status
 * @property string|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 */
class CommentReport extends Model
{
    public const REASON_SPAM = 'spam';

    public const REASON_OFFENSIVE = 'ofensivo';

    public const REASON_SEXUAL_VIOLENT = 'sexual_violento';

    public const REASON_OTHER = 'otro';

    /** Motivos que acepta la API (mismos textos que muestran la app y la web). */
    public const REASONS = [
        self::REASON_SPAM,
        self::REASON_OFFENSIVE,
        self::REASON_SEXUAL_VIOLENT,
        self::REASON_OTHER,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_REMOVED = 'removed';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'comment_id',
        'work_id',
        'reporter_id',
        'reason',
        'details',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    // Comentario reportado (puede haberse eliminado ya al resolver el reporte).
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    // Quien reportó.
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
