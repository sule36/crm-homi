<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    activeTab: {
        type: String,
        default: 'bookings', // 'reservations' | 'negotiations' | 'bookings' | 'commissions'
    }
});

const steps = [
    {
        id: 'reservations',
        num: '1',
        href: '/reservations',
        routeMatch: 'reservations.*',
        icon: '🔖',
        title: 'Reservasi Unit (UTJ)',
        subtitle: 'Hold Unit 100% Refundable',
    },
    {
        id: 'negotiations',
        num: '2',
        href: '/negotiations',
        routeMatch: 'negotiations.*',
        icon: '🤝',
        title: 'Pengajuan Negosiasi',
        subtitle: 'Deal Harga, Termin & Bonus',
    },
    {
        id: 'bookings',
        num: '3',
        href: '/bookings',
        routeMatch: 'bookings.*',
        icon: '📋',
        title: 'Booking & SPR',
        subtitle: 'SPR Resmi & Jadwal Tagihan',
    },
    {
        id: 'commissions',
        num: '4',
        href: '/commissions',
        routeMatch: 'commissions.*',
        icon: '💸',
        title: 'Komisi Penjualan',
        subtitle: 'Pencairan Komisi Agen',
    },
];
</script>

<template>
    <div class="bg-white border border-slate-200/90 rounded-2xl p-2 mb-6 shadow-xs overflow-x-auto scrollbar-none">
        <div class="flex items-center gap-1.5 min-w-max">
            <template v-for="(step, idx) in steps" :key="step.id">
                <Link
                    :href="step.href"
                    :class="activeTab === step.id || route().current(step.routeMatch)
                        ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md shadow-blue-500/20 border-blue-600'
                        : 'bg-slate-50/70 text-slate-600 hover:bg-slate-100 hover:text-slate-900 border-transparent'"
                    class="px-3.5 py-2.5 rounded-xl transition-all flex items-center gap-2.5 shrink-0 border group"
                >
                    <span
                        :class="activeTab === step.id || route().current(step.routeMatch)
                            ? 'bg-white/20 text-white'
                            : 'bg-white text-slate-500 border border-slate-200 group-hover:border-slate-300'"
                        class="w-6 h-6 rounded-lg text-[10px] font-black flex items-center justify-center shrink-0"
                    >
                        {{ step.num }}
                    </span>
                    <div class="text-left leading-tight">
                        <div class="text-xs font-black flex items-center gap-1">
                            <span>{{ step.icon }}</span>
                            <span>{{ step.title }}</span>
                        </div>
                        <div
                            :class="activeTab === step.id || route().current(step.routeMatch)
                                ? 'text-blue-100'
                                : 'text-slate-400'"
                            class="text-[10px] font-semibold mt-0.5"
                        >
                            {{ step.subtitle }}
                        </div>
                    </div>
                </Link>

                <span v-if="idx < steps.length - 1" class="text-slate-300 font-black text-xs px-0.5 select-none">
                    →
                </span>
            </template>
        </div>
    </div>
</template>
