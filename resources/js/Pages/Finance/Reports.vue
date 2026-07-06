<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    monthly_data: Array,
    income_by_category: Object,
    expense_by_category: Object,
    total_income: Number,
    total_expense: Number,
    net_profit: Number,
    project_summaries: Array,
    category_labels: Object,
    projects: Array,
    taxes: Object,
    filters: Object,
});

const formatCurrency = (v) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v || 0);

const activeTab = ref('cashflow'); // cashflow | pnl | taxes | project

const selectedYear = ref(props.filters?.year || new Date().getFullYear());
const selectedProject = ref(props.filters?.project_id || '');

function applyFilters() {
    router.get('/finance/reports', {
        year: selectedYear.value,
        project_id: selectedProject.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}

// Chart helpers
const maxCashflow = computed(() => {
    if (!props.monthly_data?.length) return 1;
    return Math.max(...props.monthly_data.map(m => Math.max(m.income, m.expense)), 1);
});

const maxProjectNet = computed(() => {
    if (!props.project_summaries?.length) return 1;
    return Math.max(...props.project_summaries.map(p => Math.max(Math.abs(p.income), Math.abs(p.expense))), 1);
});
</script>

<template>
    <Head title="Laporan Keuangan" />
    <CrmLayout>
        <template #breadcrumb>Keuangan / Laporan</template>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Laporan Keuangan</h1>
                <p class="text-sm text-slate-500 mt-1">Analisis lengkap arus kas, laba/rugi, dan performa per proyek.</p>
            </div>
            <div class="flex items-center gap-3">
                <Link href="/finance" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">← Kas Utama</Link>
                <a :href="`/finance/reports/export?year=${selectedYear}`" target="_blank"
                    class="px-4 py-2.5 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition-all">📥 Export CSV</a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-emerald-50 rounded-2xl p-6 border border-emerald-100 shadow-sm">
                <p class="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-1">Total Pemasukan</p>
                <h3 class="text-2xl font-black text-emerald-700">{{ formatCurrency(total_income) }}</h3>
                <p class="text-[10px] text-emerald-400 font-bold mt-2">Tahun {{ selectedYear }}</p>
            </div>
            <div class="bg-rose-50 rounded-2xl p-6 border border-rose-100 shadow-sm">
                <p class="text-[10px] font-black text-rose-500 uppercase tracking-widest mb-1">Total Pengeluaran</p>
                <h3 class="text-2xl font-black text-rose-700">{{ formatCurrency(total_expense) }}</h3>
                <p class="text-[10px] text-rose-400 font-bold mt-2">Tahun {{ selectedYear }}</p>
            </div>
            <div class="rounded-2xl p-6 border shadow-sm" :class="net_profit >= 0 ? 'bg-blue-50 border-blue-100' : 'bg-amber-50 border-amber-100'">
                <p class="text-[10px] font-black uppercase tracking-widest mb-1" :class="net_profit >= 0 ? 'text-blue-500' : 'text-amber-500'">Laba / Rugi Bersih</p>
                <h3 class="text-2xl font-black" :class="net_profit >= 0 ? 'text-blue-700' : 'text-amber-700'">{{ formatCurrency(net_profit) }}</h3>
                <p class="text-[10px] font-bold mt-2" :class="net_profit >= 0 ? 'text-blue-400' : 'text-amber-400'">{{ net_profit >= 0 ? '📈 Profit' : '📉 Loss' }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm mb-6 flex flex-col sm:flex-row items-end gap-3">
            <div class="w-full sm:w-32">
                <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Tahun</label>
                <select v-model="selectedYear" @change="applyFilters" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">
                    <option v-for="y in 5" :key="y" :value="new Date().getFullYear() - y + 1">{{ new Date().getFullYear() - y + 1 }}</option>
                </select>
            </div>
            <div class="w-full sm:w-52">
                <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Filter Proyek</label>
                <select v-model="selectedProject" @change="applyFilters" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">
                    <option value="">Semua Proyek</option>
                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>
        </div>

        <!-- Tab Nav -->
        <div class="flex gap-1 mb-6 bg-slate-100 rounded-2xl p-1 w-fit">
            <button @click="activeTab = 'cashflow'" :class="activeTab === 'cashflow' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500'"
                class="px-5 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all">📊 Arus Kas</button>
            <button @click="activeTab = 'pnl'" :class="activeTab === 'pnl' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500'"
                class="px-5 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all">📋 Laba/Rugi</button>
            <button @click="activeTab = 'taxes'" :class="activeTab === 'taxes' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500'"
                class="px-5 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all">🧾 Pajak</button>
            <button v-if="!selectedProject" @click="activeTab = 'project'" :class="activeTab === 'project' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500'"
                class="px-5 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all">🏗️ Per Proyek</button>
        </div>

        <!-- ═══ CASH FLOW TAB ═══ -->
        <div v-if="activeTab === 'cashflow'" class="bg-white rounded-3xl border border-slate-100 p-8 shadow-sm">
            <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-6">Arus Kas Bulanan — {{ selectedYear }}</h3>
            <div class="space-y-4">
                <div v-for="m in monthly_data" :key="m.month" class="flex items-center gap-4">
                    <span class="w-10 text-xs font-black text-slate-500 text-right shrink-0">{{ m.month }}</span>
                    <div class="flex-1 space-y-1">
                        <div class="flex items-center gap-2">
                            <div class="h-4 bg-emerald-500 rounded-sm transition-all duration-500" :style="`width: ${Math.max(2, (m.income / maxCashflow) * 100)}%`"></div>
                            <span class="text-[10px] font-bold text-emerald-600 whitespace-nowrap">{{ formatCurrency(m.income) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="h-4 bg-rose-500 rounded-sm transition-all duration-500" :style="`width: ${Math.max(2, (m.expense / maxCashflow) * 100)}%`"></div>
                            <span class="text-[10px] font-bold text-rose-600 whitespace-nowrap">{{ formatCurrency(m.expense) }}</span>
                        </div>
                    </div>
                    <div class="w-28 text-right shrink-0">
                        <span class="text-xs font-black" :class="m.net >= 0 ? 'text-emerald-600' : 'text-rose-600'">{{ formatCurrency(m.net) }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-4 mt-6 pt-4 border-t border-slate-100">
                <span class="flex items-center gap-1.5 text-[10px] font-bold text-slate-500"><span class="w-3 h-3 bg-emerald-500 rounded-sm"></span> Pemasukan</span>
                <span class="flex items-center gap-1.5 text-[10px] font-bold text-slate-500"><span class="w-3 h-3 bg-rose-500 rounded-sm"></span> Pengeluaran</span>
            </div>
        </div>

        <!-- ═══ P&L TAB ═══ -->
        <div v-if="activeTab === 'pnl'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Income -->
            <div class="bg-white rounded-3xl border border-slate-100 p-8 shadow-sm">
                <h3 class="text-sm font-black text-emerald-600 uppercase tracking-wider mb-6">📈 Pendapatan</h3>
                <div class="space-y-3">
                    <div v-for="(amount, cat) in income_by_category" :key="cat" class="flex items-center justify-between py-2 border-b border-dashed border-slate-100">
                        <span class="text-xs font-bold text-slate-700">{{ category_labels[cat] || cat }}</span>
                        <span class="text-xs font-black text-emerald-600">{{ formatCurrency(amount) }}</span>
                    </div>
                    <div v-if="!Object.keys(income_by_category).length" class="text-center py-4">
                        <p class="text-xs text-slate-400 font-bold italic">Belum ada data pemasukan.</p>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t-2 border-emerald-200 flex justify-between">
                    <span class="text-xs font-black text-slate-900 uppercase">Total Pendapatan</span>
                    <span class="text-sm font-black text-emerald-700">{{ formatCurrency(total_income) }}</span>
                </div>
            </div>

            <!-- Expenses -->
            <div class="bg-white rounded-3xl border border-slate-100 p-8 shadow-sm">
                <h3 class="text-sm font-black text-rose-600 uppercase tracking-wider mb-6">📉 Pengeluaran</h3>
                <div class="space-y-3">
                    <div v-for="(amount, cat) in expense_by_category" :key="cat" class="flex items-center justify-between py-2 border-b border-dashed border-slate-100">
                        <span class="text-xs font-bold text-slate-700">{{ category_labels[cat] || cat }}</span>
                        <span class="text-xs font-black text-rose-600">{{ formatCurrency(amount) }}</span>
                    </div>
                    <div v-if="!Object.keys(expense_by_category).length" class="text-center py-4">
                        <p class="text-xs text-slate-400 font-bold italic">Belum ada data pengeluaran.</p>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t-2 border-rose-200 flex justify-between">
                    <span class="text-xs font-black text-slate-900 uppercase">Total Pengeluaran</span>
                    <span class="text-sm font-black text-rose-700">{{ formatCurrency(total_expense) }}</span>
                </div>
            </div>
        </div>

        <!-- ═══ TAXES TAB ═══ -->
        <div v-if="activeTab === 'taxes'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- PPN (Value Added Tax) -->
            <div class="bg-white rounded-3xl border border-slate-100 p-8 shadow-sm">
                <h3 class="text-sm font-black text-blue-600 uppercase tracking-wider mb-6">🧾 Pajak Pertambahan Nilai (PPN)</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-2.5 border-b border-dashed border-slate-100">
                        <div>
                            <p class="text-xs font-bold text-slate-800">PPN Keluaran (Collected)</p>
                            <p class="text-[9px] text-slate-400 font-bold mt-0.5">Ditarik 11% dari pemesanan konsumen</p>
                        </div>
                        <span class="text-xs font-black text-emerald-600">+ {{ formatCurrency(taxes.ppn_keluaran) }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2.5 border-b border-dashed border-slate-100">
                        <div>
                            <p class="text-xs font-bold text-slate-800">PPN Masukan (Paid)</p>
                            <p class="text-[9px] text-slate-400 font-bold mt-0.5">Dibayarkan kepada vendor/kontraktor</p>
                        </div>
                        <span class="text-xs font-black text-rose-600">- {{ formatCurrency(taxes.ppn_masukan) }}</span>
                    </div>
                    
                    <div class="mt-6 pt-4 border-t-2 border-blue-200 flex justify-between items-center">
                        <div>
                            <p class="text-xs font-black text-slate-900 uppercase">Selisih PPN</p>
                            <p class="text-[9px] text-slate-400 font-bold mt-0.5">Kurang / (Lebih) Bayar PPN</p>
                        </div>
                        <span class="text-sm font-black text-blue-700">{{ formatCurrency(taxes.ppn_net) }}</span>
                    </div>
                </div>
            </div>

            <!-- PPh (Income Tax) -->
            <div class="bg-white rounded-3xl border border-slate-100 p-8 shadow-sm">
                <h3 class="text-sm font-black text-amber-600 uppercase tracking-wider mb-6">🧾 Pajak Penghasilan (PPh)</h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-2.5 border-b border-dashed border-slate-100">
                        <div>
                            <p class="text-xs font-bold text-slate-800">PPh Final Pengalihan Tanah/Bangunan</p>
                            <p class="text-[9px] text-slate-400 font-bold mt-0.5">Tarif 2.5% dari harga jual transaksi unit</p>
                        </div>
                        <span class="text-xs font-black text-slate-900">{{ formatCurrency(taxes.pph_final) }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2.5 border-b border-dashed border-slate-100">
                        <div>
                            <p class="text-xs font-bold text-slate-800">PPh Jasa / Konstruksi / Lainnya</p>
                            <p class="text-[9px] text-slate-400 font-bold mt-0.5">Potongan PPh 21/23 atas upah/vendor</p>
                        </div>
                        <span class="text-xs font-black text-slate-900">{{ formatCurrency(taxes.pph_jasa) }}</span>
                    </div>
                    
                    <div class="mt-6 pt-4 border-t-2 border-amber-200 flex justify-between items-center">
                        <div>
                            <p class="text-xs font-black text-slate-900 uppercase">Total Kewajiban PPh</p>
                            <p class="text-[9px] text-slate-400 font-bold mt-0.5">Total estimasi PPh tahun {{ selectedYear }}</p>
                        </div>
                        <span class="text-sm font-black text-amber-700">{{ formatCurrency(taxes.pph_final + taxes.pph_jasa) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ PER PROJECT TAB ═══ -->
        <div v-if="activeTab === 'project'" class="bg-white rounded-3xl border border-slate-100 overflow-hidden shadow-sm">
            <div class="px-6 py-5 border-b border-slate-100">
                <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">Performa Per Proyek — {{ selectedYear }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">
                            <th class="px-6 py-4">Proyek</th>
                            <th class="px-6 py-4 text-right">Pemasukan</th>
                            <th class="px-6 py-4 text-right">Pengeluaran</th>
                            <th class="px-6 py-4 text-right">Laba/Rugi</th>
                            <th class="px-6 py-4">Visualisasi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="proj in project_summaries" :key="proj.id" class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 font-black text-slate-900">{{ proj.name }}</td>
                            <td class="px-6 py-4 text-right font-black text-emerald-600">{{ formatCurrency(proj.income) }}</td>
                            <td class="px-6 py-4 text-right font-black text-rose-600">{{ formatCurrency(proj.expense) }}</td>
                            <td class="px-6 py-4 text-right font-black" :class="proj.net >= 0 ? 'text-blue-600' : 'text-amber-600'">{{ formatCurrency(proj.net) }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-1 w-40">
                                    <div class="h-3 bg-emerald-500 rounded-sm" :style="`width: ${(proj.income / maxProjectNet) * 100}%`"></div>
                                    <div class="h-3 bg-rose-500 rounded-sm" :style="`width: ${(proj.expense / maxProjectNet) * 100}%`"></div>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!project_summaries?.length">
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400 italic font-bold">Belum ada data keuangan per proyek.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </CrmLayout>
</template>
