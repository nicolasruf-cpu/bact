# DEPLOYMENT

## Alcance
- **Demo con datos sintéticos en un hosting compartido con cPanel y sin SSH.** Es lo que está documentado acá.
- **Producción con pacientes reales:** pendiente, en la Fase 6 (ver ROADMAP.md y Q-T2). No se usa un hosting compartido para datos de salud.

## Requisitos del hosting
| Requisito | Verificado en Sitios Hispanos (2026-11-05) |
|---|---|
| PHP 8.5 con `pdo_mysql`, `mbstring`, `intl`, `bcmath`, `fileinfo`, `zip`, `openssl`, `tokenizer`, `ctype`, `xml`, `sodium` | PHP 8.5 disponible; las extensiones se revisan en «Select PHP Version» |
| MariaDB 10.6 o superior | 10.6.28 |
| Permiso para crear triggers (auditoría inmutable) | Sí (probado en phpMyAdmin) |
| Cron cada minuto | cPanel → Cron Jobs |
| Subdominio con la carpeta pública apuntando a `public/` | cPanel → Domains |
| HTTPS | AutoSSL |

## Cómo funciona sin SSH
- El paquete se arma en la máquina de desarrollo con `vendor/` y el frontend compilado: el hosting no necesita Composer ni Node.
- `deploy/cron.sh` se programa cada minuto. Busca PHP 8.5 y:
  - si el código subido trae una versión nueva (`deploy/VERSION`), corre las migraciones; en la primera instalación también los datos iniciales;
  - si no, corre el planificador (`schedule:run`), que libera las actividades a su hora y verifica la auditoría a diario.
- Lo que pasa en cada instalación queda en `storage/logs/instalacion.log`.
- Si una instalación falla, no se reintenta sola: se corrige el problema y se borra `storage/instalacion-fallida`.
- No hay cola de trabajos que atender: hoy ningún proceso usa la cola.

## Armar el paquete (máquina de desarrollo)
```
./sail composer test
./sail npm run build
python deploy/package.py
```
Deja `bactruf-<fecha>-<commit>.zip` en `../BactRuf-paquetes/`. Incluye `vendor/` con las dependencias de desarrollo, porque la demo usa Faker para crear sus usuarios. No incluye el `.env`, las pruebas ni el contenido de `storage/`.

## Primera instalación (cPanel)
1. **PHP:** en «Select PHP Version», elegir 8.5 y activar las extensiones de la tabla.
2. **Base de datos:** en «MySQL Databases», crear una base nueva y vacía (por ejemplo `cuenta_bactruf`) y un usuario con todos los privilegios sobre ella. No usar la base de otra aplicación.
3. **Archivos:** en el Administrador de archivos, crear `/home/<cuenta>/bactruf` (fuera de `public_html`), subir el `.zip` ahí y usar «Extract».
4. **.env:** copiar `deploy/env.hosting.example` como `.env` en `/home/<cuenta>/bactruf` y completar `APP_KEY`, `APP_URL`, los datos de la base y `DEMO_PASSWORD`. La `APP_KEY` se genera en desarrollo con `./sail artisan key:generate --show`. Para ver el `.env` en el Administrador de archivos hay que activar «Show Hidden Files».
5. **Subdominio:** en «Domains», crear por ejemplo `bactruf.tudominio.com` con la raíz del documento `/home/<cuenta>/bactruf/public`. Nunca la carpeta del proyecto: quedarían expuestos el `.env` y el código. Activar AutoSSL.
6. **Cron:** en «Cron Jobs», cada minuto (`* * * * *`):
   ```
   /bin/sh /home/<cuenta>/bactruf/deploy/cron.sh
   ```
7. A los dos o tres minutos, revisar `storage/logs/instalacion.log`: tiene que terminar en `=== Listo`. Después entrar al subdominio con `admin@bactruf.test` y la `DEMO_PASSWORD`.

## Actualizar
1. Armar un paquete nuevo.
2. Subirlo a `/home/<cuenta>/bactruf` y extraerlo encima, aceptando reemplazar los archivos. El `.env` y `storage/` no se tocan.
3. En el minuto siguiente el cron corre las migraciones. Revisar `storage/logs/instalacion.log`.
4. Si la versión trae datos de referencia nuevos (lo dice el CHANGELOG), no se cargan solos: los nuevos catálogos se agregan desde Administración, o se reinstala la demo desde cero.

## Reinstalar la demo desde cero
1. En phpMyAdmin, borrar todas las tablas de la base de BactRuf (solo de esa base).
2. En el Administrador de archivos, borrar `storage/version` y, si existe, `storage/instalacion-fallida`.
3. En el minuto siguiente, el cron instala todo de nuevo.
