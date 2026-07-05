<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class ClientAlreadyBookedException extends RuntimeException
{
    public function __construct(string $fecha = '')
    {
        $msg = $fecha
            ? "Ya tienes una cita agendada para el {$fecha}. Solo se permite una cita por día."
            : 'Ya tienes una cita agendada para ese día. Solo se permite una cita por día.';

        parent::__construct($msg);
    }
}
