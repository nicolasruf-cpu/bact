# Changelog

## [Sin publicar]
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
