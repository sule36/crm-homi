<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    transaction: Object,
    spelled_text: String,
    settings: Object,
});

const wetForm = useForm({
    wet_receipt_file: null,
});

const handleWetUpload = (e) => {
    wetForm.wet_receipt_file = e.target.files[0];
};

const submitWetReceipt = () => {
    if (!wetForm.wet_receipt_file) return alert('Silakan pilih berkas scan kwitansi basah terlebih dahulu.');
    wetForm.post(`/finance/transactions/${props.transaction.id}/wet-receipt`, {
        preserveScroll: true,
        onSuccess: () => alert('Berkas Kwitansi Basah Asli berhasil diunggah!'),
    });
};

const sendWaReceipt = () => {
    const name = props.transaction.booking?.lead?.name || 'Konsumen';
    const amountStr = formatCurrency(props.transaction.amount);
    const unitStr = props.transaction.booking?.unit ? `Blok ${props.transaction.booking.unit.block} No. ${props.transaction.booking.unit.number}` : 'Unit';
    const projStr = props.transaction.booking?.unit?.project?.name || props.settings?.company_name || 'Developer Properti';
    const rawPhone = props.transaction.booking?.lead?.phone || '';
    const phone = rawPhone.replace(/\D/g, '').replace(/^0/, '62');
    const receiptUrl = `${window.location.origin}/finance/transactions/${props.transaction.id}/receipt`;

    const message = `Halo Bapak/Ibu *${name}*,\n\nTerima kasih, pembayaran Anda untuk unit *${unitStr}* (*${projStr}*) sebesar *${amountStr}* telah kami terima dengan tanda tangan & cap stempel resmi.\n\nBerikut link Kwitansi Resmi Anda:\n${receiptUrl}\n\nSalam,\n*Keuangan ${projStr}*`;

    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank');
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};

const printReceipt = () => {
    window.print();
};

const bookingReceiptSettings = props.transaction.booking?.receipt_settings || {};

const projectLogo = props.transaction.booking?.unit?.project?.logo || null;
const logoImage = projectLogo || props.settings?.company_logo || null;
const companyName = props.transaction.booking?.unit?.project?.name || props.settings?.company_name || 'Developer Properti';
const projectCode = props.transaction.booking?.unit?.project?.code || 'ALN';
const txYear = new Date(props.transaction.created_at).getFullYear();
const paddedId = String(props.transaction.id).padStart(4, '0');

let receiptNumber = props.transaction.receipt_number;
if (!receiptNumber) {
    if (bookingReceiptSettings.receipt_number_custom) {
        receiptNumber = bookingReceiptSettings.receipt_number_custom;
    } else if (bookingReceiptSettings.receipt_number_prefix) {
        receiptNumber = `${bookingReceiptSettings.receipt_number_prefix}/${txYear}/${paddedId}`;
    } else {
        receiptNumber = `KW/${projectCode.toUpperCase()}/${txYear}/${paddedId}`;
    }
}

const slotKey = bookingReceiptSettings.receipt_sig_slot || 'sig1';

let defaultSigImage = props.settings?.spr_signatures?.sig1_image || null;
let defaultTitle = props.transaction.booking?.sig1_title || props.settings?.spr_signatures?.sig1_title || 'Kasir & Keuangan';
let defaultName = props.transaction.booking?.sig1_name || props.settings?.spr_signatures?.sig1_name || props.transaction.recorded_by_user?.name || 'Keuangan';

if (slotKey === 'sig2') {
    defaultSigImage = props.settings?.spr_signatures?.sig2_image || defaultSigImage;
    defaultTitle = props.transaction.booking?.sig2_title || props.settings?.spr_signatures?.sig2_title || 'Direktur';
    defaultName = props.transaction.booking?.sig2_name || props.settings?.spr_signatures?.sig2_name || defaultName;
} else if (slotKey === 'sig3') {
    defaultSigImage = props.settings?.spr_signatures?.sig3_image || defaultSigImage;
    defaultTitle = props.transaction.booking?.sig3_title || props.settings?.spr_signatures?.sig3_title || 'Sales';
    defaultName = props.transaction.booking?.sig3_name || props.settings?.spr_signatures?.sig3_name || defaultName;
} else if (slotKey === 'sig4') {
    defaultSigImage = props.settings?.spr_signatures?.sig4_image || defaultSigImage;
    defaultTitle = props.transaction.booking?.sig4_title || props.settings?.spr_signatures?.sig4_title || 'Penanda Tangan';
    defaultName = props.transaction.booking?.sig4_name || props.settings?.spr_signatures?.sig4_name || defaultName;
}

const headerTitle = bookingReceiptSettings.receipt_header_title || 'Bukti Pembayaran Resmi';
const cityName = bookingReceiptSettings.receipt_city || props.transaction.booking?.sigs_city || props.settings?.spr_signatures?.city || 'Jakarta';
const sigImage = bookingReceiptSettings.receipt_sig_image || defaultSigImage;
const officerTitle = bookingReceiptSettings.receipt_sig_title || defaultTitle;
const officerName = bookingReceiptSettings.receipt_sig_name || defaultName;
const receiptNotes = bookingReceiptSettings.receipt_notes || null;
</script>

<template>
    <Head title="Kwitansi Pembayaran" />
    <div class="min-h-screen bg-slate-100 py-12 px-4 print:bg-white print:py-0">
        <!-- Actions & Share Toolbar (Hidden during print) -->
        <div class="max-w-3xl mx-auto flex flex-wrap items-center justify-between gap-3 mb-6 print:hidden">
            <button onclick="window.history.back()" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all shadow-sm">
                ← Kembali
            </button>

            <div class="flex flex-wrap items-center gap-2">
                <button @click="sendWaReceipt" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-1.5">
                    <span>💬</span> <span>Kirim Kwitansi WA</span>
                </button>

                <button @click="printReceipt" class="px-5 py-2.5 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition-all shadow-md flex items-center gap-1.5">
                    <span>🖨️</span> <span>Cetak Kwitansi</span>
                </button>
            </div>
        </div>

        <!-- UPLOAD KWITANSI BASAH SECTION (PRINT HIDDEN) -->
        <div class="max-w-3xl mx-auto bg-amber-50/80 border border-amber-200 rounded-3xl p-6 mb-6 shadow-sm print:hidden">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-xs font-black text-amber-900 uppercase tracking-wider flex items-center gap-1.5">
                        <span>📜</span> <span>Kwitansi Basah Asli (Fisik / Scan TTD & Cap Tinta)</span>
                    </h3>
                    <p class="text-xs text-amber-700/80 mt-1">
                        Unggah scan foto/PDF kwitansi basah fisik jika konsumen meminta dokumen berkas cetak asli.
                    </p>
                </div>

                <div v-if="transaction.wet_receipt_file" class="shrink-0">
                    <a :href="`/storage/${transaction.wet_receipt_file}`" target="_blank" class="px-4 py-2 bg-amber-600 text-white text-xs font-bold rounded-xl hover:bg-amber-700 transition-all shadow-md flex items-center gap-1.5">
                        <span>📎</span> <span>Lihat Kwitansi Basah</span>
                    </a>
                </div>
            </div>

            <form @submit.prevent="submitWetReceipt" class="mt-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-3 border-t border-amber-200/60">
                <input type="file" @change="handleWetUpload" accept="image/*,application/pdf" class="flex-1 text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-amber-600 file:text-white hover:file:bg-amber-700 cursor-pointer" />
                <button type="submit" :disabled="wetForm.processing" class="px-5 py-2.5 bg-amber-900 text-white text-xs font-black rounded-xl hover:bg-amber-950 transition-all shadow-md disabled:opacity-50">
                    {{ wetForm.processing ? 'Mengunggah...' : 'Upload Berkas Basah' }}
                </button>
            </form>
        </div>

        <!-- Premium Kwitansi Layout -->
        <div id="receipt-paper" class="max-w-3xl mx-auto bg-white rounded-3xl border border-slate-200 shadow-xl overflow-hidden p-10 relative print:shadow-none print:border-none print:p-0">
            <!-- Background Watermark -->
            <div class="absolute inset-0 opacity-[0.02] flex items-center justify-center pointer-events-none select-none">
                <span class="text-9xl font-black rotate-[25deg]">{{ projectCode }}</span>
            </div>

            <!-- Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-6 border-b border-slate-200">
                <div class="flex items-center gap-3">
                    <div v-if="logoImage" class="h-12 flex items-center">
                        <img :src="`/storage/${logoImage}`" class="max-h-12 max-w-[180px] object-contain" />
                    </div>
                    <div v-else class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center text-white font-black text-lg shadow-lg">
                        H
                    </div>
                    <div>
                        <h2 class="text-lg font-black text-slate-900 tracking-tight leading-none">{{ companyName }}</h2>
                        <p class="text-[10px] text-blue-500 font-bold uppercase tracking-wider mt-1">{{ headerTitle }}</p>
                    </div>
                </div>
                <div class="text-right sm:text-right">
                    <h1 class="text-xl font-black text-slate-800 uppercase tracking-widest">Kwitansi</h1>
                    <p class="text-xs text-slate-500 font-black mt-1">No: {{ receiptNumber }}</p>
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
                        <p v-if="transaction.booking?.unit" class="text-[10px] text-slate-500 font-medium mt-1">
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
                    <div class="text-center w-48">
                        <p class="text-[10px] font-bold text-slate-500">{{ cityName }}, {{ new Date(transaction.created_at).toLocaleDateString('id-ID', { dateStyle: 'long' }) }}</p>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-wider mt-0.5">{{ officerTitle }}</p>
                        <div class="h-20 flex items-center justify-center relative my-1">
                            <!-- Digital Signature & Stamp Image if available -->
                            <img v-if="sigImage" :src="`/storage/${sigImage}`" class="h-16 max-w-full object-contain" />
                            <div v-else class="text-[10px] text-slate-300 font-bold italic tracking-wide">Tanda Tangan & Stempel</div>
                        </div>
                        <p class="text-xs font-black text-slate-800 border-t border-slate-200 pt-1.5">{{ officerName }}</p>
                    </div>
                </div>
            </div>

            <!-- Footer Notes / Disclaimer -->
            <div v-if="receiptNotes" class="mt-8 pt-4 border-t border-slate-100 text-[10px] text-slate-500 italic">
                <span class="font-bold text-slate-600 not-italic block uppercase tracking-wider mb-0.5">Catatan & Ketentuan:</span>
                <p class="whitespace-pre-line">{{ receiptNotes }}</p>
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
