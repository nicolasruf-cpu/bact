# Estado del proyecto

**Última actualización:** 2026-11-01
**Fase actual:** 3 — Base técnica (parte 1 hecha) → siguiente: primer circuito de urocultivo (Fase 4a, con datos sintéticos)

## Terminado
- **Fase 0:** análisis, versiones, arquitectura y ADR 0001–0008 (aceptados).
- **Fase 1 (borrador):** `docs/functional/` (actividades, máquinas de estado, excepciones, roles, mesadas). Respuestas del laboratorio incorporadas el 2026-10-31.
- **Fase 3, parte 1 (2026-11-01):**
  - Proyecto Laravel 13 + Inertia 3 + Vue/TS + Tailwind 4 + shadcn-vue corriendo en Docker (PHP 8.5, MariaDB 12.3). Ver `DEVELOPMENT.md`.
  - Sedes (`Organization`), roles y permisos por sede, sede activa y selector (`Identity`).
  - Interfaz en español; login sin autorregistro; pantalla de inicio con las mesadas previstas.
  - Tests de arquitectura de módulos.
  - **Pruebas:** `./sail composer test` → 70 en verde, Larastan sin errores; frontend sin errores de tipos ni de lint.
- **Auditoría base (2026-11-01):** append-only con triggers, hash encadenado, `audit:verify`, pantalla de consulta.
- **Urocultivo, entrega 1 (2026-11-02):** plantilla de referencia, recepción manual, actividades de siembra, mesada de siembra.
- **Recepción por orden (2026-11-02, Q-F8):** una orden con varias muestras; muestras posteriores se suman a la orden. 94 pruebas en verde.

## En desarrollo
- Nada a medio hacer.

## Bloqueos
- Ninguno técnico.
- Clínicos: se avanza con plantillas de referencia (D-0008). Sigue abierto Q-C4 (edición CLSI; quién confirma los puntos de corte).

## Próximos pasos
1. **Urocultivo, entrega 2:** tanda de siembra (WorkBatches) → cultivo y placas según protocolo → lectura programada (`READ_CULTURE` SCHEDULED → READY).
2. **Urocultivo, entrega 3:** lectura (opciones y criterios de la plantilla, a agregar con su fuente; Q-C2).
3. Administración de usuarios (alta, asignación de roles por sede, desactivación).
4. CI (pendiente Q-T3: repositorio remoto).

## Problemas conocidos
- El script oficial `vendor/bin/sail` no funciona en Git Bash: usar `./sail` (atajo propio).
- VS Code marca errores PHP falsos si usa el PHP 7.1 de Laragon (ver `DEVELOPMENT.md`).
- La primera compilación del frontend tarda (~1,5 min) por el disco de Windows montado en Docker.
