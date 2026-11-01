# Registro de decisiones

| ID | Fecha | Decisión | Estado | Detalle |
|---|---|---|---|---|
| ADR-0001 | 2026-10-30 | Monolito modular Laravel | Aceptada | [adr/0001](adr/0001-monolito-modular.md) |
| ADR-0002 | 2026-10-30 | Stack y versiones | Aceptada | [adr/0002](adr/0002-stack-y-versiones.md) |
| ADR-0003 | 2026-10-30 | Frontend: Inertia + Vue + shadcn-vue | Aceptada | [adr/0003](adr/0003-frontend.md) |
| ADR-0004 | 2026-10-30 | Motor de actividades por políticas explícitas, no BPMN | Aceptada | [adr/0004](adr/0004-motor-actividades.md) |
| ADR-0005 | 2026-10-30 | Actualización de mesada: polling primero, WebSockets después | Aceptada | [adr/0005](adr/0005-tiempo-real.md) |
| ADR-0006 | 2026-10-30 | Auditoría propia append-only con hash encadenado | Aceptada | [adr/0006](adr/0006-auditoria.md) |
| ADR-0007 | 2026-10-30 | Código en inglés, UI y docs en español | Aceptada | [adr/0007](adr/0007-idioma.md) |
| D-0008 | 2026-10-31 | Contenido clínico inicial a partir de plantillas genéricas aceptadas por la industria, en `draft` hasta aprobación del Director técnico; puntos de corte CLSI excluidos | Aceptada (pedido del usuario) | [MICROBIOLOGY_RULES §6](MICROBIOLOGY_RULES.md) |
| D-0009 | 2026-10-31 | Entorno de desarrollo: Docker Desktop + Laravel Sail (Q-T1) | Aceptada | [OPEN_QUESTIONS](OPEN_QUESTIONS.md) |
| ADR-0008 | 2026-10-31 | Multisede: una instalación, entidad `Site`, sede de origen vs. de procesamiento, configuración clínica compartida, la sede de origen valida | Aceptada | [adr/0008](adr/0008-multisede.md) |
| D-0010 | 2026-10-31 | Sin Redis: colas, caché, sesiones y bloqueos en MariaDB (modifica ADR-0002) | Aceptada | [adr/0002](adr/0002-stack-y-versiones.md) |
| D-0011 | 2026-11-01 | Sin autorregistro ni autoeliminación de cuentas: las crea y desactiva el Administrador (la auditoría debe conservar al autor de cada acción) | Aceptada (seguridad, alineada con SECURITY.md) | — |
| D-0012 | 2026-11-01 | Roles por sede con la función *teams* de spatie/laravel-permission (team = `Site`); roles definidos una vez, asignados por sede | Aceptada (implementa ADR-0008) | [ADR-0008](adr/0008-multisede.md) |
| D-0013 | 2026-11-02 | En la recepción el paciente se identifica por número de documento; si ya existe se reutiliza sin modificar sus datos (la corrección de datos de paciente será una operación aparte, auditada) | Propuesta (❓ Q-F6) | [OPEN_QUESTIONS](OPEN_QUESTIONS.md) |
| D-0014 | 2026-11-02 | Alias polimórficos estables (`Relation::enforceMorphMap`): la base no guarda nombres de clase PHP | Aceptada (técnica) | — |

ADR-0001 a 0007 aprobadas por el usuario el 2026-10-30 ("avancemos"). D-0008, D-0009 y ADR-0008 decididas por el usuario el 2026-10-31.
