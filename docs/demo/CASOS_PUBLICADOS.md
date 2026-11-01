# Casos publicados para la demo

> **Solo demo.** Cada escenario parte de un caso clínico publicado. Del caso se toman la edad, el sexo, el cuadro y los hallazgos de laboratorio.
> - **Los pacientes son ficticios:** "Caso publicado NN", documento 99000NNN.
> - **No se inventan resultados:** lo que la publicación no informa queda pendiente.
> - **Antibiogramas:** las publicaciones informan sobre todo S/R, sin los valores medidos. Por eso los antibiogramas quedan armados y sin leer.
> - **Interpretación:** en la demo se hace con el set de puntos de corte **ficticio**.

**Carga.**
- La hace `PublishedCaseSeeder`, que también corre dentro de `DemoSeeder`.
- Para cargarlos sobre una demo existente: `./sail artisan db:seed --class=PublishedCaseSeeder`.
- Es idempotente: no repite un caso que ya tiene su código de barras.
- Cada caso recorre las acciones reales del sistema con un reloj simulado: recepción, siembra, directo, lecturas, identificación, críticos e informes. La auditoría queda completa.
- La prueba `tests/Feature/Demo/PublishedCaseSeederTest.php` verifica que todos los casos pasen sin errores.

## Casos

| Código | Estudio | Escenario | Fuente | Hasta dónde llega |
|---|---|---|---|---|
| PUB-01 | Urocultivo (chorro medio) | Mujer de 24 años, cistitis por *E. coli* BLEE (CTX-M-15) | DeBusscher J, et al. *Emerg Infect Dis.* 2009;15(11):1862-4. [PMC2857214](https://pmc.ncbi.nlm.nih.gov/articles/PMC2857214/) | Identificada; antibiograma por CIM armado, sin leer |
| PUB-02 | Hemocultivo | Mujer de 55 años, bacteriemia por *S. aureus* meticilino sensible con foco en un orzuelo | *J Fam Pract.* 1989;29(4). [PDF](https://cdn.mdedge.com/files/s3fs-public/jfp-archived-issues/1989-volume_28-29/JFP_1989-10_v29_i4_staphylococcus-aureus-hordeolum-as-a-cau.pdf) | Positivo a las 14 h → Gram CGP en racimos → crítico comunicado → preliminar → subcultivo → *S. aureus*; antibiograma armado |
| PUB-03 | Hemocultivo | Mujer de 67 años, bacteriemia por *K. pneumoniae* KPC (paciente 3) | Goldfarb D, et al. *J Clin Microbiol.* 2009;47(6):1920-2. [PMC2691101](https://pmc.ncbi.nlm.nih.gov/articles/PMC2691101) | Gram BGN → crítico comunicado → subcultivo → *K. pneumoniae*; antibiograma por CIM armado |
| PUB-04 | LCR | Niño de 3 años, meningitis por *N. meningitidis* | CNS Case Studies — Part I, caso 1 (WebPath, Univ. de Tel Aviv). [Enlace](https://www.tau.ac.il/medicine/tau-only/webpath/microbio/micrns01.htm) | Directo inmediato con diplococos gram negativos → crítico comunicado → preliminar → desarrollo a las 24 h → identificado; siguen las lecturas de 48 y 72 h |
| PUB-05 | Líquido pleural | Hombre de 61 años con quimioterapia, empiema por *S. pneumoniae* 23F | Yovi I, et al. *Acta Med Indones.* 2025;57(4):503. [Enlace](https://www.actamedindones.org/index.php/ijim/article/view/2902) | Directo con CGP → preliminar → desarrollo → identificado sin antibiograma (no hay panel) |
| PUB-06 | Líquido articular | Hombre de 49 años, artritis séptica por *S. aureus* con **Gram negativo** | *Reumatol Clin.* 2016, carta sobre falsos negativos del líquido sinovial. [Enlace](https://reumatologiaclinica.org/en-false-negatives-synovial-fluid-in-articulo-S217357431600023X) | Directo sin microorganismos → desarrollo a las 22 h → **en la mesada de identificación** |
| PUB-07 | Lavado broncoalveolar | 87 años, UTI, miastenia gravis: *S. aureus* 10⁵ y *P. aeruginosa* 10² UFC/mL | Kasprzycka K, et al. *J Educ Health Sport.* 2023;45(1):76-85. [doi:10.12775/JEHS.2023.45.01.005](https://apcz.umk.pl/JEHS/article/view/45307) | Dos aislamientos: *S. aureus* significativo (≥ 10⁴) identificado con antibiograma armado; *P. aeruginosa* no significativo (< 10³) |
| PUB-08 | Coprocultivo | Mujer de 60 años, gastroenteritis por *Salmonella* Enteritidis con shock | *J Clin Diagn Res.* 2012 (caso clínico). [PDF](https://www.jcdr.net/articles/PDF/2173/49%20-%204281.pdf) | Desarrollo en XLD → repique del selenito → *S. enterica*; antibiograma armado; falta la lectura de 48 h |
| PUB-09 | Coprocultivo | Niña de 4 años, enteritis por *C. jejuni* | Albahra S, McElvania TeKippe E. Lablogatory, 12/01/2016. [Enlace](https://labmedicineblog.com/2016/01/12/microbiology-case-study-4-year-old-girl-with-diarrhea/) | Directo con leucocitos → desarrollo en medio para Campylobacter a 42 °C → identificado |
| PUB-10 | Secreción uretral | Hombre de 29 años, uretritis por *N. gonorrhoeae* | Petrovska Basovska B, et al. *Acta Morphologica.* 2021. [Enlace](https://repository.ukim.mk/handle/20.500.12188/28149) | Desarrollo en Thayer-Martin → identificado; **el directo queda pendiente** (la publicación no informa el Gram) |
| PUB-11 | Exudado de fauces | Lactante de 2 meses, faringitis por *S. pyogenes* | Sharif MR, et al. *Jundishapur J Microbiol.* 2016;9(5):e32630. [doi:10.5812/jjm.32630](https://brieflands.com/articles/jjm-56653) | **Circuito completo:** desarrollo → identificación → lectura de 48 h → informe validado |
| PUB-12 | Urocultivo | Mujer de 31 años, mismo cuadro que PUB-01 | Escenario basado en PUB-01 | Recibida, **sin sembrar** (mesada de siembra) |

### Datos de las publicaciones que quedan fuera del sistema
- **PUB-01:** resistente a ampicilina, cefazolina, ceftriaxona (CIM > 64), aztreonam, piperacilina, ceftazidima (CIM 1–4), cefepime, fluoroquinolonas, tetraciclina y TMS; sensible a cefoxitina, aminoglucósidos, carbapenemes y nitrofurantoína; amoxicilina-clavulánico CIM 16 (I).
- **PUB-03:** meropenem CIM 2 (sensible al principio), ertapenem > 128, tigeciclina 2, colistina 0,5. Gentamicina I y el resto de los β-lactámicos R. *bla*<sub>KPC</sub> por PCR.
- **PUB-05:** serotipo 23F.
- **PUB-08:** serotipo Enteritidis.
- **PUB-09:** identificación por MALDI-TOF.
- **PUB-10:** confirmación por PCR; CIM sensible a ceftriaxona y ciprofloxacina.

## Qué mostró la carga (huecos para resolver)
Detalle en `OPEN_QUESTIONS.md`, Q-C16 a Q-C19.
1. **Antibiograma sin valor medido (Q-C16).** Hoy cada antibiótico del panel exige un valor medido (halo o CIM). Faltan dos casos:
   - cargar un resultado que solo trae la interpretación (laboratorio derivante o publicación);
   - no ensayar un antibiótico del panel.
2. **Paneles (Q-C3).** Solo hay paneles de urocultivo. *S. pneumoniae*, *S. pyogenes*, *Neisseria* y *Campylobacter* quedan sin antibiograma, y *S. aureus* de sangre o BAL usa el panel de orina.
3. **Pruebas complementarias (Q-C17).** No hay dónde registrar:
   - serotipo o serogrupo;
   - mecanismo de resistencia (BLEE, KPC, β-lactamasa);
   - PCR o antígenos;
   - el método MALDI-TOF.
   Hoy van como texto libre en la identificación.
4. **Repique del caldo y primera lectura (Q-C18).** En el coprocultivo, si el selenito se repica antes de leer las placas primarias, la lectura de las 24 h espera las 18 h de incubación de las placas del repique.
5. **Directo no realizado (Q-C19).** No hay forma de marcar que un directo no se hizo (PUB-10): la actividad queda abierta.
6. **Retrocultivo y tiempo diferencial (Q-C10).** No se cargó un caso porque no se pudo acceder a la fuente; el par catéter/periférica sigue sin cálculo.
7. **Aspirado traqueal (Q-C13).** La publicación usa recuento semicuantitativo ("abundante") y la plantilla tiene categorías en UFC/mL.
