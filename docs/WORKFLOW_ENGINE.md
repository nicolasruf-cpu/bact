# Motor de actividades (Workflow)

> Principio: **la unidad de seguimiento es la muestra; la unidad de trabajo es la actividad.**

## 1. Qué es una actividad
Una tarea concreta, atómica y asignable sobre un sujeto (muestra, placa, aislamiento, antibiograma, etc.).

| Campo | Descripción |
|---|---|
| `type` | Enum (ver `functional/ACTIVITY_CATALOG.md`): `RECEIVE_SAMPLE`, `INOCULATE`, `DIRECT_EXAM`, `READ_CULTURE`, `REINCUBATE_READ`, `SUBCULTURE`, `READ_SUBCULTURE`, `ID_RAPID_TESTS`, `ID_BIOCHEM_SETUP`, `ID_BIOCHEM_READ`, `VITEK_LOAD`, `VITEK_RESULT`, `AST_SETUP`, `AST_READ`, `VALIDATE_REPORT`, `CORRECT_REPORT`, `COMMUNICATE_CRITICAL`, `REQUEST_NEW_SAMPLE` |
| `subject_type/subject_id` | Sujeto polimórfico |
| `sample_id` | Desnormalizado: permite buscar y agrupar rápido por muestra |
| `status` | ver §2 |
| `priority` | `routine`, `urgent`, `stat` (calculada: solicitud + reglas + escalamiento) |
| `available_at` | Desde cuándo se puede hacer (p. ej., fin de la incubación mínima) |
| `due_at` | Límite; si se pasa, la actividad está **demorada** |
| `caused_by_activity_id` | Trazabilidad de qué actividad la originó |
| `attempt` | Número de intento (repeticiones, reincubaciones) |
| `claimed_by / claimed_at` | Quién la tiene tomada (vía tanda o individual) |
| `completed_by / completed_at` | |
| `idempotency_key`, `lock_version` | Concurrencia |

## 2. Estados de actividad

```
            ┌───────────── cancel (motivo) ─────────────┐
            │                                           ▼
 SCHEDULED ──(available_at alcanzado)──► READY ──claim──► IN_PROGRESS ──complete──► COMPLETED
     │                                    ▲  │                │
     │                                    │  └─ hold ─► ON_HOLD (p. ej., falta material)
     │                                    └── release ────────┘ (interrupción / retiro de tanda)
     └──────────────────────────────────────────────────────────────────────────► CANCELLED
```
- `SCHEDULED` es "trabajo previsto": la actividad existe pero todavía no está lista (la placa sigue incubando). Esto alimenta la tarjeta "Trabajo previsto para el siguiente turno".
- **Demorada** no es un estado, es una condición: `now() > due_at` y la actividad no está terminada. Así se evitan transiciones artificiales.
- `COMPLETED` y `CANCELLED` son finales. Si hace falta corregir, se crea una actividad nueva vinculada.

## 3. Generación automática (políticas de flujo)
No se construye un motor BPMN genérico (ADR-0004). Se usan **políticas explícitas en PHP**, parametrizadas por la configuración versionada del protocolo:

| Evento de dominio | Política → actividades generadas |
|---|---|
| `SampleReceived` | `INOCULATE` por cada estudio (prioridad heredada) |
| `SampleRejected` | Cancela las actividades abiertas de la muestra; registra el motivo |
| `CultureInoculated` | `READ_CULTURE` por cultivo/placa, con `available_at = sembrado + incubación mínima del protocolo` |
| `ReadingRecorded(no_growth, final)` | Si todo el estudio está completo → `VALIDATE_REPORT` |
| `ReadingRecorded(reincubate)` | `REINCUBATE_READ` con nuevo `available_at` |
| `ReadingRecorded(growth)` + aislamientos creados | Por aislamiento: actividades de identificación según protocolo (Gram/bioquímicas/VITEK) |
| `IsolateIdentified(definitive)` | Según protocolo + organismo + significancia → `AST_SETUP` o `VITEK_AST` |
| `AstSetUp` | `AST_READ` con `available_at` según el método |
| `AstRead` | Interpretación automática → si todo terminó → `VALIDATE_REPORT` |
| `CriticalRuleMatched` | `CriticalAlert` + `COMMUNICATE_CRITICAL` (prioridad máxima) |
| Positivo de hemocultivo (manual o integración) | `GRAM_STAIN` urgente + evaluación de regla crítica |

Reglas del motor:
- Las políticas se ejecutan **de forma síncrona en la misma transacción** que el cambio clínico.
- Toda generación es **idempotente**: hay una clave única (`subject`, `type`, `attempt`), así que si el evento se repite no se duplican actividades.
- Las decisiones que dependen de criterios clínicos (qué medios, qué tiempos, cuándo hacer antibiograma) **se leen de la configuración**: nunca quedan escritas en el código (ver MICROBIOLOGY_RULES.md).

## 4. Mesadas (workbenches)
Una mesada es una **consulta guardada y optimizada** sobre actividades: tipo(s), estado, prioridad y agrupación. No hay tablas duplicadas.

| Tarjeta de la pantalla principal | Fuente |
|---|---|
| Urgencias y resultados críticos | `CriticalAlert` abiertas + actividades `stat`/`urgent` |
| Pendientes de siembra | `INOCULATE` READY, agrupadas por tipo de muestra y protocolo |
| Cultivos pendientes de lectura | `READ_CULTURE`/`REINCUBATE_READ` READY |
| Identificaciones pendientes | `GRAM_STAIN`, `BIOCHEMICAL_TESTS` READY |
| Identificaciones VITEK | `VITEK_ID` READY |
| Pruebas bioquímicas pendientes | `BIOCHEMICAL_TESTS` con lectura pendiente |
| Antibiogramas a preparar | `AST_SETUP`, `VITEK_AST` READY |
| Antibiogramas a leer | `AST_READ` READY |
| Pendientes de validación | `VALIDATE_REPORT` READY |
| Demoradas | cualquier tipo con `due_at < now()` |
| Previsto para el siguiente turno | `SCHEDULED` con `available_at` dentro de la ventana del próximo turno |

Cada tarjeta muestra: pendientes, en proceso, cuántas de prioridad alta, demoradas y la acción principal ("Iniciar tanda de siembra"). Los contadores salen de consultas agregadas con índices compuestos (`type, status, priority, available_at`).

## 5. Prioridad y orden
Orden por defecto dentro de una mesada: crítico > stat > urgente > demorada > `available_at` más antiguo. El orden es explicable: la UI muestra por qué cada ítem está arriba.
