<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCostEstimate, type GenerationDraft } from '@/composables/useCostEstimate';
import { formatUsd, formatUsdExact } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { Generation, Option, SharedData } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Loader2, Plus, Sparkles, X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ sizes: Option[]; qualities: Option[] }>();

const MAX_PROMPT = 4000;
const MAX_REFERENCES = 4;

const page = usePage<SharedData>();
const balance = computed(() => page.props.auth.user.credit_balance);

const form = useForm<GenerationDraft>({
    prompt: '',
    size: props.sizes[0].value,
    quality: 'medium',
    reference_images: [],
});

const draft = computed(() => form.data());
const { estimate, loading: estimating } = useCostEstimate(draft);

const canAfford = computed(() => !estimate.value || estimate.value.max <= balance.value);
const canSubmit = computed(() => form.prompt.trim() !== '' && !form.processing && canAfford.value);

function addReference() {
    if (form.reference_images.length < MAX_REFERENCES) {
        form.reference_images.push('');
    }
}

function removeReference(index: number) {
    form.reference_images.splice(index, 1);
}

function submit() {
    form.transform((data) => ({ ...data, reference_images: data.reference_images.filter((url) => url.trim() !== '') })).post(
        route('generations.store'),
        { preserveScroll: true, onSuccess: () => form.reset('prompt') },
    );
}

/** Prefill the form from an earlier generation (used by "Try again"). */
function fill(generation: Generation) {
    form.prompt = generation.prompt;
    form.size = generation.size;
    form.quality = generation.quality;
    form.reference_images = [...generation.reference_images];
    form.clearErrors();
}

defineExpose({ fill });
</script>

<template>
    <form class="flex flex-col gap-5 rounded-xl border bg-card p-5 shadow-sm" @submit.prevent="submit">
        <div class="grid gap-2">
            <div class="flex items-baseline justify-between">
                <Label for="prompt">Prompt</Label>
                <span class="text-xs tabular-nums text-muted-foreground">{{ form.prompt.length }} / {{ MAX_PROMPT }}</span>
            </div>
            <textarea
                id="prompt"
                v-model="form.prompt"
                rows="5"
                :maxlength="MAX_PROMPT"
                placeholder="A lighthouse on a cliff at dusk, oil painting"
                class="w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                @keydown.meta.enter="submit"
                @keydown.ctrl.enter="submit"
            />
            <InputError :message="form.errors.prompt" />
        </div>

        <fieldset class="grid gap-2">
            <legend class="mb-2 text-sm font-medium">Size</legend>
            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="size in sizes"
                    :key="size.value"
                    type="button"
                    :class="
                        cn(
                            'flex flex-col items-center gap-1 rounded-md border px-2 py-2 text-xs transition-colors',
                            form.size === size.value ? 'border-primary bg-primary/5 font-medium' : 'hover:bg-accent',
                        )
                    "
                    :aria-pressed="form.size === size.value"
                    @click="form.size = size.value"
                >
                    <span>{{ size.label }}</span>
                    <span class="text-muted-foreground">{{ size.value }}</span>
                </button>
            </div>
            <InputError :message="form.errors.size" />
        </fieldset>

        <fieldset class="grid gap-2">
            <legend class="mb-2 text-sm font-medium">Quality</legend>
            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="quality in qualities"
                    :key="quality.value"
                    type="button"
                    :class="
                        cn(
                            'rounded-md border px-2 py-2 text-sm transition-colors',
                            form.quality === quality.value ? 'border-primary bg-primary/5 font-medium' : 'hover:bg-accent',
                        )
                    "
                    :aria-pressed="form.quality === quality.value"
                    @click="form.quality = quality.value"
                >
                    {{ quality.label }}
                </button>
            </div>
            <InputError :message="form.errors.quality" />
        </fieldset>

        <div class="grid gap-2">
            <div class="flex items-center justify-between">
                <Label>Reference images <span class="font-normal text-muted-foreground">(optional)</span></Label>
                <Button v-if="form.reference_images.length < MAX_REFERENCES" type="button" variant="ghost" size="sm" @click="addReference">
                    <Plus /> Add URL
                </Button>
            </div>
            <div v-for="(_, index) in form.reference_images" :key="index" class="grid gap-1">
                <div class="flex gap-2">
                    <Input v-model="form.reference_images[index]" type="url" placeholder="https://example.com/image.png" />
                    <Button type="button" variant="ghost" size="icon" aria-label="Remove reference image" @click="removeReference(index)">
                        <X />
                    </Button>
                </div>
                <InputError :message="form.errors[`reference_images.${index}` as keyof typeof form.errors]" />
            </div>
            <InputError :message="form.errors.reference_images" />
        </div>

        <div class="rounded-lg bg-muted/60 p-4 text-sm">
            <div class="flex items-center justify-between">
                <span class="text-muted-foreground">Estimated cost</span>
                <span class="flex items-center gap-2 text-lg font-semibold tabular-nums">
                    <Loader2 v-if="estimating" class="size-4 animate-spin text-muted-foreground" />
                    <span v-if="estimate" :title="formatUsdExact(estimate.expected)">{{ formatUsd(estimate.expected) }}</span>
                    <span v-else class="text-muted-foreground">-</span>
                </span>
            </div>
            <p v-if="estimate" class="mt-2 text-xs leading-relaxed text-muted-foreground">
                Up to <span class="font-medium text-foreground">{{ formatUsd(estimate.max) }}</span> is reserved while it runs. You're charged
                the actual cost once the image is ready, and nothing if it fails.
            </p>
            <p v-else class="mt-2 text-xs text-muted-foreground">Write a prompt to see the price.</p>
        </div>

        <div v-if="form.errors.credits || !canAfford" class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
            {{ form.errors.credits ?? `Your balance (${formatUsd(balance)}) doesn't cover the reserved amount.` }}
            <Link :href="route('credits.index')" class="font-medium underline underline-offset-2">Buy credits</Link>
        </div>

        <Button type="submit" size="lg" :disabled="!canSubmit">
            <Loader2 v-if="form.processing" class="animate-spin" />
            <Sparkles v-else />
            Generate
        </Button>
    </form>
</template>
