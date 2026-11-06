<?php

namespace App\Notifications\Moderation;

use App\Notifications\Concerns\PushesToDevices;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Aviso a quien reportó un comentario del muro de que ya se revisó (política de contenido de usuarios:
 * el reportante debe saber que su reporte se atendió). Va a la bandeja y, si el usuario activó «push»,
 * al teléfono. No revela quién escribió el comentario ni si el autor fue vetado.
 */
class ReportResolvedNotification extends Notification implements ShouldQueue
{
    use PushesToDevices;
    use Queueable;

    public const OUTCOME_REMOVED = 'removed';

    public const OUTCOME_DISMISSED = 'dismissed';

    public function __construct(public readonly string $outcome) {}

    public function via(object $notifiable): array
    {
        return ['database', ...$this->pushChannels($notifiable)];
    }

    public function toArray(object $notifiable): array
    {
        $removed = $this->outcome === self::OUTCOME_REMOVED;

        return [
            'type' => 'report_resolved',
            'outcome' => $this->outcome,
            'title' => 'Revisamos tu reporte',
            'message' => $removed
                ? 'Eliminamos el comentario que reportaste. Gracias por ayudarnos a cuidar la comunidad.'
                : 'Revisamos el comentario que reportaste y cumple con las reglas, por eso se conserva. Gracias por avisarnos.',
            'url' => config('app.frontend_url'),
        ];
    }
}
