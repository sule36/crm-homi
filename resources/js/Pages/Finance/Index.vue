<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

import { ref, computed } from 'vue';

const props = defineProps({
    transactions: Object,
    overdue_schedules: Array,
    stats: Object,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};

// Search & Filter State
const searchTx = ref('');
const filterMethod = ref('');

// Computed filtered transactions local search/filter
const filteredTransactions = computed(() => {
    if (!props.transactions?.data) return [];
    return props.transactions.data.filter(tx => {
        const matchesSearch = !searchTx.value || 
            tx.booking?.lead?.name?.toLowerCase().includes(searchTx.value.toLowerCase()) || 
            tx.booking?.unit?.code?.toLowerCase().includes(searchTx.value.toLowerCase());
        const matchesMethod = !filterMethod.value || tx.payment_method === filterMethod.value;
        return matchesSearch && matchesMethod;
    });
});

// WA Billing Template Generator
const triggerWaReminder = (schedule) => {
    const buyerName = schedule.booking?.lead?.name || 'Konsumen';
    const unitCode = schedule.booking?.unit ? `Blok ${schedule.booking.unit.block} No. ${schedule.booking.unit.number}` : 'Unit';
    const projectName = schedule.booking?.unit?.project?.name || 'Proyek Homi';
    const amountStr = formatCurrency(schedule.amount);
    const dueDateStr = new Date(schedule.due_date).toLocaleDateString('id-ID', { dateStyle: 'long' });
    const phone = schedule.booking?.lead?.phone?.replace(/^0/, '62') || '';

    const text = `Halo Bapak/Ibu *${buyerName}*,\n\nKami dari divisi keuangan Homi Developer menginformasikan bahwa tagihan KPR/Cicilan untuk unit *${unitCode}* (*${projectName}*) sebesar *${amountStr}* telah melewati tanggal jatuh tempo pada *${dueDateStr}*.\n\nMohon untuk segera melakukan pembayaran dan konfirmasi melalui sistem. Terima kasih atas perhatiannya.`;
    
    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(text)}`, '_blank');
};

const triggerWaReceipt = (tx) => {
    const buyerName = tx.booking?.lead?.name || 'Konsumen';
    const unitCode = tx.booking?.unit ? `Blok ${tx.booking.unit.block} No. ${tx.booking.unit.number}` : 'Unit';
    const projectName = tx.booking?.unit?.project?.name || 'Proyek Homi';
    const amountStr = formatCurrency(tx.amount);
    const dateStr = new Date(tx.created_at).toLocaleDateString('id-ID', { dateStyle: 'long' });
    const phone = tx.booking?.lead?.phone?.replace(/^0/, '62') || '';
    const receiptUrl = `${window.location.origin}/finance/transactions/${tx.id}/receipt`;

    const text = `Halo Bapak/Ibu *${buyerName}*,\n\nTerima kasih, pembayaran Anda untuk unit *${unitCode}* (*${projectName}*) sebesar *${amountStr}* telah kami terima pada tanggal *${dateStr}*.\n\nBerikut adalah tautan bukti pembayaran (Kwitansi Digital) Anda:\n${receiptUrl}\n\nSalam,\n*Keuangan Homi Developer*`;
    
    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(text)}`, '_blank');
};
</script>

<template>
    <Head title="Keuangan" />
    <CrmLayout>
        <template #breadcrumb>Keuangan</template>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Manajemen Keuangan</h1>
                <p class="text-sm text-slate-500 mt-1">Pantau arus kas dan riwayat pembayaran konsumen.</p>
            </div>
            <Link href="/finance/create"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Catat Pembayaran
            </Link>
        </div>

        <!-- STATS CARDS -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Revenue</p>
                <h3 class="text-2xl font-black text-slate-900">{{ formatCurrency(stats.total_revenue) }}</h3>
                <p class="text-[10px] text-emerald-500 font-bold mt-2">Accumulative</p>
            </div>
            <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Bulan Ini</p>
                <h3 class="text-2xl font-black text-blue-600">{{ formatCurrency(stats.monthly_revenue) }}</h3>
                <p class="text-[10px] text-blue-400 font-bold mt-2">Mtd Performance</p>
            </div>
            <div class="bg-rose-50 rounded-2xl p-6 border border-rose-100 shadow-sm">
                <p class="text-[10px] font-black text-rose-400 uppercase tracking-widest mb-1">Piutang Jatuh Tempo</p>
                <h3 class="text-2xl font-black text-rose-600">{{ formatCurrency(stats.overdue_payments) }}</h3>
                <p class="text-[10px] text-rose-400 font-bold mt-2">Segera Follow Up!</p>
            </div>
            <div class="bg-indigo-50 rounded-2xl p-6 border border-indigo-100 shadow-sm">
                <p class="text-[10px] font-black text-indigo-600 uppercase tracking-widest mb-1">Proyeksi Depan</p>
                <h3 class="text-2xl font-black text-indigo-700">{{ formatCurrency(stats.projections?.reduce((a,b) => a + Number(b.total), 0) || 0) }}</h3>
                <p class="text-[10px] text-indigo-400 font-bold mt-2">Future Revenue</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- CASHFLOW FORECAST -->
            <div class="lg:col-span-1 bg-slate-900 rounded-3xl p-8 text-white shadow-xl">
                <h3 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-6">Cashflow Forecast</h3>
                <div v-if="stats.projections?.length" class="space-y-6">
                    <div v-for="proj in stats.projections" :key="proj.month" class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-black text-white uppercase tracking-tight">{{ new Date(proj.month).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' }) }}</p>
                            <div class="w-32 h-1.5 bg-slate-800 rounded-full mt-2 overflow-hidden">
                                <div class="h-full bg-blue-500" :style="`width: ${Math.min(100, (proj.total / 100000000) * 100)}%`"></div>
                            </div>
                        </div>
                        <p class="text-sm font-black text-blue-400">{{ formatCurrency(proj.total) }}</p>
                    </div>
                </div>
                <div v-else class="text-center py-10">
                    <p class="text-xs text-slate-500 font-bold uppercase tracking-widest">Belum ada proyeksi tagihan.</p>
                </div>
            </div>

            <!-- TRANSACTIONS TABLE -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-100 overflow-hidden shadow-sm flex flex-col">
                <div class="px-6 py-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Riwayat Transaksi</h2>
                        <p class="text-[10px] text-slate-400 font-bold mt-0.5">Riwayat pembayaran dana yang masuk ke sistem.</p>
                    </div>
                    <a href="/finance/export" target="_blank" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-sm text-center">
                        📥 Export CSV
                    </a>
                </div>

                <!-- Search & Filters Row -->
                <div class="p-6 bg-slate-50 border-b border-slate-100 flex flex-col sm:flex-row gap-3">
                    <div class="flex-1 relative">
                        <input v-model="searchTx" type="text" placeholder="Cari nama konsumen atau kode unit..." 
                            class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500" />
                    </div>
                    <div class="w-full sm:w-48">
                        <select v-model="filterMethod" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 cursor-pointer focus:ring-1 focus:ring-blue-500">
                            <option value="">Semua Metode</option>
                            <option value="transfer">Transfer Bank</option>
                            <option value="cash">Cash Keras</option>
                            <option value="kpr">KPR Bank</option>
                            <option value="check">Cek / Giro</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                <th class="px-6 py-4">Tgl</th>
                                <th class="px-6 py-4">Konsumen / Unit</th>
                                <th class="px-6 py-4">Metode</th>
                                <th class="px-6 py-4 text-right">Jumlah</th>
                                <th class="px-6 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="tx in filteredTransactions" :key="tx.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <span class="font-bold text-slate-700">{{ new Date(tx.created_at).toLocaleDateString('id-ID') }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-black text-slate-900">{{ tx.booking?.lead?.name }}</p>
                                    <p class="text-[10px] text-slate-500 font-bold uppercase">{{ tx.booking?.unit ? `Blok ${tx.booking.unit.block} No. ${tx.booking.unit.number}` : '-' }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-0.5 bg-slate-100 text-slate-650 rounded text-[9px] font-black uppercase">{{ tx.payment_method }}</span>
                                </td>
                                <td class="px-6 py-4 text-right font-black text-blue-600">
                                    {{ formatCurrency(tx.amount) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a :href="`/finance/transactions/${tx.id}/receipt`" target="_blank" class="px-2 py-1 bg-slate-100 text-slate-700 hover:bg-slate-200 text-[10px] font-black rounded-lg transition-all flex items-center gap-1">
                                            📄 Kwitansi
                                        </a>
                                        <button @click="triggerWaReceipt(tx)" class="px-2 py-1 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-[10px] font-black rounded-lg transition-all flex items-center gap-1">
                                            💬 WA
                                        </button>
                                        <Link :href="`/bookings/${tx.booking_id}`" class="px-2 py-1 bg-slate-900 text-white hover:bg-slate-800 text-[10px] font-black rounded-lg transition-all">
                                            👁️ Detail
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!filteredTransactions.length">
                                <td colspan="5" class="px-6 py-16 text-center">
                                    <p class="text-3xl mb-3">💸</p>
                                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest">Tidak Ada Riwayat Transaksi</p>
                                    <p class="text-xs text-slate-500 mt-1">Tidak ada catatan pembayaran yang cocok dengan kriteria filter.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- OVERDUE RECEIVABLES PANEL -->
        <div class="bg-white rounded-3xl border border-slate-100 overflow-hidden shadow-sm">
            <div class="px-6 py-5 border-b border-slate-100">
                <h3 class="text-xs font-black uppercase tracking-widest text-rose-600">Daftar Piutang Jatuh Tempo (Overdue Receivables)</h3>
                <p class="text-[10px] text-slate-400 font-bold mt-0.5">Segera hubungi konsumen di bawah yang melewati masa jatuh tempo pembayaran.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">
                            <th class="px-6 py-4">Konsumen</th>
                            <th class="px-6 py-4">Proyek / Unit</th>
                            <th class="px-6 py-4">Keterangan Tagihan</th>
                            <th class="px-6 py-4 text-center">Jatuh Tempo</th>
                            <th class="px-6 py-4 text-right">Jumlah Tagihan</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="sch in overdue_schedules" :key="sch.id" class="hover:bg-rose-50/20 transition-colors">
                            <td class="px-6 py-4">
                                <p class="font-black text-slate-900">{{ sch.booking?.lead?.name }}</p>
                                <p class="text-[10px] text-slate-500 font-bold">{{ sch.booking?.lead?.phone }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-bold text-slate-800">{{ sch.booking?.unit?.project?.name }}</p>
                                <p class="text-[10px] text-slate-500 font-black uppercase">{{ sch.booking?.unit ? `Blok ${sch.booking.unit.block} No. ${sch.booking.unit.number}` : '-' }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-0.5 bg-rose-50 text-rose-600 rounded text-[9px] font-black uppercase">{{ sch.label || `Cicilan #${sch.installment_number}` }}</span>
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-rose-600">
                                {{ new Date(sch.due_date).toLocaleDateString('id-ID', { dateStyle: 'medium' }) }}
                            </td>
                            <td class="px-6 py-4 text-right font-black text-slate-900">
                                {{ formatCurrency(sch.amount) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button @click="triggerWaReminder(sch)" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-[10px] uppercase rounded-xl transition-all shadow-md shadow-emerald-500/10">
                                    💬 Tagih WA
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!overdue_schedules?.length">
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400 italic font-bold">
                                Tidak ada piutang jatuh tempo. Arus kas berjalan lancar! 🎉
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </CrmLayout>
</template>
