<?php

namespace App\Modules\Identity\Data;

use App\Modules\Identity\Enums\Role;

/**
 * Datos de un usuario cargados por el Administrador. `assignments` son los perfiles en
 * cada sede; la contraseña es opcional al modificar (solo se cambia si viene).
 */
final readonly class UserData
{
    /**
     * @param  list<array{site_id: int, role: Role}>  $assignments
     */
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password,
        public array $assignments,
        public ?int $lockVersion = null,
    ) {}
}
