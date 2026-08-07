<?php

namespace App\Http\Requests\Appointment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para creación de citas desde el panel (admin/recepción). No
 * exige rol específico: authorize() solo pide un usuario autenticado porque
 * el control fino de quién puede crear citas se hace en el controlador/rutas.
 */
class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'string', 'exists:clients,id'],
            'barber_id' => ['required', 'string', 'exists:barbers,id'],
            'service_id' => ['required', 'string', 'exists:services,id'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            // "after:hora_inicio" evita citas con hora de fin igual o menor al inicio.
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            // 'estado' es opcional aquí (nullable) porque al crear una cita nueva
            // normalmente se asume "pendiente" por defecto en el modelo/servicio.
            'estado' => ['nullable', Rule::in(['pendiente', 'confirmada', 'en_proceso', 'completada', 'cancelada', 'no_asistio'])],
            'notas' => ['nullable', 'string', 'max:1000'],
            'precio_cobrado' => ['nullable', 'numeric', 'min:0'],
            'motivo_reagendamiento' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required' => 'El cliente es obligatorio.',
            'client_id.exists' => 'El cliente seleccionado no existe.',
            'barber_id.required' => 'El barbero es obligatorio.',
            'barber_id.exists' => 'El barbero seleccionado no existe.',
            'service_id.required' => 'El servicio es obligatorio.',
            'service_id.exists' => 'El servicio seleccionado no existe.',
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.after_or_equal' => 'La fecha no puede estar en el pasado.',
            'hora_inicio.required' => 'La hora de inicio es obligatoria.',
            'hora_fin.required' => 'La hora de fin es obligatoria.',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
            'estado.in' => 'El estado seleccionado no es válido.',
        ];
    }
}
