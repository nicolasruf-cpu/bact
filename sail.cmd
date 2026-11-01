@echo off
rem Atajo para trabajar con los contenedores desde PowerShell o cmd
rem (mismo uso que ./sail en Git Bash). Ver docs/DEVELOPMENT.md.
setlocal
cd /d "%~dp0"
set "EXEC=docker compose exec -u sail laravel.test"

if "%~1"=="up"       ( docker compose %* -d & exit /b )
if "%~1"=="down"     ( docker compose %* & exit /b )
if "%~1"=="build"    ( docker compose %* & exit /b )
if "%~1"=="ps"       ( docker compose ps & exit /b )
if "%~1"=="logs"     ( docker compose %* -f & exit /b )
if "%~1"=="artisan"  ( %EXEC% php %* & exit /b )
if "%~1"=="test"     ( %EXEC% php artisan %* & exit /b )
if "%~1"=="composer" ( %EXEC% %* & exit /b )
if "%~1"=="npm"      ( %EXEC% %* & exit /b )
if "%~1"=="npx"      ( %EXEC% %* & exit /b )
if "%~1"=="php"      ( %EXEC% %* & exit /b )
if "%~1"=="shell"    ( %EXEC% bash & exit /b )
if "%~1"=="db"       ( docker compose exec mariadb mariadb -usail -ppassword bactruf & exit /b )

echo Uso: sail {up^|down^|build^|ps^|logs^|artisan^|composer^|npm^|npx^|php^|test^|shell^|db} [args]
exit /b 1
