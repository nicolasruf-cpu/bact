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

## 4. Pendiente para la entrega de lectura (no incluido todavía)
- Opciones de lectura y su redacción (sin desarrollo, desarrollo, flora mixta, etc.).
- Recuentos (UFC/mL) y criterios de significancia por tipo de muestra (Q-C2).
- Cuándo se identifica y cuándo se hace antibiograma (Q-C3).

Estos puntos se agregarán a esta plantilla, con su fuente, antes de construir la lectura.
