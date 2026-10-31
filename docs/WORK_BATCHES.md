# Tandas de trabajo

Una **tanda** agrupa actividades del mismo tipo (o de tipos compatibles) que una persona procesa en conjunto.

## 1. Modelo

**WorkBatch**: `code` legible (p. ej. `SIE-20261030-03`), `type` (siembra, lectura, identificación, VITEK, preparación de ATB, lectura de ATB, validación), `owner_id`, `status`, `started_at`, `paused_at`, `completed_at`, `notes`, `lock_version`.

**WorkBatchItem**: `work_batch_id`, `activity_id`, `position` (orden, o posición física en VITEK: casete/slot), `status` (`pending`, `done`, `skipped`, `failed`, `removed`), `removed_reason`, `removed_by`, `done_at`, datos específicos del tipo (p. ej., número de tarjeta VITEK).

**WorkBatchEvent** (historial, append-only): creada, iniciada, ítem agregado/retirado, pausada (motivo), reanudada, incidencia, finalizada, cancelada.

**Garantía clave:** una actividad puede estar en **una sola tanda activa** a la vez. MariaDB no tiene índices únicos parciales, así que se usa una columna generada `active_activity_id` (vale `activity_id` mientras el ítem está activo y NULL si no) con índice `UNIQUE`.

## 2. Estados

```
DRAFT ──start──► IN_PROGRESS ──pause──► PAUSED ──resume──► IN_PROGRESS ──finish──► COMPLETED
  │                    │                   │
  └──────── cancel (motivo; libera actividades pendientes) ─────────────────────► CANCELLED
```
- **Finalizar** con ítems pendientes obliga a elegir, para cada uno o en bloque: devolver a la mesada (liberar) o marcar como incidencia. Nunca se pierden en silencio.
- **Pausar** conserva la posición. Al reanudar, la UI abre en el primer ítem pendiente.
- Cerrar sesión o perder la conexión no rompe nada: la tanda queda `IN_PROGRESS` y aparece en "Mis tandas abiertas" la próxima vez que la persona entra.

## 3. Operaciones
| Operación | Regla |
|---|---|
| Crear desde mesada | Preselecciona las actividades READY del filtro actual; el usuario ajusta |
| Agregar ítem | Lector de código de barras o selección; valida tipo, estado y que no esté en otra tanda |
| Retirar ítem | Requiere motivo; la actividad vuelve a READY; queda en el historial |
| Registrar ítem | Guarda la actividad individual (transacción propia) → `done` |
| Confirmación múltiple | Solo para operaciones **homogéneas y seguras** (p. ej., "sembré estos 12 con el protocolo estándar"). Se muestra un resumen previo y se confirma explícitamente. **No se permite** para resultados que llevan valores o interpretación (lecturas con desarrollo, antibiogramas, validación). |
| Interrumpir | Pausa con motivo opcional; el usuario queda libre para atender la urgencia |
| Transferir | Cambio de responsable (cambio de turno), con auditoría |

## 4. Escenario de referencia: interrupción
1. Ana inicia una tanda de siembra con 30 muestras y procesa 12 (12 `done`).
2. Llega un hemocultivo positivo y aparece una alerta en la barra superior. Ana pausa la tanda (o el sistema la pausa al abrir la urgencia).
3. Atiende el Gram urgente, que es una actividad individual, fuera de la tanda.
4. Vuelve a la mesada: arriba aparece "Tanda SIE-…-03 en pausa · 12/30 · Reanudar". Abre en el ítem 13.
5. No hay duplicados: los 12 ítems hechos ya tienen su actividad `COMPLETED` (clave idempotente) y sus lecturas generadas.

## 5. VITEK
Mesada propia: aislamientos con `VITEK_ID`/`VITEK_AST` READY. Se arma la tanda indicando casete, posición, tipo de tarjeta y número de tarjeta, y se genera una hoja de carga. Mientras no exista la integración, los resultados se cargan a mano o se importan de un archivo de exportación (formato a confirmar). La integración automática queda para una fase posterior, una vez que tengamos la documentación técnica del equipo.
