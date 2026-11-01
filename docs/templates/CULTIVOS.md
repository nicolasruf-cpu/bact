# Plantillas de referencia — Resto de los cultivos en placa

> **PLANTILLA DE REFERENCIA — REQUIERE VALIDACIÓN DEL LABORATORIO (D-0008).**
> Contenido genérico basado en práctica difundida en microbiología clínica. No es una definición del laboratorio RUF.
> En producción se carga en estado `draft` y **no se usa con pacientes** hasta que el Director técnico responsable (Q-A8) la revise, la ajuste y la apruebe.
> En los entornos de desarrollo y demostración se carga activa, con datos sintéticos, solo para poder probar el circuito.

**Fuentes de referencia** (a confirmar por el laboratorio contra sus ediciones vigentes):
- *Clinical Microbiology Procedures Handbook* (ASM Press): secciones de heridas, líquidos estériles, LCR, vías respiratorias, materia fecal, tracto genital y fauces.
- *IDSA/ASM Guide to Utilization of the Microbiology Laboratory* (2024).

## 1. Definido por el laboratorio (2026-11-01)
- **Estudios de esta etapa:**
  - secreciones y heridas, y líquidos de punción;
  - LCR;
  - muestras respiratorias;
  - coprocultivo;
  - secreciones genitales y fauces.
- **Examen directo:** cada protocolo indica si lleva directo y con qué opciones. Con el directo se puede emitir un **informe preliminar**.
- **Resultado crítico:** el **directo de LCR con microorganismos** se comunica, igual que el Gram de un frasco de hemocultivo positivo. Otros directos se registran sin abrir un crítico.
- **Cronograma de lecturas por protocolo:**
  - cada protocolo fija sus lecturas (p. ej., 24, 48 y 72 h desde la siembra);
  - "sin desarrollo" antes de la última lectura espera la siguiente;
  - "sin desarrollo" en la última lectura cierra el cultivo.

## 2. Cómo funciona el cronograma (D-0017)
- Cada lectura programada está disponible desde **h − 6** y vence a las **h** contadas desde la siembra (24 h: de 18 a 24; 48 h: de 42 a 48; 72 h: de 66 a 72). Los tiempos son de la plantilla (Q-C12).
- **Si hay desarrollo en una lectura intermedia,** se cargan los aislamientos y se siguen haciendo las lecturas programadas. En esas lecturas solo cabe "desarrollo nuevo" (se suman aislamientos) o "sin desarrollo nuevo".
- El cultivo se cierra como positivo cuando se terminaron las lecturas **y** están resueltos todos los aislamientos. **Pregunta Q-C12:** ¿se sigue leyendo después de un desarrollo?
- **El informe** usa la redacción de la lectura con desarrollo; si no la hubo, la de la última lectura. Un preliminar con el cultivo abierto y sin desarrollo dice "Cultivo en curso": no adelanta un negativo.
- **Caldo de enriquecimiento:** se repica a placas a las horas que fija el protocolo.
  - Las placas del repique se leen junto con el resto del cultivo, después de la incubación mínima de la primera lectura.
  - La última lectura no se puede registrar con un caldo sin repicar.

## 3. Estudios y protocolos
Todos los medios se incuban a 35 ± 2 °C, salvo que se indique otra cosa.

**Lecturas de todos los protocolos:**
- "Desarrollo" y "Reincubar" (24 h, máximo 1).
- El antibiograma se lee a las 16–24 h, con los mismos paneles por grupo de organismo que el urocultivo (a validar por tipo de muestra, Q-C3).

| Estudio (código) | Tipos de muestra | Medios | Lecturas | Directo |
|---|---|---|---|---|
| Cultivo de secreción y herida (`SEC`) | `HER` Herida / úlcera (hisopado) · `ABS` Absceso (punción o aspirado) | AS (CO₂) · CHOC (CO₂) · MAC | 24 · 48 h | Gram |
| Cultivo de líquido de punción (`LIQ`) | `LPL` Pleural · `LAS` Ascítico · `LAR` Articular | AS (CO₂) · CHOC (CO₂) · MAC | 24 · 48 · 72 h | Gram |
| Cultivo de LCR (`LCR`) | `LCR` Líquido cefalorraquídeo | AS (CO₂) · CHOC (CO₂) | 24 · 48 · 72 h | Gram **inmediato y crítico con microorganismos** |
| Cultivo de secreción respiratoria (`RESP`) | `ESP` Esputo · `ATQ` Aspirado traqueal · `BAL` Lavado broncoalveolar | AS (CO₂) · CHOC (CO₂) · MAC | 24 · 48 h | Gram con calidad de la muestra |
| Coprocultivo (`COPRO`) | `MF` Materia fecal | MAC · SS · XLD · Campylobacter (microaerofilia, 42 ± 1 °C) · **caldo selenito → SS y XLD a las 18–24 h** | 24 · 48 h | Leucocitos y hematíes |
| Cultivo de secreción genital (`GEN`) | `FV` Flujo vaginal: AS (CO₂) y Sabouraud, 24 · 48 h · `URE` Uretral y `ENC` Endocervical: AS · CHOC · Thayer-Martin (CO₂), 24 · 48 · 72 h | según el tipo | según el tipo | `FV`: fresco y Gram · `URE`, `ENC`: Gram |
| Exudado de fauces (`FAU`) | `HFA` Hisopado de fauces | AS (CO₂) | 24 · 48 h | — |

### Opciones de lectura sin desarrollo y no significativo
| Estudio | Sin desarrollo | No significativo |
|---|---|---|
| `SEC` | Sin desarrollo | Desarrollo de flora habitual de piel |
| `LIQ`, `LCR` | Sin desarrollo | — |
| `RESP` | Sin desarrollo | Desarrollo de flora habitual de vías respiratorias superiores |
| `COPRO` | No se aislaron Salmonella, Shigella ni Campylobacter | — |
| `GEN` | Sin desarrollo | Desarrollo de flora habitual |
| `FAU` | No se aisló Streptococcus beta hemolítico del grupo A | — |

En `COPRO` y `FAU`, "Desarrollo" se redacta como "Desarrollo de enteropatógeno" y "Desarrollo de colonias beta hemolíticas".

### Recuentos
| Muestra | Categorías |
|---|---|
| `SEC`, `ESP` | Escaso · Moderado · Abundante desarrollo |
| `ATQ` | < 10⁵ · 10⁵–10⁶ · ≥ 10⁶ UFC/mL |
| `BAL` | < 10³ · 10³–10⁴ · ≥ 10⁴ UFC/mL |

**El sistema no aplica umbrales de significancia:** el bacteriólogo elige la categoría y marca si el aislamiento es significativo (Q-C13).

## 4. Examen directo
- **Gram (`SEC`, `LIQ`, `LCR`, `URE`, `ENC`):**
  - leucocitos: no se observan · escasos · moderados · abundantes;
  - microorganismos (una o más opciones): cocos gram positivos en racimos, en cadenas o en pares · diplococos gram negativos · bacilos gram negativos · bacilos gram positivos · levaduras · no se observan microorganismos.
- **Respiratorias:** además de los microorganismos, células epiteliales y leucocitos por campo de 100×, en tres categorías (< 10, 10–25, > 25). **El sistema no rechaza la muestra por calidad:** registra lo observado (Q-C14).
- **Coprocultivo:** leucocitos y hematíes (no se observan · escasos · moderados · abundantes).
- **Flujo vaginal:**
  - leucocitos;
  - células guía;
  - levaduras / pseudohifas;
  - Trichomonas;
  - microbiota (predominio de lactobacilos · lactobacilos disminuidos · ausentes, flora mixta).
  - **No se calcula un puntaje** (p. ej., Nugent) ni se interpreta (Q-C14).
- **Crítico:** solo el LCR abre un resultado crítico, cuando alguna opción indica microorganismos. "No se observan microorganismos" no lo abre.

## 5. Fuera de la plantilla
- Cultivo de anaerobios, micológico y de micobacterias: estudios aparte, a definir.
- Búsquedas dirigidas adicionales (p. ej., *Streptococcus agalactiae* en embarazadas, *E. coli* productor de toxina Shiga, *Yersinia*, *Vibrio*).
- Criterios de rechazo de muestras, puntos de corte y reglas expertas (D-0008).
