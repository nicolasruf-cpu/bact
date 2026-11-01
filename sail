#!/usr/bin/env bash
# Atajo para trabajar con los contenedores desde Git Bash en Windows
# (el script oficial vendor/bin/sail no soporta MINGW). Ver docs/DEVELOPMENT.md.
set -e
export MSYS_NO_PATHCONV=1
cd "$(dirname "$0")"
SVC=laravel.test
EXEC=(docker compose exec -u sail)
[ -t 0 ] && [ -t 1 ] || EXEC+=(-T)

case "${1:-}" in
  up)       shift; docker compose up -d "$@" ;;
  down)     shift; docker compose down "$@" ;;
  build)    shift; docker compose build "$@" ;;
  ps)       docker compose ps ;;
  logs)     shift; docker compose logs -f "$@" ;;
  artisan|art) shift; "${EXEC[@]}" "$SVC" php artisan "$@" ;;
  composer) shift; "${EXEC[@]}" "$SVC" composer "$@" ;;
  npm)      shift; "${EXEC[@]}" "$SVC" npm "$@" ;;
  npx)      shift; "${EXEC[@]}" "$SVC" npx "$@" ;;
  php)      shift; "${EXEC[@]}" "$SVC" php "$@" ;;
  test)     shift; "${EXEC[@]}" "$SVC" php artisan test "$@" ;;
  shell|bash) "${EXEC[@]}" "$SVC" bash ;;
  mariadb|db) docker compose exec mariadb mariadb -usail -ppassword bactruf ;;
  *) echo "Uso: ./sail {up|down|build|ps|logs|artisan|composer|npm|npx|php|test|shell|db} [args]"; exit 1 ;;
esac
