# ADR-0006 — Auditoría propia append-only
**Estado:** Aceptada (2026-10-30) · **Fecha:** 2026-10-30

**Decisión:** tabla `audit_events` propia, escrita desde las Actions dentro de la transacción. Registra el motivo, el contexto (tanda/actividad), los valores anteriores y nuevos, y un hash encadenado. El usuario de base de datos de la aplicación no tiene permisos de UPDATE/DELETE sobre ella.
**Por qué:** los paquetes genéricos auditan cambios de modelos, pero no la intención clínica ("validó el informe v2 por corrección de CIM", con su motivo). Necesitamos eventos de dominio auditables y reproducibles.
**Alternativas:** owen-it/laravel-auditing o spatie/laravel-activitylog: se podrían usar como complemento para cambios de catálogos, a evaluar en la Fase 3.
