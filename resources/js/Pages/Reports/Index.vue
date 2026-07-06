<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    salesByProject: Array,
    revenueTrend: Array,
    leadSources: Array,
    commissions: Object,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};
</script>

<template>
    <Head title="Management Reports" />
    <CrmLayout>
        <template #breadcrumb>Laporan Manajemen</template>

        <div class="mb-8">
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Executive Summary</h1>
            <p class="text-sm text-slate-500 mt-1">Data performa bisnis real-time untuk pengambilan keputusan.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-2">Total Komisi Dibayar</p>
                <h3 class="text-xl font-black text-emerald-600">{{ formatCurrency(commissions.paid) }}</h3>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-2">Hutang Komisi</p>
                <h3 class="text-xl font-black text-rose-600">{{ formatCurrency(commissions.unpaid) }}</h3>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm lg:col-span-2">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-2">Total Proyek</p>
                <h3 class="text-xl font-black text-slate-900">{{ salesByProject.length }} Proyek Berjalan</h3>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Sales by Project -->
            <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-6 flex items-center gap-2">
                    <span class="w-2 h-6 bg-blue-600 rounded-full"></span>
                    Penjualan per Proyek
                </h2>
                <div class="space-y-4">
                    <div v-for="project in salesByProject" :key="project.id" class="group">
                        <div class="flex justify-between items-end mb-2">
                            <div>
                                <p class="text-sm font-bold text-slate-700">{{ project.name }}</p>
                                <p class="text-[10px] text-slate-400">{{ project.code }}</p>
                            </div>
                            <span class="text-sm font-black text-slate-900">{{ project.total_sales }} Unit</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full" 
                                :style="{ width: (project.total_sales > 0 ? (project.total_sales / 20) * 100 : 0) + '%' }"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lead Conversion by Source -->
            <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-6 flex items-center gap-2">
                    <span class="w-2 h-6 bg-amber-500 rounded-full"></span>
                    Sumber Lead Efektif
                </h2>
                <div class="grid grid-cols-2 gap-4">
                    <div v-for="source in leadSources" :key="source.source" class="bg-slate-50 p-4 rounded-xl border border-slate-100 group hover:border-amber-200 transition-colors">
                        <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">{{ source.source?.replace('_', ' ') || 'Unknown' }}</p>
                        <h4 class="text-xl font-black text-slate-900 group-hover:text-amber-600 transition-colors flex justify-between items-baseline">
                            <span>{{ source.total }} <span class="text-xs text-slate-400 font-medium">Leads</span></span>
                            <span class="text-xs font-bold text-emerald-600">Won: {{ source.won_count }}</span>
                        </h4>
                        <p class="text-[9px] text-slate-400 mt-1 font-semibold">Konversi: {{ source.conversion_rate }}%</p>
                    </div>
                </div>
            </div>

            <!-- Revenue Trend Table -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
                <div class="px-6 py-4 border-b border-slate-50">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Tren Pendapatan Bulanan</h2>
                </div>
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50 text-[10px] font-black text-slate-500 uppercase tracking-wider">
                            <th class="px-6 py-4">Bulan</th>
                            <th class="px-6 py-4 text-right">Total Uang Masuk</th>
                            <th class="px-6 py-4 text-center">Status Pertumbuhan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="trend in revenueTrend" :key="trend.month" class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 font-bold text-slate-700">{{ trend.month }}</td>
                            <td class="px-6 py-4 text-right font-black text-blue-600">{{ formatCurrency(trend.total) }}</td>
                            <td class="px-6 py-4 text-center">
                                <span v-if="trend.growth === null" class="text-[10px] font-black px-2 py-1 bg-slate-100 text-slate-500 rounded-md uppercase tracking-wider">Tahun/Bulan Awal</span>
                                <span v-else-if="trend.growth > 0" class="text-[10px] font-black px-2 py-1 bg-emerald-50 text-emerald-600 rounded-md uppercase tracking-wider">▲ +{{ trend.growth }}% NAIK</span>
                                <span v-else-if="trend.growth < 0" class="text-[10px] font-black px-2 py-1 bg-rose-50 text-rose-600 rounded-md uppercase tracking-wider">▼ {{ trend.growth }}% TURUN</span>
                                <span v-else class="text-[10px] font-black px-2 py-1 bg-slate-100 text-slate-600 rounded-md uppercase tracking-wider">STAGNAN</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </CrmLayout>
</template>
