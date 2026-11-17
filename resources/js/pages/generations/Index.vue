<script setup lang="ts">
import GenerationCard from '@/components/generations/GenerationCard.vue';
import GenerationForm from '@/components/generations/GenerationForm.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { isInFlight } from '@/lib/generations';
import type { BreadcrumbItem, Generation, Option, ResourceCollection } from '@/types';
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { ImageIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    generations: ResourceCollection<Generation>;
    sizes: Option[];
    qualities: Option[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Studio', href: '/generations' }];

const form = ref<InstanceType<typeof GenerationForm> | null>(null);

// Live status: poll only while something on screen is still running.
// `auth` is reloaded too, so the balance updates when a charge settles.
const hasInFlight = computed(() => props.generations.data.some(isInFlight));
const { start, stop } = usePoll(2500, { only: ['generations', 'auth'] }, { autoStart: false });
watch(hasInFlight, (running) => (running ? start() : stop()), { immediate: true });

function reuse(generation: Generation) {
    form.value?.fill(generation);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<template>
    <Head title="Studio" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="grid gap-6 p-4 lg:grid-cols-[380px_1fr] lg:items-start">
            <div class="lg:sticky lg:top-4">
                <GenerationForm ref="form" :sizes="sizes" :qualities="qualities" />
            </div>

            <section class="flex flex-col gap-4">
                <div class="flex items-baseline justify-between">
                    <h2 class="text-lg font-semibold">Your generations</h2>
                    <span class="text-sm text-muted-foreground">{{ generations.meta.total }} total</span>
                </div>

                <div v-if="generations.data.length === 0" class="flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center text-muted-foreground">
                    <ImageIcon class="size-8" />
                    <p>No generations yet. Write a prompt to create your first image.</p>
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <GenerationCard v-for="generation in generations.data" :key="generation.id" :generation="generation" @reuse="reuse" />
                </div>

                <nav v-if="generations.meta.last_page > 1" class="flex items-center justify-between">
                    <Button variant="outline" size="sm" :disabled="!generations.links.prev" as-child>
                        <Link :href="generations.links.prev ?? ''" preserve-scroll>Newer</Link>
                    </Button>
                    <span class="text-sm text-muted-foreground">Page {{ generations.meta.current_page }} of {{ generations.meta.last_page }}</span>
                    <Button variant="outline" size="sm" :disabled="!generations.links.next" as-child>
                        <Link :href="generations.links.next ?? ''" preserve-scroll>Older</Link>
                    </Button>
                </nav>
            </section>
        </div>
    </AppLayout>
</template>
