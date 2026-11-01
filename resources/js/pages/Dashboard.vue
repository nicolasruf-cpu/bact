<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
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
import { dashboard } from '@/routes';

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

const page = usePage();
const user = computed(() => page.props.auth.user);
const roles = computed(() => page.props.auth.roles);
const site = computed(() => page.props.site.active);

// Tarjetas de la pantalla principal (docs/WORKFLOW_ENGINE.md §4).
// Se activan a medida que se construye cada mesada.
const workbenches = [
    {
        title: 'Urgencias y resultados críticos',
        description: 'Alertas abiertas y actividades urgentes',
        icon: AlertTriangle,
    },
    {
        title: 'Pendientes de siembra',
        description: 'Muestras recibidas, agrupadas por protocolo',
        icon: TestTubes,
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
    {
        title: 'Recepción',
        description: 'Escanear la etiqueta del LIS',
        icon: ScanLine,
    },
];
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
            <Card
                v-for="workbench in workbenches"
                :key="workbench.title"
                class="opacity-70"
            >
                <CardHeader>
                    <div class="flex items-start justify-between gap-2">
                        <component
                            :is="workbench.icon"
                            class="size-5 text-muted-foreground"
                        />
                        <Badge variant="outline">Próximamente</Badge>
                    </div>
                    <CardTitle class="text-base">{{
                        workbench.title
                    }}</CardTitle>
                    <CardDescription>{{
                        workbench.description
                    }}</CardDescription>
                </CardHeader>
            </Card>
        </div>
    </div>
</template>
