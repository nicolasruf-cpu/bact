<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    Dna,
    Microscope,
    Pill,
    ScanLine,
    ShieldCheck,
    TestTubes,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import { create as receptionCreate } from '@/routes/reception';
import { susceptibility as profileRoute } from '@/routes/statistics';
import { inoculation, show as workbenchRoute } from '@/routes/workbenches';

type Counter = {
    ready: number;
    urgent: number;
    in_progress?: number;
    overdue?: number;
    scheduled?: number;
};

const props = defineProps<{ counters: Record<string, Counter | undefined> }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Inicio', href: dashboard() }] },
});

const page = usePage();
const { can } = usePermissions();
const user = computed(() => page.props.auth.user);
const roles = computed(() => page.props.auth.roles);
const site = computed(() => page.props.site.active);

type Tile = {
    title: string;
    description: string;
    icon: Component;
    href?: string;
    counter?: Counter;
};

// Tarjetas de la pantalla principal (WORKFLOW_ENGINE.md §4), según permisos.
const tiles = computed<Tile[]>(() => {
    const list: Tile[] = [];
    if (can('samples.receive')) {
        list.push({
            title: 'Recepción',
            description: 'Escanear la etiqueta del LIS',
            icon: ScanLine,
            href: receptionCreate().url,
        });
    }
    const workbench = (
        key: string,
        title: string,
        description: string,
        icon: Component,
        href: string,
    ) => {
        if (props.counters[key]) {
            list.push({
                title,
                description,
                icon,
                href,
                counter: props.counters[key],
            });
        }
    };
    workbench(
        'inoculation',
        'Siembra',
        'Muestras recibidas, por protocolo',
        TestTubes,
        inoculation().url,
    );
    workbench(
        'reading',
        'Lectura',
        'Cultivos que cumplieron la incubación',
        Microscope,
        workbenchRoute('reading').url,
    );
    workbench(
        'identification',
        'Identificación',
        'Aislamientos significativos',
        Dna,
        workbenchRoute('identification').url,
    );
    workbench(
        'susceptibility',
        'Antibiogramas',
        'Para preparar y para leer',
        Pill,
        workbenchRoute('susceptibility').url,
    );
    workbench(
        'validation',
        'Validación',
        'Informes listos para validar',
        ShieldCheck,
        workbenchRoute('validation').url,
    );
    if (can('statistics.view')) {
        list.push({
            title: 'Perfil de sensibilidad',
            description: '% de sensibles por germen y antibiótico',
            icon: BarChart3,
            href: profileRoute().url,
        });
    }
    list.push({
        title: 'Urgencias y críticos',
        description: 'Próximamente (hemocultivo)',
        icon: AlertTriangle,
    });

    return list;
});
</script>

<template>
    <Head title="Inicio" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Hola, {{ user.name }}
            </h1>
            <p v-if="site" class="mt-1 text-muted-foreground">
                Estás trabajando en <strong>{{ site.name }}</strong>
                <template v-if="roles.length">
                    como
                    <Badge
                        v-for="role in roles"
                        :key="role"
                        variant="secondary"
                        class="ml-1"
                        >{{ role }}</Badge
                    >
                </template>
            </p>
            <p v-else class="mt-1 text-destructive">
                Todavía no tenés ninguna sede asignada. Pedile al administrador
                que te asigne un rol.
            </p>
        </div>

        <div v-if="site" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <component
                :is="tile.href ? Link : 'div'"
                v-for="tile in tiles"
                :key="tile.title"
                :href="tile.href"
                class="rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :data-test="`tile-${tile.title}`"
            >
                <Card
                    class="h-full transition-colors"
                    :class="tile.href ? 'hover:border-primary' : 'opacity-60'"
                >
                    <CardHeader>
                        <div class="flex items-start justify-between gap-2">
                            <component
                                :is="tile.icon"
                                class="size-5 text-muted-foreground"
                            />
                            <Badge v-if="!tile.href" variant="outline"
                                >Próximamente</Badge
                            >
                            <div
                                v-else
                                class="flex flex-wrap justify-end gap-1"
                            >
                                <Badge
                                    v-if="tile.counter?.urgent"
                                    class="bg-amber-500 text-black hover:bg-amber-500"
                                    >{{ tile.counter.urgent }} urgentes</Badge
                                >
                                <Badge
                                    v-if="tile.counter?.overdue"
                                    class="bg-orange-500 text-black hover:bg-orange-500"
                                    >{{ tile.counter.overdue }} demoradas</Badge
                                >
                            </div>
                        </div>
                        <CardTitle class="text-base">{{
                            tile.title
                        }}</CardTitle>
                        <CardDescription>{{
                            tile.description
                        }}</CardDescription>
                        <p
                            v-if="tile.counter"
                            class="pt-2 text-3xl font-semibold tabular-nums"
                        >
                            {{
                                tile.counter.ready +
                                (tile.counter.in_progress ?? 0)
                            }}
                            <span
                                class="text-sm font-normal text-muted-foreground"
                                >pendientes</span
                            >
                            <span
                                v-if="tile.counter.scheduled"
                                class="block text-xs font-normal text-muted-foreground"
                            >
                                + {{ tile.counter.scheduled }} programadas
                            </span>
                        </p>
                    </CardHeader>
                </Card>
            </component>
        </div>
    </div>
</template>
