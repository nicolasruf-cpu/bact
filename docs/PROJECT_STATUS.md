# Estado del proyecto

**Última actualización:** 2026-10-31
**Fase actual:** 1 — Diseño funcional (borrador completo, **pendiente de revisión del usuario y de respuestas clínicas**)

## Terminado
- **Fase 0:** análisis, versiones verificadas, arquitectura y ADR 0001–0007 (**aceptados** el 2026-10-30).
- **Fase 1 (borrador):** `docs/functional/`
  - ACTIVITY_CATALOG.md — 18 tipos de actividad: datos, resultados, tanda y confirmación múltiple
  - STATE_MACHINES.md — muestra, estudio, cultivo, placa, aislamiento, identificación, antibiograma, informe, alerta
  - EXCEPTIONS.md — 19 flujos de excepción
  - ROLES_PERMISSIONS.md — matriz rol × permiso (propuesta, con ❓)
  - WORKBENCHES.md — definición de cada mesada y de la pantalla de tanda

## En desarrollo
- Nada en código todavía.

## Bloqueos
- Ninguno técnico: Docker Desktop operativo (resuelto 2026-10-31).
- Clínicos: se avanza con plantillas de referencia (D-0008); siguen abiertas Q-C4 (edición CLSI y quién carga los puntos de corte) y las parciales de `OPEN_QUESTIONS.md`.

## Próximos pasos
1. Usuario: aprobar ADR-0008 (multisede) y responder lo que falta de Q-C4.
2. Commit inicial + crear el proyecto Laravel 13 con Sail (Fase 3, parte técnica base).
3. Redactar la plantilla de referencia de urocultivo (protocolo, lectura, paneles) para revisión del Director técnico.
4. Fase 2: design system + prototipos navegables con datos sintéticos.

## Problemas conocidos
- PHP local 7.1 (Laragon): no se usa; todo corre en contenedores (Sail).
