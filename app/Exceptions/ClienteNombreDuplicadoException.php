<?php

namespace App\Exceptions;

use RuntimeException;

class ClienteNombreDuplicadoException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No se puede restaurar el cliente porque ya existe un cliente activo con ese nombre.');
    }
}
