<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatUsd, formatUsdExact } from '@/lib/money';
import type { BreadcrumbItem, Paginator, SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Wallet } from 'lucide-vue-next';
import { computed } from 'vue';

interface Transaction {
    id: number;
    type: 'purchase' | 'hold' | 'hold_release' | 'charge';
    amount: number;
    description: string | null;
    generation_id: string | null;
    created_at: string;
}

defineProps<{ packs: number[]; transactions: Paginator<Transaction> }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Credits', href: '/credits' }];

const page = usePage<SharedData>();
const balance = computed(() => page.props.auth.user.credit_balance);
const flash = computed(() => page.props.flash.success);

const form = useForm({ pack: 0 });

function buy(pack: number) {
    form.pack = pack;
    form.post(route('credits.store'), { preserveScroll: true });
}

const TYPE_LABELS: Record<Transaction['type'], string> = {
    purchase: 'Purchase',
    hold: 'Reserved',
    hold_release: 'Released',
    charge: 'Charge',
};
</script>

<template>
    <Head title="Credits" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4">
            <section class="flex flex-col gap-6 rounded-xl border bg-card p-6 shadow-sm md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-4">
                    <div class="rounded-full bg-primary/10 p-3"><Wallet class="size-6" /></div>
                    <div>
                        <p class="text-sm text-muted-foreground">Available balance</p>
                        <p class="text-3xl font-semibold tabular-nums" :title="formatUsdExact(balance)">{{ formatUsd(balance, 4) }}</p>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <p class="text-sm font-medium">Buy credits</p>
                    <div class="flex gap-2">
                        <Button v-for="pack in packs" :key="pack" variant="outline" :disabled="form.processing" @click="buy(pack)">
                            ${{ pack }}
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">Demo only: no payment is taken.</p>
                </div>
            </section>

            <p v-if="flash" class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ flash }}</p>

            <p class="text-sm text-muted-foreground">
                When you generate, the highest possible price is reserved from your balance. When the image is ready, the reservation is
                released and only the actual cost is charged. Failed generations release the reservation and cost nothing.
            </p>

            <section class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-muted/60 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Date</th>
                            <th class="px-4 py-2 font-medium">Type</th>
                            <th class="px-4 py-2 font-medium">Details</th>
                            <th class="px-4 py-2 text-right font-medium">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="transactions.data.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">No transactions yet.</td>
                        </tr>
                        <tr v-for="tx in transactions.data" :key="tx.id">
                            <td class="whitespace-nowrap px-4 py-2 text-muted-foreground">{{ new Date(tx.created_at).toLocaleString() }}</td>
                            <td class="px-4 py-2">{{ TYPE_LABELS[tx.type] }}</td>
                            <td class="px-4 py-2 text-muted-foreground">{{ tx.description }}</td>
                            <td
                                class="px-4 py-2 text-right tabular-nums"
                                :class="tx.amount >= 0 ? 'text-emerald-600 dark:text-emerald-400' : ''"
                                :title="formatUsdExact(tx.amount)"
                            >
                                {{ tx.amount >= 0 ? '+' : '' }}{{ formatUsd(tx.amount, 6) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <nav v-if="transactions.last_page > 1" class="flex justify-between">
                <Button variant="outline" size="sm" :disabled="!transactions.prev_page_url" as-child>
                    <Link :href="transactions.prev_page_url ?? ''" preserve-scroll>Newer</Link>
                </Button>
                <Button variant="outline" size="sm" :disabled="!transactions.next_page_url" as-child>
                    <Link :href="transactions.next_page_url ?? ''" preserve-scroll>Older</Link>
                </Button>
            </nav>
        </div>
    </AppLayout>
</template>
