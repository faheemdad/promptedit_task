<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatUsd, formatUsdExact } from '@/lib/money';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle2, XCircle } from 'lucide-vue-next';
import { computed, reactive } from 'vue';

/** All money is nano-USD. */
interface Totals {
    generations: number;
    charged: number;
    provider_cost: number;
    profit: number;
}

interface DayRow extends Totals {
    day: string;
    pixelforge_cost: number | null;
}

interface UserRow extends Totals {
    user_id: number;
    user_name: string;
    user_email: string;
}

interface BreakdownRow extends Totals {
    day: string;
    user_id: number;
    user_name: string;
}

const props = defineProps<{
    range: { start: string; end: string };
    days: DayRow[];
    users: UserRow[];
    breakdown: BreakdownRow[];
    pixelforge: { available: boolean; total_cost: number | null };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin report', href: '/admin/report' }];

const filters = reactive({ ...props.range });

function apply() {
    router.get(route('admin.report'), filters, { preserveState: true });
}

const totals = computed(() =>
    props.days.reduce(
        (sum, d) => ({
            generations: sum.generations + d.generations,
            charged: sum.charged + d.charged,
            provider_cost: sum.provider_cost + d.provider_cost,
            profit: sum.profit + d.profit,
        }),
        { generations: 0, charged: 0, provider_cost: 0, profit: 0 },
    ),
);

const totalMatches = computed(() => props.pixelforge.total_cost === totals.value.provider_cost);
</script>

<template>
    <Head title="Admin report" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="apply">
                <div class="grid gap-1">
                    <Label for="start">From (UTC)</Label>
                    <Input id="start" v-model="filters.start" type="date" />
                </div>
                <div class="grid gap-1">
                    <Label for="end">To (UTC)</Label>
                    <Input id="end" v-model="filters.end" type="date" />
                </div>
                <Button type="submit">Apply</Button>
                <p class="text-xs text-muted-foreground">Days are UTC completion days, which is how Pixelforge bills. Up to 92 days.</p>
            </form>

            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-sm text-muted-foreground">Credit spend (users charged)</p>
                    <p class="text-2xl font-semibold tabular-nums" :title="formatUsdExact(totals.charged)">{{ formatUsd(totals.charged) }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-sm text-muted-foreground">Provider cost (our records)</p>
                    <p class="text-2xl font-semibold tabular-nums" :title="formatUsdExact(totals.provider_cost)">{{ formatUsd(totals.provider_cost) }}</p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-sm text-muted-foreground">Profit</p>
                    <p class="text-2xl font-semibold tabular-nums text-emerald-600 dark:text-emerald-400" :title="formatUsdExact(totals.profit)">
                        {{ formatUsd(totals.profit) }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-4 shadow-sm">
                    <p class="text-sm text-muted-foreground">Pixelforge reports (GET /v1/usage)</p>
                    <template v-if="pixelforge.available && pixelforge.total_cost !== null">
                        <p class="flex items-center gap-2 text-2xl font-semibold tabular-nums" :title="formatUsdExact(pixelforge.total_cost)">
                            {{ formatUsd(pixelforge.total_cost) }}
                            <CheckCircle2 v-if="totalMatches" class="size-5 text-emerald-500" />
                            <XCircle v-else class="size-5 text-red-500" />
                        </p>
                        <p class="text-xs text-muted-foreground">{{ totalMatches ? 'Matches our records exactly.' : 'Differs from our records.' }}</p>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">Unavailable right now.</p>
                </div>
            </section>

            <section class="overflow-x-auto rounded-xl border bg-card shadow-sm">
                <h2 class="px-4 pt-4 font-semibold">By day</h2>
                <table class="mt-2 w-full text-sm tabular-nums">
                    <thead class="bg-muted/60 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Day</th>
                            <th class="px-4 py-2 text-right font-medium">Images</th>
                            <th class="px-4 py-2 text-right font-medium">Credit spend</th>
                            <th class="px-4 py-2 text-right font-medium">Provider cost</th>
                            <th class="px-4 py-2 text-right font-medium">Pixelforge says</th>
                            <th class="px-4 py-2 text-right font-medium">Profit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="days.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">No completed generations in this range.</td>
                        </tr>
                        <tr v-for="row in days" :key="row.day">
                            <td class="px-4 py-2">{{ row.day }}</td>
                            <td class="px-4 py-2 text-right">{{ row.generations }}</td>
                            <td class="px-4 py-2 text-right" :title="formatUsdExact(row.charged)">{{ formatUsdExact(row.charged) }}</td>
                            <td class="px-4 py-2 text-right" :title="formatUsdExact(row.provider_cost)">{{ formatUsdExact(row.provider_cost) }}</td>
                            <td class="px-4 py-2 text-right">
                                <span v-if="row.pixelforge_cost === null" class="text-muted-foreground">-</span>
                                <span v-else class="inline-flex items-center gap-1">
                                    {{ formatUsdExact(row.pixelforge_cost) }}
                                    <CheckCircle2 v-if="row.pixelforge_cost === row.provider_cost" class="size-4 text-emerald-500" />
                                    <XCircle v-else class="size-4 text-red-500" />
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">{{ formatUsdExact(row.profit) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="overflow-x-auto rounded-xl border bg-card shadow-sm">
                <h2 class="px-4 pt-4 font-semibold">By user</h2>
                <table class="mt-2 w-full text-sm tabular-nums">
                    <thead class="bg-muted/60 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">User</th>
                            <th class="px-4 py-2 text-right font-medium">Images</th>
                            <th class="px-4 py-2 text-right font-medium">Credit spend</th>
                            <th class="px-4 py-2 text-right font-medium">Provider cost</th>
                            <th class="px-4 py-2 text-right font-medium">Profit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="users.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">No data.</td>
                        </tr>
                        <tr v-for="row in users" :key="row.user_id">
                            <td class="px-4 py-2">
                                {{ row.user_name }} <span class="text-muted-foreground">{{ row.user_email }}</span>
                            </td>
                            <td class="px-4 py-2 text-right">{{ row.generations }}</td>
                            <td class="px-4 py-2 text-right">{{ formatUsdExact(row.charged) }}</td>
                            <td class="px-4 py-2 text-right">{{ formatUsdExact(row.provider_cost) }}</td>
                            <td class="px-4 py-2 text-right">{{ formatUsdExact(row.profit) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="overflow-x-auto rounded-xl border bg-card shadow-sm">
                <h2 class="px-4 pt-4 font-semibold">By day and user</h2>
                <table class="mt-2 w-full text-sm tabular-nums">
                    <thead class="bg-muted/60 text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Day</th>
                            <th class="px-4 py-2 font-medium">User</th>
                            <th class="px-4 py-2 text-right font-medium">Images</th>
                            <th class="px-4 py-2 text-right font-medium">Credit spend</th>
                            <th class="px-4 py-2 text-right font-medium">Provider cost</th>
                            <th class="px-4 py-2 text-right font-medium">Profit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-if="breakdown.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">No data.</td>
                        </tr>
                        <tr v-for="row in breakdown" :key="`${row.day}-${row.user_id}`">
                            <td class="px-4 py-2">{{ row.day }}</td>
                            <td class="px-4 py-2">{{ row.user_name }}</td>
                            <td class="px-4 py-2 text-right">{{ row.generations }}</td>
                            <td class="px-4 py-2 text-right">{{ formatUsdExact(row.charged) }}</td>
                            <td class="px-4 py-2 text-right">{{ formatUsdExact(row.provider_cost) }}</td>
                            <td class="px-4 py-2 text-right">{{ formatUsdExact(row.profit) }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </AppLayout>
</template>
