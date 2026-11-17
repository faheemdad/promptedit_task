<script setup lang="ts">
import StatusBadge from '@/components/generations/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { errorMessage, isImageExpired, isInFlight, STATUS_HINTS } from '@/lib/generations';
import { formatUsd, formatUsdExact } from '@/lib/money';
import type { Generation } from '@/types';
import { useTimeAgo } from '@vueuse/core';
import { AlertTriangle, ImageOff, Loader2, RotateCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ generation: Generation }>();
defineEmits<{ reuse: [generation: Generation] }>();

const imageFailed = ref(false);
const createdAgo = useTimeAgo(() => props.generation.created_at);

const aspect = computed(() => {
    const [w, h] = props.generation.size.split('x').map(Number);
    return `${w} / ${h}`;
});

const showImage = computed(
    () => props.generation.status === 'succeeded' && props.generation.image_url && !isImageExpired(props.generation) && !imageFailed.value,
);
</script>

<template>
    <article class="flex flex-col overflow-hidden rounded-xl border bg-card text-card-foreground shadow-sm">
        <div class="relative flex items-center justify-center bg-muted" :style="{ aspectRatio: aspect }">
            <a v-if="showImage" :href="generation.image_url!" target="_blank" rel="noopener" class="h-full w-full">
                <img :src="generation.image_url!" :alt="generation.prompt" class="h-full w-full object-cover" loading="lazy" @error="imageFailed = true" />
            </a>

            <div v-else-if="isInFlight(generation)" class="flex flex-col items-center gap-2 p-6 text-center text-sm text-muted-foreground">
                <Loader2 class="size-6 animate-spin" />
                <span>{{ STATUS_HINTS[generation.status] }}</span>
            </div>

            <div v-else-if="generation.status === 'failed'" class="flex flex-col items-center gap-2 p-6 text-center text-sm">
                <AlertTriangle class="size-6 text-red-500" />
                <span class="text-foreground">{{ errorMessage(generation) }}</span>
                <span class="text-muted-foreground">You were not charged.</span>
            </div>

            <div v-else class="flex flex-col items-center gap-2 p-6 text-center text-sm text-muted-foreground">
                <ImageOff class="size-6" />
                <span>The image link has expired (Pixelforge keeps images for 24 hours).</span>
            </div>

            <StatusBadge :status="generation.status" class="absolute left-2 top-2 shadow-sm" />
        </div>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <p class="line-clamp-2 text-sm" :title="generation.prompt">{{ generation.prompt }}</p>

            <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
                <span>{{ generation.size }}</span>
                <span class="capitalize">{{ generation.quality }} quality</span>
                <span v-if="generation.reference_images.length">{{ generation.reference_images.length }} ref.</span>
                <span :title="new Date(generation.created_at).toLocaleString()">{{ createdAgo }}</span>
            </div>

            <dl class="mt-auto grid grid-cols-2 gap-2 rounded-lg bg-muted/60 p-3 text-xs">
                <div>
                    <dt class="text-muted-foreground">Estimated</dt>
                    <dd class="font-medium tabular-nums" :title="`Up to ${formatUsdExact(generation.max_cost)}`">
                        {{ formatUsd(generation.estimated_cost) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Charged</dt>
                    <dd class="font-medium tabular-nums">
                        <span v-if="generation.charged_cost !== null" :title="formatUsdExact(generation.charged_cost)">
                            {{ formatUsd(generation.charged_cost) }}
                        </span>
                        <span v-else-if="generation.status === 'failed'" class="text-emerald-600 dark:text-emerald-400">Free</span>
                        <span v-else class="text-muted-foreground">On completion</span>
                    </dd>
                </div>
            </dl>

            <Button v-if="generation.status === 'failed'" variant="outline" size="sm" @click="$emit('reuse', generation)">
                <RotateCcw /> Try again
            </Button>
        </div>
    </article>
</template>
