<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    ClipboardList,
    Dna,
    Droplets,
    FlaskConical,
    LayoutGrid,
    Library,
    Microscope,
    ListChecks,
    Pill,
    ScanLine,
    ScrollText,
    ShieldCheck,
    SlidersHorizontal,
    TestTubes,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import SiteSwitcher from '@/components/SiteSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import { index as astPanelsIndex } from '@/routes/admin/ast-panels';
import { index as astRulesIndex } from '@/routes/admin/ast-rules';
import { index as breakpointsIndex } from '@/routes/admin/breakpoint-sets';
import { index as catalogsIndex } from '@/routes/admin/catalogs';
import { index as identificationTestsIndex } from '@/routes/admin/identification-tests';
import { index as protocolsIndex } from '@/routes/admin/protocols';
import { index as usersIndex } from '@/routes/admin/users';
import { index as auditIndex } from '@/routes/audit';
import { create as receptionCreate } from '@/routes/reception';
import { susceptibility as profile } from '@/routes/statistics';
import {
    bloodCultures,
    inoculation,
    show as workbench,
} from '@/routes/workbenches';
import type { NavItem } from '@/types';

const { can } = usePermissions();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Inicio',
        href: dashboard(),
        icon: LayoutGrid,
    },
    ...(can('samples.receive')
        ? [{ title: 'Recepción', href: receptionCreate(), icon: ScanLine }]
        : []),
    ...(can('cultures.inoculate')
        ? [{ title: 'Siembra', href: inoculation(), icon: TestTubes }]
        : []),
    ...(can('blood_cultures.load') ||
    can('blood_cultures.signal_positive') ||
    can('blood_cultures.confirm_negative') ||
    can('gram.perform')
        ? [
              {
                  title: 'Hemocultivos',
                  href: bloodCultures(),
                  icon: Droplets,
              },
          ]
        : []),
    ...(can('direct_exams.perform') || can('cultures.inoculate')
        ? [
              {
                  title: 'Procesamiento',
                  href: workbench('processing'),
                  icon: FlaskConical,
              },
          ]
        : []),
    ...(can('cultures.read_negative') || can('cultures.read_positive')
        ? [{ title: 'Lectura', href: workbench('reading'), icon: Microscope }]
        : []),
    ...(can('identification.perform') || can('identification.biochem.read')
        ? [
              {
                  title: 'Identificación',
                  href: workbench('identification'),
                  icon: Dna,
              },
          ]
        : []),
    ...(can('ast.setup') || can('ast.read')
        ? [
              {
                  title: 'Antibiogramas',
                  href: workbench('susceptibility'),
                  icon: Pill,
              },
          ]
        : []),
    ...(can('reports.validate')
        ? [
              {
                  title: 'Validación',
                  href: workbench('validation'),
                  icon: ShieldCheck,
              },
          ]
        : []),
    ...(can('statistics.view')
        ? [
              {
                  title: 'Perfil de sensibilidad',
                  href: profile(),
                  icon: BarChart3,
              },
          ]
        : []),
]);

const adminNavItems = computed<NavItem[]>(() => [
    ...(can('users.manage')
        ? [{ title: 'Usuarios', href: usersIndex(), icon: Users }]
        : []),
    ...(can('config.catalogs.edit')
        ? [{ title: 'Catálogos', href: catalogsIndex(), icon: Library }]
        : []),
    ...(can('config.clinical.view')
        ? [
              {
                  title: 'Protocolos',
                  href: protocolsIndex(),
                  icon: ClipboardList,
              },
              {
                  title: 'Pruebas de identificación',
                  href: identificationTestsIndex(),
                  icon: FlaskConical,
              },
              {
                  title: 'Paneles de antibiograma',
                  href: astPanelsIndex(),
                  icon: Pill,
              },
              {
                  title: 'Reglas de antibiograma',
                  href: astRulesIndex(),
                  icon: ListChecks,
              },
              {
                  title: 'Puntos de corte',
                  href: breakpointsIndex(),
                  icon: SlidersHorizontal,
              },
          ]
        : []),
    ...(can('audit.view')
        ? [{ title: 'Auditoría', href: auditIndex(), icon: ScrollText }]
        : []),
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SiteSwitcher />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain
                v-if="adminNavItems.length"
                :items="adminNavItems"
                label="Administración"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
