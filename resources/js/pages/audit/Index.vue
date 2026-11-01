<script setup lang="ts">
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import { ShieldAlert, ShieldCheck } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/datetime';
import { index as auditIndex } from '@/routes/audit';

type AuditRow = {
    id: number;
    occurred_at: string;
    action: string;
    action_label: string;
    user: string | null;
    site: string | null;
    subject: string | null;
    reason: string | null;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    context: Record<string, unknown> | null;
    ip_address: string | null;
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

const props = defineProps<{
    events: Paginated<AuditRow>;
    actions: Record<string, string>;
    filters: { action: string | null };
    chain?: { valid: boolean; checked: number; problem: string | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Auditoría', href: auditIndex() }],
    },
});

function filterBy(action: string) {
    router.get(auditIndex().url, action ? { action } : {}, {
        preserveState: true,
        preserveScroll: true,
    });
}

function details(row: AuditRow): string {
    const parts: string[] = [];
    if (row.before) parts.push(`antes: ${JSON.stringify(row.before)}`);
    if (row.after) parts.push(`después: ${JSON.stringify(row.after)}`);
    if (row.context) parts.push(`contexto: ${JSON.stringify(row.context)}`);

    return parts.join(' · ');
}

function pageLabel(label: string): string {
    return label
        .replace('&laquo; Previous', '« Anterior')
        .replace('Next &raquo;', 'Siguiente »');
}
</script>

<template>
    <Head title="Auditoría" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Auditoría</h1>
                <p class="text-sm text-muted-foreground">
                    Registro inalterable de las operaciones ({{
                        props.events.total
                    }}
                    eventos).
                </p>
            </div>

            <Deferred data="chain">
                <template #fallback>
                    <Badge variant="outline">Verificando la cadena…</Badge>
                </template>
                <Badge
                    v-if="props.chain?.valid"
                    variant="secondary"
                    class="gap-1"
                    data-test="chain-ok"
                >
                    <ShieldCheck class="size-4" />
                    Cadena íntegra ({{ props.chain.checked }} eventos)
                </Badge>
                <Badge v-else variant="destructive" class="gap-1">
                    <ShieldAlert class="size-4" />
                    Cadena alterada: {{ props.chain?.problem }}
                </Badge>
            </Deferred>
        </div>

        <label class="flex items-center gap-2 text-sm">
            Acción
            <select
                class="h-9 rounded-md border bg-background px-2"
                :value="props.filters.action ?? ''"
                @change="filterBy(($event.target as HTMLSelectElement).value)"
            >
                <option value="">Todas</option>
                <option
                    v-for="(label, value) in props.actions"
                    :key="value"
                    :value="value"
                >
                    {{ label }}
                </option>
            </select>
        </label>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-3 py-2 font-medium">Fecha y hora</th>
                        <th class="px-3 py-2 font-medium">Acción</th>
                        <th class="px-3 py-2 font-medium">Usuario</th>
                        <th class="px-3 py-2 font-medium">Sede</th>
                        <th class="px-3 py-2 font-medium">Sobre</th>
                        <th class="px-3 py-2 font-medium">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in props.events.data"
                        :key="row.id"
                        class="border-t align-top"
                    >
                        <td class="px-3 py-2 whitespace-nowrap tabular-nums">
                            {{ formatDateTime(row.occurred_at) }}
                        </td>
                        <td class="px-3 py-2">{{ row.action_label }}</td>
                        <td class="px-3 py-2">{{ row.user ?? '—' }}</td>
                        <td class="px-3 py-2">{{ row.site ?? '—' }}</td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ row.subject ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-xs text-muted-foreground">
                            <div v-if="row.reason" class="text-foreground">
                                Motivo: {{ row.reason }}
                            </div>
                            {{ details(row) }}
                        </td>
                    </tr>
                    <tr v-if="props.events.data.length === 0">
                        <td
                            colspan="6"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            No hay eventos.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="props.events.links.length > 3"
            class="flex flex-wrap gap-1"
            aria-label="Páginas"
        >
            <template v-for="link in props.events.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="rounded-md border px-3 py-1 text-sm"
                    :class="{
                        'bg-primary text-primary-foreground': link.active,
                    }"
                    >{{ pageLabel(link.label) }}</Link
                >
                <span
                    v-else
                    class="rounded-md px-3 py-1 text-sm text-muted-foreground"
                    >{{ pageLabel(link.label) }}</span
                >
            </template>
        </nav>
    </div>
</template>
