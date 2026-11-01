<?php

/*
 * Límites entre módulos (docs/ARCHITECTURE.md §3). Cada módulo nuevo agrega aquí sus reglas.
 */

arch('no quedan funciones de depuración')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('preset de seguridad de PHP')
    ->preset()->security();

arch('los enums de los módulos son enums')
    ->expect('App\Modules\*\Enums')
    ->toBeEnums();

arch('los módulos base no dependen de módulos clínicos')
    ->expect(['App\Modules\Organization', 'App\Modules\Identity', 'App\Modules\Audit', 'App\Modules\Configuration'])
    ->not->toUse([
        'App\Modules\Patients',
        'App\Modules\Orders',
        'App\Modules\Samples',
        'App\Modules\Cultures',
        'App\Modules\Isolates',
        'App\Modules\Identification',
        'App\Modules\Susceptibility',
        'App\Modules\Workflow',
        'App\Modules\WorkBatches',
        'App\Modules\Reporting',
        'App\Modules\CriticalResults',
        'App\Modules\Integrations',
    ]);

arch('los módulos clínicos no conocen a Workflow: emiten eventos (ARCHITECTURE §3)')
    ->expect(['App\Modules\Patients', 'App\Modules\Orders', 'App\Modules\Samples'])
    ->not->toUse(['App\Modules\Workflow', 'App\Modules\WorkBatches']);

arch('los eventos de dominio son inmutables')
    ->expect('App\Modules\*\Events')
    ->toBeReadonly()
    ->toBeFinal();

arch('las consultas de lectura no escriben en la base')
    ->expect('App\Modules\*\Queries')
    ->not->toUse(['App\Modules\Audit\Services\Auditor']);

arch('Audit no depende de Identity (los módulos registran sus acciones en Audit, no al revés)')
    ->expect('App\Modules\Audit')
    ->not->toUse('App\Modules\Identity');

arch('solo Audit escribe la tabla de auditoría')
    ->expect('App\Modules\Audit\Models\AuditEvent')
    ->toOnlyBeUsedIn(['App\Modules\Audit']);

arch('Organization no depende de Identity')
    ->expect('App\Modules\Organization')
    ->not->toUse('App\Modules\Identity');

arch('los controladores no usan la base de datos directamente')
    ->expect('App\Modules\*\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('las acciones exponen un único método handle')
    ->expect('App\Modules\*\Actions')
    ->toHaveMethod('handle');
