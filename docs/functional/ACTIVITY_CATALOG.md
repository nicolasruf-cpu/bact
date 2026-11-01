# Catálogo de actividades (Fase 1)

> Define la **estructura del proceso**: qué actividades existen, qué datos registran y qué generan. Los **parámetros clínicos** (qué medios, cuántas horas, qué opciones, qué paneles) vienen de la configuración versionada y aparecen indicados como `⚙ config`. Lo que necesita definición del laboratorio está marcado como `❓ Q-xx` (ver OPEN_QUESTIONS.md).

Columnas comunes:
- **En tanda:** la actividad se puede agrupar en una tanda.
- **Confirmación múltiple:** se pueden confirmar varios ítems con una sola acción (ver WORK_BATCHES.md §3).
- **Roles propuestos:** pendientes de confirmar en ROLES_PERMISSIONS.md.

---

## A. Recepción

### RECEIVE_SAMPLE — Recepción física
| | |
|---|---|
| Sujeto | Sample (preregistrada por el LIS) |
| Se crea cuando | El LIS informa una solicitud con muestra esperada (sin integración: no existe; la recepción se registra directamente) |
| Registra | **Por orden** (Q-F8): número de orden de LABWIN, paciente, médico, servicio, prioridad; **por muestra**: código propio, tipo, estudios, hora de toma, observaciones. Si la orden ya existe en la sede, las muestras se suman a ella (pueden llegar en distintos momentos). Cada entrega queda registrada como `Reception`. Condiciones de recepción ⚙ config: pendiente |
| Resultado | `SampleReceived` → un `INOCULATE` por estudio (y `DIRECT_EXAM` si el protocolo lo indica) · o `SampleRejected` (motivo de rechazo ⚙ config, ❓ Q-C8) |
| En tanda | Sí (recepción por lote con lector de barras) |
| Confirmación múltiple | Sí, solo "recibida en condiciones" sin observaciones |
| Roles | Auxiliar, Bioquímico |

## B. Procesamiento primario

### INOCULATE — Siembra
| | |
|---|---|
| Sujeto | SampleStudy |
| Disponible | En cuanto se recibe la muestra |
| Vence | Tiempo objetivo desde la recepción ⚙ config (❓ Q-C6) |
| Registra | Medios sembrados (precargados desde el protocolo, editables con motivo), condición de incubación, hora, operador; lote de medio (❓ Q-F2); observaciones |
| Resultado | Crea `Culture` + `Plate`s → `READ_CULTURE` según el cronograma de lecturas del protocolo ⚙ config (❓ Q-C1) |
| En tanda | Sí (agrupada por tipo de muestra + protocolo) |
| Confirmación múltiple | Sí, **solo** si se usa el protocolo estándar sin modificaciones |
| Roles | Auxiliar, Bioquímico |

### DIRECT_EXAM — Examen directo / Gram de la muestra
**Implementado (2026-11-01, D-0018).**
- Se crea al recibir la muestra si el protocolo tiene `direct_exam_settings`; es inmediato si el protocolo lo marca urgente (LCR).
- Se hace en la mesada de procesamiento.
- Uno por estudio.
- El LCR con microorganismos abre `COMMUNICATE_CRITICAL`.
- Habilita el informe preliminar una vez sembrada la muestra.

| | |
|---|---|
| Sujeto | SampleStudy |
| Se crea cuando | El protocolo lo incluye ⚙ config |
| Registra | Resultado estructurado según la plantilla del protocolo (p. ej., morfologías, células) ⚙ config + texto |
| Resultado | Puede disparar una regla crítica; puede habilitar un informe preliminar ⚙ config (❓ Q-C7) |
| En tanda | Sí · Confirmación múltiple: No |
| Roles | Bioquímico |

### READ_CULTURE — Lectura de cultivo
| | |
|---|---|
| Sujeto | Culture (muestra todas sus placas juntas) |
| Disponible | Hora de siembra + incubación mínima de esa lectura ⚙ config |
| Registra | Por lectura: opción de resultado permitida por el protocolo ⚙ config (❓ Q-C2); por placa: desarrollo sí/no; recuento si el protocolo lo pide; **creación de aislamientos** (morfotipo, placa de origen, recuento, significancia); observaciones |
| Resultados posibles | **Sin desarrollo (intermedia)** → espera la siguiente lectura programada · **Sin desarrollo (final)** → cierra el cultivo y verifica si el estudio está completo para validar · **Desarrollo** → aislamientos → actividades de identificación por aislamiento · **Flora mixta / contaminación** → acción según protocolo (cerrar, informar, pedir nueva muestra) ⚙ config · **No significativo** → se cierra con ese resultado · **Reincubar** → `REINCUBATE_READ` |
| En tanda | Sí |
| Confirmación múltiple | Solo para "sin desarrollo" **y** si el protocolo lo permite explícitamente (Q-F1: configurable por protocolo, desactivado por defecto). Nunca cuando hay desarrollo |
| Roles | Bioquímico (el Auxiliar no hace lecturas — Q-S1) |

### Cronograma de lecturas (implementado 2026-11-01, D-0017)
- `READ_CULTURE` se repite según el cronograma del protocolo (p. ej., 24, 48 y 72 h desde la siembra).
- Sin desarrollo antes de la última lectura → se programa la siguiente (`attempt + 1`).
- Con desarrollo → aislamientos e identificación, y siguen las lecturas programadas (desarrollo nuevo o sin desarrollo nuevo).
- El cultivo cierra al terminar las lecturas y resolver los aislamientos.

### SUBCULTURE_ENRICHMENT — Repique de caldo de enriquecimiento (implementado 2026-11-01)
| | |
|---|---|
| Sujeto | Plate (caldo) |
| Disponible | Siembra + horas del caldo ⚙ config (selenito: 18–24 h en la plantilla) |
| Registra | Placas nuevas del mismo cultivo, en los medios del protocolo |
| Resultado | Las placas se leen en las lecturas programadas; la última lectura exige el repique |
| Mesada | Procesamiento · Roles: Auxiliar, Bioquímico (`cultures.inoculate`) |

### REINCUBATE_READ — Lectura de reincubación
Igual que READ_CULTURE, con `attempt + 1` y `available_at` = ahora + reincubación ⚙ config. El protocolo puede fijar un máximo de reincubaciones.

### SUBCULTURE — Repique / purificación
| | |
|---|---|
| Sujeto | Isolate |
| Se crea cuando | El usuario indica que el aislamiento no está puro o hace falta otro medio |
| Registra | Medio, hora |
| Resultado | Aislamiento hijo (`parent_isolate_id`) + `READ_SUBCULTURE` (lectura del repique) |
| En tanda | Sí · Confirmación múltiple: No |

## C. Identificación

### ID_RAPID_TESTS — Gram y pruebas rápidas del aislamiento
| | |
|---|---|
| Sujeto | Isolate |
| Registra | Gram (morfología + tinción), catalasa, oxidasa y otras rápidas ⚙ config (catálogo de pruebas) |
| Resultado | Sugiere el siguiente método según la configuración (bioquímicas, API, VITEK); el usuario confirma o elige otro. Puede crear una identificación **preliminar** a nivel de grupo |
| En tanda | Sí · Confirmación múltiple: No |
| Roles | Bioquímico |

### ID_BIOCHEM_SETUP / ID_BIOCHEM_READ — Pruebas bioquímicas y galerías (TSI, LIA, SIM, citrato, ureasa, API…)
| | |
|---|---|
| Sujeto | IdentificationAttempt (método = bioquímicas / API) |
| SETUP registra | Pruebas sembradas (set sugerido ⚙ config, editable), hora |
| READ disponible | Al cumplirse la incubación de la prueba ⚙ config |
| READ registra | Resultado de cada prueba (valores permitidos ⚙ config); código o perfil API; organismo resultante + nivel (preliminar/definitivo) **o** "no concluyente" |
| Resultado | Concluyente definitivo → `IsolateIdentified` → antibiograma si corresponde ⚙ config (❓ Q-C3) · No concluyente → el usuario elige: otro método, pruebas adicionales o derivación |
| En tanda | Sí · Confirmación múltiple: No |
| Roles | SETUP: Auxiliar, Bioquímico · READ (resultado de cada prueba): Auxiliar, Bioquímico · Organismo definitivo: Bioquímico |

### VITEK_LOAD — Carga en VITEK (ID y/o AST)
| | |
|---|---|
| Sujeto | Isolate (genera un IdentificationAttempt y/o un SusceptibilityTest con método VITEK) |
| Registra | Tipo de tarjeta, número de tarjeta, casete y posición, hora; suspensión/turbidez si se registra (❓ Q-F3) |
| Resultado | `VITEK_RESULT` (pendiente de resultado) |
| En tanda | **Sí, obligatoriamente** (tanda = casete o corrida) · genera hoja de carga |

### VITEK_RESULT — Resultado VITEK
| | |
|---|---|
| Registra | Carga manual o importación (❓ Q-B2): organismo, % de probabilidad, nivel de confianza; CIM por antibiótico |
| Resultado | ID → revisión del usuario (acepta como definitiva, preliminar o no concluyente) · AST → interpretación (ver AST_READ) |
| Confirmación múltiple | No |

## D. Sensibilidad

### AST_SETUP — Preparación de antibiograma
| | |
|---|---|
| Sujeto | SusceptibilityTest |
| Se crea cuando | Identificación definitiva (o a nivel de grupo, si la configuración lo permite) + criterio de antibiograma ⚙ config (❓ Q-C3) |
| Registra | Método (difusión / CIM por tira / microdilución), panel sugerido ⚙ config (editable con motivo), hora, lotes (❓ Q-F2) |
| Resultado | `AST_READ` con `available_at` según el método ⚙ config |
| En tanda | Sí · Confirmación múltiple: Sí, solo con el panel sugerido sin cambios |

### AST_READ — Lectura de antibiograma
| | |
|---|---|
| Registra | Valor medido por antibiótico (halo en mm o CIM con operador); pruebas complementarias ⚙ config |
| El sistema calcula | Interpretación automática con el BreakpointSet activo (congelado) y aplica reglas expertas ⚙ config |
| El usuario | Revisa; cualquier cambio de interpretación exige motivo; marca qué se informa |
| Resultados posibles | Completo → el estudio puede quedar listo para validar · **Repetir** (con motivo) → nuevo SusceptibilityTest `repeat_of_id` + AST_SETUP · Inválido (control de calidad, contaminación) |
| Confirmación múltiple | **No** |

## E. Validación, informes y críticos

### VALIDATE_REPORT — Validación
| | |
|---|---|
| Sujeto | Report (borrador generado automáticamente) |
| Se crea cuando | Estudio completo, o hito de informe preliminar o parcial ⚙ config (❓ Q-C7) |
| Muestra | Resumen completo: muestra, paciente (mínimo), lecturas, aislamientos, identificaciones, antibiograma (medido / automático / revisado / informado), discordancias, alertas, historial |
| Acciones | Validar (firma ❓ Q-S2) · Devolver a una actividad (con motivo, la reabre) · Editar comentarios del informe |
| Confirmación múltiple | **No** (decisión de seguridad: cada informe se valida individualmente) — confirmado por el laboratorio (Q-F4) |
| Roles | Bioquímico, Director técnico (❓ Q-S3: ¿cuatro ojos?) |

### CORRECT_REPORT — Corrección de informe emitido
Abre una nueva versión del informe a partir del definitivo, con motivo obligatorio. El informe anterior pasa a `SUPERSEDED` y la nueva versión vuelve a validación. Roles: ❓ Q-S4.

### COMMUNICATE_CRITICAL — Comunicación de resultado crítico
| | |
|---|---|
| Sujeto | CriticalAlert |
| Prioridad | Máxima; siempre visible en la barra superior hasta que se cierre |
| Registra | Intento(s): destinatario, rol o servicio, medio, hora, éxito/fallo, relectura confirmada (❓ Q-C5) |
| Resultado | Comunicado → cierra la actividad · Fallo → sigue abierta y escala según el plazo ⚙ config |

### REQUEST_NEW_SAMPLE — Solicitud de nueva muestra
Se crea por rechazo o contaminación, si el protocolo lo indica. Registra a quién se pidió y cuándo.

## F. Hemocultivo y retrocultivo (implementado 2026-11-01)
Un frasco por muestra. Los tiempos salen del protocolo congelado (⚙ [plantilla](../templates/HEMOCULTIVO.md)).

| Actividad | Se crea cuando | Sujeto | Quién (plantilla) |
|---|---|---|---|
| `LOAD_BOTTLE` Carga de frasco | Se recibe la muestra | Estudio | Auxiliar, Bioquímico |
| `CONFIRM_NEGATIVE_BOTTLE` Descarga del negativo | Equipo: carga + días de incubación | Frasco | Bioquímico |
| `BLIND_SUBCULTURE` Repique | Manual: cada 24 h desde la carga, hasta completar los días | Frasco | Auxiliar, Bioquímico |
| `READ_BLIND_SUBCULTURE` Lectura de repique | Repique + 18 h (vence a las 24 h) | Repique | Bioquímico |
| `GRAM_STAIN` Gram (**inmediata**) | El frasco da positivo | Frasco | Bioquímico |
| `SUBCULTURE_BOTTLE` Subcultivo (**inmediata**) | Positivo por alarma o por signos visibles | Frasco | Auxiliar, Bioquímico |
| `COMMUNICATE_CRITICAL` (**inmediata**) | Se registra el Gram (Q-C5) | Resultado crítico | Bioquímico |

- Cuando el frasco da positivo, se cancelan sus repiques, sus lecturas pendientes y la descarga del negativo.
- Si el positivo sale de un repique, esa placa pasa a ser el cultivo (sin subcultivo aparte).
- Desde el subcultivo sigue el circuito común: `READ_CULTURE`, `IDENTIFY`, antibiograma y `VALIDATE_REPORT`.
- Un frasco negativo pasa directo a `VALIDATE_REPORT`.

Diseño original (Fase 1): La positividad de un frasco (manual o por integración ❓ Q-B3) genera: `DIRECT_EXAM` (Gram del frasco, **urgente**) + `INOCULATE` del frasco positivo + evaluación de regla crítica. El cronograma de frascos negativos (días de incubación) se toma de ⚙ config.
