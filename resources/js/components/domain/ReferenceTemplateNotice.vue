<script setup lang="ts">
import { FlaskConicalOff } from '@lucide/vue';
</script>

<template>
    <div
        class="flex items-start gap-2 rounded-md border border-amber-500/50 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        role="note"
    >
        <FlaskConicalOff class="mt-0.5 size-4 shrink-0" />
        <p>
            <strong
                >Plantilla de referencia, no validada por el
                laboratorio.</strong
            >
            Solo para pruebas con datos sintéticos. No usar con pacientes reales
            hasta que el Director técnico responsable apruebe el protocolo.
        </p>
    </div>
</template>
