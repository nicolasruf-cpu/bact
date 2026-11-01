# Desarrollo

## Requisitos
- Windows 10/11 con **Docker Desktop** (motor WSL2) y **Git for Windows** (Git Bash).
- No hace falta PHP, Composer, Node ni MariaDB instalados en Windows: todo corre en contenedores (D-0009).

## Contenedores (`compose.yaml`, basado en Laravel Sail)
| Servicio | Imagen | Puerto en Windows |
|---|---|---|
| `laravel.test` (app) | `sail-8.5/app` (PHP 8.5, Composer, Node) | http://localhost:8080 · Vite 5173 |
| `mariadb` | `mariadb:12.3` (utf8mb4) | 3307 (Laragon ocupa 80 y 3306) |

Sin Redis: colas, caché y sesiones usan MariaDB (D-0010).

## Atajo `./sail`
El script oficial `vendor/bin/sail` no funciona en Git Bash, así que el repo trae `./sail`, que llama a `docker compose`:

```bash
./sail up                      # levantar contenedores
./sail down                    # detenerlos
./sail artisan migrate:fresh --seed   # base limpia + datos de demostración
./sail composer test           # Pint (estilo) + Larastan (tipos) + Pest (pruebas)
./sail composer lint           # corregir estilo PHP
./sail test --filter=ActiveSite      # pruebas puntuales
./sail npm run dev             # Vite con recarga en caliente
./sail npm run build           # compilar el frontend
./sail npm run types:check     # vue-tsc
./sail npm run check           # formato + lint del frontend (check:fix corrige)
./sail shell                   # bash dentro del contenedor
./sail db                      # consola de MariaDB
```

## Primera instalación (PC nueva)
```bash
cp .env.example .env
./sail up --build              # la primera vez tarda (construye la imagen)
./sail composer install
./sail artisan key:generate
./sail artisan migrate --seed
./sail npm ci && ./sail npm run build
```
Si aparecen errores de permisos en `storage/` o `node_modules/`:
`MSYS_NO_PATHCONV=1 docker compose exec laravel.test chown -R sail:sail /var/www/html`.

## Usuarios de demostración (sintéticos, contraseña `password`)
| Email | Rol | Sedes |
|---|---|---|
| admin@bactruf.test | Administrador | las 3 |
| director.central@bactruf.test | Director técnico | Central |
| director.norte@bactruf.test | Director técnico | Norte |
| director.sur@bactruf.test | Director técnico | Sur |
| bioquimica@bactruf.test | Bioquímico | Central y Norte |
| auxiliar@bactruf.test | Auxiliar | Central |
| consulta@bactruf.test | Consulta | Central |

`DemoSeeder` nunca corre en producción.

## Estructura
- Módulos en `app/Modules/<Modulo>/` (ver ARCHITECTURE.md). Hoy: `Organization` (sedes) e `Identity` (roles, permisos, sede activa).
- Tests: `tests/Arch` (límites entre módulos), `tests/Feature`, `tests/Unit`. Las pruebas corren contra MariaDB (base `testing`).
- Rutas tipadas para el frontend: Laravel Wayfinder (`resources/js/routes`, `resources/js/actions`; se regeneran solos).

## Nota sobre el editor
VS Code puede marcar errores de sintaxis PHP falsos si usa el PHP 7.1 de Laragon. Configurá `php.validate.executablePath` con un PHP 8.5, o desactivá esa validación: la verificación real la hacen Larastan y Pest en el contenedor.
