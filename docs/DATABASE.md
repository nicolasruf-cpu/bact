# Base de datos (lineamientos)

- **MariaDB 12.3 LTS**, InnoDB, `utf8mb4` / `utf8mb4_unicode_520_ci` (o `uca1400` según disponibilidad; se define en la Fase 3).
- Claves primarias `BIGINT UNSIGNED` autoincrementales + `ulid` público en las entidades expuestas en URLs (evita IDs enumerables).
- Claves foráneas en todas las relaciones; `ON DELETE RESTRICT` por defecto (los datos clínicos no se borran en cascada).
- **Sin borrado físico** de datos clínicos: estados finales, anulaciones con motivo y versiones.
- Tiempos en `DATETIME(6)` UTC; la UI muestra hora local (America/Argentina/Buenos_Aires).
- Concurrencia: `lock_version INT` en entidades editables; `idempotency_key CHAR(36) UNIQUE` donde corresponda.
- Unicidad de ítem activo en tanda: columna generada `active_activity_id` + `UNIQUE`.
- Índices para mesadas: `activities(type, status, priority, available_at)`, `activities(sample_id)`, `activities(status, due_at)`.
- Estadísticas: tablas de hechos/resumen alimentadas por *jobs*, para que las consultas analíticas no carguen la operación diaria; las consultas son reproducibles porque guardan versiones y parámetros.
- Migraciones solo hacia adelante en producción; cada migración destructiva requiere aprobación.

El modelo físico detallado (tablas y columnas) se redacta en la Fase 3.
