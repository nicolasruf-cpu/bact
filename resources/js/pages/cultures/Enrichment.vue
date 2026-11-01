<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { FlaskConical } from '@lucide/vue';
import SampleHeaderCard from '@/components/domain/SampleHeaderCard.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/datetime';
import { store as storeEnrichment } from '@/routes/enrichments';
import type { SampleHeader } from '@/types/clinical';

type Broth = {
    id: number;
    medium: string;
    status: string;
    available_at: string;
    available: boolean;
    targets: string[];
    subcultures: { medium: string; inoculated_at: string | null }[];
};

const props = defineProps<{
    sample: SampleHeader;
    culture: { ulid: string; status_label: string; inoculated_at: string };
    broths: Broth[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Mesada de procesamiento',
                href: '/workbenches/processing',
            },
            { title: 'Repique de caldo', href: '#' },
        ],
    },
});

const form = useForm({});

function subculture(broth: Broth) {
    form.post(storeEnrichment(broth.id).url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Repique de caldo ${props.sample.barcode}`" />

    <div class="grid gap-4 p-4">
        <SampleHeaderCard :sample="props.sample" />

        <section class="grid gap-3 rounded-lg border p-4">
            <h1 class="flex items-center gap-2 text-xl font-semibold">
                <FlaskConical class="size-5" /> Caldos de enriquecimiento
            </h1>
            <p class="text-sm text-muted-foreground">
                Sembrado {{ formatDateTime(props.culture.inoculated_at) }} ·
                {{ props.culture.status_label }}. Las placas del repique se leen
                en las lecturas programadas del cultivo.
            </p>

            <div
                v-for="broth in props.broths"
                :key="broth.id"
                class="grid gap-2 rounded-md border p-3"
                data-test="broth"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium">{{ broth.medium }}</span>
                    <Badge
                        :variant="
                            broth.status === 'incubating'
                                ? 'outline'
                                : 'secondary'
                        "
                        >{{
                            broth.status === 'incubating'
                                ? 'Sin repicar'
                                : 'Repicado'
                        }}</Badge
                    >
                </div>
                <template v-if="broth.subcultures.length">
                    <p
                        v-for="(plate, index) in broth.subcultures"
                        :key="index"
                        class="text-sm"
                    >
                        → {{ plate.medium }}
                        <span
                            v-if="plate.inoculated_at"
                            class="text-xs text-muted-foreground"
                            >({{ formatDateTime(plate.inoculated_at) }})</span
                        >
                    </p>
                </template>
                <template v-else-if="broth.status === 'incubating'">
                    <p class="text-sm">
                        Repicar a: {{ broth.targets.join(', ') }}
                    </p>
                    <p
                        v-if="!broth.available"
                        class="text-sm text-amber-700 dark:text-amber-300"
                    >
                        Disponible desde
                        {{ formatDateTime(broth.available_at) }} (incubación del
                        caldo).
                    </p>
                    <Button
                        class="w-fit"
                        :disabled="!broth.available || form.processing"
                        data-test="subculture-broth"
                        @click="subculture(broth)"
                    >
                        <Spinner v-if="form.processing" /> Registrar repique
                    </Button>
                </template>
            </div>
            <InputError
                :message="(form.errors as Record<string, string>).plate"
            />
        </section>
    </div>
</template>
