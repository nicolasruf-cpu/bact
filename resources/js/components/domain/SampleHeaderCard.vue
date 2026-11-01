<script setup lang="ts">
import PriorityBadge from '@/components/domain/PriorityBadge.vue';
import ReferenceTemplateNotice from '@/components/domain/ReferenceTemplateNotice.vue';
import { formatDateTime } from '@/lib/datetime';
import type { SampleHeader } from '@/types/clinical';

// Identidad de la muestra, fija arriba en cada pantalla de trabajo (UX_UI.md).
defineProps<{ sample: SampleHeader }>();
</script>

<template>
    <div class="grid gap-2">
        <div class="grid gap-1 rounded-lg border bg-muted/30 p-3">
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="font-mono text-2xl font-semibold"
                    data-test="sample-barcode"
                    >{{ sample.barcode }}</span
                >
                <PriorityBadge
                    :priority="sample.priority"
                    :label="sample.priority_label"
                />
                <span class="text-lg">{{ sample.patient }}</span>
                <span class="text-sm text-muted-foreground"
                    >DNI {{ sample.document_number }}</span
                >
            </div>
            <div class="text-sm text-muted-foreground">
                {{ sample.sample_type }} · {{ sample.study }} ·
                {{ sample.protocol }} v{{ sample.protocol_version }}
                <template v-if="sample.order_number">
                    · Orden
                    <span class="font-mono">{{
                        sample.order_number
                    }}</span></template
                >
                <template v-if="sample.physician">
                    · {{ sample.physician }}</template
                >
                · Recibida {{ formatDateTime(sample.received_at) }}
            </div>
        </div>
        <ReferenceTemplateNotice v-if="sample.is_reference_template" />
    </div>
</template>
