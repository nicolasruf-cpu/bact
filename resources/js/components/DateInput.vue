<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { localDateInput } from '@/lib/datetime';

// Fecha con la de hoy a un clic (UX_UI.md).
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
            type="date"
            :readonly="props.readonly"
            :disabled="props.disabled"
        />
        <Button
            type="button"
            variant="outline"
            :disabled="props.readonly || props.disabled"
            data-test="date-today"
            @click="model = localDateInput()"
            >Hoy</Button
        >
    </div>
</template>
