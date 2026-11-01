<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Contracts\AuditAction;
use BackedEnum;
use InvalidArgumentException;

/**
 * Cada módulo registra aquí su enum de acciones auditables, para que la consulta
 * de auditoría muestre nombres legibles sin que Audit dependa de los módulos.
 */
class AuditActionRegistry
{
    /** @var array<string, AuditAction> */
    private array $actions = [];

    /**
     * @param  class-string  $enum  enum backed que implementa AuditAction
     */
    public function register(string $enum): void
    {
        if (! enum_exists($enum) || ! is_subclass_of($enum, AuditAction::class) || ! is_subclass_of($enum, BackedEnum::class)) {
            throw new InvalidArgumentException("{$enum} debe ser un enum que implemente AuditAction.");
        }

        foreach ($enum::cases() as $case) {
            $this->actions[$case->auditName()] = $case;
        }
    }

    public function label(string $action): string
    {
        return isset($this->actions[$action]) ? $this->actions[$action]->label() : $action;
    }

    /**
     * @return array<string, string> nombre => etiqueta
     */
    public function options(): array
    {
        return array_map(fn (AuditAction $action) => $action->label(), $this->actions);
    }
}
