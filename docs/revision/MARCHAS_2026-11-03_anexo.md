# Anexo — Reconstrucción de las 20 marchas (2026-11-03)

> Datos sintéticos. Generado recorriendo las acciones reales del sistema en una base de prueba; cada bloque «Reconstrucción» sale solo de lo guardado. Los ✘ son pasos que un bacteriólogo haría y el sistema no tiene. Los puntos de corte son FICTICIOS.

## 1. Urocultivo chorro medio — sin desarrollo
_Qué hice en la mesada:_ Sembré con asa calibrada en sangre y MacConkey; a las 18 h no hubo colonias en ninguna placa; validé.

**Reconstrucción solo con lo guardado:**
- Muestra X-01: Urocultivo / Orina — chorro medio; protocolo v1; estado del estudio: final_reported
- Siembra: Agar sangre · Asa calibrada 1 µL · 35 ± 2 °C aerobic; estado placa: finished
- Siembra: Agar MacConkey · Asa calibrada 1 µL · 35 ± 2 °C aerobic; estado placa: finished
- Lectura 1 a las 18 h: Sin desarrollo
- Informe final v1 (issued): resultado «Sin desarrollo»

**Pasos que intenté y lo que respondió el sistema:**
  - ✔ validar el informe

## 2. Urocultivo — E. coli ≥ 10⁵ UFC/mL, bioquímicas, difusión, BLEE
_Qué hice en la mesada:_ Desarrollo puro de colonias lactosa + en MacConkey. Gram de la colonia: BGN; oxidasa −. TSI A/A, indol +, citrato −, urea −. ATB por difusión (panel de orina). Ampicilina y cefalosporinas R; sinergia con AMC → BLEE: informo cefalosporinas como R.

**Reconstrucción solo con lo guardado:**
- Muestra X-02: Urocultivo / Orina — chorro medio; protocolo v1; estado del estudio: final_reported
- Siembra: Agar sangre · Asa calibrada 1 µL · 35 ± 2 °C aerobic; estado placa: finished
- Siembra: Agar MacConkey · Asa calibrada 1 µL · 35 ± 2 °C aerobic; estado placa: finished
- Lectura 1 a las 18 h: Desarrollo
- Aislamiento 1: «Colonias lactosa + en MacConkey, no hemolíticas en sangre» en Agar MacConkey; recuento GE5; significativo; estado resolved
  - Identificación definitive por Pruebas bioquímicas: Escherichia coli · pruebas: TSI A/A gas+, SH2 −; indol +; citrato −; urea −; LIA +; motilidad +
  - Antibiograma: panel Enterobacterales — orina; estado completed; puntos de corte: DEMO — valores ficticios, NO CLÍNICOS
    - Ampicilina [disk]: 8 mm → auto R, final R
    - Amoxicilina-clavulánico [disk]: 12 mm → auto R, final R
    - Cefalotina [disk]: 10 mm → auto R, final R
    - Cefuroxima [disk]: 12 mm → auto R, final R
    - Ceftriaxona [disk]: 14 mm → auto R, final R
    - Ceftazidima [disk]: 16 mm → auto I, final I
    - Cefepime [disk]: 20 mm → auto S, final S
    - Imipenem [disk]: 20 mm → auto S, final S
    - Gentamicina [disk]: 20 mm → auto S, final S
    - Amikacina [disk]: 20 mm → auto S, final S
    - Ciprofloxacina [disk]: 20 mm → auto S, final S
    - Trimetoprima-sulfametoxazol [disk]: 20 mm → auto S, final S
    - Nitrofurantoína [disk]: 20 mm → auto S, final S
    - Fosfomicina [disk]: 20 mm → auto S, final S
- Informe final v1 (issued): resultado «Desarrollo»

**Pasos que intenté y lo que respondió el sistema:**
  - ✘ registrar el Gram de la colonia (BGN) y oxidasa negativa como datos propios → «No hay paso de Gram ni pruebas rápidas del aislamiento (ID_RAPID_TESTS no implementado); solo texto libre en «pruebas» de la identificación»
  - ✘ pasar el aislamiento a una placa para purificarlo antes de las bioquímicas → «No existe el repique/purificación de un aislamiento (SUBCULTURE no implementado)»
  - ✘ registrar el test de sinergia de doble disco (BLEE) como prueba → «No hay registro de pruebas fenotípicas de resistencia ni reglas expertas (Q-C17, Q-A12); quedó como motivo del cambio de interpretación»

## 3. Urocultivo — dos uropatógenos (E. coli + E. faecalis)
_Qué hice en la mesada:_ Dos morfotipos: lactosa + en MacConkey (≥10⁵) y colonias pequeñas solo en sangre (10⁴–10⁵). Gram: BGN y CGP en cadenas; catalasa − en el segundo; bilis esculina + y NaCl 6,5 % +. ATB de los dos.

**Reconstrucción solo con lo guardado:**
- Muestra X-03: Urocultivo / Orina — chorro medio; protocolo v1; estado del estudio: final_reported
- Siembra: Agar sangre · Asa calibrada 1 µL · 35 ± 2 °C aerobic; estado placa: finished
- Siembra: Agar MacConkey · Asa calibrada 1 µL · 35 ± 2 °C aerobic; estado placa: finished
- Lectura 1 a las 18 h: Desarrollo
- Aislamiento 1: «Lactosa +» en Agar MacConkey; recuento GE5; significativo; estado resolved
  - Identificación definitive por Pruebas bioquímicas: Escherichia coli · pruebas: TSI A/A, indol +
  - Antibiograma: panel Enterobacterales — orina; estado completed; puntos de corte: DEMO — valores ficticios, NO CLÍNICOS
    - Ampicilina [disk]: 20 mm → auto S, final S
    - Amoxicilina-clavulánico [disk]: 20 mm → auto S, final S
    - Cefalotina [disk]: 20 mm → auto S, final S
    - Cefuroxima [disk]: 20 mm → auto S, final S
    - Ceftriaxona [disk]: 20 mm → auto S, final S
    - Ceftazidima [disk]: 20 mm → auto S, final S
    - Cefepime [disk]: 20 mm → auto S, final S
    - Imipenem [disk]: 20 mm → auto S, final S
    - Gentamicina [disk]: 20 mm → auto S, final S
    - Amikacina [disk]: 20 mm → auto S, final S
    - Ciprofloxacina [disk]: 20 mm → auto S, final S
    - Trimetoprima-sulfametoxazol [disk]: 20 mm → auto S, final S
    - Nitrofurantoína [disk]: 20 mm → auto S, final S
    - Fosfomicina [disk]: 20 mm → auto S, final S
- Aislamiento 2: «Colonias pequeñas grisáceas, no crecen en MacConkey» en Agar sangre; recuento 4-5; significativo; estado resolved
  - Identificación definitive por Pruebas bioquímicas: Enterococcus faecalis · pruebas: CGP en cadenas cortas, catalasa −, bilis esculina +, NaCl 6,5 % +, PYR +
  - Antibiograma: panel Enterococcus — orina; estado completed; puntos de corte: DEMO — valores ficticios, NO CLÍNICOS
    - Ampicilina [disk]: 20 mm → auto S, final S
    - Vancomicina [disk]: 20 mm → auto S, final S
    - Nitrofurantoína [disk]: 20 mm → auto S, final S
    - Ciprofloxacina [disk]: 20 mm → auto S, final S
    - Fosfomicina [disk]: 20 mm → auto S, final S
- Informe final v1 (issued): resultado «Desarrollo»

**Pasos que intenté y lo que respondió el sistema:**
  - ✘ registrar screening de alta carga de gentamicina en Enterococcus → «GEN de alta carga no está como antibiótico propio; el panel de Enterococcus no lo incluye»

## 4. Urocultivo — flora mixta
_Qué hice en la mesada:_ Tres morfotipos sin predominio: probable contaminación, pido nueva muestra.

**Reconstrucción solo con lo guardado:**
- Muestra X-04: Urocultivo / Orina — chorro medio; protocolo v1; estado del estudio: final_reported
- Siembra: Agar sangre · Asa calibrada 1 µL · 35 ± 2 °C aerobic; estado placa: finished
- Siembra: Agar MacConkey · Asa calibrada 1 µL · 35 ± 2 °C aerobic; estado placa: finished
- Lectura 1 a las 18 h: Flora mixta (≥ 3 morfotipos), probable contaminación — notas: 3 morfotipos sin predominio
- Informe final v1 (issued): resultado «Flora mixta (≥ 3 morfotipos), probable contaminación»

**Pasos que intenté y lo que respondió el sistema:**
  - ✘ sugerir/registrar «solicitar nueva muestra» → «REQUEST_NEW_SAMPLE no implementado; solo queda la nota»

## 5. Urocultivo por sonda — reincubación y sin desarrollo
_Qué hice en la mesada:_ A las 18 h dudo de unas colonias puntiformes, reincubo 24 h; a las 42 h no hay desarrollo.

**Reconstrucción solo con lo guardado:**
- Muestra X-05: Urocultivo / Orina — sonda vesical; protocolo v1; estado del estudio: final_reported
- Siembra: Agar sangre · Asa calibrada 10 µL · 35 ± 2 °C aerobic; estado placa: finished
- Siembra: Agar MacConkey · Asa calibrada 10 µL · 35 ± 2 °C aerobic; estado placa: finished
- Lectura 1 a las 18 h: Reincubar — notas: Colonias puntiformes dudosas
- Lectura 2 a las 42 h: Sin desarrollo
- Informe final v1 (issued): resultado «Sin desarrollo»

**Pasos que intenté y lo que respondió el sistema:**
  - (ninguno fuera de lo normal)

## 6. Urocultivo — K. pneumoniae por VITEK, tarjeta AST y disco de meropenem
_Qué hice en la mesada:_ Colonias mucosas lactosa +. Cargué tarjeta GN (ID) y AST-N en el VITEK: K. pneumoniae 99 %. CIM de imipenem 4 → agrego disco de meropenem; preliminar con el VITEK; al día siguiente meropenem R.

**Reconstrucción solo con lo guardado:**
- Muestra X-06: Urocultivo / Orina — chorro medio; protocolo v1; estado del estudio: final_reported
- Siembra: Agar sangre · Asa calibrada 1 µL · 35 ± 2 °C aerobic; estado placa: finished
- Siembra: Agar MacConkey · Asa calibrada 1 µL · 35 ± 2 °C aerobic; estado placa: finished
- Lectura 1 a las 18 h: Desarrollo
- Aislamiento 1: «Mucosas lactosa +» en Agar MacConkey; recuento GE5; significativo; estado resolved
  - Identificación definitive por VITEK 2 (carga manual): Klebsiella pneumoniae · pruebas: (no registradas) · confianza 99 % — excelente
  - Antibiograma: panel Tarjeta VITEK sintética VTK-N (VITEK); estado completed; puntos de corte: DEMO — valores ficticios, NO CLÍNICOS
    - Ampicilina [mic]: 32 µg/mL → auto R, final R
    - Amoxicilina-clavulánico [mic]: 20 µg/mL → auto R, final R
    - Ceftriaxona [mic]: 64 µg/mL → auto R, final R
    - Ceftazidima [mic]: 16 µg/mL → auto R, final R
    - Cefepime [mic]: 8 µg/mL → auto R, final R
    - Imipenem [mic]: 4 µg/mL → auto R, final R
    - Gentamicina [mic]: 20 µg/mL → auto R, final R
    - Amikacina [mic]: 20 µg/mL → auto R, final R
    - Ciprofloxacina [mic]: 20 µg/mL → auto R, final R
    - Trimetoprima-sulfametoxazol [mic]: 20 µg/mL → auto R, final R
    - Meropenem [disk, fuera del panel]: 18 mm → auto —, final R (cambio: Sin punto de corte cargado)
- Informe preliminary v1 (superseded): resultado «Desarrollo»
- Informe final v2 (issued): resultado «Desarrollo»

**Pasos que intenté y lo que respondió el sistema:**
  - ✘ registrar la tarjeta VITEK GN cargada (número, casete/posición, turbidez) → «VITEK_LOAD no implementado (Q-F3): no hay tanda VITEK ni datos de la tarjeta»
  - ✘ leer la tarjeta AST del VITEK a las 10 h sin confirmar «antes de tiempo» → «Todavía no se cumplió el tiempo de incubación del protocolo. Confirmá para registrar igual.»
  - ✔ preliminar con el VITEK mientras falta el disco de meropenem
  - ✘ registrar test de carbapenemasa (Blue-Carba / inmunocromatografía KPC) → «Q-C17: no hay registro de pruebas de mecanismo»

## 7. Hemocultivo — negativo a los 5 días
_Qué hice en la mesada:_ Frascos aerobio y anaerobio en BacT/ALERT; sin alarma a los 5 días; descargo como negativos.

**Reconstrucción solo con lo guardado:**
- Muestra X-07: Hemocultivo / Sangre periférica; protocolo v1; estado del estudio: final_reported
- Frasco AER (automated, BacT/ALERT P-07) cargado 12/11 00:20; estado negative
- Informe final v1 (issued): resultado «Sin desarrollo a los 5 días de incubación»

**Pasos que intenté y lo que respondió el sistema:**
  - ✔ cargar el segundo frasco (anaerobio) del mismo hemocultivo
  - ✔ validar

## 8. Hemocultivo — S. aureus
_Qué hice en la mesada:_ Alarma a las 14 h; Gram CGP en racimos; comunico; preliminar; subcultivo en sangre, chocolate y MacConkey; colonias doradas β-hemolíticas; catalasa +, coagulasa +; ATB por difusión con cefoxitina.

**Reconstrucción solo con lo guardado:**
- Muestra X-08: Hemocultivo / Sangre periférica; protocolo v1; estado del estudio: final_reported
- Frasco AER (automated, BacT/ALERT P-08) cargado 17/11 00:20; estado positive; positivo a las 14 h (instrument)
  - Gram del frasco: Cocos gram positivos en racimos
- Siembra: Agar sangre · Gota del frasco · 35 ± 2 °C co2; estado placa: finished
- Siembra: Agar chocolate · Gota del frasco · 35 ± 2 °C co2; estado placa: finished
- Siembra: Agar MacConkey · Gota del frasco · 35 ± 2 °C aerobic; estado placa: finished
- Lectura 1 a las 18 h: Desarrollo
- Aislamiento 1: «Colonias doradas beta hemolíticas» en Agar sangre; recuento —; significativo; estado resolved
  - Identificación definitive por Pruebas bioquímicas: Staphylococcus aureus · pruebas: Catalasa +, coagulasa en tubo +, DNasa +
  - Antibiograma: panel Staphylococcus — orina; estado completed; puntos de corte: DEMO — valores ficticios, NO CLÍNICOS
    - Oxacilina (cefoxitina) [disk]: 20 mm → auto S, final S
    - Trimetoprima-sulfametoxazol [disk]: 20 mm → auto S, final S
    - Nitrofurantoína [disk]: 20 mm → auto S, final S
    - Ciprofloxacina [disk]: 20 mm → auto S, final S
    - Vancomicina [disk]: 20 mm → auto S, final S
- Crítico (positive_gram): Hemocultivo positivo · X-08 · Gram: Cocos gram positivos en racimos; communicated; comunicaciones: 1
- Informe preliminary v1 (superseded): resultado «Frasco positivo: estudio en curso»
- Informe final v2 (issued): resultado «Desarrollo»

**Pasos que intenté y lo que respondió el sistema:**
  - ✘ registrar coagulasa directa / prueba rápida del frasco (aglutinación) antes del desarrollo → «No hay pruebas rápidas sobre el frasco; solo Gram»
  - ✘ agregar disco de cefoxitina (screening de meticilino resistencia) y clindamicina con test D → «Cefoxitina y clindamicina no están en el catálogo de antibióticos de referencia»
  - ✔ validar a las 18 h del subcultivo (faltan lecturas del cronograma)

## 9. Hemocultivo — S. epidermidis en 1 de 2 frascos
_Qué hice en la mesada:_ Alarma a las 30 h; CGP en racimos; colonias blancas; coagulasa −: S. epidermidis en un solo set → probable contaminante, no hago ATB.

**Reconstrucción solo con lo guardado:**
- Muestra X-09: Hemocultivo / Sangre periférica; protocolo v1; estado del estudio: ready_for_validation
- Frasco AER (automated, BacT/ALERT P-09) cargado 19/11 01:20; estado positive; positivo a las 30 h (instrument)
  - Gram del frasco: Cocos gram positivos en racimos
- Siembra: Agar sangre · Gota del frasco · 35 ± 2 °C co2; estado placa: finished
- Siembra: Agar chocolate · Gota del frasco · 35 ± 2 °C co2; estado placa: finished
- Siembra: Agar MacConkey · Gota del frasco · 35 ± 2 °C aerobic; estado placa: finished
- Lectura 1 a las 18 h: Desarrollo
- Aislamiento 1: «Colonias blancas no hemolíticas» en Agar sangre; recuento —; significativo; estado resolved
  - Identificación definitive por Pruebas bioquímicas: Staphylococcus epidermidis · pruebas: Catalasa +, coagulasa −
  - Sin antibiograma: Probable contaminante: 1 de 2 hemocultivos
- Crítico (positive_gram): Hemocultivo positivo · X-09 · Gram: Cocos gram positivos en racimos; communicated; comunicaciones: 1

**Pasos que intenté y lo que respondió el sistema:**
  - ✔ identificarlo como S. epidermidis y marcarlo «probable contaminante (1 de 2 frascos)» sin antibiograma
  - ✘ cambiar la significancia del aislamiento después de identificarlo → «No existe: la significancia se fija al crear el aislamiento en la lectura»
  - ✘ ver en este estudio que el otro hemocultivo de la orden fue negativo → «El resumen «X de N positivos» está en la orden, pero no asociado a la decisión sobre el aislamiento»

## 10. LCR — N. meningitidis
_Qué hice en la mesada:_ Directo: abundantes leucocitos y diplococos gram negativos → crítico comunicado → preliminar. Desarrollo en chocolate; oxidasa +, glucosa y maltosa → N. meningitidis; derivo para serogrupo y CIM.

**Reconstrucción solo con lo guardado:**
- Muestra X-10: Cultivo de LCR / Líquido cefalorraquídeo; protocolo v1; estado del estudio: final_reported
- Directo: Leucocitos: Abundantes; Microorganismos: Diplococos gram negativos — Diplococos gram negativos intra y extracelulares
- Siembra: Agar sangre · Gota del sedimento · 35 ± 2 °C co2; estado placa: finished
- Siembra: Agar chocolate · Gota del sedimento · 35 ± 2 °C co2; estado placa: finished
- Lectura 1 a las 18 h: Desarrollo
- Lectura 2 a las 42 h: Sin desarrollo
- Lectura 3 a las 66 h: Sin desarrollo
- Aislamiento 1: «Colonias grises en chocolate» en Agar chocolate; recuento —; significativo; estado resolved
  - Identificación definitive por Pruebas bioquímicas: Neisseria meningitidis · pruebas: Oxidasa +, utiliza glucosa y maltosa
  - Sin antibiograma: No hay panel; se deriva al laboratorio de referencia
- Crítico (direct_exam): Cultivo de LCR · X-10 · Directo: Abundantes · Diplococos gram negativos; communicated; comunicaciones: 1
- Informe preliminary v1 (superseded): resultado «Cultivo en curso»
- Informe final v2 (issued): resultado «Desarrollo»

**Pasos que intenté y lo que respondió el sistema:**
  - ✘ registrar el látex / antígenos capsulares en LCR → «Q-C17: no hay registro de antígenos»
  - ✘ registrar la derivación del aislamiento al laboratorio de referencia y su resultado (serogrupo) → «Q-F5: no hay registro de derivaciones»

## 11. Esputo — muestra salival, flora habitual
_Qué hice en la mesada:_ Gram: > 25 células epiteliales y < 10 leucocitos por campo → muestra no representativa; igual se sembró; flora habitual.

**Reconstrucción solo con lo guardado:**
- Muestra X-11: Cultivo de secreción respiratoria / Esputo; protocolo v1; estado del estudio: final_reported
- Directo: Células epiteliales por campo (100×): Más de 25; Leucocitos por campo (100×): Menos de 10; Microorganismos: Cocos gram positivos en pares, Bacilos gram negativos
- Siembra: Agar sangre · Porción purulenta, siembra por agotamiento · 35 ± 2 °C co2; estado placa: finished
- Siembra: Agar chocolate · Porción purulenta, siembra por agotamiento · 35 ± 2 °C co2; estado placa: finished
- Siembra: Agar MacConkey · Porción purulenta, siembra por agotamiento · 35 ± 2 °C aerobic; estado placa: finished
- Lectura 1 a las 18 h: Desarrollo de flora habitual de vías respiratorias superiores
- Informe final v1 (issued): resultado «Desarrollo de flora habitual de vías respiratorias superiores»

**Pasos que intenté y lo que respondió el sistema:**
  - ✘ rechazar la muestra por mala calidad (Murray-Washington) y pedir otra → «Q-C8: no hay rechazo de muestras ni criterios; el directo no condiciona la siembra»

## 12. Herida — S. aureus a las 24 h y P. aeruginosa a las 48 h
_Qué hice en la mesada:_ Directo: CGP en racimos y BGN. 24 h: S. aureus en sangre. 48 h: aparece P. aeruginosa en MacConkey. ATB de los dos.

**Reconstrucción solo con lo guardado:**
- Muestra X-12: Cultivo de secreción y herida / Herida / úlcera (hisopado); protocolo v1; estado del estudio: final_reported
- Directo: Leucocitos: Moderados; Microorganismos: Cocos gram positivos en racimos, Bacilos gram negativos
- Siembra: Agar sangre · Hisopo, siembra por agotamiento · 35 ± 2 °C co2; estado placa: finished
- Siembra: Agar chocolate · Hisopo, siembra por agotamiento · 35 ± 2 °C co2; estado placa: finished
- Siembra: Agar MacConkey · Hisopo, siembra por agotamiento · 35 ± 2 °C aerobic; estado placa: finished
- Lectura 1 a las 18 h: Desarrollo
- Lectura 2 a las 42 h: Desarrollo
- Aislamiento 1: «Colonias doradas β» en Agar sangre; recuento MOD; significativo; estado resolved
  - Identificación definitive por Pruebas bioquímicas: Staphylococcus aureus · pruebas: Catalasa +, coagulasa +
  - Antibiograma: panel Staphylococcus — orina; estado completed; puntos de corte: DEMO — valores ficticios, NO CLÍNICOS
    - Oxacilina (cefoxitina) [disk]: 20 mm → auto S, final S
    - Trimetoprima-sulfametoxazol [disk]: 20 mm → auto S, final S
    - Nitrofurantoína [disk]: 20 mm → auto S, final S
    - Ciprofloxacina [disk]: 20 mm → auto S, final S
    - Vancomicina [disk]: 20 mm → auto S, final S
- Aislamiento 2: «Colonias planas, olor a uva, pigmento verde, lactosa −» en Agar MacConkey; recuento ESC; significativo; estado resolved
  - Identificación definitive por Pruebas bioquímicas: Pseudomonas aeruginosa · pruebas: Oxidasa +, pigmento, crece a 42 °C
  - Antibiograma: panel Pseudomonas aeruginosa; estado completed; puntos de corte: DEMO — valores ficticios, NO CLÍNICOS
    - Ceftazidima [disk]: 20 mm → auto S, final S
    - Cefepime [disk]: 20 mm → auto S, final S
    - Piperacilina-tazobactam [disk]: 20 mm → auto S, final S
    - Imipenem [disk]: 20 mm → auto S, final S
    - Meropenem [disk]: 20 mm → auto S, final S
    - Gentamicina [disk]: 20 mm → auto S, final S
    - Amikacina [disk]: 20 mm → auto S, final S
    - Ciprofloxacina [disk]: 20 mm → auto S, final S
- Informe final v1 (issued): resultado «Desarrollo»

**Pasos que intenté y lo que respondió el sistema:**
  - ✔ validar

## 13. Coprocultivo — Salmonella del repique del selenito
_Qué hice en la mesada:_ Leucocitos abundantes. 18 h: nada en MacConkey/SS/XLD primarias; repico el selenito a SS y XLD. 42 h: colonias SH2 + en XLD del repique; TSI K/A SH2 +, LIA +; serología O:9 → S. Enteritidis; ATB.

**Reconstrucción solo con lo guardado:**
- Muestra X-13: Coprocultivo / Materia fecal; protocolo v1; estado del estudio: final_reported
- Directo: Leucocitos: Abundantes; Hematíes: Escasos
- Siembra: Agar MacConkey · Hisopo cargado, siembra por agotamiento · 35 ± 2 °C aerobic; estado placa: finished
- Siembra: Agar Salmonella-Shigella · Hisopo cargado, siembra por agotamiento · 35 ± 2 °C aerobic; estado placa: finished
- Siembra: Agar XLD · Hisopo cargado, siembra por agotamiento · 35 ± 2 °C aerobic; estado placa: finished
- Siembra: Agar selectivo para Campylobacter · Hisopo cargado, siembra por agotamiento · 42 ± 1 °C microaerophilic; estado placa: finished
- Siembra: Caldo selenito · Hisopo cargado, siembra por agotamiento · 35 ± 2 °C aerobic (caldo); estado placa: finished
- Siembra: Agar Salmonella-Shigella · Repique de SEL · 35 ± 2 °C aerobic (repique del caldo); estado placa: finished
- Siembra: Agar XLD · Repique de SEL · 35 ± 2 °C aerobic (repique del caldo); estado placa: finished
- Lectura 1 a las 18 h: No se aislaron Salmonella, Shigella ni Campylobacter
- Lectura 2 a las 42 h: Desarrollo de enteropatógeno
- Aislamiento 1: «Colonias rojas con centro negro (SH2 +)» en Agar XLD; recuento —; significativo; estado resolved
  - Identificación definitive por Pruebas bioquímicas: Salmonella enterica · pruebas: TSI K/A SH2 +; LIA +; urea −; indol −
  - Antibiograma: panel Enterobacterales — orina; estado completed; puntos de corte: DEMO — valores ficticios, NO CLÍNICOS
    - Ampicilina [disk]: 20 mm → auto S, final S
    - Amoxicilina-clavulánico [disk]: 20 mm → auto S, final S
    - Cefalotina [disk]: 20 mm → auto S, final S
    - Cefuroxima [disk]: 20 mm → auto S, final S
    - Ceftriaxona [disk]: 20 mm → auto S, final S
    - Ceftazidima [disk]: 20 mm → auto S, final S
    - Cefepime [disk]: 20 mm → auto S, final S
    - Imipenem [disk]: 20 mm → auto S, final S
    - Gentamicina [disk]: 20 mm → auto S, final S
    - Amikacina [disk]: 20 mm → auto S, final S
    - Ciprofloxacina [disk]: 20 mm → auto S, final S
    - Trimetoprima-sulfametoxazol [disk]: 20 mm → auto S, final S
    - Nitrofurantoína [disk]: 20 mm → auto S, final S
    - Fosfomicina [disk]: 20 mm → auto S, final S
- Informe final v1 (issued): resultado «Desarrollo de enteropatógeno»

**Pasos que intenté y lo que respondió el sistema:**
  - ✘ registrar la serología (aglutinación O:9 / Vi) de Salmonella → «Q-C17: no hay registro de serotipificación»
  - ✘ buscar Shigella / STEC (sorbitol MacConkey) → «Q-C15: búsquedas dirigidas no definidas»
  - ✘ informar solo AMP, CIP y SXT en Salmonella intestinal (informe selectivo) → «Se puede destildar «informar» a mano, uno por uno; no hay reglas de informe selectivo por germen y muestra»
  - ✔ validar

## 14. Exudado de fauces — S. pyogenes
_Qué hice en la mesada:_ Colonias β-hemolíticas en sangre; catalasa −, bacitracina S, PYR +, látex grupo A +.

**Reconstrucción solo con lo guardado:**
- Muestra X-14: Exudado de fauces / Hisopado de fauces; protocolo v1; estado del estudio: final_reported
- Siembra: Agar sangre · Hisopo, siembra por agotamiento · 35 ± 2 °C co2; estado placa: finished
- Lectura 1 a las 18 h: Desarrollo de colonias beta hemolíticas
- Lectura 2 a las 42 h: No se aisló Streptococcus beta hemolítico del grupo A
- Aislamiento 1: «Colonias pequeñas con β-hemólisis amplia» en Agar sangre; recuento —; significativo; estado resolved
  - Identificación definitive por Pruebas bioquímicas: Streptococcus pyogenes · pruebas: Catalasa −, bacitracina S, PYR +, látex grupo A +
  - Sin antibiograma: S. pyogenes es uniformemente sensible a penicilina; no se hace antibiograma de rutina
- Informe final v1 (issued): resultado «Desarrollo de colonias beta hemolíticas»

**Pasos que intenté y lo que respondió el sistema:**
  - ✘ registrar el test rápido de antígeno de S. pyogenes hecho en guardia → «Q-C17: no hay registro de pruebas rápidas de antígenos»

## 15. Flujo vaginal — levaduras y vaginosis
_Qué hice en la mesada:_ Fresco: levaduras y clue cells; pH 5, aminas +. Colonias cremosas en Sabouraud; tubo germinativo + → C. albicans.

**Reconstrucción solo con lo guardado:**
- Muestra X-15: Cultivo de secreción genital / Flujo vaginal; protocolo v1; estado del estudio: in_process
- Directo: Leucocitos: Moderados; Células guía (clue cells): Se observan; Levaduras / pseudohifas: Se observan; Trichomonas: No se observan; Microbiota: Lactobacilos disminuidos — pH 5,0; test de aminas +
- Siembra: Agar sangre · Hisopo, siembra por agotamiento · 35 ± 2 °C co2; estado placa: incubating
- Siembra: Agar Sabouraud · Hisopo, siembra por agotamiento · 35 ± 2 °C aerobic; estado placa: incubating
- Lectura 1 a las 42 h: Desarrollo
- Aislamiento 1: «Colonias cremosas en Sabouraud» en Agar Sabouraud; recuento —; significativo; estado resolved
  - Identificación definitive por Otro método: Candida albicans · pruebas: Tubo germinativo +
  - Sin antibiograma: No se realiza antifungigrama

**Pasos que intenté y lo que respondió el sistema:**
  - ✘ registrar pH y test de aminas como datos (score de Nugent / Amsel) → «Q-C14: no son campos del directo; van como descripción libre»
  - ✔ primera lectura a las 42 h sin haber hecho la de 18 h
  - ✔ identificar C. albicans (tubo germinativo +) sin antifungigrama

## 16. Secreción uretral — N. gonorrhoeae
_Qué hice en la mesada:_ Directo: diplococos gram negativos intracelulares. Thayer-Martin: colonias pequeñas grises; oxidasa +, solo glucosa; nitrocefina −.

**Reconstrucción solo con lo guardado:**
- Muestra X-16: Cultivo de secreción genital / Secreción uretral; protocolo v1; estado del estudio: in_process
- Directo: Leucocitos: Abundantes; Microorganismos: Diplococos gram negativos — Diplococos gram negativos intracelulares
- Siembra: Agar sangre · Hisopo, siembra por agotamiento · 35 ± 2 °C co2; estado placa: incubating
- Siembra: Agar chocolate · Hisopo, siembra por agotamiento · 35 ± 2 °C co2; estado placa: incubating
- Siembra: Agar Thayer-Martin modificado · Hisopo, siembra por agotamiento · 35 ± 2 °C co2; estado placa: incubating
- Lectura 1 a las 42 h: Desarrollo
- Aislamiento 1: «Colonias pequeñas grises en Thayer-Martin» en Agar Thayer-Martin modificado; recuento —; significativo; estado resolved
  - Identificación definitive por Pruebas bioquímicas: Neisseria gonorrhoeae · pruebas: Oxidasa +, utiliza solo glucosa; nitrocefina −
  - Sin antibiograma: Sin panel; se informa β-lactamasa negativa

**Pasos que intenté y lo que respondió el sistema:**
  - ✔ lectura a las 48 h directamente (la de 24 h no se hizo)
  - ✔ identificar N. gonorrhoeae y probar β-lactamasa (nitrocefina)

## 17. Absceso — la identificación preliminar no se confirma
_Qué hice en la mesada:_ Coagulasa en lámina + (preliminar S. aureus); el VITEK da S. epidermidis 95 %: la coagulasa en lámina fue falso positivo.

**Reconstrucción solo con lo guardado:**
- Muestra X-17: Cultivo de secreción y herida / Absceso (punción o aspirado); protocolo v1; estado del estudio: in_process
- Siembra: Agar sangre · Gota del material, siembra por agotamiento · 35 ± 2 °C co2; estado placa: incubating
- Siembra: Agar chocolate · Gota del material, siembra por agotamiento · 35 ± 2 °C co2; estado placa: incubating
- Siembra: Agar MacConkey · Gota del material, siembra por agotamiento · 35 ± 2 °C aerobic; estado placa: incubating
- Lectura 1 a las 18 h: Desarrollo
- Aislamiento 1: «Colonias blancas» en Agar sangre; recuento ABU; significativo; estado resolved
  - Identificación preliminary por Pruebas bioquímicas: Staphylococcus aureus · pruebas: Catalasa +, coagulasa en lámina +
  - Identificación definitive por VITEK 2 (carga manual): Staphylococcus epidermidis · pruebas: (no registradas) · confianza 95 % muy bueno
  - Sin antibiograma: Coagulasa negativo de absceso: se consulta con el médico

**Pasos que intenté y lo que respondió el sistema:**
  - ✘ marcar «no concluyente» o «mezcla de colonias» y repetir la identificación → «No hay estado «no concluyente» ni repetición con motivo; solo preliminar/definitiva»

## 18. Líquido pleural — sin desarrollo a las 72 h (con una placa rota)
_Qué hice en la mesada:_ Se rompió el chocolate a las 2 h; resembré. Sin desarrollo a las 24, 48 y 72 h.

**Reconstrucción solo con lo guardado:**
- Muestra X-18: Cultivo de líquido de punción / Líquido pleural; protocolo v1; estado del estudio: final_reported
- Siembra: Agar sangre · Gota del sedimento · 35 ± 2 °C co2; estado placa: finished
- Siembra: Agar chocolate · Gota del sedimento · 35 ± 2 °C co2; estado placa: finished
- Siembra: Agar MacConkey · Gota del sedimento · 35 ± 2 °C aerobic; estado placa: finished
- Lectura 1 a las 18 h: Sin desarrollo
- Lectura 2 a las 42 h: Sin desarrollo
- Lectura 3 a las 66 h: Sin desarrollo
- Informe final v1 (issued): resultado «Sin desarrollo»

**Pasos que intenté y lo que respondió el sistema:**
  - ✘ dar de baja la placa de chocolate (se rompió) y volver a sembrarla → «No hay acción para placa perdida (existe la columna lost_reason pero ningún paso la usa)»

## 19. BAL — desarrollo solo en chocolate
_Qué hice en la mesada:_ Solo creció en chocolate (necesita factores/CO₂); el aislamiento no tiene placa indicada; optoquina S, bilis soluble → S. pneumoniae; sin panel.

**Reconstrucción solo con lo guardado:**
- Muestra X-19: Cultivo de secreción respiratoria / Lavado broncoalveolar; protocolo v1; estado del estudio: ready_for_validation
- Siembra: Agar sangre · Asa calibrada 1 µL · 35 ± 2 °C co2; estado placa: finished
- Siembra: Agar chocolate · Asa calibrada 1 µL · 35 ± 2 °C co2; estado placa: finished
- Siembra: Agar MacConkey · Asa calibrada 1 µL · 35 ± 2 °C aerobic; estado placa: finished
- Lectura 1 a las 18 h: Desarrollo
- Lectura 2 a las 42 h: Sin desarrollo
- Aislamiento 1: «Colonias mucoides, satelitismo» en (placa no indicada); recuento GE4; significativo; estado resolved
  - Identificación definitive por Pruebas bioquímicas: Streptococcus pneumoniae · pruebas: α-hemólisis, optoquina S, bilis soluble
  - Sin antibiograma: Sin panel para Streptococcus en BAL

**Pasos que intenté y lo que respondió el sistema:**
  - ✘ indicar «sangre: sin desarrollo; chocolate: desarrollo; MacConkey: sin desarrollo» → «La lectura es del cultivo entero; el desarrollo por placa (previsto en ACTIVITY_CATALOG) no se registra»

## 20. Urocultivo — un solo aislamiento < 10³, marcado no significativo
_Qué hice en la mesada:_ Desarrollo escaso (< 10³) de un morfotipo, no significativo.

**Reconstrucción solo con lo guardado:**
- Muestra X-20: Urocultivo / Orina — chorro medio; protocolo v1; estado del estudio: in_process
- Siembra: Agar sangre · Asa calibrada 1 µL · 35 ± 2 °C aerobic; estado placa: incubating
- Siembra: Agar MacConkey · Asa calibrada 1 µL · 35 ± 2 °C aerobic; estado placa: incubating

**Pasos que intenté y lo que respondió el sistema:**
  - ✘ desarrollo con un único aislamiento no significativo → «Ningún aislamiento está marcado como significativo: usá la opción de desarrollo no significativo.»
  - ✘ validar → «El estudio no está listo para validar.»
