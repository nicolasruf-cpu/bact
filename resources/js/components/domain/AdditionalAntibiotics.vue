<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type AdditionalRow = {
    antibiotic_id: number;
    method: string;
    reason: string;
};

// Antibióticos por fuera del panel, cada uno con su método (D-0022).
const props = defineProps<{
    antibiotics: { id: number; code: string; name: string }[];
    methods: { value: string; label: string }[];
    /** Los que ya están en el antibiograma o en el panel elegido. */
    exclude: number[];
    hint: string;
    error?: string;
}>();

const rows = defineModel<AdditionalRow[]>({ required: true });
const toAdd = ref<number | ''>('');

const available = computed(() =>
    props.antibiotics.filter(
        (a) =>
            !props.exclude.includes(a.id) &&
            !rows.value.some((r) => r.antibiotic_id === a.id),
    ),
);
const name = (id: number) => {
    const a = props.antibiotics.find((x) => x.id === id);

    return a ? `${a.code} — ${a.name}` : String(id);
};

function add() {
    if (toAdd.value !== '') {
        rows.value.push({
            antibiotic_id: toAdd.value,
            method: 'disk',
            reason: '',
        });
        toAdd.value = '';
    }
}
</script>

<template>
    <fieldset class="grid gap-2 rounded-md border border-dashed p-3">
        <legend class="px-1 text-sm font-medium">
            Antibióticos por fuera del panel
        </legend>
        <p class="text-xs text-muted-foreground">{{ hint }}</p>
        <div
            v-for="(row, i) in rows"
            :key="row.antibiotic_id"
            class="grid items-center gap-2 text-sm sm:grid-cols-[1fr_12rem_1fr_auto]"
            data-test="additional-row"
        >
            <span>{{ name(row.antibiotic_id) }}</span>
            <select
                v-model="row.method"
                class="h-9 rounded-md border bg-background px-2 text-sm"
                :aria-label="`Método ${name(row.antibiotic_id)}`"
            >
                <option v-for="m in methods" :key="m.value" :value="m.value">
                    {{ m.label }}
                </option>
            </select>
            <Input v-model="row.reason" placeholder="Motivo (opcional)" />
            <Button
                type="button"
                size="icon"
                variant="ghost"
                aria-label="Quitar"
                @click="rows.splice(i, 1)"
                ><X class="size-4"
            /></Button>
        </div>
        <div class="flex items-center gap-2">
            <Plus class="size-4 text-muted-foreground" />
            <select
                v-model="toAdd"
                class="h-9 max-w-sm flex-1 rounded-md border bg-background px-2 text-sm"
                data-test="add-additional"
                @change="add"
            >
                <option value="">Agregar antibiótico…</option>
                <option v-for="a in available" :key="a.id" :value="a.id">
                    {{ a.code }} — {{ a.name }}
                </option>
            </select>
        </div>
        <InputError :message="error" />
    </fieldset>
</template>
