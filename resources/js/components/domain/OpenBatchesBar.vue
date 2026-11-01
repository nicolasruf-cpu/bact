<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Layers, Pause, Play } from '@lucide/vue';
import { computed } from 'vue';
import { show as showBatch } from '@/routes/batches';

// "Mis tandas abiertas" (UX_UI.md): siempre visibles para retomar después de una interrupción.
const page = usePage();
const batches = computed(() =>
    page.props.openBatches.filter(
        (b) => !page.url.startsWith(`/batches/${b.ulid}`),
    ),
);
</script>

<template>
    <div
        v-if="batches.length"
        class="flex flex-wrap items-center gap-2 border-b bg-muted/40 px-4 py-2 text-sm"
        data-test="open-batches"
    >
        <span class="flex items-center gap-1 font-medium">
            <Layers class="size-4" /> Mis tandas abiertas:
        </span>
        <Link
            v-for="batch in batches"
            :key="batch.ulid"
            :href="showBatch(batch.ulid)"
            class="flex items-center gap-1 rounded-md border bg-background px-2 py-1 hover:border-primary"
        >
            <Pause v-if="batch.status === 'paused'" class="size-3" />
            <Play v-else class="size-3" />
            <span class="font-mono">{{ batch.code }}</span>
            <span class="text-muted-foreground tabular-nums"
                >{{ batch.done }}/{{ batch.items }}</span
            >
            <span class="text-xs text-muted-foreground"
                >· {{ batch.status_label }}</span
            >
            <span class="font-medium text-primary">Retomar</span>
        </Link>
    </div>
</template>
