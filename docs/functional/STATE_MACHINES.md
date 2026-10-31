# Máquinas de estado (Fase 1)

Reglas generales:
- Cada transición es una Action del backend: valida el estado de origen, el permiso y el `lock_version`, audita y emite su evento.
- Los estados finales no se reabren. Si hace falta corregir, se crea una entidad o versión nueva vinculada.
- Toda transición marcada como **[motivo]** exige un texto de motivo.

## Muestra (Sample)
```
REGISTERED ──receive──► RECEIVED ──(1.ª siembra)──► IN_PROCESS ──(algún informe emitido)──► PARTIALLY_REPORTED
    │                      │                            │                                     │
    │                      │                            └────(todos los estudios con definitivo)─┴──► REPORTED
    ├──reject [motivo]──► REJECTED ◄──reject [motivo]──┘   (rechazo tardío desde IN_PROCESS: solo Bioquímico/Director)
    └──cancel [motivo]──► CANCELLED (solicitud anulada)
```
- `REGISTERED` existe solo si el LIS preregistra; si no, la muestra nace en `RECEIVED`.
- Al rechazar o cancelar: se cancelan las actividades abiertas, se retiran de las tandas activas y se genera `REQUEST_NEW_SAMPLE` si la configuración lo indica.

## Estudio solicitado (SampleStudy)
```
PENDING ──inoculate──► IN_PROCESS ──(todo cerrado)──► READY_FOR_VALIDATION ──validate──► FINAL_REPORTED
   │                      │  └──(hito preliminar)──► PRELIMINARY_REPORTED ──(continúa)──┘
   └──cancel [motivo]──► CANCELLED      FINAL_REPORTED ──correct [motivo]──► IN_CORRECTION ──validate──► FINAL_REPORTED (v+1)
```

## Cultivo (Culture)
```
INCUBATING ──read(intermedia)──► INCUBATING
     │──read(reincubar)──► INCUBATING (attempt+1)
     │──read(desarrollo)──► WORKUP (aislamientos en estudio) ──(todos los aislamientos resueltos)──► CLOSED_POSITIVE
     │──read(sin desarrollo final)──► CLOSED_NEGATIVE
     │──read(no significativo / flora mixta / contaminado)──► CLOSED_NOT_SIGNIFICANT | CLOSED_CONTAMINATED  ⚙ config
```

## Placa (Plate)
`INCUBATING → FINISHED` · `INCUBATING → LOST [motivo]` (rota, contaminada, extraviada) → el sistema propone volver a sembrar si la muestra está disponible.

## Aislamiento (Isolate)
```
CREATED ──► IN_IDENTIFICATION ──► IDENTIFIED_PRELIMINARY ──► IDENTIFIED_FINAL ──(AST si corresponde)──► RESOLVED
   │              │                                                    └──(sin AST según config)──► RESOLVED
   ├──mark not significant [motivo]──► NOT_SIGNIFICANT
   ├──discard [motivo]──► DISCARDED
   └──subculture──► (aislamiento hijo; el padre queda SUPERSEDED_BY_SUBCULTURE si se reemplaza)
```
"Resuelto" = identificación final + antibiograma(s) terminado(s) o no requerido(s).

## Intento de identificación (IdentificationAttempt)
`PLANNED → SET_UP → RESULTED → { CONCLUSIVE_PRELIMINARY | CONCLUSIVE_FINAL | INCONCLUSIVE }` · `→ INVALID [motivo]`.
Un aislamiento puede tener varios intentos; la identificación vigente la elige explícitamente el usuario y queda auditada.

## Antibiograma (SusceptibilityTest)
```
PLANNED ──setup──► SET_UP ──read──► READ ──review──► REVIEWED
                      │              ├──repeat [motivo]──► (nuevo test, repeat_of_id) ; este pasa a SUPERSEDED
                      └──invalidate [motivo]──► INVALID
```
Dentro de REVIEWED, cada resultado conserva el valor medido, la interpretación automática, la revisada [motivo] y la informada.

## Informe (Report)
```
DRAFT ──validate [firma]──► ISSUED ──correct [motivo]──► SUPERSEDED   (nace DRAFT v+1)
  └──return to work [motivo]──► (reabre la actividad correspondiente; el borrador se regenera)
```
Tipos: `PRELIMINARY`, `PARTIAL`, `FINAL`, `CORRECTED`. Un informe ISSUED queda **inmutable** (snapshot JSON + PDF).

## Alerta crítica (CriticalAlert)
`OPEN → COMMUNICATED → CLOSED` · si no se comunica dentro del plazo ⚙ config: `OPEN → ESCALATED → COMMUNICATED → CLOSED`. Cada intento queda en CommunicationLog.

## Actividad y tanda
Ver WORKFLOW_ENGINE.md §2 y WORK_BATCHES.md §2.
