# Estado del proyecto

**Última actualización:** 2026-11-03
**Fase actual:** 4a — MVP urocultivo: circuito de punta a punta funcionando con datos sintéticos; pendiente revisión del usuario y validación clínica

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
- **Recepción por orden (2026-11-02, Q-F8):** una orden con varias muestras; muestras posteriores se suman a la orden.
- **Tanda de siembra (2026-11-03):** cultivos y placas según protocolo; lectura programada.
- **Urocultivo de punta a punta (2026-11-03):** tanda de siembra → lectura → aislamientos → identificación → antibiograma → validación → informe → perfil de sensibilidad. 128 pruebas en verde.

## En desarrollo
- Nada a medio hacer.

## Bloqueos
- Ninguno técnico.
- Clínicos: la plantilla de urocultivo (docs/templates/UROCULTIVO.md) debe revisarla el Director técnico responsable; puntos de corte reales (Q-C4); metodología del perfil (Q-C9).

## Próximos pasos
1. Usuario: recorrer el circuito en la demo y revisar la plantilla con el Director técnico.
2. Administración de usuarios y de configuración clínica (aprobar protocolos y cargar puntos de corte por CSV, Fase 4d).
3. Corrección de informes (nueva versión con motivo), PDF.
4. Tandas de lectura (Q-F9), hemocultivo y resultados críticos (Fase 4b).
5. CI (Q-T3).

## Problemas conocidos
- El script oficial `vendor/bin/sail` no funciona en Git Bash: usar `./sail` (atajo propio).
- VS Code marca errores PHP falsos si usa el PHP 7.1 de Laragon (ver `DEVELOPMENT.md`).
- La primera compilación del frontend tarda (~1,5 min) por el disco de Windows montado en Docker.
