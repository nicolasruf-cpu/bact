# Plantilla de referencia — Hemocultivo y retrocultivo

> **PLANTILLA DE REFERENCIA — REQUIERE VALIDACIÓN DEL LABORATORIO (D-0008).**
> Contenido genérico basado en práctica difundida en microbiología clínica. No es una definición del laboratorio RUF.
> En producción se carga en estado `draft` y **no se usa con pacientes** hasta que el Director técnico responsable (Q-A8) la revise, la ajuste y la apruebe.
> En los entornos de desarrollo y demostración se carga activa, con datos sintéticos, solo para poder probar el circuito.

**Fuentes de referencia** (a confirmar por el laboratorio contra sus ediciones vigentes):
- CLSI M47, *Principles and Procedures for Blood Cultures*.
- *Clinical Microbiology Procedures Handbook* (ASM Press), sección "Blood Cultures".

## 1. Definido por el laboratorio (2026-11-01)
- **Un frasco por muestra:** cada hemocultivo o retrocultivo de la orden es una muestra con su propio código, y cada frasco se sigue por separado.
- **"X de N positivos" se cuenta por separado** para hemocultivos y retrocultivos de la misma orden (p. ej., "Hemocultivo: 1 de 2 positivos · Retrocultivo: 0 de 1 positivo").
- **Dos formas de procesar:** en el equipo automatizado (BacT/ALERT), registrando lo que informa el equipo, o en forma manual con **repique diario a placa**.
- **Ante un frasco positivo:** Gram urgente, **informe preliminar** con el Gram y **registro de la comunicación** al médico.

## 2. Estudios y tipos de muestra
| Estudio | Código | Tipo de muestra |
|---|---|---|
| Hemocultivo | `HEMO` | `SAN-PER` Sangre periférica |
| Retrocultivo | `RETRO` | `SAN-CAT` Sangre por catéter |

## 3. Frasco e incubación
| Parámetro | Valor de la plantilla | Pregunta |
|---|---|---|
| Tipos de frasco | Aerobio · Anaerobio · Pediátrico | Q-C11 |
| Equipo | BacT/ALERT | — |
| Incubación en equipo | 5 días; sin alarma → se descarga como negativo | Q-C11 |
| Incubación manual | 7 días | Q-C11 |
| Repique manual | 1 por día (a las 24 h, 48 h… desde la carga), en agar chocolate | Q-C11 |
| Lectura del repique | disponible a las 18 h, vence a las 24 h | Q-C11 |
| Resultado negativo | "Sin desarrollo a los {días} días de incubación" | — |

**Frasco manual:**
- La lectura de un repique **con desarrollo** vuelve positivo al frasco, y esa placa pasa a ser el cultivo en estudio.
- Si **el último repique se lee sin desarrollo** (y todos están leídos), el frasco se cierra como negativo.
- También se puede registrar un positivo por **signos visibles** en el frasco (turbidez, hemólisis, gas).

## 4. Gram del frasco positivo
Opciones (con descripción libre adicional):
- Cocos gram positivos en racimos
- Cocos gram positivos en cadenas
- Cocos gram positivos en pares
- Bacilos gram negativos
- Bacilos gram positivos
- Cocos gram negativos
- Levaduras
- Más de un morfotipo
- No se observan microorganismos

Por decisión del laboratorio, el Gram de un frasco positivo **abre un resultado crítico a comunicar**. Está pendiente si se comunica también cuando no se observan microorganismos (Q-C5).

## 5. Subcultivo del frasco positivo
| Parámetro | Valor de la plantilla |
|---|---|
| Medios | Agar sangre (CO₂) · agar chocolate (CO₂) · agar MacConkey (aerobiosis), 35 ± 2 °C |
| Lectura | disponible a las 18 h, vence a las 24 h; reincubación +24 h (máximo 1) |
| Opciones de lectura | Desarrollo (se cargan aislamientos) · Sin desarrollo en el subcultivo · Reincubar |
| Recuento | no aplica |
| Antibiograma | mismos paneles por grupo de organismo que en urocultivo (a validar para sangre, Q-C3); lectura a las 16–24 h |

**El sistema no decide si un aislamiento es significativo o contaminante:** lo marca el bacteriólogo (p. ej., un estafilococo coagulasa negativo en 1 de 2 frascos). El resumen de la orden está a la vista para tomar esa decisión.

## 6. Fuera de la plantilla
- Tiempo diferencial de positividad entre hemocultivo y retrocultivo: el sistema muestra los tiempos de cada frasco, pero no calcula la diferencia ni la interpreta (Q-C10).
- Puntos de corte, reglas expertas y criterios de rechazo (D-0008).
- Interfaz con el BacT/ALERT: por ahora se registra a mano lo que informa el equipo (Q-B3).
