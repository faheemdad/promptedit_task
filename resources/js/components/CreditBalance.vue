<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { formatUsd, formatUsdExact } from '@/lib/money';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const balance = computed(() => page.props.auth.user.credit_balance);
</script>

<template>
    <div class="flex items-center justify-between gap-2 rounded-lg border bg-background px-3 py-2 group-data-[collapsible=icon]:hidden">
        <div>
            <p class="text-xs text-muted-foreground">Balance</p>
            <p class="font-semibold tabular-nums" :title="formatUsdExact(balance)">{{ formatUsd(balance) }}</p>
        </div>
        <Button size="sm" variant="outline" as-child>
            <Link :href="route('credits.index')"><Plus /> Buy credits</Link>
        </Button>
    </div>
</template>
