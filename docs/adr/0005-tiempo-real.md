# ADR-0005 — Actualización de la mesada
**Estado:** Aceptada (2026-10-30) · **Fecha:** 2026-10-30

**Decisión:** etapa 1 con *polling* Inertia (15–30 s) y recargas parciales de contadores. Etapa 2, solo si hace falta: Laravel Reverb (WebSockets) para alertas críticas instantáneas.
**Por qué:** menos infraestructura y menos fallas; la latencia es aceptable para contadores. Las alertas críticas pueden justificar WebSockets más adelante.
