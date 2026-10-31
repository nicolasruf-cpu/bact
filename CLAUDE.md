# CLAUDE.md — BactRuf (Sistema de Gestión de Bacteriología, Laboratorio RUF)

## Objetivo
Mesada digital inteligente para bacteriología clínica: el sistema organiza el trabajo del bacteriólogo.
**La unidad de seguimiento es la muestra; la unidad de trabajo es la actividad.**

## Al iniciar cada sesión (obligatorio)
1. Leer este archivo.
2. Leer `docs/PROJECT_STATUS.md` (estado, pendientes, próximos pasos).
3. Consultar `docs/ROADMAP.md` y `docs/OPEN_QUESTIONS.md`.
4. `git log --oneline -20` para ver cambios recientes.
5. Correr las pruebas (`composer test`) y anotar el resultado.
6. Continuar desde el último punto documentado.

Al cerrar una etapa: actualizar `docs/PROJECT_STATUS.md` y `docs/CHANGELOG.md`; registrar decisiones en `docs/DECISIONS.md` / `docs/adr/`.

## Estado
Fases 0 y 1 (borrador) hechas; ADR aceptados. Ver `docs/PROJECT_STATUS.md` para bloqueos y próximos pasos.

## Stack (ver ADR-0002)
PHP 8.5 · Laravel 13 · MariaDB 12.3 LTS (InnoDB, utf8mb4) · Redis · Inertia 3 + Vue 3 + TypeScript · Tailwind 4 · shadcn-vue (Reka UI) · Pest · Larastan · Pint.

## Arquitectura (ver docs/ARCHITECTURE.md)
Monolito modular Laravel. Módulos en `app/Modules/<Modulo>/`. Lógica clínica en Actions/Services del backend, **nunca en el frontend ni en controladores**. Límites entre módulos verificados con tests de arquitectura de Pest.

## Convenciones
- Código, tablas y clases en **inglés**; interfaz y documentación en **español (es-AR)**. Glosario en `docs/DOMAIN_MODEL.md`.
- Estados con enums PHP backed + transiciones explícitas. Nada de strings mágicos.
- Toda operación clínica: transacción + auditoría + idempotencia (`idempotency_key`) + control de concurrencia (`lock_version`).
- Controladores delgados: Form Request → Action → respuesta Inertia.
- Datos de prueba: **solo sintéticos**.

## Reglas no negociables
- No inventar reglas clínicas, puntos de corte ni umbrales. Si falta una definición: documentarla en `docs/OPEN_QUESTIONS.md` y seguir con otra tarea.
- Puntos de corte CLSI: se cargan como datos versionados por el laboratorio; no se incluyen en el código.
- No eliminar ni debilitar pruebas para que algo pase. No ocultar errores.
- Pedir aprobación antes de: cambios de arquitectura, procesos microbiológicos, criterios de validación, cambios destructivos de datos, despliegue a producción.
- Una funcionalidad está terminada solo si cumple la Definición de Terminado (`docs/ROADMAP.md`).

## Comandos (se completarán al crear el proyecto Laravel)
- `composer test` — Pest
- `composer lint` — Pint + Larastan
- `npm run dev` / `npm run build` / `npm run test` (Vitest) / `npm run typecheck`

## Documentación
`docs/functional/` — catálogo de actividades, estados, excepciones, roles, mesadas.
`docs/` — ARCHITECTURE, DOMAIN_MODEL, WORKFLOW_ENGINE, WORK_BATCHES, MICROBIOLOGY_RULES, DATABASE, SECURITY, UX_UI, TESTING, AGENTS, ROADMAP, PROJECT_STATUS, DECISIONS, OPEN_QUESTIONS, RISKS, CHANGELOG, `adr/`.
