# Preguntas que requieren definición

Estado: **abiertas** salvo que se indique otra cosa. Las marcadas con ★ bloquean el MVP (Fase 4a).
Las respondidas se marcan **[RESPONDIDA aaaa-mm-dd]** y conservan la respuesta; las parciales, **[PARCIAL]**, con lo que falta.

## A. Alcance y operación
- **Q-A1 ★ [PARCIAL 2026-10-31]** Volumen: ¿cuántas muestras por día de bacteriología, cuántos usuarios simultáneos y en qué turnos (horarios)?
  - Respuesta: **~30 muestras diarias**.
  - Falta (no bloquea): usuarios simultáneos y turnos. Con este volumen no hay requisitos de rendimiento especiales.
- **Q-A2 ★ [RESPONDIDA 2026-10-31]** Alcance inicial: **urocultivo** primero, después hemocultivo y luego el resto (secreciones, coprocultivo, LCR, etc.). Propuesta aceptada.
- **Q-A3 [PARCIAL 2026-10-31]** Las muestras **llegan etiquetadas desde el LIS** (el código de barras lo genera el LIS; BactRuf no lo genera).
  - Falta: ¿imprimimos etiquetas de placas y tubos? ¿Con qué impresora (modelo/lenguaje, p. ej., Zebra ZPL)?
- **Q-A4 [RESPONDIDA 2026-10-31]** Hay **3 sedes; cada una procesa su propia bacteriología; solo una tiene equipamiento** (VITEK, BacT/ALERT). Modelo propuesto en ADR-0008.
- **Q-A5 [RESPONDIDA 2026-10-31]** Derivar es **poco frecuente**: a veces se envían hemocultivos o aislamientos. La sede de origen debe poder consultar lo derivado y **ver siempre todas sus muestras**.
- **Q-A6 [RESPONDIDA 2026-10-31]** El informe de un caso derivado lo **valida y firma la sede de origen**.
- **Q-A7 [RESPONDIDA 2026-10-31]** **Cada sede tiene su Director técnico.** Algunas personas trabajan en más de una sede. La configuración clínica es **una sola, compartida** por las 3 sedes.
- **Q-A8 [RESPONDIDA 2026-10-31]** La configuración clínica compartida la aprueba **un Director técnico designado como responsable**. Implementación: permiso `config.clinical.approve`, global (no por sede), asignado a esa persona; la designación se puede cambiar y queda auditada.

## B. Integraciones
- **Q-B1 ★ [PARCIAL 2026-10-31]** LIS: **LABWIN**. Hasta tener la interfaz: recepción manual escaneando la etiqueta del LIS.
  - Falta: qué interfaz ofrece LABWIN (HL7 v2, ASTM, API, base intermedia, archivos) → consultar al proveedor. Ya no bloquea el MVP (se arranca con recepción manual).
- **Q-B2 [PARCIAL 2026-10-31]** VITEK: **VITEK 2 Compact**.
  - Falta: ¿usan *middleware* (MYLA / Observa) o solo el software del equipo? ¿Qué exportación está disponible? ¿Hay documentación técnica?
- **Q-B3 [PARCIAL 2026-10-31]** Hemocultivos: **BacT/ALERT (bioMérieux)**.
  - Falta: ¿modelo 3D o VIRTUO? ¿Se integra la señal de positividad o se registra a mano?
- **Q-B4** ¿Hay MALDI-TOF u otros equipos, actuales o previstos?

## C. Clínicas
Decisión del usuario (2026-10-31): **usar contenido genérico aceptado por la industria** como punto de partida (ver D-0008 en `DECISIONS.md`). Se carga como **plantilla de referencia** en estado `draft`, con la fuente citada, y **no se usa con pacientes hasta que el Director técnico la apruebe**. Las preguntas quedan abiertas como "validación del laboratorio".
- **Q-C1 ★ [PLANTILLA]** Protocolos de siembra por estudio y tipo de muestra: medios, condiciones de incubación, tiempos de lectura y reincubación.
- **Q-C2 ★ [PLANTILLA]** Criterios de lectura de urocultivo: opciones, recuentos y umbrales de significancia por tipo de muestra.
- **Q-C3 ★ [PARCIAL 2026-10-31] [PLANTILLA]** Antibiogramas: **algunos manuales (difusión) y otros por VITEK**, con **panel según el germen identificado**. Falta: qué grupos van por cada método y la composición de cada panel (se parte de la plantilla).
- **Q-C4 ★ [PARCIAL 2026-10-31]** Puntos de corte: **módulo de carga** (carga asistida + importación CSV) → el bacteriólogo **revisa, ajusta y confirma**. Coincide con el flujo `draft → approved → active` de MICROBIOLOGY_RULES §3.
  - Falta: ¿qué edición del M100 usan? ¿Confirma cualquier Bioquímico o solo el Director técnico? ¿Se aplican recomendaciones locales (SADEBAC / INEI-ANLIS)? **Los puntos de corte no tienen plantilla** (derechos de autor de CLSI): los datos salen de la edición licenciada del laboratorio.
- **Q-C5 [PARCIAL 2026-11-01]** El **Gram de un frasco de hemocultivo positivo se comunica** (decisión del laboratorio). Se registra a quién, por qué medio, si fue exitoso y si hubo relectura; un intento fallido deja el crítico abierto.
  - Falta: el resto de la lista de críticos; plazos y escalamiento; si la relectura es obligatoria; si se comunica también un Gram sin microorganismos observados (hoy se comunica).
- **Q-C6** Tiempos objetivo por actividad o estudio (para calcular "demoradas").
- **Q-C7 [PARCIAL 2026-11-01]** Hemocultivo positivo: **informe preliminar con el Gram** (decisión del laboratorio). Lo emite quien tiene permiso de validar. El preliminar congela lo registrado hasta ese momento, y el definitivo lo reemplaza.
  - Falta: ¿preliminares en otros estudios? ¿Hace falta un segundo preliminar con la identificación, antes del antibiograma? (Hoy se puede emitir otro.)
- **Q-C8** Criterios de rechazo de muestras.

## D. Roles y validación
- **Q-S1 ★ [RESPONDIDA 2026-10-31]** El Auxiliar **recibe, siembra y hace pruebas bioquímicas; no hace lecturas de cultivo** (ni siquiera "sin desarrollo").
  - En las bioquímicas, el Auxiliar **monta y registra el resultado** (respondido 2026-10-31). La identificación final la confirma el Bioquímico.
- **Q-S2** Firma de validación: ¿alcanza con reingresar la contraseña o se requiere firma digital (Ley 25.506)?
- **Q-S3** ¿Se permite que el mismo bioquímico procese y valide, o se exige un segundo validador en algunos casos?
- **Q-S4** ¿Quién puede corregir un informe definitivo ya emitido?

## E. Técnicas
- **Q-T1 [RESPONDIDA 2026-10-31]** Entorno de desarrollo: **Docker Desktop + Laravel Sail (WSL2)**. Docker Desktop 4.47 funcionando (`hypervisorlaunchtype Auto`).
- **Q-T2** Servidor de producción: ¿propio (on-premise) en el laboratorio o en la nube? ¿Qué versión de Ubuntu? ¿Dónde se guardan los respaldos?
- **Q-T3** Repositorio remoto y CI: ¿GitHub, GitLab u otro? ¿Privado?

## F. Surgidas en la Fase 1 (diseño funcional)
- **Q-F1 [RESPONDIDA 2026-10-31]** Confirmación en bloque de lecturas "sin desarrollo": **configurable por protocolo, desactivada por defecto**.
- **Q-F2** ¿Se registran lotes de medios de cultivo, discos o tarjetas (trazabilidad)? ¿Se gestiona el control de calidad con cepas ATCC dentro del sistema?
- **Q-F3** VITEK: ¿qué datos de carga se registran (turbidez de la suspensión, operador, casete/posición)?
- **Q-F4 [RESPONDIDA 2026-10-31]** Validación de informes negativos en lote: **no**; cada informe se valida individualmente.
- **Q-F6 [RESPONDIDA 2026-11-02]** El paciente se identifica por número de documento (D-0013 aceptada).
- **Q-F8 [RESPONDIDA 2026-11-02]** Una orden (paciente, médico) puede tener varias muestras (p. ej., urocultivo + hemocultivo ×2 + retrocultivo). Cada muestra tiene su propio código; suelen llegar juntas, pero pueden llegar en distintos momentos. Implementado: recepción por orden con número de LABWIN; las muestras posteriores se suman a la orden existente.
- **Q-F7** Tiempo objetivo para sembrar desde la recepción (para marcar siembras demoradas). Sin definir, las siembras no vencen (relacionada con Q-C6).
- **Q-C9** Perfil de sensibilidad: se aplica la metodología de referencia de CLSI M39 (primer aislamiento por paciente y especie en el período, solo resultados informados, advertencia con < 30 aislamientos). ¿El laboratorio la adopta así? ¿Período anual? ¿Separar por servicio (internados/ambulatorios) o por tipo de muestra?
- **Q-C10** Tiempo diferencial de positividad (retrocultivo vs. hemocultivo periférico): el sistema muestra el tiempo hasta la positividad de cada frasco. ¿Hay que calcular la diferencia y mostrarla? ¿Con qué umbral y redacción? (No se calcula ni se interpreta hasta que el laboratorio lo defina.)
- **Q-C11 [PLANTILLA]** Hemocultivo: tipos de frasco, días de incubación (5 en equipo y 7 manual en la plantilla), medio y horario del repique manual, medios del subcultivo y opciones de Gram. Ver `templates/HEMOCULTIVO.md`.
- **Q-S5** Hemocultivo: en la plantilla, el Auxiliar carga frascos y registra la alarma de positivo del equipo, pero no informa negativos ni lee repiques (siguiendo Q-S1). El Gram lo hace el Bioquímico. ¿Es así?
- **Q-F9** Tandas de lectura: hoy la lectura, la identificación y el antibiograma se hacen ítem por ítem desde su mesada; ¿hace falta tanda también para la lectura (como en la siembra)?
- **Q-F5** ¿Se derivan aislamientos a laboratorios de referencia (p. ej., INEI-ANLIS Malbrán)? ¿Hay que registrar derivaciones y sus resultados?
