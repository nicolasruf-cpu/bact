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
    ->expect(['App\Modules\Organization', 'App\Modules\Identity'])
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

arch('Organization no depende de Identity')
    ->expect('App\Modules\Organization')
    ->not->toUse('App\Modules\Identity');

arch('los controladores no usan la base de datos directamente')
    ->expect('App\Modules\*\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('las acciones exponen un único método handle')
    ->expect('App\Modules\*\Actions')
    ->toHaveMethod('handle');
