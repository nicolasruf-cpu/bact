# ADR-0001 — Monolito modular Laravel
**Estado:** Aceptada (2026-10-30) · **Fecha:** 2026-10-30

**Contexto:** un laboratorio, un equipo chico, operaciones clínicas que requieren consistencia transaccional entre muestra, actividad, tanda y auditoría.
**Decisión:** un único despliegue Laravel, organizado en `app/Modules/*` con dependencias dirigidas, eventos de dominio síncronos y límites verificados con tests de arquitectura de Pest.
**Consecuencias:** despliegue y operación simples, transacciones ACID. Exige disciplina de límites (mitigada con tests `arch()`). Si en el futuro hiciera falta, un módulo con límites claros se puede extraer.
**Alternativas descartadas:** microservicios (costo y consistencia eventual sin beneficio); Laravel "plano" sin módulos (se degrada con el tamaño del sistema).
