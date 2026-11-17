<script setup lang="ts">
import { STATUS_HINTS, STATUS_LABELS } from '@/lib/generations';
import { cn } from '@/lib/utils';
import type { GenerationStatus } from '@/types';
import { computed } from 'vue';

const props = defineProps<{ status: GenerationStatus }>();

const styles: Record<GenerationStatus, string> = {
    pending: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    queued: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    processing: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    succeeded: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    failed: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
};

const animated = computed(() => ['pending', 'queued', 'processing'].includes(props.status));
</script>

<template>
    <span
        :title="STATUS_HINTS[status]"
        :class="cn('inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium', styles[status])"
    >
        <span class="relative flex size-1.5">
            <span v-if="animated" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-current opacity-60" />
            <span class="relative inline-flex size-1.5 rounded-full bg-current" />
        </span>
        {{ STATUS_LABELS[status] }}
    </span>
</template>
