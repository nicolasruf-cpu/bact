<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { localDateTimeInput } from '@/lib/datetime';

// Fecha y hora con "ahora" a un clic (UX_UI.md).
const props = defineProps<{
    id?: string;
    readonly?: boolean;
    disabled?: boolean;
}>();

const model = defineModel<string | null>({ required: true });
</script>

<template>
    <div class="flex gap-2">
        <Input
            :id="props.id"
            :model-value="model ?? ''"
            @update:model-value="model = String($event)"
            type="datetime-local"
            :readonly="props.readonly"
            :disabled="props.disabled"
        />
        <Button
            type="button"
            variant="outline"
            :disabled="props.readonly || props.disabled"
            data-test="datetime-now"
            @click="model = localDateTimeInput()"
            >Ahora</Button
        >
    </div>
</template>
