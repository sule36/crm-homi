<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    transaction: Object,
    spelled_text: String,
    settings: Object,
});

const wetForm = useForm({
    wet_receipt_file: null,
});

// Determine Initial & Effective Payment Date (defaults to schedule paid_date or transaction created_at)
const getInitialPaymentDate = () => {
    if (props.transaction.payment_schedule?.paid_date) {
        return props.transaction.payment_schedule.paid_date.split('T')[0];
    }
    if (props.transaction.created_at) {
        return props.transaction.created_at.split('T')[0];
    }
    if (props.transaction.payment_schedule?.due_date) {
        return props.transaction.payment_schedule.due_date.split('T')[0];
    }
    return new Date().toISOString().split('T')[0];
};

const paymentDate = ref(getInitialPaymentDate());

const paymentDateFormatted = computed(() => {
    if (!paymentDate.value) return '-';
    const parts = paymentDate.value.split('-');
    if (parts.length === 3) {
        const d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    }
    return new Date(paymentDate.value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
});

// Payment Method (Transfer, Cash, Cheque)
const paymentMethod = ref(props.transaction.payment_method || 'transfer');

const paymentMethodLabel = computed(() => {
    if (paymentMethod.value === 'transfer') return 'TRANSFER BANK';
    if (paymentMethod.value === 'cash') return 'TUNAI / CASH';
    if (paymentMethod.value === 'cheque') return 'CEK / GIRO';
    return (paymentMethod.value || 'TRANSFER').toUpperCase();
});

// Unit Payment Scheme label from booking (Cash Bertahap, Cash Keras, KPR)
const bookingPaymentSchemeLabel = computed(() => {
    const scheme = props.transaction.booking?.payment_scheme;
    if (scheme === 'cash_installment') return 'Cash Bertahap (In-House)';
    if (scheme === 'cash') return 'Cash Keras';
    if (scheme === 'kpr') return 'KPR Bank';
    return null;
});

// Determine Effective Destination Bank Account
const computeEffectiveBankAccount = () => {
    const getHolder = (ba) => {
        if (!ba) return '';
        const h = ba.account_holder || ba.account_name || ba.holder_name || '';
        return h ? ` a.n. ${h}` : '';
    };

    const bk = props.transaction.booking;
    const label = (props.transaction.payment_schedule?.label || props.transaction.notes || '').toLowerCase();
    const receiptSettings = bk?.receipt_settings || {};

    // 1. Explicit Receipt Settings Bank Custom Override
    if (receiptSettings.receipt_bank_custom) {
        return receiptSettings.receipt_bank_custom;
    }

    // 2. Direct transaction relational bank account (if cashier selected a registered bank account)
    if (props.transaction.bank_account) {
        const ba = props.transaction.bank_account;
        const bName = ba.bank_name || ba.name || 'Bank';
        return `${bName} - ${ba.account_number || '-'}${getHolder(ba)}`;
    }

    // 3. Per-booking spr_bank_info (Configured in SPR & Kwitansi Parameters)
    if (bk?.spr_bank_info && typeof bk.spr_bank_info === 'object') {
        const info = bk.spr_bank_info;

        // A. If payment is for UTJ / Booking Fee
        if (label.includes('utj') || label.includes('booking')) {
            if (info.utj && info.utj.bank_name && info.utj.account_number) {
                return `${info.utj.bank_name} - ${info.utj.account_number}${getHolder(info.utj)}`;
            }
        }

        // B. If payment is for DP / Down Payment
        if (label.includes('dp') || label.includes('down payment')) {
            if (info.dp && info.dp.bank_name && info.dp.account_number) {
                return `${info.dp.bank_name} - ${info.dp.account_number}${getHolder(info.dp)}`;
            }
        }

        // C. If payment is for Cicilan / Angsuran
        if (label.includes('cicilan') || label.includes('angsuran')) {
            if (info.installment && info.installment.bank_name && info.installment.account_number) {
                return `${info.installment.bank_name} - ${info.installment.account_number}${getHolder(info.installment)}`;
            }
        }

        // D. Main / Default Bank from spr_bank_info
        const mainBank = info.main || info;
        if (mainBank.bank_name && mainBank.account_number) {
            return `${mainBank.bank_name} - ${mainBank.account_number}${getHolder(mainBank)}`;
        }
    }

    // 4. Booking Eloquent bankAccount relation
    if (bk?.bank_account || bk?.bankAccount) {
        const acc = bk.bank_account || bk.bankAccount;
        const bName = acc.bank_name || acc.name || 'Bank';
        return `${bName} - ${acc.account_number || '-'}${getHolder(acc)}`;
    }

    // 5. Direct transaction text bank_name (if typed manually by cashier during payment)
    if (props.transaction.bank_name) {
        const refStr = props.transaction.reference_number ? ` (Ref: ${props.transaction.reference_number})` : '';
        return `${props.transaction.bank_name}${refStr}`;
    }

    // 6. Global Company Settings fallback (spr_bank_info or company_bank_name)
    if (props.settings?.spr_bank_info?.bank_name && props.settings?.spr_bank_info?.account_number) {
        const sb = props.settings.spr_bank_info;
        return `${sb.bank_name} - ${sb.account_number}${getHolder(sb)}`;
    }

    if (props.settings?.company_bank_name && props.settings?.company_bank_account) {
        const holder = props.settings.company_bank_holder || companyName;
        return `${props.settings.company_bank_name} - ${props.settings.company_bank_account}${holder ? (' a.n. ' + holder) : ''}`;
    }

    return null;
};

const effectiveBankAccount = ref(computeEffectiveBankAccount());

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

    const message = `Halo Bapak/Ibu *${name}*,\n\nTerima kasih, pembayaran Anda untuk unit *${unitStr}* (*${projStr}*) sebesar *${amountStr}* (No. Kwitansi: *${receiptNumber.value}*) pada tanggal *${paymentDateFormatted.value}* via *${paymentMethodLabel.value}* telah kami terima dengan tanda tangan & cap stempel resmi.\n\nBerikut link Kwitansi Resmi Anda:\n${receiptUrl}\n\nSalam,\n*Keuangan ${projStr}*`;

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

// Dynamic schedule tag detection (UTJ, DP1, DP2, CICILAN-1, CICILAN-2, PELUNASAN, etc.)
const getScheduleCode = () => {
    const sched = props.transaction.payment_schedule;
    const schedLabel = (sched?.label || '').trim();
    const notesLabel = (props.transaction.notes || '').trim();

    // 1. Check schedule label first if available
    if (schedLabel) {
        const cicilMatch = schedLabel.match(/(?:cicilan|angsuran)\s*(?:ke-?)?\s*(\d+)/i);
        if (cicilMatch) {
            return `CICILAN-${cicilMatch[1]}`;
        }

        const dpMatch = schedLabel.match(/(?:dp|down\s*payment)\s*(\d+)?/i);
        if (dpMatch) {
            return `DP${dpMatch[1] || '1'}`;
        }

        if (/utj|booking/i.test(schedLabel)) {
            return 'UTJ';
        }

        if (/lunas|pelunasan/i.test(schedLabel)) {
            return 'PELUNASAN';
        }

        if (/kpr/i.test(schedLabel)) {
            return 'KPR';
        }
    }

    // 2. Check notes fallback
    if (notesLabel) {
        const cicilMatchNotes = notesLabel.match(/(?:cicilan|angsuran)\s*(?:ke-?)?\s*(\d+)/i);
        if (cicilMatchNotes) {
            return `CICILAN-${cicilMatchNotes[1]}`;
        }

        const dpMatchNotes = notesLabel.match(/(?:dp|down\s*payment)\s*(\d+)?/i);
        if (dpMatchNotes) {
            return `DP${dpMatchNotes[1] || '1'}`;
        }

        if (/utj|booking/i.test(notesLabel)) {
            return 'UTJ';
        }

        if (/lunas|pelunasan/i.test(notesLabel)) {
            return 'PELUNASAN';
        }
    }

    return 'TRX';
};

const resolveInitialReceiptNumber = () => {
    const scheduleCode = getScheduleCode();
    const tokenRegex = /(?:DP\s*\d+|CICILAN-\d+|CICIL-\d+|CICILAN\s*\d+|UTJ|PELUNASAN|KPR|LEGAL)/i;
    
    const rawNumberId = props.transaction.booking?.id || props.transaction.id || 1;
    const paddedId = String(rawNumberId).padStart(3, '0');
    const proj = (projectCode || 'ALN').toUpperCase();

    // 1. Explicit transaction receipt_number
    if (props.transaction.receipt_number) {
        let num = props.transaction.receipt_number;
        if (tokenRegex.test(num)) {
            return num.replace(tokenRegex, scheduleCode);
        }
        return num;
    }

    // 2. Custom receipt number override from booking receipt settings
    if (bookingReceiptSettings.receipt_number_custom) {
        let customNum = bookingReceiptSettings.receipt_number_custom.trim();
        if (tokenRegex.test(customNum)) {
            return customNum.replace(tokenRegex, scheduleCode);
        }
        return customNum;
    }

    // 3. Prefix from booking receipt settings
    if (bookingReceiptSettings.receipt_number_prefix) {
        let prefix = bookingReceiptSettings.receipt_number_prefix.trim().replace(/\/+$/, '');
        
        if (/\/\d+$/.test(prefix) && tokenRegex.test(prefix)) {
            return prefix.replace(tokenRegex, scheduleCode);
        }

        const endsWithToken = prefix.match(/^(.*?)\/?(?:DP\s*\d+|CICILAN-\d+|CICIL-\d+|UTJ|PELUNASAN|KPR|LEGAL)$/i);
        if (endsWithToken) {
            prefix = endsWithToken[1] || prefix;
        }

        return `${prefix}/${scheduleCode}/${paddedId}`;
    }

    // 4. Default automatic generation: KW/{PROJECT_CODE}/{SCHEDULE_CODE}/{PADDED_ID}
    return `KW/${proj}/${scheduleCode}/${paddedId}`;
};

const receiptNumber = ref(resolveInitialReceiptNumber());

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

const isSaving = ref(false);
const saveSuccessMessage = ref('');

const saveReceiptData = () => {
    isSaving.value = true;
    router.put(`/finance/transactions/${props.transaction.id}`, {
        payment_date: paymentDate.value,
        payment_method: paymentMethod.value,
        receipt_number: receiptNumber.value,
        bank_account_text: effectiveBankAccount.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isSaving.value = false;
            saveSuccessMessage.value = 'Perubahan data kwitansi berhasil disimpan!';
            setTimeout(() => {
                saveSuccessMessage.value = '';
            }, 4000);
        },
        onError: () => {
            isSaving.value = false;
            alert('Gagal menyimpan perubahan kwitansi. Silakan periksa kembali.');
        }
    });
};
</script>

<template>
    <Head title="Kwitansi Pembayaran" />
    <div class="min-h-screen bg-slate-100 py-12 px-4 print:bg-white print:py-0">
        <!-- Actions & Share Toolbar (Hidden during print) -->
        <div class="max-w-3xl mx-auto flex flex-wrap items-center justify-between gap-3 mb-4 print:hidden">
            <button onclick="window.history.back()" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all shadow-sm cursor-pointer">
                ← Kembali
            </button>

            <div class="flex flex-wrap items-center gap-2">
                <button 
                    @click="saveReceiptData" 
                    :disabled="isSaving" 
                    class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-1.5 disabled:opacity-50 cursor-pointer"
                    title="Simpan tanggal, metode bayar, nomor kwitansi & rekening ke database"
                >
                    <span>💾</span> <span>{{ isSaving ? 'Menyimpan...' : 'Simpan Kwitansi' }}</span>
                </button>

                <button @click="sendWaReceipt" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-1.5 cursor-pointer">
                    <span>💬</span> <span>Kirim Kwitansi WA</span>
                </button>

                <button @click="printReceipt" class="px-5 py-2.5 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition-all shadow-md flex items-center gap-1.5 cursor-pointer">
                    <span>🖨️</span> <span>Cetak Kwitansi</span>
                </button>
            </div>
        </div>

        <!-- Success Toast Alert -->
        <div v-if="saveSuccessMessage" class="max-w-3xl mx-auto mb-4 p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold flex items-center gap-2 print:hidden shadow-sm">
            <span>✅</span> <span>{{ saveSuccessMessage }}</span>
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
                <button type="submit" :disabled="wetForm.processing" class="px-5 py-2.5 bg-amber-900 text-white text-xs font-black rounded-xl hover:bg-amber-950 transition-all shadow-md disabled:opacity-50 cursor-pointer">
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
                    <div class="flex items-center justify-end gap-1.5 mt-1">
                        <span class="text-xs text-slate-500 font-black">No:</span>
                        <input 
                            v-model="receiptNumber" 
                            type="text" 
                            class="text-xs text-slate-800 font-mono font-black border-b border-dashed border-slate-300 hover:border-slate-500 focus:border-blue-500 focus:outline-none bg-transparent px-1 py-0.5 text-right w-56 print:border-none print:p-0 print:w-auto" 
                            title="Klik untuk mengubah nomor kwitansi manual jika diperlukan"
                        />
                    </div>
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
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-black text-slate-800">
                                {{ transaction.payment_schedule?.label ? (transaction.payment_schedule.label.toLowerCase().startsWith('pembayaran') ? transaction.payment_schedule.label : 'Pembayaran ' + transaction.payment_schedule.label) : (transaction.notes || 'Cicilan / Pembayaran Unit') }}
                            </p>
                            <!-- Badge Skema Pembelian Unit -->
                            <span v-if="bookingPaymentSchemeLabel" class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-amber-50 text-amber-800 border border-amber-200">
                                Skema Unit: {{ bookingPaymentSchemeLabel }}
                            </span>
                        </div>
                        <p v-if="transaction.booking?.unit" class="text-[10px] text-slate-500 font-medium mt-1">
                            Unit Kavling: Blok {{ transaction.booking?.unit?.block }} No. {{ transaction.booking?.unit?.number }} 
                            • Proyek: {{ transaction.booking?.unit?.project?.name }}
                        </p>
                    </div>
                </div>

                <!-- Row 4: Tanggal Pembayaran (Editable di layar, Teks formal saat print) -->
                <div class="flex flex-col sm:flex-row border-b border-slate-100 pb-3">
                    <span class="w-48 text-[10px] font-black text-slate-400 uppercase tracking-wider shrink-0">Tanggal Pembayaran</span>
                    <div class="text-xs font-bold text-slate-800 flex items-center gap-2">
                        <span>📅</span>
                        <!-- Tampilan di layar: Input Tanggal interaktif -->
                        <div class="print:hidden flex flex-wrap items-center gap-2">
                            <input 
                                v-model="paymentDate" 
                                type="date" 
                                class="text-xs font-black text-slate-800 border-b border-dashed border-slate-400 hover:border-blue-500 focus:border-blue-600 focus:outline-none bg-slate-50 px-2 py-1 rounded cursor-pointer transition-colors"
                                title="Klik untuk mengubah tanggal kwitansi"
                            />
                            <span class="text-xs font-black text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">
                                {{ paymentDateFormatted }}
                            </span>
                            <span class="text-[10px] text-slate-400 italic font-medium">
                                (Dapat diubah bebas sebelum cetak)
                            </span>
                        </div>
                        <!-- Tampilan saat cetak: Teks rapi format Indonesia -->
                        <span class="hidden print:inline text-xs font-bold text-slate-800">
                            {{ paymentDateFormatted }}
                        </span>
                    </div>
                </div>

                <!-- Row 5: Metode & Rekening Tujuan (Editable di layar, Teks formal saat print) -->
                <div class="flex flex-col sm:flex-row border-b border-slate-100 pb-3">
                    <span class="w-48 text-[10px] font-black text-slate-400 uppercase tracking-wider shrink-0">Metode & Rekening Tujuan</span>
                    <div class="text-xs font-bold text-slate-800 flex flex-wrap items-center gap-2 flex-1">
                        <!-- Pilihan Metode (Screen) -->
                        <div class="print:hidden flex items-center gap-1.5">
                            <select 
                                v-model="paymentMethod" 
                                class="text-xs font-black text-blue-700 bg-blue-50 px-2.5 py-1 rounded-lg border border-blue-200 focus:outline-none focus:ring-1 focus:ring-blue-500 cursor-pointer uppercase"
                                title="Klik untuk mengubah metode pembayaran kwitansi"
                            >
                                <option value="transfer">TRANSFER BANK</option>
                                <option value="cash">TUNAI / CASH</option>
                                <option value="cheque">CEK / GIRO</option>
                            </select>
                        </div>

                        <!-- Tampilan Print -->
                        <span class="hidden print:inline uppercase font-black text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200 mr-2 shrink-0">
                            {{ paymentMethodLabel }}
                        </span>

                        <!-- Rekening Tujuan jika Transfer atau ada isian rekening -->
                        <div v-if="paymentMethod === 'transfer' || effectiveBankAccount" class="inline-flex items-center gap-1.5 flex-1 min-w-[240px]">
                            <span class="shrink-0 text-sm">💳</span>
                            <input 
                                v-model="effectiveBankAccount" 
                                type="text" 
                                placeholder="Contoh: BRI - 012001004640307 a.n. PT Developer"
                                class="text-xs font-black text-slate-900 border-b border-dashed border-slate-300 hover:border-slate-500 focus:border-blue-500 focus:outline-none bg-transparent px-1 py-0.5 w-full max-w-md print:border-none print:p-0 print:w-auto" 
                                title="Klik untuk mengubah rekening tujuan kwitansi jika diperlukan"
                            />
                        </div>
                        <span v-else class="text-slate-500 italic">
                            (Pembayaran Tunai Kasir)
                        </span>
                    </div>
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
                        <p class="text-[10px] font-bold text-slate-500">{{ cityName }}, {{ paymentDateFormatted }}</p>
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
