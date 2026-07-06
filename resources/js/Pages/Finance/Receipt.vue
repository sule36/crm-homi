<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    transaction: Object,
    spelled_text: String,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};

const printReceipt = () => {
    window.print();
};
</script>

<template>
    <Head title="Kwitansi Pembayaran" />
    <div class="min-h-screen bg-slate-100 py-12 px-4 print:bg-white print:py-0">
        <!-- Actions (Hidden during print) -->
        <div class="max-w-3xl mx-auto flex items-center justify-between mb-6 print:hidden">
            <button onclick="window.history.back()" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all shadow-sm">
                ← Kembali
            </button>
            <button @click="printReceipt" class="px-5 py-2.5 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition-all shadow-md">
                🖨️ Cetak Kwitansi
            </button>
        </div>

        <!-- Premium Kwitansi Layout -->
        <div id="receipt-paper" class="max-w-3xl mx-auto bg-white rounded-3xl border border-slate-200 shadow-xl overflow-hidden p-10 relative print:shadow-none print:border-none print:p-0">
            <!-- Background Watermark -->
            <div class="absolute inset-0 opacity-[0.02] flex items-center justify-center pointer-events-none select-none">
                <span class="text-9xl font-black rotate-[25deg]">HOMI</span>
            </div>

            <!-- Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-6 border-b border-slate-200">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center text-white font-black text-lg shadow-lg">
                        H
                    </div>
                    <div>
                        <h2 class="text-lg font-black text-slate-900 tracking-tight leading-none">Homi Developer</h2>
                        <p class="text-[10px] text-blue-500 font-bold uppercase tracking-wider mt-1">Premium Living & Housing</p>
                    </div>
                </div>
                <div class="text-right sm:text-right">
                    <h1 class="text-xl font-black text-slate-800 uppercase tracking-widest">Kwitansi</h1>
                    <p class="text-xs text-slate-400 font-bold mt-1">No: Receipt-{{ transaction.id }}</p>
                </div>
            </div>

            <!-- Grid Details -->
            <div class="mt-8 space-y-6">
                <!-- Row 1: Telah Terima Dari -->
                <div class="flex flex-col sm:flex-row border-b border-slate-100 pb-3">
                    <span class="w-48 text-[10px] font-black text-slate-400 uppercase tracking-wider shrink-0">Telah Terima Dari</span>
                    <span class="text-sm font-black text-slate-800">{{ transaction.booking?.lead?.name || 'Customer' }}</span>
                </div>

                <!-- Row 2: Uang Sejumlah (Terbilang) -->
                <div class="flex flex-col sm:flex-row border-b border-slate-100 pb-3">
                    <span class="w-48 text-[10px] font-black text-slate-400 uppercase tracking-wider shrink-0">Uang Sejumlah</span>
                    <span class="text-xs font-bold text-slate-700 italic bg-slate-50 px-3 py-1.5 rounded-lg flex-1 border border-slate-100">
                        " {{ spelled_text }} "
                    </span>
                </div>

                <!-- Row 3: Untuk Pembayaran -->
                <div class="flex flex-col sm:flex-row border-b border-slate-100 pb-3">
                    <span class="w-48 text-[10px] font-black text-slate-400 uppercase tracking-wider shrink-0">Untuk Pembayaran</span>
                    <div class="flex-1">
                        <p class="text-sm font-black text-slate-800">
                            {{ transaction.notes || 'Cicilan / Pembayaran Unit' }}
                        </p>
                        <p class="text-[10px] text-slate-500 font-medium mt-1">
                            Unit Kavling: Blok {{ transaction.booking?.unit?.block }} No. {{ transaction.booking?.unit?.number }} 
                            • Proyek: {{ transaction.booking?.unit?.project?.name }}
                        </p>
                    </div>
                </div>

                <!-- Row 4: Metode & Akun Bank -->
                <div class="flex flex-col sm:flex-row border-b border-slate-100 pb-3">
                    <span class="w-48 text-[10px] font-black text-slate-400 uppercase tracking-wider shrink-0">Metode & Rekening</span>
                    <span class="text-xs font-bold text-slate-700">
                        {{ transaction.payment_method?.toUpperCase() }} 
                        <span v-if="transaction.bank_account"> ({{ transaction.bank_account?.name }} - {{ transaction.bank_account?.account_number }})</span>
                        <span v-else-if="transaction.bank_name"> ({{ transaction.bank_name }} - {{ transaction.reference_number || '-' }})</span>
                    </span>
                </div>
            </div>

            <!-- Amount & Signature Row -->
            <div class="mt-12 flex flex-col sm:flex-row justify-between items-center gap-8">
                <!-- Amount Block -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-2xl px-6 py-4 shadow-inner">
                    <p class="text-[9px] font-black text-blue-400 uppercase tracking-widest">Total Bayar</p>
                    <p class="text-2xl font-black text-blue-700 mt-1">{{ formatCurrency(transaction.amount) }}</p>
                </div>

                <!-- Signature & Stamp -->
                <div class="flex items-center gap-10">
                    <!-- Paid Stamp -->
                    <div class="border-4 border-emerald-500 text-emerald-500 rounded-2xl px-4 py-2 font-black text-lg uppercase tracking-widest rotate-[-12deg] select-none opacity-80 print:opacity-100">
                        LUNAS
                    </div>

                    <!-- Signature Box -->
                    <div class="text-center w-40">
                        <p class="text-[10px] font-bold text-slate-500">{{ new Date(transaction.created_at).toLocaleDateString('id-ID', { dateStyle: 'long' }) }}</p>
                        <div class="h-20 flex items-center justify-center">
                            <!-- Digital Signature Placeholder / Stamp -->
                            <div class="text-[10px] text-slate-300 font-bold italic tracking-wide">Tanda Tangan Cashier</div>
                        </div>
                        <p class="text-xs font-black text-slate-800 border-t border-slate-200 pt-1.5">{{ transaction.recorded_by_user?.name ?? 'Keuangan Homi' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    body * { visibility: hidden; }
    #receipt-paper, #receipt-paper * { visibility: visible; }
    #receipt-paper { position: absolute; left: 0; top: 0; width: 100%; border: none; padding: 0; margin: 0; }
}
</style>
