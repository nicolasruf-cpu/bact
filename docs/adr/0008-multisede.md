# ADR-0008 — Multisede
**Estado:** Aceptada (2026-10-31) · **Fecha:** 2026-10-31

**Contexto (Q-A4…Q-A7):** el laboratorio tiene 3 sedes. **Cada una procesa su propia bacteriología y tiene su propio Director técnico**; **solo una tiene equipamiento** (VITEK 2 Compact, BacT/ALERT). **Derivar es poco frecuente**: a veces se envían hemocultivos o aislamientos. Algunas personas trabajan en más de una sede.

**Decisión:**
- **Una sola instalación y una sola base de datos** para las 3 sedes, con un historial único por paciente y por muestra, y una sola auditoría.
- Nueva entidad **`Site`** (sede).
  - La muestra tiene una **sede de origen** (`origin_site_id`), que es la dueña del caso.
  - Lo que se procesa físicamente (cultivo, frasco de hemocultivo, aislamiento) tiene una **sede de procesamiento** (`processing_site_id`). Normalmente coincide con la de origen y cambia solo si se deriva.
  - Mesadas, colas de actividades, tandas, equipos (`Instrument`) e impresoras pertenecen a una sede.
- **Usuarios:** pueden tener **varias sedes habilitadas**, y en cada sesión eligen una **sede activa**. Los permisos (y el rol de Director técnico) se asignan **por sede**.
- **Visibilidad:** la sede de origen **ve siempre todas sus muestras**, también las partes que se procesan en otra sede (estado, lecturas, resultados). La sede que procesa ve solo lo que le derivaron.
- **Configuración clínica: una sola, compartida** por las 3 sedes (protocolos, paneles, puntos de corte, reglas), con versiones y aprobación. Lo que cambia entre sedes son los **métodos disponibles** según el equipamiento. La aprueba **un Director técnico designado como responsable** (Q-A8), con un permiso global, no por sede.
- **Derivación interna (poco frecuente):** `SEND_TO_SITE` (envío) y `RECEIVE_FROM_SITE` (recepción), con trazabilidad (quién envió, cuándo, quién recibió). El trabajo derivado aparece en la mesada de la sede que procesa; los resultados vuelven al caso original.
- **Informe:** lo **valida y firma la sede de origen**, bajo la responsabilidad de su Director técnico, aunque una parte se haya procesado en otra sede. El informe indica qué sede procesó cada parte.

**Por qué:** una sola configuración clínica para mantener y aprobar, historial único y transacciones ACID entre sedes. Separar instalaciones por sede duplicaría la configuración y haría muy difícil la derivación y la consulta desde el origen.

**Consecuencias:**
- `site_id` / `origin_site_id` / `processing_site_id` en las tablas operativas desde la primera migración (agregarlos después es caro).
- *Scoping* por sede en consultas y policies, verificado con tests (incluido "la sede de origen ve lo derivado" y "otra sede no ve lo que no le derivaron").
- Dos actividades nuevas en el catálogo. Como derivar es poco frecuente, la derivación puede llegar después del MVP, pero el modelo de datos la soporta desde el principio.
