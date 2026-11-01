# Plantilla de referencia — Urocultivo

> **PLANTILLA DE REFERENCIA — REQUIERE VALIDACIÓN DEL LABORATORIO (D-0008).**
> Contenido genérico basado en práctica difundida en microbiología clínica. No es una definición del laboratorio RUF.
> En producción se carga en estado `draft` y **no se usa con pacientes** hasta que el Director técnico responsable (Q-A8) la revise, la ajuste y la apruebe.
> En los entornos de desarrollo y demostración se carga activa, con datos sintéticos, solo para poder probar el circuito.

**Fuentes de referencia** (a confirmar por el laboratorio contra sus ediciones vigentes):
- *Clinical Microbiology Procedures Handbook* (ASM Press), sección "Urine Cultures".
- Miller JM et al. *A Guide to Utilization of the Microbiology Laboratory for Diagnosis of Infectious Diseases: 2024 Update by the IDSA and ASM*. Clin Infect Dis.

## 1. Tipos de muestra incluidos
| Código | Tipo de muestra |
|---|---|
| `URI-CM` | Orina — chorro medio |
| `URI-SV` | Orina — sonda vesical (cateterismo) |

Otros tipos (punción suprapúbica, bolsa recolectora, sonda permanente, nefrostomía) quedan para una versión posterior.

## 2. Siembra
| Parámetro | Chorro medio (`URI-CM`) | Sonda vesical (`URI-SV`) | Pregunta |
|---|---|---|---|
| Medios | Agar sangre + agar MacConkey | Agar sangre + agar MacConkey | Q-C1 (¿CLED o cromogénico?) |
| Inóculo | Asa calibrada 1 µL (0,001 mL) | Asa calibrada 10 µL (0,01 mL) | Q-C1 |
| Incubación | 35 ± 2 °C, aerobiosis | 35 ± 2 °C, aerobiosis | Q-C1 |

## 3. Cronograma de lectura
| Parámetro | Valor de la plantilla |
|---|---|
| Primera lectura disponible desde | 18 h desde la siembra |
| Primera lectura vence a las | 24 h desde la siembra |
| Reincubación (cuando el bacteriólogo la indica) | +24 h (hasta 48 h en total) |
| Máximo de reincubaciones | 1 |
| Confirmación en bloque de "sin desarrollo" | **Desactivada** (Q-F1) |

## 4. Opciones de lectura (Q-C2)
El bacteriólogo elige una opción por lectura. El sistema **no decide la significancia**: la marca el bacteriólogo en cada aislamiento.

| Código | Opción | Qué hace el sistema |
|---|---|---|
| `NEG` | Sin desarrollo | Cierra el cultivo como negativo → listo para validar |
| `POS` | Desarrollo (se cargan aislamientos) | Cada aislamiento significativo pasa a identificación |
| `MIX` | Flora mixta (≥ 3 morfotipos sin predominio), probable contaminación | Cierra el cultivo como contaminado → listo para validar (el informe sugiere nueva muestra) |
| `NS` | Desarrollo no significativo | Cierra el cultivo como no significativo → listo para validar |
| `REI` | Reincubar | Programa una nueva lectura a las +24 h (máximo 1 vez) |

## 5. Categorías de recuento (UFC/mL)
`< 10³` · `10³–10⁴` · `10⁴–10⁵` · `≥ 10⁵`

Referencia (a validar): en chorro medio el criterio clásico de significancia es ≥ 10⁵ UFC/mL, y se aceptan recuentos menores en pacientes sintomáticos o con ciertos organismos; en sonda vesical se consideran recuentos desde 10³ UFC/mL (guía IDSA de infección urinaria asociada a catéter, 2009). **La significancia la decide el bacteriólogo; el sistema solo registra.**

## 6. Identificación y antibiograma (Q-C3)
- Se identifica cada aislamiento marcado como significativo.
- Se hace antibiograma a los aislamientos significativos con identificación definitiva cuyo grupo de organismo tenga panel. El bacteriólogo puede indicar "no requiere antibiograma" con motivo (p. ej., *Staphylococcus saprophyticus*, flora habitual).
- Lectura del antibiograma: disponible a las 16 h de la preparación, vence a las 24 h.

## 7. Paneles de antibióticos (plantilla, a validar)
| Grupo | Antibióticos |
|---|---|
| Enterobacterales (orina) | Ampicilina, amoxicilina-clavulánico, cefalotina, cefuroxima, ceftriaxona, ceftazidima, cefepime, imipenem, gentamicina, amikacina, ciprofloxacina, trimetoprima-sulfametoxazol, nitrofurantoína, fosfomicina |
| *Pseudomonas aeruginosa* | Ceftazidima, cefepime, piperacilina-tazobactam, imipenem, meropenem, gentamicina, amikacina, ciprofloxacina |
| *Enterococcus* spp. (orina) | Ampicilina, vancomicina, nitrofurantoína, ciprofloxacina, fosfomicina |
| *Staphylococcus* spp. (orina) | Oxacilina (cefoxitina), trimetoprima-sulfametoxazol, nitrofurantoína, ciprofloxacina, vancomicina |

## 8. Puntos de corte
**No se incluyen** (derechos de autor de CLSI; Q-C4). El laboratorio los carga desde su edición del M100 y el responsable los aprueba. En demo se usa un set **FICTICIO**, con valores uniformes sin significado clínico, rotulado "NO CLÍNICO".
