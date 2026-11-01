<?php

use Illuminate\Support\Facades\Schedule;

// Verificación diaria de la integridad de la auditoría (ADR-0006).
Schedule::command('audit:verify')->dailyAt('03:00');

// Las actividades programadas (p. ej., lecturas) pasan a disponibles al cumplirse su hora.
Schedule::command('activities:release')->everyMinute()->withoutOverlapping();
