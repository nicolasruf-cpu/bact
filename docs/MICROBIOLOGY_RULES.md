# Reglas microbiológicas

> **Principio:** el sistema no inventa criterios clínicos. Toda regla clínica es **configuración versionada**, aprobada por un rol autorizado (Director técnico) y auditada. Este documento describe *cómo* se modelan las reglas y *qué definiciones faltan*.

## 1. Mecanismo general
- Cada catálogo clínico (protocolos, paneles, puntos de corte, reglas expertas, reglas críticas, plantillas) tiene **versiones**: `draft → approved → active → retired`.
- Al recibir una muestra, el estudio **congela** la versión de protocolo vigente. Al interpretar un antibiograma, se congela el `BreakpointSet` usado. Así los resultados históricos se pueden reproducir.
- Activar una versión nueva no altera los casos en curso, salvo que se decida migrarlos en forma explícita y auditada.

## 2. Protocolos (por estudio + tipo de muestra)
Definen: medios a sembrar, condiciones de incubación, tiempos mínimos y máximos de lectura, opciones de lectura permitidas, criterios de reincubación, cuándo se crean aislamientos, métodos de identificación sugeridos y criterios para hacer antibiograma.
**Contenido clínico pendiente:** lo provee el laboratorio (ver OPEN_QUESTIONS, sección clínica).

## 3. Antibiogramas e interpretación CLSI
- Separación obligatoria: **valor medido → interpretación automática → interpretación revisada (motivo) → resultado informado**.
- `BreakpointSet`: edición (p. ej., "CLSI M100 Ed. 3X"), organismo o grupo, antibiótico, método, puntos de corte S/SDD/I/R, notas y vigencia.
- **Los puntos de corte CLSI están protegidos por derechos de autor y no se incluyen en el código.** El laboratorio los carga desde su edición licenciada (carga asistida e importación CSV con validación), y el Director técnico la aprueba.
- Reglas expertas configurables (p. ej., resistencias intrínsecas, informe en cascada, supresión de informe, comentarios automáticos): se modelan como reglas declarativas versionadas, **sin contenido precargado**.
- Discordancias (automático vs. revisado, o entre métodos): se señalan visualmente y exigen motivo.

## 4. Resultados críticos
Reglas configurables (condición sobre estudio / tipo de muestra / organismo / Gram / perfil de resistencia) → alerta + actividad de comunicación. **La lista de condiciones críticas la define el laboratorio.**

## 5. Definiciones clínicas pendientes
Ver `OPEN_QUESTIONS.md` (sección C).

## 6. Plantillas de referencia (D-0008)
A pedido del laboratorio, los catálogos de **protocolos, opciones de lectura y paneles de antibióticos** parten de una **plantilla genérica basada en práctica aceptada por la industria** (bibliografía de referencia de microbiología clínica), en lugar de quedar vacíos.
- Cada ítem de plantilla cita su **fuente** y nace en estado `draft` con la marca **"PLANTILLA DE REFERENCIA — requiere validación del laboratorio"**.
- **No puede usarse con pacientes** hasta que el Director técnico la revise, la ajuste si hace falta y la apruebe (`draft → approved → active`), con auditoría.
- **Excluidos de la plantilla:** puntos de corte CLSI (derechos de autor; los carga el laboratorio), reglas expertas, lista de resultados críticos y criterios de rechazo, salvo que el laboratorio pida otra cosa.
- Las plantillas viven como datos (seeders/importación), nunca como lógica en el código.
- Plantillas disponibles: [urocultivo](templates/UROCULTIVO.md).
