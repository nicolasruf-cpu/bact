# Arquitectura

## 1. Estilo: monolito modular Laravel
Un único despliegue Laravel con módulos de límites explícitos (ADR-0001).

**Por qué no microservicios:** un solo laboratorio, un equipo chico y operaciones clínicas que necesitan transacciones ACID entre muestra, actividad y tanda. Los microservicios sumarían consistencia eventual, más infraestructura y más puntos de falla, sin ningún beneficio concreto en este contexto.

## 2. Capas dentro de cada módulo

```
app/Modules/<Module>/
  Models/          Eloquent (persistencia + relaciones; sin reglas clínicas complejas)
  Enums/           Estados, tipos (PHP backed enums, con transiciones permitidas)
  Data/            DTOs / value objects (p. ej. MicValue, ZoneDiameter, Priority)
  Actions/         Casos de uso: una clase = una operación (InoculateSample, RecordReading…)
  Services/        Lógica de dominio reutilizable y sin estado (BreakpointInterpreter…)
  Events/ Listeners/
  Policies/
  Http/Controllers/  Delgados: FormRequest → Action → Inertia::render / redirect
  Http/Requests/
  Queries/         Consultas de lectura optimizadas (contadores de mesadas, bandejas)
  Database/        Factories y seeders del módulo (migraciones en database/migrations con prefijo)
  <Module>ServiceProvider.php
```

Frontend:
```
resources/js/
  pages/<module>/…        Páginas Inertia
  components/ui/…         Design system (shadcn-vue adaptado)
  components/domain/…     Componentes de dominio (SampleChip, AstGrid, PlateBadge…)
  composables/            useBarcodeScanner, useShortcuts, useBatchSession…
  types/                  Tipos TS generados desde DTOs/enums de PHP
```

## 3. Módulos

| Módulo | Responsabilidad |
|---|---|
| **Identity** | Usuarios, autenticación (Fortify), roles/permisos, turnos |
| **Patients** | Pacientes (datos mínimos necesarios) |
| **Orders** | Solicitudes y estudios solicitados (se usa "Orders" para no chocar con `Request` de HTTP) |
| **Samples** | Recepción, código de barras, rechazo, estado de muestra |
| **Cultures** | Cultivos, placas/medios, incubación, lecturas |
| **Isolates** | Aislamientos, repiques |
| **Identification** | Intentos de identificación, pruebas bioquímicas, Gram, API, VITEK (ID) |
| **Susceptibility** | Antibiogramas, interpretación CLSI versionada, reglas expertas |
| **Workflow** | Actividades, generación automática, prioridades, demoras, mesadas (consultas) |
| **WorkBatches** | Tandas, ítems, interrupción/reanudación, historial |
| **Reporting** | Validación, informes (preliminar/parcial/definitivo/corregido), PDF |
| **CriticalResults** | Detección, alertas, registro de comunicación |
| **Configuration** | Catálogos versionados (protocolos, medios, paneles, reglas, plantillas) |
| **Integrations** | LIS, VITEK, impresión de etiquetas (adaptadores detrás de interfaces) |
| **Audit** | Registro inmutable de eventos |
| **Statistics** | Indicadores y epidemiología (fase posterior) |

### Dependencias permitidas (dirigidas, sin ciclos)
```
Configuration, Identity, Audit         ← base; no dependen de módulos clínicos
Patients → Orders → Samples → Cultures → Isolates → Identification / Susceptibility
Workflow    depende de los módulos clínicos solo mediante eventos y contratos (interfaces)
WorkBatches → Workflow
Reporting, CriticalResults → módulos clínicos (lectura)
Integrations → contratos de los módulos (nunca al revés)
```
Los módulos clínicos **no conocen** a Workflow: emiten eventos de dominio (`CultureInoculated`, `ReadingRecorded`, `IsolateIdentified`…) y Workflow reacciona creando o cerrando actividades **dentro de la misma transacción**, con listeners síncronos (ver WORKFLOW_ENGINE.md). Los tests de arquitectura de Pest (`arch()`) controlan estas reglas.

## 4. Transacciones, concurrencia e idempotencia
- Cada Action clínica corre dentro de `DB::transaction()`: el cambio de dominio, el cierre o creación de actividades, la actualización de la tanda y la auditoría se confirman juntos o no se confirma nada.
- **Bloqueo optimista**: columna `lock_version` en entidades editables. Si hay conflicto, se responde 409 y la UI muestra qué cambió y quién lo cambió.
- **Bloqueo pesimista** (`SELECT … FOR UPDATE`) solo al tomar actividades o ítems de tanda, para que dos personas no procesen lo mismo.
- **Idempotencia**: cada envío desde la UI lleva un `idempotency_key` (UUID). Hay un índice único por operación, así que un doble clic o un reintento de red no duplica nada.

## 5. Tiempo real en la mesada
- Etapa 1: *polling* de Inertia (`usePoll`, cada 15–30 s) y recargas parciales solo de los contadores. Es simple y robusto.
- Etapa 2 (si hace falta): Laravel Reverb (WebSockets) para alertas críticas instantáneas. Ver ADR-0005.

## 6. Procesamiento asíncrono (colas Redis)
Solo para lo que **no** es parte de la consistencia clínica: generación de PDF, envío y recepción de integraciones (LIS/VITEK), notificaciones, recálculo de estadísticas y el *job* periódico que marca actividades demoradas.

## 7. Integraciones
Adaptadores detrás de interfaces (`LisGateway`, `VitekGateway`, `LabelPrinter`). En el MVP: carga manual e importación de archivos. Los protocolos reales se definen recién con la documentación técnica de cada equipo (no se asume HL7/ASTM sin verificar). Toda la mensajería entrante y saliente queda registrada en una tabla `integration_messages`, con reintentos y un estado visible para el usuario.

## 8. Entornos
| Entorno | Descripción |
|---|---|
| Desarrollo | Docker (Laravel Sail sobre WSL2) **o** Laravel Herd para Windows — decisión pendiente (OPEN_QUESTIONS Q-T1) |
| Pruebas/QA | Servidor Ubuntu con datos sintéticos, igual a producción |
| Producción | Ubuntu Server LTS · Nginx · PHP-FPM 8.5 · MariaDB 12.3 · Redis · systemd (workers, scheduler) · HTTPS |
