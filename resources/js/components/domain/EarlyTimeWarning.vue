<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { computed, useId } from 'vue';
import InputError from '@/components/InputError.vue';
import { formatDateTime } from '@/lib/datetime';
import { elapsedSince } from '@/lib/elapsed';

// Los tiempos del protocolo advierten pero no bloquean (D-0020): antes de tiempo se
// registra igual, tildando la confirmación y, si se quiere, explicando el motivo.
const props = defineProps<{
    availableAt: string;
    error?: string;
}>();

const confirmed = defineModel<boolean>('confirmed', { required: true });
const reason = defineModel<string>('reason', { required: true });

const id = useId();
const remaining = computed(() =>
    elapsedSince(new Date().toISOString(), new Date(props.availableAt)),
);
</script>

<template>
    <div
        class="grid gap-2 rounded-md border border-amber-500/50 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        data-test="early-warning"
    >
        <p class="flex items-start gap-2 font-medium">
            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
            OJO: todavía no se cumplió el tiempo de incubación del protocolo.
        </p>
        <p>
            Corresponde desde {{ formatDateTime(props.availableAt) }} (faltan
            {{ remaining }}).
        </p>
        <label class="flex w-fit items-center gap-2 font-medium">
            <input
                v-model="confirmed"
                type="checkbox"
                class="size-4"
                data-test="early-confirm"
            />
            Registrar igual, antes de tiempo
        </label>
        <div v-if="confirmed" class="grid gap-1">
            <label :for="`${id}-reason`">Motivo (opcional)</label>
            <textarea
                :id="`${id}-reason`"
                v-model="reason"
                rows="2"
                maxlength="500"
                placeholder="Por ejemplo: la hora de siembra se cargó tarde."
                class="rounded-md border bg-background px-3 py-2 text-sm text-foreground"
            />
        </div>
        <InputError :message="props.error" />
    </div>
</template>
