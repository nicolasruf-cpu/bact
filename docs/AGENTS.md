# Organización del equipo de agentes

| Perfil | Responsabilidad principal | Cuándo interviene |
|---|---|---|
| Arquitecto (agente principal) | Coordina, decide arquitectura, integra, mantiene docs de continuidad | Siempre |
| Microbiología clínica | Revisa modelos, flujos y reglas; detecta supuestos clínicos y los envía a OPEN_QUESTIONS | Fases 1, 4 (cada módulo clínico), 6 |
| UX | Jornada, flujos, clics por tarea, prevención de errores | Fases 1, 2, revisión de cada pantalla |
| UI | Design system, tokens, accesibilidad visual | Fase 2 y revisiones |
| Backend Laravel | Actions, servicios, políticas, migraciones, pruebas | Fases 3, 4 |
| Frontend | Páginas Inertia, componentes, composables | Fases 2, 4 |
| MariaDB | Modelo físico, índices, rendimiento | Fases 3, 5 |
| QA y seguridad | Revisión de código, pruebas de concurrencia y permisos, auditoría de dependencias | Al cierre de cada módulo, Fases 5 y 6 |
| Documentación | Mantiene docs sincronizadas con el código | Cierre de cada etapa |

**Modo de trabajo:** el agente principal ejecuta en forma secuencial las tareas que dependen entre sí. Los subagentes se usan donde aportan paralelismo real o una mirada independiente: revisión de QA y seguridad de cada módulo, exploración de documentación técnica de integraciones y revisión de accesibilidad. Así se evita duplicar contexto y trabajo.

En la Fase 0 el análisis se hizo de manera secuencial por perfil.
