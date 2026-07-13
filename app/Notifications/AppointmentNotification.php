<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Notifications\Channels\TwilioChannel;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $subject,
        public readonly string $title,
        public readonly string $message,
        // Accion del correo/notificacion. Por defecto apunta al panel del
        // cliente; barbero/recepcion pasan su propia etiqueta y ruta.
        public readonly ?string $actionLabel = null,
        public readonly ?string $actionUrl = null,
        // Color de acento y etiqueta de estado del correo (verde=confirmada,
        // rojo=cancelada, azul=barbero, etc.).
        public readonly string $accent = '#d4af37',
        public readonly ?string $badge = null,
        // Adjunta invite .ics + enlace de Google Calendar (solo confirmaciones).
        public readonly bool $attachCalendar = false,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('in_app')) {
            $channels[] = 'database';
        }

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('email')) {
            $channels[] = 'mail';
        }

        if (method_exists($notifiable, 'wantsNotificationChannel')
            && ($notifiable->wantsNotificationChannel('sms') || $notifiable->wantsNotificationChannel('whatsapp'))) {
            $channels[] = TwilioChannel::class;
        }

        return $channels ?: ['database'];
    }

    /**
     * Texto corto para SMS/WhatsApp (canal Twilio).
     */
    public function toTwilio(object $notifiable): string
    {
        $fecha = optional($this->appointment->fecha)->format('d/m') ?? '';
        $hora = substr((string) $this->appointment->hora_inicio, 0, 5);
        $servicio = $this->appointment->service?->nombre ?? 'tu servicio';

        return "UrbanBlade: {$this->title}. {$servicio} el {$fecha} a las {$hora}.";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hora = $this->appointment->hora_inicio
            ? substr($this->appointment->hora_inicio, 0, 5).' – '.substr($this->appointment->hora_fin ?? '', 0, 5)
            : 'N/D';

        $data = [
            'accent' => $this->accent,
            'badge' => $this->badge,
            'title' => $this->title,
            'greeting' => 'Hola '.$notifiable->name.',',
            'intro' => $this->message,
            'rows' => [
                'Servicio' => $this->appointment->service?->nombre ?? 'N/D',
                'Barbero' => $this->appointment->barber?->user?->name ?? 'N/D',
                'Fecha' => optional($this->appointment->fecha)->format('d/m/Y') ?? 'N/D',
                'Horario' => $hora,
            ],
            'ctaLabel' => $this->actionLabel ?? 'Ver mis citas',
            'ctaUrl' => $this->actionUrl ?? route('client.appointments.index'),
        ];

        if ($this->attachCalendar && ($google = $this->googleCalendarUrl())) {
            $data['secondary'] = '[Agregar a Google Calendar]('.$google.') · o abre el archivo .ics adjunto.';
        }

        $mail = (new MailMessage)->subject($this->subject)->markdown('emails.message', $data);

        if ($this->attachCalendar && ($ics = $this->icsContent())) {
            $mail->attachData($ics, 'urbanblade-cita.ics', ['mime' => 'text/calendar; charset=UTF-8; method=PUBLISH']);
        }

        return $mail;
    }

    /**
     * Inicio/fin de la cita en UTC (formato ICS Ymd\THis\Z), o null si faltan datos.
     *
     * @return array{0:string,1:string}|null
     */
    private function utcRange(): ?array
    {
        $fecha = optional($this->appointment->fecha)->format('Y-m-d');
        if (! $fecha || ! $this->appointment->hora_inicio) {
            return null;
        }

        $tz = config('app.timezone', 'America/Mexico_City');
        $start = Carbon::parse($fecha.' '.$this->appointment->hora_inicio, $tz)->utc();
        $end = $this->appointment->hora_fin
            ? Carbon::parse($fecha.' '.$this->appointment->hora_fin, $tz)->utc()
            : $start->copy()->addMinutes((int) ($this->appointment->service?->duracion_min ?? 30));

        return [$start->format('Ymd\THis\Z'), $end->format('Ymd\THis\Z')];
    }

    private function googleCalendarUrl(): ?string
    {
        $range = $this->utcRange();
        if (! $range) {
            return null;
        }

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => 'UrbanBlade · '.($this->appointment->service?->nombre ?? 'Cita'),
            'dates' => $range[0].'/'.$range[1],
            'details' => 'Cita con '.($this->appointment->barber?->user?->name ?? 'tu barbero').' en UrbanBlade.',
            'location' => 'Av. Reforma 123, CDMX',
        ]);
    }

    private function icsContent(): ?string
    {
        $range = $this->utcRange();
        if (! $range) {
            return null;
        }

        $uid = ($this->appointment->id ?? uniqid()).'@urbanblade';
        $stamp = Carbon::now('UTC')->format('Ymd\THis\Z');
        $summary = 'UrbanBlade · '.($this->appointment->service?->nombre ?? 'Cita');
        $desc = 'Cita con '.($this->appointment->barber?->user?->name ?? 'tu barbero').' en UrbanBlade.';

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//UrbanBlade//Citas//ES',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$stamp,
            'DTSTART:'.$range[0],
            'DTEND:'.$range[1],
            'SUMMARY:'.$summary,
            'DESCRIPTION:'.$desc,
            'LOCATION:Av. Reforma 123\, CDMX',
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment',
            'appointment_id' => $this->appointment->id,
            'subject' => $this->subject,
            'title' => $this->title,
            'message' => $this->message,
            'fecha' => optional($this->appointment->fecha)->toDateString(),
            'hora_inicio' => $this->appointment->hora_inicio,
            'hora_fin' => $this->appointment->hora_fin,
            'url' => $this->actionUrl ?? route('client.appointments.index'),
        ];
    }
}
