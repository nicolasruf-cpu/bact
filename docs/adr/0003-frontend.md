# ADR-0003 — Frontend: Inertia 3 + Vue 3 + TypeScript + Tailwind 4 + shadcn-vue
**Estado:** Aceptada (2026-10-30) · **Fecha:** 2026-10-30

**Decisión:** SPA "monolítica" con Inertia: las rutas, la autorización y la validación viven en Laravel, sin API REST duplicada. Vue 3 (Composition API) + TypeScript. Tailwind 4 con *design tokens*. Componentes **shadcn-vue**: el código se copia al repo y se personaliza, y se apoya en **Reka UI** (primitivas accesibles: foco, ARIA, teclado). Iconos Lucide. Tablas con TanStack Table (virtualización para listas largas).
**Por qué:** máxima productividad con Laravel; una única fuente de verdad en el servidor; componentes accesibles que no imponen un aspecto de "panel administrativo genérico", porque el código es nuestro y se adapta a la identidad visual.
**Alternativas:** Livewire (menos adecuado para interacciones ricas de teclado y lector en tandas largas); SPA + API separada (duplica validación y autorización); PrimeVue/Vuetify (más rápido al inicio, pero con estética genérica y personalización costosa).
