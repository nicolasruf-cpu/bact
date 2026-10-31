# BactRuf — Sistema de Gestión de Bacteriología Clínica

Laboratorio RUF.

BactRuf es una **mesada digital inteligente**: organiza el trabajo diario del bacteriólogo (siembras, lecturas, identificaciones, tandas VITEK, antibiogramas, validación y resultados críticos) en actividades y tandas. Así el profesional encuentra su trabajo ordenado al abrir la aplicación.

> Estado: **planificación (Fase 0)**. Todavía no hay código. Ver [docs/PROJECT_STATUS.md](docs/PROJECT_STATUS.md).

## Stack previsto
PHP 8.5 · Laravel 13 · MariaDB 12.3 LTS · Redis · Inertia 3 · Vue 3 + TypeScript · Tailwind CSS 4 · shadcn-vue. Despliegue en Ubuntu Server (Nginx, PHP-FPM, systemd/Supervisor, HTTPS).

## Documentación
| Documento | Contenido |
|---|---|
| [CLAUDE.md](CLAUDE.md) | Instrucciones permanentes para agentes |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Arquitectura y módulos |
| [docs/DOMAIN_MODEL.md](docs/DOMAIN_MODEL.md) | Modelo de dominio y glosario |
| [docs/WORKFLOW_ENGINE.md](docs/WORKFLOW_ENGINE.md) | Actividades, estados y generación automática |
| [docs/WORK_BATCHES.md](docs/WORK_BATCHES.md) | Tandas de trabajo |
| [docs/MICROBIOLOGY_RULES.md](docs/MICROBIOLOGY_RULES.md) | Reglas microbiológicas y definiciones clínicas pendientes |
| [docs/UX_UI.md](docs/UX_UI.md) | Estrategia UX y sistema visual |
| [docs/DATABASE.md](docs/DATABASE.md) | Lineamientos de base de datos |
| [docs/SECURITY.md](docs/SECURITY.md) | Seguridad, roles y auditoría |
| [docs/TESTING.md](docs/TESTING.md) | Estrategia de pruebas |
| [docs/ROADMAP.md](docs/ROADMAP.md) | Fases e hitos |
| [docs/PROJECT_STATUS.md](docs/PROJECT_STATUS.md) | Estado actual |
| [docs/OPEN_QUESTIONS.md](docs/OPEN_QUESTIONS.md) | Preguntas que requieren definición |
| [docs/RISKS.md](docs/RISKS.md) | Riesgos |
| [docs/DECISIONS.md](docs/DECISIONS.md) · [docs/adr/](docs/adr/) | Decisiones |
| [docs/AGENTS.md](docs/AGENTS.md) | Organización del equipo de agentes |
| [docs/CHANGELOG.md](docs/CHANGELOG.md) | Cambios |

Instalación, despliegue, respaldo, desarrollo y manual de usuario: en `docs/` (se completan durante la implementación).
