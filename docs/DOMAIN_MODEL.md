# Modelo de dominio (conceptual)

> Versión conceptual de la Fase 0. Las columnas definitivas se fijan en la Fase 3 (DATABASE.md).

## 1. Diagrama de entidades

```
Patient 1──* LabOrder 1──* Sample 1──* SampleStudy (estudio solicitado)
                                   │            │
                                   │            └──1..* Culture ──* Plate (medio + incubación)
                                   │                       │  └──* Reading (lectura por placa/cultivo)
                                   │                       └──* Isolate ──* Isolate (repique: parent_isolate_id)
                                   │                                 ├──* IdentificationAttempt ──* TestResult
                                   │                                 │        (Gram, bioquímicas, API, VITEK)
                                   │                                 └──* SusceptibilityTest ──* SusceptibilityResult
                                   │                                          (repeat_of_id para repeticiones)
                                   ├──* Report (versiones: preliminar/parcial/definitivo/corregido)
                                   └──* CriticalAlert ──* CommunicationLog

Activity  (subject polimórfico: Sample | Culture | Plate | Isolate | IdentificationAttempt | SusceptibilityTest | Report | CriticalAlert)
   *──0..1 WorkBatchItem *──1 WorkBatch ──* WorkBatchEvent

AuditEvent (append-only; referencia a cualquier entidad)
Configuration: SampleType, Study, Protocol(+versiones), Medium, IncubationCondition, Organism,
               BiochemicalTest, IdentificationMethod, Antibiotic, AstPanel, BreakpointSet(+versión CLSI),
               ExpertRule, CriticalRule, ReportTemplate, Shift
```

**No hay relaciones 1:1 forzadas:**
- Una muestra puede tener varios estudios.
- Un estudio puede generar varios cultivos (p. ej., aerobio y anaerobio).
- Un cultivo tiene varias placas y varios aislamientos.
- Un aislamiento puede tener varios intentos de identificación y varios antibiogramas (repeticiones, otros métodos).
- Una muestra tiene varias versiones de informe.

## 2. Entidades principales

| Entidad | Puntos clave |
|---|---|
| **Patient** | Datos identificatorios mínimos, ID externo del LIS, documento. Datos sensibles bajo control de acceso. |
| **LabOrder** (Solicitud) | Procedencia/servicio, médico solicitante, prioridad, número externo. |
| **Sample** (Muestra) | Código de barras único, tipo de muestra, fecha y hora de toma y de recepción, condiciones de recepción, estado (registrada → recibida → en proceso → informada → cerrada; o rechazada con motivo). |
| **SampleStudy** | Estudio solicitado + **versión de protocolo congelada** al momento de recibir la muestra. |
| **Culture** | Proceso microbiológico de un estudio; estado propio. |
| **Plate** | Medio, condición de incubación, sembrada por, sembrada a las, lectura esperada a las, etiqueta. |
| **Reading** | Resultado de lectura según las opciones del protocolo (sin desarrollo, desarrollo, flora mixta, no significativo, reincubar…), recuento si corresponde, observaciones, autor y hora. **Inmutable**: una corrección genera una nueva lectura que reemplaza a la anterior. |
| **Isolate** (Aislamiento) | Número dentro del cultivo, morfotipo, recuento, significancia, aislamiento padre (repique), identificación vigente. |
| **IdentificationAttempt** | Método, estado, resultados de pruebas, organismo resultante, nivel (preliminar/definitivo), confianza (p. ej., % VITEK), datos de tanda VITEK (tarjeta, posición). |
| **SusceptibilityTest** | Método (difusión, CIM, automatizado), panel, **BreakpointSet + edición CLSI usada**, repetición de, estado. |
| **SusceptibilityResult** | Antibiótico; **valor medido** (mm, o CIM con operador ≤/>); **interpretación automática**; **interpretación revisada** (con motivo); **resultado informado** (sí/no + categoría); reglas aplicadas. |
| **Report** | Versión, tipo, *snapshot* JSON inmutable del contenido, PDF, validado por y cuándo, reemplaza a (versión anterior), motivo de corrección. |
| **CriticalAlert** | Regla disparada, entidad, estado (abierta → comunicada → cerrada), registros de comunicación (destinatario, medio, responsable, hora, confirmación por relectura). |
| **Activity** | Ver WORKFLOW_ENGINE.md. |
| **WorkBatch** | Ver WORK_BATCHES.md. |

## 3. Glosario (UI español ↔ código inglés)

| Español | Código |
|---|---|
| Paciente | Patient |
| Solicitud | LabOrder |
| Muestra | Sample |
| Estudio solicitado | SampleStudy |
| Cultivo | Culture |
| Placa / Medio | Plate / Medium |
| Lectura | Reading |
| Aislamiento / Repique | Isolate / Subculture |
| Identificación | IdentificationAttempt |
| Pruebas bioquímicas | BiochemicalTest / TestResult |
| Antibiograma | SusceptibilityTest (AST) |
| Punto de corte | Breakpoint |
| Informe | Report |
| Resultado crítico | CriticalAlert |
| Actividad | Activity |
| Mesada | Workbench |
| Tanda | WorkBatch |
| Procedencia | Origin / Ward |
| Turno | Shift |
