# Estrategia de pruebas

| Nivel | Herramienta | Alcance |
|---|---|---|
| Unitarias | Pest | Servicios de dominio puros: interpretación de puntos de corte, transiciones de estado, políticas de flujo, reglas críticas |
| Integración/funcionales | Pest + MariaDB real (no SQLite: hacen falta columnas generadas, bloqueos y FKs reales) | Actions completas con transacción, auditoría y actividades generadas |
| Arquitectura | Pest `arch()` | Límites entre módulos, controladores sin lógica, sin dependencias prohibidas |
| Frontend | Vitest + Vue Test Utils | Composables (lector de barras, atajos), componentes del design system |
| Interfaz/E2E | Pest 4 browser testing (Playwright) | Flujos completos y accesibilidad (axe) |
| Seguridad | Pest | Permisos por rol para cada ruta, CSRF, accesos indebidos (IDOR) |
| Concurrencia | Pest con dos conexiones o procesos | Doble *claim*, doble envío, conflicto de `lock_version` |
| Estático | Larastan (nivel alto), Pint, `vue-tsc` | En CI |

## Escenarios de aceptación (datos 100 % sintéticos)
1. Urocultivo negativo · 2. Urocultivo positivo · 3. Cultivo polimicrobiano · 4. Reincubación · 5. Identificación no concluyente · 6. Identificación VITEK · 7. Antibiograma discordante · 8. Resultado crítico · 9. Corrección de informe · 10. Tanda interrumpida · 11. Dos usuarios sobre la misma muestra · 12. Falla de integración · 13. Repetición de antibiograma · 14. Muestra rechazada · 15. Cambio de turno.

Cada escenario se implementa como prueba funcional y, en la Fase 5, como simulación de jornada (*seeder* de jornada con N muestras, M usuarios y urgencias).
