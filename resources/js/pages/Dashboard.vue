<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Clock,
    FlaskConical,
    Microscope,
    Pill,
    ScanLine,
    ShieldCheck,
    TestTubes,
} from '@lucide/vue';
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
import { inoculation } from '@/routes/workbenches';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Inicio',
                href: dashboard(),
            },
        ],
    },
});

const props = defineProps<{
    counters: {
        inoculation?: { ready: number; in_progress: number; urgent: number };
    };
}>();

const page = usePage();
const { can } = usePermissions();
const user = computed(() => page.props.auth.user);
const roles = computed(() => page.props.auth.roles);
const site = computed(() => page.props.site.active);

// Tarjetas de la pantalla principal (docs/WORKFLOW_ENGINE.md §4).
// Las que todavía no están construidas se muestran como "Próximamente".
type Workbench = {
    title: string;
    description: string;
    icon: unknown;
    href?: ReturnType<typeof dashboard>;
    count?: number;
    urgent?: number;
};

const workbenches = computed<Workbench[]>(() => [
    {
        title: 'Urgencias y resultados críticos',
        description: 'Alertas abiertas y actividades urgentes',
        icon: AlertTriangle,
    },
    ...(can('samples.receive')
        ? [
              {
                  title: 'Recepción',
                  description: 'Escanear la etiqueta del LIS',
                  icon: ScanLine,
                  href: receptionCreate(),
              },
          ]
        : []),
    {
        title: 'Pendientes de siembra',
        description: 'Muestras recibidas, agrupadas por protocolo',
        icon: TestTubes,
        ...(props.counters.inoculation
            ? {
                  href: inoculation(),
                  count:
                      props.counters.inoculation.ready +
                      props.counters.inoculation.in_progress,
                  urgent: props.counters.inoculation.urgent,
              }
            : {}),
    },
    {
        title: 'Cultivos pendientes de lectura',
        description: 'Placas que cumplieron la incubación',
        icon: Microscope,
    },
    {
        title: 'Identificaciones y bioquímicas',
        description: 'Gram, pruebas bioquímicas y VITEK',
        icon: FlaskConical,
    },
    {
        title: 'Antibiogramas',
        description: 'Para preparar y para leer',
        icon: Pill,
    },
    {
        title: 'Pendientes de validación',
        description: 'Informes listos para validar',
        icon: ShieldCheck,
    },
    {
        title: 'Demoradas',
        description: 'Actividades fuera de término',
        icon: Clock,
    },
]);
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
                :is="workbench.href ? Link : 'div'"
                v-for="workbench in workbenches"
                :key="workbench.title"
                :href="workbench.href"
                class="rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <Card
                    class="h-full transition-colors"
                    :class="
                        workbench.href ? 'hover:border-primary' : 'opacity-60'
                    "
                >
                    <CardHeader>
                        <div class="flex items-start justify-between gap-2">
                            <component
                                :is="workbench.icon"
                                class="size-5 text-muted-foreground"
                            />
                            <Badge v-if="!workbench.href" variant="outline"
                                >Próximamente</Badge
                            >
                            <Badge
                                v-else-if="workbench.urgent"
                                class="bg-amber-500 text-black hover:bg-amber-500"
                                >{{ workbench.urgent }} urgentes</Badge
                            >
                        </div>
                        <CardTitle class="text-base">{{
                            workbench.title
                        }}</CardTitle>
                        <CardDescription>{{
                            workbench.description
                        }}</CardDescription>
                        <p
                            v-if="workbench.count !== undefined"
                            class="pt-2 text-3xl font-semibold tabular-nums"
                        >
                            {{ workbench.count }}
                            <span
                                class="text-sm font-normal text-muted-foreground"
                                >pendientes</span
                            >
                        </p>
                    </CardHeader>
                </Card>
            </component>
        </div>
    </div>
</template>
