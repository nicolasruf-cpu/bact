# Seguridad (lineamientos iniciales)

## Autenticación
- Laravel Fortify: inicio de sesión, políticas de contraseña, bloqueo por intentos fallidos, 2FA (TOTP) opcional por rol (se recomienda obligatorio para Administrador y Director técnico).
- Sesiones en BD (D-0010), cookies `Secure`, `HttpOnly`, `SameSite=Lax`; expiración por inactividad configurable (equipos compartidos en el laboratorio).
- **Firma de validación**: al validar un informe se reingresa la contraseña o PIN (a definir, ver OPEN_QUESTIONS Q-S2).

## Autorización
- `spatie/laravel-permission`: permisos granulares (`samples.receive`, `cultures.read`, `ast.interpret.override`, `reports.validate`, `reports.correct`, `config.clinical.approve`…), agrupados en roles: Administrador, Director técnico, Bioquímico, Auxiliar, Consulta.
- Policies por entidad; el backend verifica **siempre**, aunque la UI ya oculte la acción.
- Separación de funciones: el Administrador gestiona usuarios y sistema, pero no valida resultados salvo que además tenga un rol clínico.

## Protección de la aplicación
CSRF (Laravel), escape automático de Vue/Blade (XSS), Eloquent/consultas parametrizadas (inyección SQL), validación del lado del servidor con Form Requests, *rate limiting*, cabeceras de seguridad (CSP, HSTS, X-Frame-Options), HTTPS obligatorio, auditoría de dependencias (`composer audit`, `npm audit`) en CI.

## Auditoría
- Tabla `audit_events` **append-only**: usuario, fecha y hora, acción, entidad, valores anteriores y nuevos (JSON), motivo, IP, tanda o actividad de contexto, `request_id`.
- El usuario de base de datos de la aplicación no tiene `UPDATE`/`DELETE` sobre esa tabla.
- Encadenamiento por hash (cada evento incluye el hash del anterior) para detectar alteraciones. Ver ADR-0006.
- Las acciones clínicas que modifican algo ya registrado exigen motivo.

## Datos sensibles y normativa
Datos de salud: mínimo necesario, acceso por rol, registro de accesos a fichas de paciente. Hay que revisar con el laboratorio el marco aplicable (p. ej., Ley 25.326 de Protección de Datos Personales, Ley 26.529 de Derechos del Paciente, Ley 25.506 de Firma Digital). **Esto no constituye asesoramiento legal.**

## Respaldo
Ver BACKUP_RECOVERY.md (Fase 6): respaldos cifrados, copia fuera del servidor, pruebas de restauración periódicas.
