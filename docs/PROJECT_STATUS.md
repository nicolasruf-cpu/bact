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
  - **Pruebas:** `./sail composer test` → 53 en verde, Larastan sin errores; frontend sin errores de tipos ni de lint.

## En desarrollo
- Nada a medio hacer.

## Bloqueos
- Ninguno técnico.
- Clínicos: se avanza con plantillas de referencia (D-0008). Sigue abierto Q-C4 (edición CLSI; quién confirma los puntos de corte).

## Próximos pasos
1. **Auditoría base** (ADR-0006: append-only con hash encadenado), necesaria antes de cualquier operación clínica.
2. **Primer circuito de urocultivo** (datos sintéticos, plantilla de referencia en `draft`): catálogos mínimos (tipo de muestra, estudio, medios, protocolo) → recepción manual escaneando la etiqueta → actividad de siembra → mesada de siembra → siembra en tanda → lectura programada.
3. Administración de usuarios (alta, asignación de roles por sede, desactivación).
4. CI (pendiente Q-T3: repositorio remoto).

## Problemas conocidos
- El script oficial `vendor/bin/sail` no funciona en Git Bash: usar `./sail` (atajo propio).
- VS Code marca errores PHP falsos si usa el PHP 7.1 de Laragon (ver `DEVELOPMENT.md`).
- La primera compilación del frontend tarda (~1,5 min) por el disco de Windows montado en Docker.
