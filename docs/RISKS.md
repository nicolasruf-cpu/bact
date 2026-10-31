# Riesgos

| # | Riesgo | Impacto | Mitigación |
|---|---|---|---|
| R1 | Definiciones clínicas incompletas o ambiguas | Alto: reglas erróneas afectan la seguridad del paciente | Todo es configurable y versionado; nada clínico en el código; aprobación del Director técnico; datos de ejemplo marcados "NO CLÍNICOS" |
| R2 | Puntos de corte CLSI (licencia, actualización anual) | Alto | El laboratorio carga su edición; importación validada; edición congelada por antibiograma |
| R3 | Integraciones LIS/VITEK sin documentación | Medio/alto | Adaptadores detrás de interfaces; MVP manual; no asumir protocolos |
| R4 | Concurrencia: duplicados o pérdidas en tandas | Alto | Transacciones, bloqueo, idempotencia, índice único de ítem activo, pruebas de concurrencia |
| R5 | Adopción: el sistema resulta lento frente al papel | Alto | Prototipos validados con bacteriólogos en la Fase 2; teclado y lector; métricas de clics por tarea |
| R6 | Alcance muy amplio (16 módulos) | Alto | Cortes verticales por estudio; MVP de urocultivo de punta a punta antes de expandir |
| R7 | Motor de flujo demasiado genérico o demasiado rígido | Medio | Políticas explícitas + configuración (ADR-0004); se revisa tras el MVP |
| R8 | Datos sensibles: fuga o acceso indebido | Alto | Permisos granulares, auditoría con hash, HTTPS, mínimo necesario, revisión de seguridad |
| R9 | Pérdida de datos (servidor on-premise) | Alto | Respaldos cifrados fuera del servidor, restauración probada, monitoreo |
| R10 | Entorno local desactualizado (PHP 7.1) | Bajo | Docker/Herd (Q-T1) |
| R11 | Pérdida de contexto entre sesiones de desarrollo | Medio | CLAUDE.md, PROJECT_STATUS, ADR, CHANGELOG mantenidos en cada etapa |
| R12 | Validación regulatoria o de calidad del software (p. ej., auditorías de acreditación) | Medio | Trazabilidad completa, pruebas documentadas, control de versiones de configuración |
