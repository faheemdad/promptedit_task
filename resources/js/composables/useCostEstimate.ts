import { watchDebounced } from '@vueuse/core';
import { ref, type Ref } from 'vue';

export interface CostEstimate {
    /** Nano-USD, including markup. */
    expected: number;
    /** Worst case, reserved from the balance until the generation finishes. */
    max: number;
    affordable: boolean;
}

export interface GenerationDraft {
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Asks the backend what a draft generation will cost. The pricing logic lives
 * in one place (PricingCalculator), so the UI never re-implements it.
 */
export function useCostEstimate(draft: Ref<GenerationDraft>) {
    const estimate = ref<CostEstimate | null>(null);
    const loading = ref(false);
    let controller: AbortController | null = null;

    async function refresh() {
        controller?.abort();

        const body = { ...draft.value, reference_images: draft.value.reference_images.filter((url) => url.trim() !== '') };

        if (body.prompt.trim() === '') {
            estimate.value = null;
            return;
        }

        controller = new AbortController();
        loading.value = true;

        try {
            const response = await fetch(route('generations.estimate'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
                body: JSON.stringify(body),
                signal: controller.signal,
            });

            // An invalid draft (e.g. a half-typed URL) keeps the last good estimate.
            if (response.ok) {
                estimate.value = await response.json();
            }
        } catch (error) {
            if ((error as Error).name !== 'AbortError') {
                estimate.value = null;
            }
        } finally {
            loading.value = false;
        }
    }

    watchDebounced(draft, refresh, { debounce: 300, deep: true, immediate: true });

    return { estimate, loading, refresh };
}
