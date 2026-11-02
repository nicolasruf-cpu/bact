<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue';

/** Sección del editor de protocolos: título, para qué sirve y lo que falta. */
defineProps<{
    id: string;
    step?: number;
    title: string;
    description?: string;
    missing?: string[];
    errors?: string[];
}>();
</script>

<template>
    <section
        :id="`section-${id}`"
        class="grid scroll-mt-20 gap-3 rounded-lg border p-4"
        :class="{
            'border-red-500/60': errors?.length,
            'border-amber-500/60': !errors?.length && missing?.length,
        }"
    >
        <header class="grid gap-1">
            <h2 class="flex items-center gap-2 font-medium">
                <span
                    v-if="step"
                    class="flex size-6 items-center justify-center rounded-full bg-muted text-xs"
                    >{{ step }}</span
                >
                {{ title }}
            </h2>
            <p
                v-if="description"
                class="max-w-3xl text-sm text-muted-foreground"
            >
                {{ description }}
            </p>
        </header>
        <ul
            v-if="missing?.length"
            class="grid gap-1 rounded-md bg-amber-50 p-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        >
            <li v-for="m in missing" :key="m" class="flex items-start gap-2">
                <AlertTriangle class="mt-0.5 size-4 shrink-0" /> {{ m }}
            </li>
        </ul>
        <ul
            v-if="errors?.length"
            class="grid gap-1 rounded-md bg-red-50 p-2 text-sm text-red-900 dark:bg-red-950/40 dark:text-red-200"
        >
            <li v-for="e in errors" :key="e">{{ e }}</li>
        </ul>
        <slot />
    </section>
</template>
