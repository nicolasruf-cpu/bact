# Roadmap

Cada fase cierra con una revisión del usuario. No se dan fechas: el avance se mide por criterios de salida.

| Fase | Contenido | Criterio de salida |
|---|---|---|
| **0 — Análisis** ✅ | Requerimientos, versiones, arquitectura, modelo de actividades y tandas, riesgos, preguntas, estructura de docs | Aprobación del usuario |
| **1 — Diseño funcional** 🟡 (borrador en docs/functional) | Catálogo de actividades, máquinas de estado definitivas, excepciones, matriz rol × permiso, definición de mesadas | Documentos aprobados + respuestas clínicas mínimas para urocultivo |
| **2 — UX/UI** | Design system (tokens, componentes), prototipos navegables: principal, siembra, lectura, VITEK, ATB, validación, crítico | El usuario valida los prototipos con un bacteriólogo real |
| **3 — Arquitectura técnica** | Proyecto Laravel 13, módulos, CI, modelo físico de datos, contratos, auth/roles, auditoría base | Proyecto que compila, CI en verde, tests de arquitectura |
| **4a — MVP vertical: urocultivo** | Recepción manual → siembra en tanda → lectura → aislamientos → identificación manual/bioquímica → ATB por difusión → validación → informe PDF; mesada + tandas + auditoría | Escenarios 1, 2, 3, 4, 10, 11, 14 en verde |
| **4b — Hemocultivo y críticos** | Positivos urgentes, Gram, resultados críticos, comunicación registrada | Escenarios 8, 15 |
| **4c — VITEK (manual) y CIM** | Mesada y tandas VITEK con hoja de carga, carga e importación de resultados, CIM, discordancias, repeticiones | Escenarios 5, 6, 7, 13 |
| **4d — Configuración clínica** | Editores versionados: protocolos, paneles, puntos de corte (importación CSV), reglas expertas y críticas, plantillas | Aprobación por Director técnico funcional |
| **4e — Correcciones e informes** | Versiones de informe, corrección con motivo, historial | Escenario 9 |
| **4f — Integraciones** | LIS (según su interfaz), VITEK automático (según documentación), etiquetas | Escenario 12 |
| **4g — Estadísticas** | Indicadores, perfiles de sensibilidad, tiempos, productividad | Reportes reproducibles |
| **5 — Simulación** | Jornadas simuladas con volumen real, usuarios concurrentes, urgencias | Sin pérdidas ni duplicados; rendimiento aceptable |
| **6 — Validación y producción** | Revisión microbiológica, seguridad, aceptación, despliegue, respaldo | Aprobación formal + plan de puesta en marcha |

## Definición de Terminado (por funcionalidad)
Funciona · es intuitiva · respeta el design system · valida en el servidor · respeta permisos · audita · tiene pruebas · no rompe nada (suite completa en verde) · está documentada · fue revisada · se probó con datos representativos.
