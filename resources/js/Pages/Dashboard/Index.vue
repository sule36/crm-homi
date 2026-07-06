<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stats: Object,
    pipeline: Object,
    recentLeads: Array,
    todayReminders: Array,
    projectStats: Array,
    revenueTrend: Array,
});

const formatRupiah = (val) => {
    if (!val) return 'Rp 0';
    if (val >= 1000000000) return `Rp ${(val / 1000000000).toFixed(1)}M`;
    if (val >= 1000000) return `Rp ${(val / 1000000).toFixed(0)}Jt`;
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val);
};

const pipelineStages = computed(() => [
    { key: 'new', label: 'Baru', color: 'bg-slate-100 text-slate-600', dot: 'bg-slate-400', count: props.pipeline?.new || 0 },
    { key: 'contacted', label: 'Dihubungi', color: 'bg-blue-50 text-blue-600', dot: 'bg-blue-400', count: props.pipeline?.contacted || 0 },
    { key: 'visited', label: 'Visit', color: 'bg-amber-50 text-amber-600', dot: 'bg-amber-400', count: props.pipeline?.visited || 0 },
    { key: 'negotiation', label: 'Negosiasi', color: 'bg-purple-50 text-purple-600', dot: 'bg-purple-400', count: props.pipeline?.negotiation || 0 },
    { key: 'booking', label: 'Booking', color: 'bg-emerald-50 text-emerald-600', dot: 'bg-emerald-400', count: props.pipeline?.booking || 0 },
    { key: 'won', label: 'Won', color: 'bg-green-50 text-green-700', dot: 'bg-green-500', count: props.pipeline?.won || 0 },
    { key: 'lost', label: 'Lost', color: 'bg-rose-50 text-rose-600', dot: 'bg-rose-400', count: props.pipeline?.lost || 0 },
]);

const statusColor = (status) => ({
    new: 'bg-slate-100 text-slate-700',
    contacted: 'bg-blue-100 text-blue-700',
    visited: 'bg-amber-100 text-amber-700',
    negotiation: 'bg-purple-100 text-purple-700',
    booking: 'bg-emerald-100 text-emerald-700',
    won: 'bg-green-100 text-green-800',
    lost: 'bg-rose-100 text-rose-700',
}[status] || 'bg-gray-100 text-gray-700');

const scoreColor = (score) => {
    if (score >= 70) return 'text-emerald-600 bg-emerald-50';
    if (score >= 40) return 'text-amber-600 bg-amber-50';
    return 'text-slate-500 bg-slate-50';
};
</script>

<template>
    <Head title="Dashboard" />
    <CrmLayout>
        <template #breadcrumb>Dashboard</template>

        <!-- HEADER -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">Dashboard</h1>
                <p class="text-sm text-gray-500 mt-1">Selamat datang kembali. Berikut ringkasan performa Anda.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Hari ini</span>
                <span class="text-xs font-black text-gray-800 bg-gray-100 px-3 py-1.5 rounded-lg">
                    {{ new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }}
                </span>
            </div>
        </div>

        <!-- STAT CARDS -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <!-- Total Revenue -->
            <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl p-5 text-white relative overflow-hidden group hover:shadow-xl hover:shadow-blue-200 transition-all duration-300">
                <div class="absolute -top-6 -right-6 w-24 h-24 bg-white/10 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
                <p class="text-blue-100 text-[10px] font-bold uppercase tracking-widest mb-2">Total Revenue</p>
                <p class="text-2xl font-black tracking-tight">{{ formatRupiah(stats.total_revenue) }}</p>
                <p class="text-blue-200 text-xs mt-1 font-medium">Bulan ini: {{ formatRupiah(stats.this_month_revenue) }}</p>
            </div>

            <!-- Active Leads -->
            <div class="bg-white rounded-2xl p-5 border border-gray-100 hover:shadow-lg hover:shadow-gray-100 transition-all duration-300 group">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest">Lead Aktif</p>
                    <span class="text-2xl group-hover:scale-110 transition-transform">👥</span>
                </div>
                <p class="text-3xl font-black text-gray-900">{{ stats.active_leads }}</p>
                <div class="flex items-center gap-2 mt-2">
                    <span class="text-[10px] font-bold text-rose-500 bg-rose-50 px-2 py-0.5 rounded-full">🔥 {{ stats.hot_leads }} Hot</span>
                </div>
            </div>

            <!-- Unit Sold -->
            <div class="bg-white rounded-2xl p-5 border border-gray-100 hover:shadow-lg hover:shadow-gray-100 transition-all duration-300 group">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest">Unit Terjual</p>
                    <span class="text-2xl group-hover:scale-110 transition-transform">🏠</span>
                </div>
                <p class="text-3xl font-black text-gray-900">{{ stats.sold_units }}<span class="text-lg text-gray-400 font-medium">/{{ stats.total_units }}</span></p>
                <div class="w-full bg-gray-100 rounded-full h-1.5 mt-3">
                    <div class="bg-emerald-500 h-1.5 rounded-full transition-all duration-1000" :style="{ width: (stats.total_units > 0 ? (stats.sold_units / stats.total_units) * 100 : 0) + '%' }"></div>
                </div>
            </div>

            <!-- Conversion Rate -->
            <div class="bg-white rounded-2xl p-5 border border-gray-100 hover:shadow-lg hover:shadow-gray-100 transition-all duration-300 group">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-gray-400 text-[10px] font-bold uppercase tracking-widest">Konversi</p>
                    <span class="text-2xl group-hover:scale-110 transition-transform">📈</span>
                </div>
                <p class="text-3xl font-black text-gray-900">{{ stats.conversion_rate }}<span class="text-lg text-gray-400 font-medium">%</span></p>
                <p class="text-xs text-gray-400 mt-2 font-medium">{{ stats.pending_bookings }} booking pending</p>
            </div>
        </div>

        <!-- PIPELINE FUNNEL + PROJECT STATS -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Pipeline -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-sm font-black text-gray-900 uppercase tracking-wide">Sales Pipeline</h2>
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ stats.total_leads }} Total Leads</span>
                </div>
                <div class="flex items-end gap-3">
                    <div v-for="stage in pipelineStages" :key="stage.key" class="flex-1 text-center group">
                        <div class="relative mb-3">
                            <div :class="stage.color" class="rounded-xl py-6 font-black text-2xl group-hover:scale-105 transition-all duration-200 cursor-pointer">
                                {{ stage.count }}
                            </div>
                        </div>
                        <div class="flex items-center justify-center gap-1">
                            <div :class="stage.dot" class="w-1.5 h-1.5 rounded-full"></div>
                            <span class="text-[9px] font-bold text-gray-500 uppercase tracking-wider">{{ stage.label }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Summary -->
            <div class="bg-white rounded-2xl border border-gray-100 p-6">
                <h2 class="text-sm font-black text-gray-900 uppercase tracking-wide mb-6">Proyek Aktif</h2>
                <div v-for="project in projectStats" :key="project.id" class="mb-5 last:mb-0">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <p class="text-sm font-bold text-gray-900">{{ project.name }}</p>
                            <p class="text-[10px] text-gray-400 font-medium">{{ project.code }}</p>
                        </div>
                        <span class="text-xs font-black text-blue-600">
                            {{ project.total_units > 0 ? Math.round((project.sold_units / project.total_units) * 100) : 0 }}%
                        </span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 transition-all duration-1000" :style="{ width: (project.total_units > 0 ? (project.sold_units / project.total_units) * 100 : 0) + '%' }"></div>
                    </div>
                    <div class="flex items-center gap-4 mt-2 text-[10px] font-bold text-gray-400">
                        <span>🟢 {{ project.available_units }} tersedia</span>
                        <span>🟡 {{ project.booked_units }} booked</span>
                        <span>🔴 {{ project.sold_units }} terjual</span>
                    </div>
                </div>
                <div v-if="!projectStats?.length" class="text-center py-8 text-gray-400 text-sm">
                    Belum ada proyek aktif
                </div>
            </div>
        </div>

        <!-- RECENT LEADS TABLE -->
        <div class="bg-white rounded-2xl border border-gray-100 p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-sm font-black text-gray-900 uppercase tracking-wide">Lead Terbaru</h2>
                <a href="/leads" class="text-xs font-bold text-blue-600 hover:text-blue-700 transition-colors">Lihat Semua →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left text-[10px] font-black text-gray-400 uppercase tracking-widest pb-3 px-2">Nama</th>
                            <th class="text-left text-[10px] font-black text-gray-400 uppercase tracking-widest pb-3 px-2">Proyek</th>
                            <th class="text-left text-[10px] font-black text-gray-400 uppercase tracking-widest pb-3 px-2">Sumber</th>
                            <th class="text-center text-[10px] font-black text-gray-400 uppercase tracking-widest pb-3 px-2">Skor</th>
                            <th class="text-left text-[10px] font-black text-gray-400 uppercase tracking-widest pb-3 px-2">Status</th>
                            <th class="text-left text-[10px] font-black text-gray-400 uppercase tracking-widest pb-3 px-2">Sales</th>
                            <th class="text-right text-[10px] font-black text-gray-400 uppercase tracking-widest pb-3 px-2">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="lead in recentLeads" :key="lead.id" class="border-b border-gray-50 hover:bg-blue-50/30 transition-colors group cursor-pointer">
                            <td class="py-3 px-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-400 to-indigo-500 flex items-center justify-center text-white text-[10px] font-black shrink-0">
                                        {{ lead.name?.charAt(0) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-gray-900 group-hover:text-blue-600 transition-colors">{{ lead.name }}</p>
                                        <p class="text-[10px] text-gray-400 font-medium">{{ lead.phone }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-2 text-xs font-medium text-gray-600">{{ lead.project || '-' }}</td>
                            <td class="py-3 px-2 text-xs font-medium text-gray-500 capitalize">{{ lead.source?.replace('_', ' ') }}</td>
                            <td class="py-3 px-2 text-center">
                                <span :class="scoreColor(lead.score)" class="inline-block text-xs font-black px-2 py-0.5 rounded-full min-w-[32px]">{{ lead.score }}</span>
                            </td>
                            <td class="py-3 px-2">
                                <span :class="statusColor(lead.status)" class="inline-block text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full">{{ lead.status }}</span>
                            </td>
                            <td class="py-3 px-2 text-xs font-medium text-gray-500">{{ lead.assigned_to || '-' }}</td>
                            <td class="py-3 px-2 text-right text-[10px] font-medium text-gray-400">{{ lead.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="!recentLeads?.length" class="text-center py-12 text-gray-400">
                <p class="text-4xl mb-3">📭</p>
                <p class="text-sm font-medium">Belum ada lead masuk</p>
            </div>
        </div>

    </CrmLayout>
</template>
