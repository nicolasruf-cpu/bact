<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';
import { elapsedSince } from '@/lib/elapsed';
import { show as showBloodCulture } from '@/routes/blood-cultures';

// Resultados críticos sin comunicar (UX_UI.md): barra persistente hasta que se comunican.
const page = usePage();
const alerts = computed(() => page.props.criticalAlerts ?? []);
</script>

<template>
    <div
        v-if="alerts.length"
        class="flex flex-wrap items-center gap-2 border-b border-red-700 bg-red-600 px-4 py-2 text-sm text-white"
        role="alert"
        data-test="critical-alerts"
    >
        <span class="flex items-center gap-1 font-semibold">
            <AlertTriangle class="size-4" />
            {{ alerts.length }}
            {{
                alerts.length === 1
                    ? 'resultado crítico sin comunicar'
                    : 'resultados críticos sin comunicar'
            }}:
        </span>
        <Link
            v-for="alert in alerts"
            :key="alert.ulid"
            :href="showBloodCulture(alert.sample_study_id)"
            class="flex items-center gap-1 rounded-md bg-white/15 px-2 py-1 hover:bg-white/25"
        >
            {{ alert.summary }}
            <span class="text-xs opacity-80"
                >· hace {{ elapsedSince(alert.opened_at) }}</span
            >
            <span v-if="alert.attempts" class="text-xs opacity-80"
                >· {{ alert.attempts }} intento(s)</span
            >
            <span class="font-semibold underline">Atender</span>
        </Link>
    </div>
</template>
