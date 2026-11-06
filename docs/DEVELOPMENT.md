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
El script oficial `vendor/bin/sail` no funciona en Git Bash, así que el repo trae `./sail`, que llama a `docker compose`. Desde PowerShell o cmd, el mismo `./sail` ejecuta `sail.cmd` (equivalente):

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
./sail artisan schedule:work   # tareas programadas (libera lecturas a su hora)
./sail artisan demo:advance 18 # SOLO DEMO: adelanta 18 h las actividades programadas
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

## Usuarios de demostración (sintéticos, contraseña `DEMO_PASSWORD` del `.env` o, si no está, `password`)
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

## Recorrer el hemocultivo en la demo
1. `auxiliar@bactruf.test` → **Recepción**: una orden con dos muestras "Sangre periférica · Hemocultivo" y una "Sangre por catéter · Retrocultivo", cada una con su código.
2. **Hemocultivos** → abrí cada "Carga de frasco" y elegí "Equipo automatizado" (o "Manual").
3. Equipo: en "Frascos en incubación", abrí un frasco → "Registrar positivo" (con la hora que informa el equipo).
4. `bioquimica@bactruf.test` → Gram → se abre la barra roja del resultado crítico → registrá la comunicación → "Emitir informe preliminar".
5. "Registrar subcultivo" → `./sail artisan demo:advance 18` → Lectura → Identificación → Antibiogramas (`demo:advance 16`) → Validación.
6. Negativo: `./sail artisan demo:advance 120` (5 días) → "Descarga de frasco negativo" en la mesada → Validación.
7. Manual: `demo:advance 24` → "Registrar repique" (Auxiliar) → `demo:advance 18` → lectura del repique (Bioquímica).

## Casos publicados
La demo trae 12 escenarios basados en casos publicados (códigos `PUB-01` a `PUB-12`) en distintas etapas.
- La ficha de cada uno está en `docs/demo/CASOS_PUBLICADOS.md`.
- Para cargarlos sobre una demo existente: `./sail artisan db:seed --class=PublishedCaseSeeder`.

## Administración
- `admin@bactruf.test`: usuarios y catálogos.
- `bioquimica@bactruf.test`: prepara borradores de protocolos y puntos de corte.
- `director.central@bactruf.test`: es el responsable de la configuración clínica; aprueba y pone vigente.

## Recorrer el resto de los cultivos en la demo
1. **Recepción:** una muestra "Líquido cefalorraquídeo · Cultivo de LCR" y otra "Materia fecal · Coprocultivo".
2. `bioquimica@bactruf.test` → **Procesamiento**: el directo del LCR aparece como inmediato.
   - Elegí "Diplococos gram negativos": se abre la barra roja.
   - Registrá la comunicación.
3. **Siembra** de las dos muestras. Después, en la pantalla del directo, "Emitir informe preliminar".
4. `./sail artisan demo:advance 18` → **Procesamiento** → "Repique de caldo" del coprocultivo (también lo puede hacer el Auxiliar).
5. **Lectura** de las 24 h "Sin desarrollo": queda programada la de las 48 h.
6. `demo:advance 24` → lectura de las 48 h. En el coprocultivo, las placas del caldo necesitan 18 h desde el repique: si la lectura todavía no está disponible, `demo:advance 18`. Después, Validación.
7. Herida: con "Desarrollo" a las 24 h, la lectura de las 48 h pide "desarrollo nuevo" o "sin desarrollo nuevo".

## Estructura
- Módulos en `app/Modules/<Modulo>/` (ver ARCHITECTURE.md). Hoy: `Organization` (sedes) e `Identity` (roles, permisos, sede activa).
- Tests: `tests/Arch` (límites entre módulos), `tests/Feature`, `tests/Unit`. Las pruebas corren contra MariaDB (base `testing`).
- Rutas tipadas para el frontend: Laravel Wayfinder (`resources/js/routes`, `resources/js/actions`; se regeneran solos).

## Nota sobre el editor
VS Code puede marcar errores de sintaxis PHP falsos si usa el PHP 7.1 de Laragon. Configurá `php.validate.executablePath` con un PHP 8.5, o desactivá esa validación: la verificación real la hacen Larastan y Pest en el contenedor.
