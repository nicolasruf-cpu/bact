<script setup lang="ts">
// S/I/R con letra visible además del color (UX_UI.md: el color nunca es el único indicador).
defineProps<{ value: string | null | undefined }>();
</script>

<template>
    <span
        v-if="value"
        class="inline-flex size-7 items-center justify-center rounded-md text-sm font-bold"
        :class="{
            'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300':
                value === 'S',
            'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300':
                value === 'I',
            'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300':
                value === 'R',
        }"
        :aria-label="
            value === 'S'
                ? 'Sensible'
                : value === 'I'
                  ? 'Intermedio'
                  : 'Resistente'
        "
        >{{ value }}</span
    >
    <span v-else class="text-muted-foreground">—</span>
</template>
