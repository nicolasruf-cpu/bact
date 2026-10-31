# Roles y permisos (Fase 1 — propuesta)

Leyenda: ✅ permitido · — no permitido · ❓ pendiente de definición (default propuesto entre paréntesis).
Los permisos son granulares (spatie/laravel-permission); los roles son agrupaciones editables por el Administrador, salvo los permisos clínicos críticos, que además requieren al Director técnico.

| Permiso | Admin | Director técnico | Bioquímico | Auxiliar | Consulta |
|---|---|---|---|---|---|
| `samples.view` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `patients.view_identity` (datos completos del paciente) | — | ✅ | ✅ | ✅ | ❓ (✅) |
| `samples.receive` | — | ✅ | ✅ | ✅ | — |
| `samples.reject` | — | ✅ | ✅ | ❓ (✅ solo en recepción) | — |
| `cultures.inoculate` | — | ✅ | ✅ | ✅ (Q-S1) | — |
| `cultures.read_negative` | — | ✅ | ✅ | — (Q-S1: no hace lecturas) | — |
| `cultures.read_positive` / `isolates.create` | — | ✅ | ✅ | — | — |
| `identification.biochem.setup` | — | ✅ | ✅ | ✅ (Q-S1) | — |
| `identification.biochem.read` (registrar resultado de bioquímicas) | — | ✅ | ✅ | ✅ (Q-S1) | — |
| `identification.perform` (identificación final) | — | ✅ | ✅ | — | — |
| `vitek.load` | — | ✅ | ✅ | ❓ (✅ carga física) | — |
| `ast.setup` | — | ✅ | ✅ | ❓ (—) | — |
| `ast.read` | — | ✅ | ✅ | — | — |
| `ast.interpretation.override` | — | ✅ | ✅ | — | — |
| `reports.validate` | — | ✅ | ✅ | — | — |
| `reports.validate_own_work` | — | ✅ | ❓ Q-S3 (✅) | — | — |
| `reports.correct_issued` | — | ✅ | ❓ Q-S4 (—) | — | — |
| `critical.communicate` | — | ✅ | ✅ | ❓ (—) | — |
| `batches.manage_own` | — | ✅ | ✅ | ✅ | — |
| `batches.transfer` / `batches.manage_any` | — | ✅ | ❓ (✅ transfer) | — | — |
| `config.catalogs.edit` (no clínicos) | ✅ | ✅ | — | — | — |
| `config.clinical.draft` | — | ✅ | ❓ (✅) | — | — |
| `config.clinical.approve` | — | ✅ solo el DT designado responsable (Q-A8) | — | — | — |
| `users.manage` | ✅ | — | — | — | — |
| `audit.view` | ✅ | ✅ | — | — | — |
| `statistics.view` | — | ✅ | ✅ | — | ✅ |

Notas:
- Los permisos se asignan **por sede** (ADR-0008). Cada sede tiene su Director técnico; un usuario puede tener sedes y roles distintos en cada una.
- `config.clinical.approve`: la configuración es compartida por las 3 sedes; la aprueba **un único Director técnico designado como responsable** (Q-A8). Es el único permiso global (no por sede); cambiar la designación queda auditado.
- El Administrador **no** tiene permisos clínicos por defecto (separación de funciones).
- Toda denegación se registra cuando ocurre en el backend (intento de acceso a algo no permitido).
