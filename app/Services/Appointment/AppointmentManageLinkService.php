<?php

namespace App\Services\Appointment;

use App\Models\Appointment;
use App\Models\BarbershopSetting;
use Carbon\Carbon;

/**
 * Enlace seguro para que un cliente gestione SU cita desde el recordatorio,
 * sin iniciar sesión.
 *
 * Por qué un token propio y no una URL firmada de Laravel: la firma de
 * Laravel valida la URL completa (dominio + ruta + query), y aquí el enlace
 * lo abre Nuxt en otro dominio y luego llama a la API -- la URL que firmaría
 * el backend nunca sería la que valida. Un token opaco por cita viaja limpio
 * entre los dos y además se puede revocar e invalidar por sí solo.
 *
 * Propiedades del token:
 *  - Aleatorio de 64 hex (32 bytes de random_bytes), no derivable del code.
 *  - Sirve para UNA cita: no da acceso a ninguna otra ni al resto de la API.
 *  - Caduca solo: deja de valer cuando la cita ya empezó, así que un correo
 *    viejo no permite tocar nada después.
 *  - Se compara con hash_equals para no filtrar información por tiempos.
 */
class AppointmentManageLinkService
{
    /**
     * Devuelve el token de gestión de la cita, creándolo la primera vez.
     * Es estable mientras la cita viva, para que dos recordatorios de la
     * misma cita lleven el mismo enlace.
     */
    public function tokenFor(Appointment $appointment): string
    {
        $current = $appointment->manage_token;

        if (is_string($current) && $current !== '') {
            return $current;
        }

        $token = bin2hex(random_bytes(32));
        $appointment->forceFill(['manage_token' => $token])->save();

        return $token;
    }

    /**
     * URL que se manda en el recordatorio: la abre Nuxt, no la API.
     */
    public function urlFor(Appointment $appointment): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            .'/cita/'.$appointment->code
            .'?t='.$this->tokenFor($appointment);
    }

    /**
     * Valida el token contra la cita. No revela por qué falla: para quien
     * pregunta, un token inválido y uno vencido son lo mismo.
     */
    public function isValid(Appointment $appointment, ?string $token): bool
    {
        $stored = $appointment->manage_token;

        if (! is_string($stored) || $stored === '' || ! is_string($token) || $token === '') {
            return false;
        }

        if (! hash_equals($stored, $token)) {
            return false;
        }

        // Una vez que la cita empezó, el enlace deja de servir aunque el
        // token sea correcto: el correo no debe poder modificar el pasado.
        return $this->startsAt($appointment)->isFuture();
    }

    /**
     * Horas de antelación exigidas por la barbería para cancelar o mover una
     * cita. Es la MISMA política que aplica el cliente autenticado
     * (AppointmentController::destroy) -- el enlace no puede ser una puerta
     * más permisiva que la app.
     */
    public function policyHours(): int
    {
        return (int) (BarbershopSetting::cached()->politica_cancelacion ?? 24);
    }

    /**
     * Si la cita todavía está dentro de la ventana permitida.
     */
    public function withinPolicy(Appointment $appointment): bool
    {
        return Carbon::now()->diffInHours($this->startsAt($appointment), false) >= $this->policyHours();
    }

    /**
     * Solo se gestiona lo que aún no terminó su ciclo de vida, igual que
     * AppointmentController::clientCanManage().
     */
    public function isManageable(Appointment $appointment): bool
    {
        return in_array((string) $appointment->estado, ['pendiente', 'confirmada'], true)
            && $this->startsAt($appointment)->isFuture();
    }

    // Mismo patrón que AppointmentController::appointmentStartsAt(): 'fecha'
    // tiene cast 'date', así que ya llega como Carbon.
    public function startsAt(Appointment $appointment): Carbon
    {
        return Carbon::parse($appointment->fecha->format('Y-m-d').' '.$appointment->hora_inicio);
    }
}
