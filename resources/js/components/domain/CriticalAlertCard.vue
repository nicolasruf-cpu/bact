<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { AlertTriangle, Phone } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/datetime';
import { uuid } from '@/lib/uuid';
import { store as storeCommunication } from '@/routes/critical-alerts/communications';
import type { CriticalAlert } from '@/types/clinical';

// Resultado crítico de un estudio: historial de intentos y registro de la comunicación.
const props = defineProps<{
    alert: CriticalAlert;
    channels: { value: string; label: string }[];
    canCommunicate: boolean;
}>();

const communicationForm = useForm({
    idempotency_key: uuid(),
    recipient_name: '',
    recipient_role: '',
    channel: props.channels[0]?.value ?? 'phone',
    successful: true,
    read_back_confirmed: false,
    notes: '',
});

function submit() {
    communicationForm.post(storeCommunication(props.alert.ulid).url, {
        preserveScroll: true,
        onSuccess: () => {
            communicationForm.reset();
            communicationForm.idempotency_key = uuid();
        },
    });
}
</script>

<template>
    <section
        class="grid gap-3 rounded-lg border p-4"
        :class="
            props.alert.status === 'open'
                ? 'border-red-500 bg-red-50/60 dark:bg-red-950/30'
                : ''
        "
        data-test="critical-alert"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="flex items-center gap-2 font-semibold">
                <AlertTriangle class="size-4" /> Resultado crítico
            </h2>
            <Badge
                :variant="
                    props.alert.status === 'open' ? 'destructive' : 'secondary'
                "
                >{{ props.alert.status_label }}</Badge
            >
        </div>
        <p class="text-sm">{{ props.alert.summary }}</p>

        <ul v-if="props.alert.communications.length" class="grid gap-1 text-sm">
            <li
                v-for="(c, index) in props.alert.communications"
                :key="index"
                class="rounded-md border bg-background p-2"
            >
                <span class="font-medium">{{
                    c.successful ? 'Comunicado' : 'Sin respuesta'
                }}</span>
                a {{ c.recipient_name }}
                <template v-if="c.recipient_role">
                    ({{ c.recipient_role }})</template
                >
                · {{ c.channel }}
                <template v-if="c.read_back_confirmed">
                    · relectura confirmada</template
                >
                <div class="text-xs text-muted-foreground">
                    {{ formatDateTime(c.communicated_at) }} ·
                    {{ c.communicated_by }}
                    <template v-if="c.notes"> · {{ c.notes }}</template>
                </div>
            </li>
        </ul>

        <form
            v-if="props.alert.status === 'open' && props.canCommunicate"
            class="grid gap-3"
            @submit.prevent="submit"
        >
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="grid gap-1">
                    <Label for="recipient_name">A quién se comunicó</Label>
                    <Input
                        id="recipient_name"
                        v-model="communicationForm.recipient_name"
                        placeholder="Nombre"
                        data-test="recipient-name"
                    />
                    <InputError
                        :message="communicationForm.errors.recipient_name"
                    />
                </div>
                <div class="grid gap-1">
                    <Label for="recipient_role">Rol o servicio</Label>
                    <Input
                        id="recipient_role"
                        v-model="communicationForm.recipient_role"
                        placeholder="p. ej., médico de guardia"
                    />
                </div>
                <div class="grid gap-1">
                    <Label for="channel">Medio</Label>
                    <select
                        id="channel"
                        v-model="communicationForm.channel"
                        class="h-9 rounded-md border bg-background px-2 text-sm"
                    >
                        <option
                            v-for="channel in props.channels"
                            :key="channel.value"
                            :value="channel.value"
                        >
                            {{ channel.label }}
                        </option>
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="flex items-center gap-2">
                    <input
                        v-model="communicationForm.successful"
                        type="checkbox"
                        class="size-4"
                    />
                    Se pudo comunicar
                </label>
                <label class="flex items-center gap-2">
                    <input
                        v-model="communicationForm.read_back_confirmed"
                        type="checkbox"
                        class="size-4"
                    />
                    El receptor repitió el resultado (relectura)
                </label>
            </div>
            <div class="grid gap-1">
                <Label for="communication_notes">Observaciones</Label>
                <Input
                    id="communication_notes"
                    v-model="communicationForm.notes"
                    placeholder="Opcional"
                />
            </div>
            <div class="flex justify-end">
                <Button
                    type="submit"
                    :variant="
                        communicationForm.successful ? 'default' : 'outline'
                    "
                    :disabled="communicationForm.processing"
                    data-test="save-communication"
                >
                    <Phone class="size-4" />
                    {{
                        communicationForm.successful
                            ? 'Registrar comunicación'
                            : 'Registrar intento fallido'
                    }}
                </Button>
            </div>
        </form>
    </section>
</template>
