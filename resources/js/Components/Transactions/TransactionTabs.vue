<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    activeTab: {
        type: String,
        default: 'bookings', // 'bookings' | 'reservations' | 'negotiations' | 'commissions'
    }
});
</script>

<template>
    <div class="flex items-center gap-2 border-b border-slate-200/80 pb-3 mb-6 overflow-x-auto scrollbar-none">
        <Link
            href="/bookings"
            :class="activeTab === 'bookings' || route().current('bookings.*')
                ? 'bg-blue-600 text-white shadow-md shadow-blue-500/25 border-blue-600'
                : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 border-slate-200'"
            class="px-4 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-2 shrink-0 border"
        >
            <span>📋</span> Booking & SPR
        </Link>

        <Link
            href="/reservations"
            :class="activeTab === 'reservations' || route().current('reservations.*')
                ? 'bg-blue-600 text-white shadow-md shadow-blue-500/25 border-blue-600'
                : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 border-slate-200'"
            class="px-4 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-2 shrink-0 border"
        >
            <span>🔖</span> Reservasi Unit (UTJ)
        </Link>

        <Link
            href="/negotiations"
            :class="activeTab === 'negotiations' || route().current('negotiations.*')
                ? 'bg-blue-600 text-white shadow-md shadow-blue-500/25 border-blue-600'
                : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 border-slate-200'"
            class="px-4 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-2 shrink-0 border"
        >
            <span>🤝</span> Pengajuan Negosiasi
        </Link>

        <Link
            href="/commissions"
            :class="activeTab === 'commissions' || route().current('commissions.*')
                ? 'bg-blue-600 text-white shadow-md shadow-blue-500/25 border-blue-600'
                : 'bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 border-slate-200'"
            class="px-4 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-2 shrink-0 border"
        >
            <span>💸</span> Komisi Penjualan
        </Link>
    </div>
</template>
