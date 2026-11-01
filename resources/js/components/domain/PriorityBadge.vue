<script setup lang="ts">
import { AlertTriangle, Zap } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';

// El color nunca es el único indicador: icono + texto (UX_UI.md).
defineProps<{ priority: string; label?: string }>();
</script>

<template>
    <Badge
        v-if="priority === 'stat'"
        class="gap-1 bg-red-600 text-white hover:bg-red-600"
    >
        <Zap class="size-3" />{{ label ?? 'Inmediata' }}
    </Badge>
    <Badge
        v-else-if="priority === 'urgent'"
        class="gap-1 bg-amber-500 text-black hover:bg-amber-500"
    >
        <AlertTriangle class="size-3" />{{ label ?? 'Urgente' }}
    </Badge>
    <Badge v-else variant="outline">{{ label ?? 'Rutina' }}</Badge>
</template>
