<script setup>
import { Head, Link } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const props = defineProps({
    sheet: Object,
});

const formatCurrency = (value) => {
    if (!value) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};

const scoreBadgeClasses = {
    high: 'bg-emerald-100 text-emerald-800 border-emerald-300',
    medium: 'bg-amber-100 text-amber-800 border-amber-300',
    low: 'bg-rose-100 text-rose-800 border-rose-300',
};
</script>

<template>
    <Head :title="`Detail Neraca KPR - ${sheet.client_name}`" />
    <CrmLayout>
        <template #breadcrumb>
            <Link href="/kpr-scoring" class="hover:underline">Analisis Neraca KPR</Link>
            <span class="mx-1">/</span>
            <span>Detail {{ sheet.client_name }}</span>
        </template>

        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ sheet.client_name }}</h1>
                        <span :class="scoreBadgeClasses[sheet.approval_score]" class="px-3 py-1 text-xs font-black uppercase rounded-full border shadow-sm">
                            Skor KPR: {{ sheet.approval_score }} ({{ sheet.total_points }} Pts)
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">
                        {{ sheet.company_name ? sheet.company_name + ' • ' : '' }}{{ sheet.business_type }} • Telp: {{ sheet.phone || '-' }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <Link href="/kpr-scoring" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all shadow-sm">
                        ← Kembali
                    </Link>
                    <button onclick="window.print()" class="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition-all shadow-md">
                        🖨️ Cetak Neraca
                    </button>
                </div>
            </div>

            <!-- SUMMARY CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Aktiva (Aset)</p>
                    <p class="text-xl font-black text-slate-900 mt-1">{{ formatCurrency(sheet.total_assets) }}</p>
                </div>
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Kewajiban (Hutang)</p>
                    <p class="text-xl font-black text-rose-600 mt-1">{{ formatCurrency(sheet.total_liabilities) }}</p>
                </div>
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Ekuitas Bersih</p>
                    <p class="text-xl font-black text-blue-600 mt-1">{{ formatCurrency(sheet.total_equity) }}</p>
                </div>
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Laba Bersih Bulanan</p>
                    <p class="text-xl font-black text-emerald-600 mt-1">{{ formatCurrency(sheet.monthly_net_profit) }}</p>
                </div>
            </div>

            <!-- NERACA TABLES -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- AKTIVA -->
                <div class="bg-white rounded-3xl border border-slate-100 p-6 shadow-sm space-y-4">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-900 border-b border-slate-100 pb-3 flex justify-between">
                        <span>📈 AKTIVA (ASET)</span>
                        <span class="text-blue-600">{{ formatCurrency(sheet.total_assets) }}</span>
                    </h3>
                    <div class="space-y-2 text-xs font-bold text-slate-700">
                        <div class="flex justify-between py-1.5 border-b border-slate-50">
                            <span class="text-slate-500">Kas & Bank</span>
                            <span>{{ formatCurrency(sheet.cash_and_bank) }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-slate-50">
                            <span class="text-slate-500">Persediaan Barang (Inventory)</span>
                            <span>{{ formatCurrency(sheet.inventory) }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-slate-50">
                            <span class="text-slate-500">Piutang Usaha</span>
                            <span>{{ formatCurrency(sheet.receivables) }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-slate-50">
                            <span class="text-slate-500">Aset Lancar Lainnya</span>
                            <span>{{ formatCurrency(sheet.other_current_assets) }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-slate-50">
                            <span class="text-slate-500">Peralatan Usaha</span>
                            <span>{{ formatCurrency(sheet.equipment) }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-slate-50">
                            <span class="text-slate-500">Kendaraan Usaha</span>
                            <span>{{ formatCurrency(sheet.vehicles) }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-slate-50">
                            <span class="text-slate-500">Mesin & Bangunan</span>
                            <span>{{ formatCurrency(sheet.machinery_and_buildings) }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 text-rose-600">
                            <span>Akumulasi Penyusutan (-)</span>
                            <span>- {{ formatCurrency(sheet.accumulated_depreciation) }}</span>
                        </div>
                    </div>
                </div>

                <!-- PASIVA -->
                <div class="bg-white rounded-3xl border border-slate-100 p-6 shadow-sm space-y-4">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-900 border-b border-slate-100 pb-3 flex justify-between">
                        <span>📉 PASIVA (KEWAJIBAN & EKUITAS)</span>
                        <span class="text-rose-600">{{ formatCurrency(sheet.total_liabilities + sheet.total_equity) }}</span>
                    </h3>
                    <div class="space-y-2 text-xs font-bold text-slate-700">
                        <div class="flex justify-between py-1.5 border-b border-slate-50">
                            <span class="text-slate-500">Hutang Dagang / Usaha</span>
                            <span>{{ formatCurrency(sheet.trade_payables) }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-slate-50">
                            <span class="text-slate-500">Hutang Bank / Pinjaman</span>
                            <span>{{ formatCurrency(sheet.bank_loans) }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-slate-50">
                            <span class="text-slate-500">Kewajiban Lainnya</span>
                            <span>{{ formatCurrency(sheet.other_liabilities) }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-slate-50 font-black text-slate-900">
                            <span>Modal Disetor</span>
                            <span>{{ formatCurrency(sheet.capital) }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 border-b border-slate-50 font-black text-slate-900">
                            <span>Laba Ditahan</span>
                            <span>{{ formatCurrency(sheet.retained_earnings) }}</span>
                        </div>
                        <div class="flex justify-between py-1.5 text-rose-600">
                            <span>Prive / Pengambilan Pribadi (-)</span>
                            <span>- {{ formatCurrency(sheet.drawings_prive) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ANALISIS RASIO KPR -->
            <div class="bg-slate-900 rounded-3xl p-8 text-white shadow-xl space-y-6">
                <h3 class="text-xs font-black uppercase tracking-widest text-blue-400">📊 Analisis Rasio Kelayakan KPR</h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white/5 rounded-2xl p-5 border border-white/10">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Current Ratio (Likuiditas)</p>
                        <p class="text-2xl font-black text-emerald-400 mt-1">{{ sheet.current_ratio }}x</p>
                        <p class="text-[10px] text-slate-400 mt-1">Standar Sehat Bank: ≥ 1.5x</p>
                    </div>

                    <div class="bg-white/5 rounded-2xl p-5 border border-white/10">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Debt to Equity Ratio (DER)</p>
                        <p class="text-2xl font-black text-blue-400 mt-1">{{ sheet.debt_to_equity_ratio }}x</p>
                        <p class="text-[10px] text-slate-400 mt-1">Standar Sehat Bank: ≤ 2.0x</p>
                    </div>

                    <div class="bg-white/5 rounded-2xl p-5 border border-white/10">
                        <p class="text-[10px] font-bold text-slate-400 uppercase">Debt Service Coverage (DSCR)</p>
                        <p class="text-2xl font-black text-amber-400 mt-1">{{ sheet.dscr }}x</p>
                        <p class="text-[10px] text-slate-400 mt-1">Standar Sehat Bank: ≥ 1.3x</p>
                    </div>
                </div>

                <div class="pt-4 border-t border-white/10">
                    <p class="text-xs text-slate-300 italic">
                        <strong>Catatan Analis:</strong> {{ sheet.notes || 'Belum ada catatan analisis khusus.' }}
                    </p>
                </div>
            </div>
        </div>
    </CrmLayout>
</template>
