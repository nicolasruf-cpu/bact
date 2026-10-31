# Estrategia UX y sistema visual

## 1. Jornada del bacteriólogo (mapa simplificado)
```
Ingreso → Mesada (¿qué hay para hacer?) → elige actividad → arma/retoma tanda
   → procesa ítem por ítem (teclado + lector) → ↺ siguiente ítem automático
   → [interrupción: urgencia] → pausa → atiende → vuelve a la tanda
   → finaliza tanda → vuelve a la mesada (contadores actualizados)
   → fin de turno: traspaso (tandas abiertas, previsto para el siguiente turno)
```

## 2. Principios aplicados
| Principio | Cómo se materializa |
|---|---|
| Una pantalla, una actividad | Pantalla de tanda en dos paneles: lista compacta de ítems a la izquierda, formulario de la actividad a la derecha. Solo los datos necesarios. |
| Mínimos clics | Valores por defecto según el protocolo; "Guardar y siguiente" (Enter); confirmación múltiple solo cuando es segura. |
| Teclado | `Ctrl+K` paleta de comandos; `/` buscar; `J/K` navegar ítems; `Enter` guardar y avanzar; teclas numéricas para opciones de lectura; `Esc` pausa. Los atajos peligrosos (validar) siempre piden confirmación. |
| Código de barras | Escucha global de lector tipo teclado (ráfaga rápida + Enter): en la mesada abre la muestra; en una tanda la agrega o selecciona. |
| Trabajo continuo | Al guardar, el foco pasa al siguiente ítem pendiente; no se vuelve al menú. |
| Interrupciones | Barra superior persistente con urgencias; tandas pausadas siempre visibles; borradores autoguardados. |
| Prevención de errores | Tres verbos distintos con estilos distintos: **Registrar** (neutro), **Confirmar** (primario), **Validar** (acción destacada + reautenticación opcional). Identidad de la muestra (código + paciente + tipo) siempre visible en el formulario. |
| Retroalimentación | Toasts con resultado y "Deshacer" cuando es reversible; estados de carga sin bloquear la pantalla. |
| Accesibilidad | WCAG 2.2 AA: contraste ≥ 4.5:1, foco visible, objetivos ≥ 24px, el color nunca es el único indicador (icono + texto), compatible con lector de pantalla gracias a Reka UI. |

## 3. Pantalla principal (boceto)
```
┌───────────────────────────────────────────────────────────────────────────┐
│ BactRuf   [Buscar / escanear muestra…  Ctrl+K]        Turno mañana · Ana ▾│
├───────────────────────────────────────────────────────────────────────────┤
│ ⚠ 1 resultado crítico sin comunicar · Hemocultivo M-10234 · hace 8 min [Atender]│
├───────────────────────────────────────────────────────────────────────────┤
│ Mis tandas abiertas:  ⏸ Siembra SIE-03  12/30  [Reanudar]                 │
├──────────────────┬──────────────────┬──────────────────┬──────────────────┤
│ Siembra          │ Lectura          │ Identificación   │ VITEK            │
│ 42 pendientes    │ 87 pendientes    │ 15 pendientes    │ 9 aislamientos   │
│ 3 urgentes · 2 ⏱ │ 5 urgentes · 11 ⏱│ 1 urgente        │ casete: 0/10     │
│ [Iniciar tanda]  │ [Iniciar tanda]  │ [Abrir]          │ [Armar tanda]    │
├──────────────────┼──────────────────┼──────────────────┼──────────────────┤
│ Bioquímicas      │ ATB a preparar   │ ATB a leer       │ Validación       │
│ …                │ …                │ …                │ 23 · 4 críticos  │
├──────────────────┴──────────────────┴──────────────────┴──────────────────┤
│ Demoradas (14)  ·  Previsto próximo turno: 64 lecturas, 12 ATB             │
└───────────────────────────────────────────────────────────────────────────┘
```
Las tarjetas se ordenan según el rol y la urgencia; el usuario puede fijar sus tarjetas favoritas.

## 4. Sistema visual (propuesta)
- **Concepto:** "precisión clínica". Superficies limpias, jerarquía tipográfica clara, color solo con significado.
- **Tipografía:** Inter (UI, con cifras tabulares para recuentos y CIM) + JetBrains Mono (códigos de muestra y de barras).
- **Paleta base:** neutros fríos (slate) + primario **azul petróleo** (#0F6E7A aprox., a validar por contraste).
- **Colores semánticos** (siempre con icono y texto): Crítico = rojo · Urgente = ámbar · Demorado = naranja · En proceso = azul · Completado = verde · Programado = gris · Interpretación S/I/R con colores propios y letra visible.
- **Densidad:** modo "cómodo" y "compacto" (las tablas de antibiograma y las listas de tanda usan compacto por defecto).
- **Modo claro/oscuro** con *design tokens* (variables CSS de Tailwind 4).
- **Movimiento:** transiciones de 150–200 ms; se respeta `prefers-reduced-motion`.
- **Componentes:** shadcn-vue (código propio en el repo, sobre Reka UI accesible) + Lucide (iconos) + TanStack Table (tablas virtualizadas). Ver ADR-0003.
- El Design System se documenta en `docs/DESIGN_SYSTEM.md` y en una página interna de componentes (`/dev/design-system`), en la Fase 2.

## 5. Prototipos de la Fase 2
Pantalla principal, mesada y tanda de siembra, mesada y tanda de lectura, tanda VITEK, preparación y lectura de antibiograma (grilla con interpretación), bandeja de validación y flujo de resultado crítico. Los prototipos se hacen como páginas Vue navegables con datos sintéticos, para que el usuario los valide antes de conectar el backend.
