# ADR-0002 — Stack y versiones
**Estado:** Aceptada (2026-10-30) · **Fecha:** 2026-10-30 (verificado contra fuentes oficiales y secundarias en esta fecha)

| Componente | Versión | Justificación |
|---|---|---|
| PHP | 8.5 (mín. 8.4) | 8.5 es la estable vigente, con seguridad hasta fines de 2029; 8.2 llega al fin de vida el 31/12/2026 |
| Laravel | 13.x | Versión mayor actual (marzo 2026), soporta PHP 8.3–8.5; correcciones de errores hasta el 3T 2027 y de seguridad hasta el 1T 2028. Se planifica migrar a Laravel 14 cuando salga |
| MariaDB | 12.3 LTS | LTS más reciente (mayo 2026), soporte comunitario hasta ~junio 2029. Alternativa conservadora: 11.8 LTS |
| ~~Redis (o Valkey)~~ | — | **Modificado 2026-10-31 (D-0010):** colas, caché, sesiones y bloqueos usan el driver `database` (MariaDB). Con ~30 muestras/día no se justifica otro servicio. Se suma Redis/Valkey solo si hace falta (p. ej., Reverb); el cambio es de configuración |
| Inertia.js | 3.x (estable desde abril 2026) | Ver ADR-0003 |
| Vue / TypeScript / Vite / Tailwind | 3.5+ / 5.x / actual / 4.x | Ver ADR-0003 |
| Auth | Laravel Fortify | Base de los starter kits oficiales, 2FA |
| Permisos | spatie/laravel-permission | Estándar de facto, mantenido |
| Pruebas | Pest 4 (+ browser testing), Vitest | |
| Calidad | Larastan, Pint, vue-tsc, ESLint | |

Las versiones exactas (patch) se fijan al crear el proyecto (Fase 3) y se registran en el CHANGELOG. Producción: Ubuntu Server LTS con repositorios oficiales de PHP (ondrej/php) y de MariaDB si la distribución no trae las versiones necesarias.
