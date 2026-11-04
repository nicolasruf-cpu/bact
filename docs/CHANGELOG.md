# Changelog

## [Sin publicar]
### 2026-11-04 — Hallazgos de la revisión de marchas corregidos
- **Prioridad 1 (B1, B2):** catálogo de pruebas de identificación (Administración → Pruebas de identificación) y registro del Gram, las pruebas rápidas y las bioquímicas en cada aislamiento, con siembra, lectura y resultado. Las bioquímicas sembradas aparecen para leer en la mesada de identificación (D-0023).
- **Prioridad 2 (A1, A2):** antibiograma con identificación presuntiva si el protocolo lo habilita, con la hora real de montaje; se lee con la definitiva. Incubación propia por panel; un antibiograma sin leer se descarta con motivo (D-0024).
- **Prioridad 3 (A4, C3, A6):** significancia editable con motivo y «probable contaminante»; «Otros desarrollos» en el informe; textos propios para «sin desarrollo nuevo» y la lectura intermedia (D-0025, D-0026).
- **Prioridad 4 (A3):** lectura por placa y medios que se leen solo en parte del cronograma (D-0026).
- **Prioridad 5 (B3, B5, B6):** carga del VITEK con tarjetas y resultado asociado; pruebas de mecanismo desde el antibiograma; identificación no concluyente (D-0023, D-0027).
- **Prioridad 6:**
  - placa perdida y resiembra (B7);
  - muestra no apta con nueva muestra pedida (B8);
  - derivación con resultado (B9);
  - campos numéricos en el directo (B10);
  - recuento en lecturas sin aislamientos (C1);
  - reglas de antibiograma del laboratorio (C2);
  - carga del disco y antibiótico subrogante (A7);
  - un frasco por muestra con error claro y placa anaerobia para el frasco anaerobio (A5);
  - plantillas y catálogo de referencia ampliados (D).
- **Marcha:** página nueva que reconstruye cada estudio paso a paso con lo registrado; se abre desde la validación, la lectura y la identificación.
- Dos migraciones nuevas (`2026_11_08_000100`, `2026_11_08_000200`). 216 pruebas en verde. `composer test` ya no corta a los 300 s.

### 2026-11-03 — Revisión de marchas como bacteriólogo
- Se recorrieron 20 marchas de los 18 protocolos con las acciones reales y se reconstruyó cada una solo con lo guardado. Informe: `docs/revision/MARCHAS_2026-11-03.md`; reconstrucciones: el anexo.
- Hallazgos principales:
  - el Gram y las pruebas de la colonia, las bioquímicas y la carga del VITEK quedan en texto libre;
  - el antibiograma exige identificación definitiva y tiene una sola incubación mínima para todos los métodos;
  - la lectura es del cultivo entero, no de cada placa;
  - la significancia no cambia después de identificar;
  - en hemocultivo hay un frasco por estudio, y el segundo se ignora sin avisar.
- Preguntas nuevas: Q-C20 a Q-C26. No se cambió código.

### 2026-11-03 — Antibióticos por fuera del panel (D-0022)
- Al preparar el antibiograma se pueden agregar antibióticos que no están en el panel, cada uno con su método y un motivo opcional. Se leen junto con el panel.
- Al leer el panel (por ejemplo, el VITEK) también se pueden agregar: lo leído queda registrado y se programa otra lectura para los agregados, con la incubación del protocolo y el mismo aviso si se lee antes de tiempo.
- El aislamiento queda resuelto, y el cultivo puede cerrarse, recién cuando no falta leer ningún antibiótico. Mientras tanto, el informe preliminar muestra lo leído y avisa cuántos faltan.
- Cada antibiótico se interpreta con el punto de corte de su método. El informe muestra la unidad en cada valor.
- Migración: `susceptibility_results` suma `method`, `is_additional`, `addition_reason`, `set_up_at`, `set_up_by` y `read_at`. Los resultados existentes toman los datos de su antibiograma.

### 2026-11-03 — Paneles de antibiograma (D-0021)
- Nueva pantalla **Administración → Paneles de antibiograma**: alta y modificación de paneles con nombre, grupos de germen, muestras, marca VITEK y antibióticos en orden.
  - Un panel vale para uno o más grupos y una o más muestras; sin muestras tildadas vale para todas.
  - La pantalla muestra qué panel le toca a cada grupo y marca con «Revisá» los grupos sin panel o con panel solo para algunas muestras.
  - Los define el Director técnico responsable; los demás con acceso a la configuración clínica los ven. Altas y cambios auditados, con control de concurrencia.
- Al preparar el antibiograma se propone el panel de la muestra antes que el general. Un panel VITEK fija el método en CIM.
- La identificación avisa cuando no hay panel para el grupo **en esa muestra**.
- El resumen del protocolo muestra qué panel le toca a cada grupo en su muestra, y cuáles no tienen.
- Migración: `ast_panels.organism_group` pasa a `organism_groups` (JSON), con `is_vitek`, `lock_version` y la tabla `ast_panel_sample_types`. Los paneles existentes conservan su grupo y valen para todas las muestras.

### 2026-11-02 — Lectura del protocolo para detectar faltantes
- El resumen del protocolo recorre el circuito completo: recepción, frasco, directo, siembra, lecturas, identificación, antibiograma, y validación e informe.
- Cada paso dice en qué mesada aparece, qué se registra y qué pasa después.
- Las opciones de lectura se muestran con el texto que sale en el informe y con lo que hace el sistema al elegirlas.
- **«Revisá»:** preguntas que no impiden aprobar (`lib/protocolReview.ts`). No son reglas clínicas: muestran lo que el sistema va a hacer con lo cargado. Por ejemplo:
  - el estudio no lleva directo;
  - el directo abre un crítico, pero ninguna opción indica microorganismos;
  - un campo del directo no tiene opciones;
  - no hay opción de flora mixta, o no hay opción de no significativo;
  - hay opciones de lectura sin texto;
  - los aislamientos se cargan sin recuento;
  - un caldo que se repica tan tarde que las placas del repique no llegan a la última lectura;
  - faltan los tipos de frasco, el Gram o los equipos.
- Se aclara lo que el protocolo no define: puntos de corte, paneles de antibiograma y criterios de rechazo.

### 2026-11-02 — Fechas a un clic
- Todos los campos de fecha tienen el botón **Hoy**: nacimiento en la recepción, y desde y hasta en el perfil de sensibilidad.
- Todos los campos de fecha y hora tienen el botón **Ahora**: toma de la muestra, carga del frasco y hora del positivo.
- Se hace con los componentes `DateInput` y `DateTimeInput` (regla en `UX_UI.md`).

### 2026-11-02 — Los tiempos de incubación advierten, no bloquean (D-0020)
- Antes de la hora del protocolo, ahora se puede registrar igual:
  - lectura del cultivo;
  - repique del caldo de enriquecimiento;
  - lectura del antibiograma;
  - repique ciego y lectura del repique;
  - cierre del frasco como negativo.
- La pantalla muestra «OJO: todavía no se cumplió el tiempo de incubación», desde cuándo corresponde y cuánto falta. Para seguir, hay que tildar «Registrar igual, antes de tiempo»; el motivo es opcional.
- Si llega un pedido sin confirmar (por ejemplo, desde una pantalla abierta hace rato), el servidor lo rechaza con la misma advertencia.
- Auditoría: evento «Registro antes del tiempo de incubación», con qué se registró, cuántos minutos faltaban y el motivo.
- Una actividad programada se puede completar antes de su hora (`ActivityLifecycle`).
- Pruebas: 174 en verde.

### 2026-11-01 — Editor de protocolos más fácil de interpretar
- Las secciones siguen el orden de la mesada: siembra, directo, lecturas, qué se informa, recuentos y antibiograma. En el hemocultivo, el frasco va primero.
- Índice lateral que marca las secciones con faltantes o errores. Cada faltante para aprobar aparece en su sección (`ProtocolVersionRules::missingItems`).
- Resumen del protocolo en lenguaje de mesada, que se actualiza mientras se edita.
- Cada opción de lectura explica qué hace el sistema al elegirla.
- Las horas se muestran también en días, y hay una línea de tiempo de las lecturas.
- Los códigos internos quedan ocultos y se arman solos a partir del texto.
- Los errores dicen en qué fila están.
- Se indica qué sigue en el ciclo de la versión.
- Listado de protocolos con buscador y columnas «En uso» y «En preparación».
- La confirmación de negativos en bloque (Q-F1) figura como «todavía no tiene efecto»: se configura, pero ninguna mesada la usa todavía.

### 2026-11-01 — Casos publicados en la demo
- `PublishedCaseSeeder`: 12 escenarios basados en casos clínicos publicados, con pacientes ficticios y la fuente citada (`docs/demo/CASOS_PUBLICADOS.md`).
  - Cubren urocultivo, hemocultivo, LCR, líquidos pleural y articular, BAL, coprocultivo, secreción uretral y fauces.
  - Recorren el circuito real hasta distintas etapas, para que haya trabajo en cada mesada.
- Catálogo de referencia: *S. epidermidis*, *S. pneumoniae*, *S. pyogenes*, *S. enterica*, *N. meningitidis*, *N. gonorrhoeae* y *C. jejuni*.
- La carga mostró huecos: Q-C16 a Q-C19.
- Pruebas: 168 en verde.

### 2026-11-01 — Administración y configuración clínica (D-0019)
- **Usuarios:**
  - alta con contraseña inicial, perfiles por sede y baja sin borrado;
  - un usuario dado de baja no ingresa y pierde la sesión abierta;
  - designación auditada del Director técnico responsable de la configuración clínica (Q-A8).
- **Catálogos:** tipos de muestra, estudios, medios, microorganismos, antibióticos y sedes. El código queda fijo y nada se borra.
- **Protocolos versionados:** editor de lecturas, opciones, recuentos, medios y caldos, examen directo y frasco de hemocultivo.
  - Flujo: borrador → aprobado → vigente → retirado. Poner vigente una versión retira la anterior.
  - Las muestras recibidas conservan su versión.
- **Puntos de corte:** sets versionados con importación CSV (plantilla sin valores) y carga en la tabla.
  - Se aprueban y se ponen vigentes; el set ficticio de demo no se toca.
- Menú "Administración" en la barra lateral.
- Demo: `director.central@bactruf.test` es el responsable de la configuración clínica.
- Pruebas: 167 en verde.

### 2026-11-01 — Resto de los cultivos en placa
- Plantillas de referencia (`docs/templates/CULTIVOS.md`): en producción, en borrador; en demo, vigentes.
  - Secreción y herida, y líquidos de punción.
  - LCR.
  - Respiratorias.
  - Coprocultivo.
  - Genitales y exudado de fauces.
- Cronograma de lecturas por protocolo (D-0017):
  - sin desarrollo antes de la última lectura programa la siguiente;
  - después de un desarrollo siguen las lecturas, con desarrollo nuevo o sin desarrollo nuevo;
  - el cultivo cierra al terminar las lecturas y resolver los aislamientos.
- Caldos de enriquecimiento (selenito): repique a placas del mismo cultivo. Sin el repique no se registra la última lectura.
- Examen directo por protocolo (D-0018), con opciones y descripción; sale en el informe y habilita el preliminar.
  - El directo de LCR con microorganismos abre un resultado crítico.
  - La barra roja lleva a la pantalla de cada crítico.
- Mesada de procesamiento (exámenes directos y repique de caldos) en el inicio y en el menú lateral.
- Tarjeta del resultado crítico reutilizable (frasco y examen directo).
- Informe:
  - el resultado toma la lectura con desarrollo;
  - un preliminar con el cultivo abierto dice "Cultivo en curso".
- `demo:advance` también adelanta las placas de los caldos repicados y las lecturas de cultivos con aislamientos.
- Pruebas: 144 en verde.

### 2026-11-01 — Hemocultivo y retrocultivo de punta a punta
- Plantilla de referencia de hemocultivo y retrocultivo (`docs/templates/HEMOCULTIVO.md`): en producción, en borrador; en demo, vigente.
- Módulo `BloodCultures`:
  - un frasco por muestra; carga en el equipo (equipo, posición y hora) o manual;
  - alarma de positivo con la hora del equipo y tiempo hasta la positividad;
  - descarga del negativo al cumplirse los días;
  - repique diario a placa en el procesamiento manual: el desarrollo vuelve positivo al frasco, y el último repique sin desarrollo lo cierra negativo;
  - Gram con opciones del protocolo;
  - subcultivo en los medios del protocolo, que continúa en el circuito común (lectura, aislamientos, identificación, antibiograma y validación).
- Resumen de la orden por estudio: "Hemocultivo: 1 de 2 positivos · Retrocultivo: 0 de 1 positivo".
- Módulo `CriticalResults`: el Gram de un frasco positivo abre un resultado crítico.
  - Cada intento de comunicación queda registrado (a quién, medio, éxito, relectura); es idempotente y auditado.
  - Hay una barra roja persistente hasta que se comunica.
- Informe preliminar con el Gram y el resumen de la orden; el definitivo lo reemplaza.
- Mesada de hemocultivos (trabajo disponible y frascos en incubación), tarjetas en el inicio y menú lateral.
- `demo:advance` también adelanta frascos y repiques.
- Corregido: la programación de la lectura fallaba si el cultivo no venía de una siembra previa.
- Pruebas: 136 en verde.

### 2026-11-03 — Urocultivo de punta a punta
- Lectura: opciones del protocolo (teclas 1–5), aislamientos con recuento, placa de origen y significancia (la marca el bacteriólogo); reincubación limitada; cierre automático según la opción.
- Identificación: preliminar o definitiva (bioquímicas, VITEK manual, otro); antibiograma solo si hay panel para el grupo; sin ATB con motivo.
- Antibiograma: difusión o CIM, panel según el grupo; grilla con vista previa; interpretación automática con el set de puntos de corte vigente (congelado en el test); cambio de interpretación con motivo y permiso; elegir qué se informa.
- Validación: vista previa del informe, confirmación explícita, informe definitivo inmutable (snapshot) e imprimible; estados de estudio y muestra.
- Perfil de sensibilidad: % S/I/R por especie y antibiótico; primer aislamiento por paciente y especie; solo informes validados; aviso con < 30 aislamientos (metodología de referencia CLSI M39, Q-C9).
- Mesadas de lectura, identificación, antibiogramas y validación; contadores en inicio; la mesada muestra lo disponible aunque el liberador no haya corrido.
- Catálogo de referencia (16 organismos, 18 antibióticos, 4 paneles) y set de puntos de corte FICTICIO solo para demo.
- `demo:advance N` simula el paso del tiempo (siembras, lecturas, antibiogramas).
- Pruebas: 128 en verde (incluye el circuito completo por HTTP).

### 2026-11-03 — Urocultivo, parte 1: tanda de siembra
- Módulo `Cultures`: cultivo + placas según la versión de protocolo congelada; omitir un medio exige motivo; idempotente.
- Módulo `WorkBatches`: iniciar tanda desde la mesada (las actividades quedan tomadas), sembrar ítem por ítem, confirmación múltiple con protocolo estándar, retirar ítem con motivo, pausar/reanudar/finalizar (los pendientes vuelven a la mesada). Una actividad en una sola tanda activa (garantizado por la base). Historial de la tanda.
- Workflow: la siembra completa `INOCULATE` y programa `READ_CULTURE` (disponible a las 18 h, vence a las 24 h según la plantilla). `activities:release` cada minuto; `demo:advance N` (solo demo) para adelantar el reloj.
- Pantallas: mesada con selección e "Iniciar tanda con N"; pantalla de tanda (J/K, Enter, Esc); barra "Mis tandas abiertas".
- Pruebas: 115 en verde.

### 2026-11-02 — Recepción por orden (Q-F8)
- Una recepción registra una orden (número de LABWIN, paciente, médico, servicio, prioridad) con una o varias muestras, cada una con su código, tipo y estudios.
- Si el número de orden ya existe en la sede, las muestras se suman a esa orden (muestras que llegan después); la orden conserva paciente, médico y prioridad. Se rechaza si el documento no coincide.
- Nueva entidad `Reception` (entrega física); la idempotencia pasa a nivel de entrega.
- Pantalla de recepción rediseñada: orden → muestras escaneadas una por una; "Recibidas hoy" agrupado por entrega.
- Pruebas: 94 en verde.

### 2026-11-02 — Urocultivo, entrega 1: recepción → mesada de siembra
- Plantilla de referencia de urocultivo (`docs/templates/UROCULTIVO.md`, D-0008): chorro medio y sonda vesical; agar sangre + MacConkey; 35 ± 2 °C; lectura 18–24 h. En producción se carga en borrador; en demo, vigente.
- Módulo `Configuration`: tipos de muestra, estudios, medios, protocolos con versiones (`draft → approved → active → retired`); la base garantiza una sola versión vigente por protocolo.
- Módulos `Patients`, `Orders`, `Samples`: recepción manual escaneando la etiqueta del LIS; búsqueda de paciente por documento; versión de protocolo congelada por estudio; idempotencia y auditoría.
- Módulo `Workflow`: actividades con generación idempotente; política `SampleReceived → INOCULATE` por estudio en la sede de procesamiento.
- Pantallas: Recepción (lector + teclado), Mesada de siembra (por protocolo, prioridad, refresco cada 30 s), inicio con contadores reales.
- Alias polimórficos estables (morph map).
- Pruebas: 88 en verde.

### 2026-11-01 — Auditoría base (ADR-0006)
- Módulo `Audit`: tabla `audit_events` append-only (triggers de MariaDB rechazan UPDATE/DELETE), hash encadenado serializado con `audit_chain_head`, escritura solo dentro de transacción (`Auditor`).
- `audit:verify` (programado a diario 03:00) detecta eventos alterados, insertados o eliminados.
- Se auditan ingresos, salidas, intentos fallidos (sin contraseña) y cambio de sede. `X-Request-Id` por request.
- Pantalla "Auditoría" (permiso `audit.view`) con filtro por acción y estado de la cadena.
- Pruebas: 70 en verde.

### 2026-11-01 — Fase 3 (base técnica, parte 1)
- Proyecto Laravel 13.17 con el starter kit oficial de Vue: Inertia 3, Vue 3 + TypeScript, Tailwind 4, shadcn-vue/Reka UI, Fortify (2FA y llaves de acceso), Wayfinder, Pest 5, Larastan (nivel 7), Pint.
- Entorno Docker: PHP 8.5.12 + MariaDB 12.3.3 (utf8mb4); app en http://localhost:8080. Atajo `./sail` para Git Bash.
- spatie/laravel-permission 8.3 con roles por sede (D-0012); laravel-lang/common 6.8 (traducciones es).
- Módulo `Organization`: sedes. Módulo `Identity`: enums de roles y permisos según la matriz, sede activa por sesión, selector de sede.
- Interfaz en español; pantalla de inicio con las mesadas previstas; raíz → inicio.
- Sin autorregistro ni autoeliminación de cuentas (D-0011).
- Datos de demostración sintéticos: 3 sedes y 7 usuarios.
- Pruebas: 53 en verde (incluye 7 de arquitectura y 8 de sedes/permisos); Larastan sin errores; frontend sin errores de tipos ni de lint.

### 2026-10-31 — Respuestas del laboratorio (tandas 1–3)
- Respondidas: Q-A2, Q-S1, Q-T1, Q-F1, Q-F4. Parciales: Q-A1, Q-A3, Q-A4, Q-B1 (LABWIN), Q-B2 (VITEK 2 Compact), Q-B3 (BacT/ALERT), Q-C3.
- D-0008: contenido clínico inicial a partir de plantillas genéricas de referencia, en `draft` hasta aprobación (MICROBIOLOGY_RULES §6).
- D-0009: entorno Docker + Sail; Docker Desktop operativo.
- D-0010: sin Redis; colas, caché y sesiones en MariaDB.
- Matriz de roles: el Auxiliar siembra, monta y registra bioquímicas; no hace lecturas de cultivo.
- Q-A4 respondida (3 sedes, cada una procesa; solo una con equipos) → ADR-0008 Multisede (propuesta). Q-A5…Q-A7 respondidas (derivación poco frecuente; la sede de origen ve todo y valida; un DT por sede; configuración compartida). Q-A8: aprueba la configuración clínica un DT designado como responsable.
- Q-C4 parcial: módulo de carga de puntos de corte con revisión y confirmación del bacteriólogo.

### 2026-10-30 — Fase 1 (diseño funcional, borrador)
- `docs/functional/`: catálogo de actividades, máquinas de estado, flujos de excepción, matriz de roles y permisos, mesadas.
- ADR 0001–0007 pasan a "Aceptada".
- Nuevas preguntas Q-F1…Q-F5.

### 2026-10-30 — Fase 0 (análisis)
- Estructura inicial de documentación, arquitectura propuesta, modelo de dominio, motor de actividades, tandas, estrategia UX/UI, roadmap, riesgos, preguntas abiertas y ADR 0001–0007.
