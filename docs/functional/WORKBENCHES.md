# Mesadas (Fase 1)

Cada mesada es una vista sobre actividades (WORKFLOW_ENGINE.md §4). Comportamiento común:
- **Encabezado:** contadores (listas · en proceso · urgentes · demoradas · programadas hoy).
- **Lista:** ordenada por prioridad explicable; filtros persistentes por usuario; escanear un código posiciona el ítem.
- **Acción principal:** "Iniciar tanda con N seleccionadas" (por defecto, todas las listas del grupo visible) o "Retomar tanda X".
- **Acción secundaria:** procesar un ítem individual sin tanda (urgencias).

| Mesada | Actividades | Agrupación por defecto | Columnas clave | Tipo de tanda |
|---|---|---|---|---|
| Recepción | RECEIVE_SAMPLE | Procedencia | Código, paciente, tipo de muestra, estudios, hora de toma, prioridad | Recepción |
| Siembra | INOCULATE, DIRECT_EXAM | Tipo de muestra → protocolo | Código, tipo, estudio, medios a sembrar (chips), tiempo desde recepción | Siembra |
| Lectura | READ_CULTURE, REINCUBATE_READ, READ_SUBCULTURE | Protocolo → número de lectura (24 h / 48 h…) | Código, estudio, placas, horas de incubación, lectura n.º, lecturas previas (resumen) | Lectura |
| Identificación | ID_RAPID_TESTS, ID_BIOCHEM_SETUP | Tipo de prueba sugerida | Código + n.º de aislamiento, morfotipo, origen, Gram (si existe) | Identificación |
| Bioquímicas | ID_BIOCHEM_READ | Hora de disponibilidad | Aislamiento, pruebas sembradas, horas | Lectura bioquímica |
| VITEK | VITEK_LOAD, VITEK_RESULT | Tipo de tarjeta | Aislamiento, ID preliminar, tarjeta(s) requerida(s) | VITEK (casete) |
| ATB a preparar | AST_SETUP | Grupo de organismo → panel | Aislamiento, organismo, panel sugerido, método | Preparación de ATB |
| ATB a leer | AST_READ | Panel | Aislamiento, organismo, panel, horas de incubación | Lectura de ATB |
| Validación | VALIDATE_REPORT | Prioridad → tipo de informe | Muestra, estudio, resumen de resultado, alertas, discordancias, tiempo de respuesta | (individual; tanda solo como lista de trabajo, sin confirmación múltiple) |
| Urgencias | COMMUNICATE_CRITICAL + stat/urgent de cualquier tipo | Ninguna (orden por antigüedad) | Qué, desde cuándo, plazo | — |
| Demoradas | Cualquiera con `due_at` vencido | Tipo de actividad | Tipo, cuánto excede, responsable si está en una tanda | — |
| Traspaso de turno | Ver EXCEPTIONS.md E16 | — | — | — |

## Pantalla de tanda (común)
- Panel izquierdo: ítems (estado, código, prioridad); `J/K` para navegar.
- Panel derecho: formulario de la actividad del ítem actual. Identidad de la muestra fija arriba.
- Pie: `Guardar y siguiente (Enter)` · `Saltar` · `Retirar de la tanda [motivo]` · `Pausar (Esc)` · progreso `12/30`.
- Confirmación múltiple (solo si la actividad lo permite): botón aparte "Confirmar N restantes con protocolo estándar…" → diálogo con resumen → confirmación explícita.
