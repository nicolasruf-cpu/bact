<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Building2, Check, ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { update as updateActiveSite } from '@/routes/active-site';
import type { SiteSummary } from '@/types';

const page = usePage();
const site = computed(() => page.props.site);
const canSwitch = computed(() => site.value.available.length > 1);
const { isMobile } = useSidebar();

function switchTo(target: SiteSummary) {
    if (target.id === site.value.active?.id) {
        return;
    }

    router.visit(updateActiveSite(), {
        data: { site_id: target.id },
        preserveScroll: true,
    });
}
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child :disabled="!canSwitch">
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent"
                        data-test="site-switcher"
                        :aria-label="`Sede activa: ${site.active?.name ?? 'ninguna'}`"
                    >
                        <div
                            class="flex aspect-square size-8 items-center justify-center rounded-md border bg-background"
                        >
                            <Building2 class="size-4" />
                        </div>
                        <div
                            class="grid flex-1 text-left text-sm leading-tight"
                        >
                            <span class="text-xs text-muted-foreground"
                                >Sede activa</span
                            >
                            <span class="truncate font-medium">{{
                                site.active?.name ?? 'Sin sede asignada'
                            }}</span>
                        </div>
                        <ChevronsUpDown
                            v-if="canSwitch"
                            class="ml-auto size-4"
                        />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="isMobile ? 'bottom' : 'right'"
                    align="start"
                    :side-offset="4"
                >
                    <DropdownMenuLabel class="text-xs text-muted-foreground">
                        Cambiar de sede
                    </DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="option in site.available"
                        :key="option.id"
                        class="gap-2"
                        @select="switchTo(option)"
                    >
                        <span class="flex-1 truncate">{{ option.name }}</span>
                        <Check
                            v-if="option.id === site.active?.id"
                            class="size-4"
                        />
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
