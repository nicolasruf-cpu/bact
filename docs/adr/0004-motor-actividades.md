# ADR-0004 — Motor de actividades por políticas explícitas
**Estado:** Aceptada (2026-10-30) · **Fecha:** 2026-10-30

**Decisión:** las actividades se generan con políticas PHP explícitas que reaccionan a eventos de dominio de forma síncrona, en la misma transacción, y que leen los parámetros clínicos de la configuración versionada (protocolos). No se adopta un motor BPMN ni un *workflow engine* genérico.
**Por qué:** el flujo microbiológico tiene pocos tipos de pasos pero mucha variación de parámetros. Las políticas explícitas se pueden probar, depurar y leer; la configuración aporta flexibilidad sin necesidad de un DSL.
**Consecuencias:** un tipo de paso nuevo requiere código (política + pantalla); un cambio de parámetros, no. Se revisa después del MVP.
