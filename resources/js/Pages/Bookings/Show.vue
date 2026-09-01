<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import KprProgress from '@/Components/Crm/KprProgress.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    booking: Object,
    bank_accounts_all: {
        type: Array,
        default: () => []
    }
});

const showPaymentModal = ref(false);
const selectedSchedule = ref(null);
const showSpkPreview = ref(false);
const showSprTemplateModal = ref(false);
const showReceiptsModal = ref(false);
const activeSprTab = ref('bank');

function getScheduleTransaction(schedule) {
    if (!props.booking?.transactions) return null;
    return props.booking.transactions.find(tx => tx.payment_schedule_id === schedule.id);
}

const defaultTerms = [
    "Pembeli menyatakan telah mengerti dan menyetujui serta akan tunduk kepada persyaratan dan ketentuan serta kebijakan yang ditetapkan oleh Pengembang dalam SPR",
    "Dalam hal pembelian rumah melalui KPR, jumlah DP dan persyaratan KPR lainnya tunduk pada ketentuan Bank pemberi KPR",
    "Dalam hal terjadi penolakan dari pihak Bank atau KPR tidak disetujui, maka Uang Tanda Jadi akan dikembalikan 100%",
    "Dalam hal KPR yang telah disetujui oleh Bank, maka Akad Kredit wajib dilaksanakan selambat-lambatnya 1 bulan sejak diterimanya down payment oleh Pengembang",
    "Dalam hal terjadi pembatalan sepihak oleh pembeli dalam masa pembangunan unit sesuai pilihan pembeli dalam SPR ini, maka seluruh pembayaran dari Pembeli akan hangus 100%",
    "Pembeli diperkenankan untuk memilih cara pembayaran selain KPR dengan syarat dan ketentuan dari Pengembang",
    "Pembayaran segala bentuk cicilan kepada Pengembang yang melebihi waktu yang telah ditentukan dalam SPR ini, akan dikenakan denda sebesar 1% per hari dengan denda maksimal 5% dari jumlah kewajiban yang terlambat",
    "Pembeli tidak diperkenankan untuk mengalihkan pembelian tanah dan bangunan, pengalihan pembelian akan dikenakan denda sebesar 2.5% dari harga jual final",
    "Nilai Uang Tanda Jadi ditetapkan sebesar Rp. 15.000.000,- (lima belas juta rupiah)",
    "Jangka waktu perjanjian ini berakhir sesuai tanggal akhir pelunasan pembayaran oleh Pembeli, kecuali untuk KPR sesuai pelunasan dari Bank setelah Serah Terima unit kepada Pembeli",
    "SPR ini akan batal dengan sendirinya dalam hal terjadinya kondisi yang dijelaskan pada pasal 3 dan 5, Pembatalan SPR dalam bentuk tertulis antara Pengembang dan Pembeli dibuat 3 rangkap dimana 1 rangkapnya milik Pemilik Tanah",
    "Penandatanganan SPR dilakukan setelah seluruh pasal didalamnya disepakati oleh masing-masing pihak"
];

const defaultBonusItems = [
    'Kitchen Set',
    'Kitchen Island',
    'Dinding Feature Wall Backdrop TV (Sesuai rumah contoh)',
    'Bench',
    'Wall Cabinet TV',
];

const defaultPackageItems = [
    'Free BPHTB (khusus aset perolehan pertama)',
    'Free AJB',
    'Free Balik Nama',
    'Free Biaya Notaris',
    'Extra Cashback 50 Juta',
];

const rawSo = props.booking.spr_special_offer;
const isSoObj = rawSo && typeof rawSo === 'object' && !Array.isArray(rawSo);

const initialSo = {
    enabled: isSoObj && rawSo.enabled !== undefined ? Boolean(rawSo.enabled) : true,
    title: (isSoObj && rawSo.title) ? rawSo.title : ('Special Offer & Benefit ' + (props.booking.unit?.project?.name || 'Umala Andara')),
    promo_valid_until: (isSoObj && rawSo.promo_valid_until) ? rawSo.promo_valid_until : '30 September 2026',
};

const initialBonusItems = Array.isArray(props.booking.special_bonus_items) && props.booking.special_bonus_items.length > 0
    ? [...props.booking.special_bonus_items]
    : (isSoObj && Array.isArray(rawSo.bonus_furniture) && rawSo.bonus_furniture.length > 0
        ? [...rawSo.bonus_furniture]
        : [...defaultBonusItems]);

const initialPackageItems = Array.isArray(props.booking.special_package_items) && props.booking.special_package_items.length > 0
    ? [...props.booking.special_package_items]
    : (isSoObj && Array.isArray(rawSo.grand_launching_package) && rawSo.grand_launching_package.length > 0
        ? [...rawSo.grand_launching_package]
        : [...defaultPackageItems]);

const sprTemplateForm = useForm({
    spk_number: props.booking.spk_number || '',
    bank_account_id: props.booking.bank_account_id || '',
    bank_account_utj_id: props.booking.bank_account_utj_id || '',
    bank_account_dp_id: props.booking.bank_account_dp_id || '',
    bank_account_installment_id: props.booking.bank_account_installment_id || '',
    buyer_nik: props.booking.buyer_nik || props.booking.lead?.identity_number || '',
    buyer_npwp: props.booking.buyer_npwp || props.booking.lead?.npwp || '',
    buyer_address: props.booking.buyer_address || props.booking.lead?.address || '',
    buyer_job: props.booking.buyer_job || props.booking.lead?.job || '',
    secondary_name: props.booking.secondary_name || '',
    secondary_nik: props.booking.secondary_nik || '',
    secondary_npwp: props.booking.secondary_npwp || '',
    secondary_phone: props.booking.secondary_phone || '',
    secondary_relationship: props.booking.secondary_relationship || 'Orang Tua',
    secondary_address: props.booking.secondary_address || '',
    secondary_email: props.booking.secondary_email || '',
    unit_certificate_status: props.booking.unit?.certificate_status || 'Dalam Proses',
    unit_certificate_number: props.booking.unit?.certificate_number || '',
    spr_date: props.booking.spr_date ? props.booking.spr_date.substring(0, 10) : (props.booking.booking_date ? props.booking.booking_date.substring(0, 10) : ''),
    spr_schedule_dates: (props.booking.spr_schedule_dates && typeof props.booking.spr_schedule_dates === 'object' && !Array.isArray(props.booking.spr_schedule_dates))
        ? { utj_date: '', dp_date: '', installment_date: '', ...props.booking.spr_schedule_dates }
        : { utj_date: '', dp_date: '', installment_date: '' },
    spr_bank_info: (props.booking.spr_bank_info && typeof props.booking.spr_bank_info === 'object' && !Array.isArray(props.booking.spr_bank_info))
        ? props.booking.spr_bank_info
        : {
            bank_name: 'MANDIRI',
            account_number: '1200008089893',
            account_holder: 'PT. Serangkai Roden Development'
        },
    spr_special_offer: initialSo,
    special_bonus_items: initialBonusItems,
    special_package_items: initialPackageItems,
    spr_terms_conditions: Array.isArray(props.booking.spr_terms_conditions) && props.booking.spr_terms_conditions.length > 0 ? [...props.booking.spr_terms_conditions] : [...defaultTerms],
    sig1_title: props.booking.sig1_title || 'AGENT COORDINATOR',
    sig1_name: props.booking.sig1_name || '',
    sig2_title: props.booking.sig2_title || 'DIREKTUR',
    sig2_name: props.booking.sig2_name || '',
    sig3_title: props.booking.sig3_title || 'SALES',
    sig3_name: props.booking.sig3_name || '',
    sig4_title: props.booking.sig4_title || '',
    sigs_city: props.booking.sigs_city || props.booking.spr_signatures?.city || 'Jakarta Selatan',
    receipt_settings: (props.booking.receipt_settings && typeof props.booking.receipt_settings === 'object')
        ? {
            receipt_sig_slot: props.booking.receipt_settings.receipt_sig_slot || 'sig1',
            receipt_number_prefix: props.booking.receipt_settings.receipt_number_prefix || '',
            receipt_number_custom: props.booking.receipt_settings.receipt_number_custom || '',
            receipt_city: props.booking.receipt_settings.receipt_city || props.booking.sigs_city || 'Jakarta Selatan',
            receipt_sig_title: props.booking.receipt_settings.receipt_sig_title || props.booking.sig1_title || 'Kasir & Keuangan',
            receipt_sig_name: props.booking.receipt_settings.receipt_sig_name || props.booking.sig1_name || '',
            receipt_header_title: props.booking.receipt_settings.receipt_header_title || 'Bukti Pembayaran Resmi',
            receipt_notes: props.booking.receipt_settings.receipt_notes || 'Pembayaran ini dianggap sah apabila telah dibubuhi stempel & TTD resmi.',
        }
        : {
            receipt_sig_slot: 'sig1',
            receipt_number_prefix: '',
            receipt_number_custom: '',
            receipt_city: props.booking.sigs_city || 'Jakarta Selatan',
            receipt_sig_title: props.booking.sig1_title || 'Kasir & Keuangan',
            receipt_sig_name: props.booking.sig1_name || '',
            receipt_header_title: 'Bukti Pembayaran Resmi',
            receipt_notes: 'Pembayaran ini dianggap sah apabila telah dibubuhi stempel & TTD resmi.',
        },
});

function onReceiptSigSlotChange() {
    const slot = sprTemplateForm.receipt_settings.receipt_sig_slot;
    if (slot === 'sig1') {
        sprTemplateForm.receipt_settings.receipt_sig_title = sprTemplateForm.sig1_title || 'Kasir & Keuangan';
        sprTemplateForm.receipt_settings.receipt_sig_name = sprTemplateForm.sig1_name || 'Keuangan';
    } else if (slot === 'sig2') {
        sprTemplateForm.receipt_settings.receipt_sig_title = sprTemplateForm.sig2_title || 'DIREKTUR';
        sprTemplateForm.receipt_settings.receipt_sig_name = sprTemplateForm.sig2_name || '';
    } else if (slot === 'sig3') {
        sprTemplateForm.receipt_settings.receipt_sig_title = sprTemplateForm.sig3_title || 'SALES';
        sprTemplateForm.receipt_settings.receipt_sig_name = sprTemplateForm.sig3_name || '';
    } else if (slot === 'sig4') {
        sprTemplateForm.receipt_settings.receipt_sig_title = sprTemplateForm.sig4_title || 'Penanda Tangan';
        sprTemplateForm.receipt_settings.receipt_sig_name = sprTemplateForm.sig4_name || '';
    }
}

function openSprTemplateModal() {
    showSprTemplateModal.value = true;
}

function handleBankAccountChange() {
    if (!sprTemplateForm.bank_account_id) return;
    const selectedBank = props.bank_accounts_all.find(b => b.id === sprTemplateForm.bank_account_id);
    if (selectedBank) {
        if (!sprTemplateForm.spr_bank_info) sprTemplateForm.spr_bank_info = {};
        sprTemplateForm.spr_bank_info.bank_name = selectedBank.bank_name;
        sprTemplateForm.spr_bank_info.account_number = selectedBank.account_number;
        sprTemplateForm.spr_bank_info.account_holder = selectedBank.account_holder;
    }
}

function handleUtjBankChange() {
    if (!sprTemplateForm.bank_account_utj_id) return;
    const b = props.bank_accounts_all.find(x => x.id === sprTemplateForm.bank_account_utj_id);
    if (b) {
        if (!sprTemplateForm.spr_bank_info) sprTemplateForm.spr_bank_info = {};
        sprTemplateForm.spr_bank_info.utj = { bank_name: b.bank_name, account_number: b.account_number, account_holder: b.account_holder };
    }
}

function handleDpBankChange() {
    if (!sprTemplateForm.bank_account_dp_id) return;
    const b = props.bank_accounts_all.find(x => x.id === sprTemplateForm.bank_account_dp_id);
    if (b) {
        if (!sprTemplateForm.spr_bank_info) sprTemplateForm.spr_bank_info = {};
        sprTemplateForm.spr_bank_info.dp = { bank_name: b.bank_name, account_number: b.account_number, account_holder: b.account_holder };
    }
}

function handleInstallmentBankChange() {
    if (!sprTemplateForm.bank_account_installment_id) return;
    const b = props.bank_accounts_all.find(x => x.id === sprTemplateForm.bank_account_installment_id);
    if (b) {
        if (!sprTemplateForm.spr_bank_info) sprTemplateForm.spr_bank_info = {};
        sprTemplateForm.spr_bank_info.installment = { bank_name: b.bank_name, account_number: b.account_number, account_holder: b.account_holder };
    }
}

function addTermItem() {
    sprTemplateForm.spr_terms_conditions.push('Poin persyaratan baru...');
}

function removeTermItem(index) {
    sprTemplateForm.spr_terms_conditions.splice(index, 1);
}

function addBonusItem() {
    if (!sprTemplateForm.special_bonus_items) sprTemplateForm.special_bonus_items = [];
    sprTemplateForm.special_bonus_items.push('Item bonus furniture baru...');
}

function removeBonusItem(idx) {
    sprTemplateForm.special_bonus_items.splice(idx, 1);
}

function addPackageItem() {
    if (!sprTemplateForm.special_package_items) sprTemplateForm.special_package_items = [];
    sprTemplateForm.special_package_items.push('Item paket promo baru...');
}

function removePackageItem(idx) {
    sprTemplateForm.special_package_items.splice(idx, 1);
}

function submitSprTemplate() {
    handleBankAccountChange();
    handleUtjBankChange();
    handleDpBankChange();
    handleInstallmentBankChange();
    
    sprTemplateForm.post(`/bookings/${props.booking.id}/spr-template`, {
        preserveScroll: true,
        onSuccess: () => {
            showSprTemplateModal.value = false;
        }
    });
}

function deleteBooking() {
    if (confirm('Apakah Anda yakin ingin menghapus booking ini? Unit properti terkait akan dikembalikan statusnya menjadi Available.')) {
        router.delete(`/bookings/${props.booking.id}`);
    }
}

const paymentForm = useForm({
    booking_id: props.booking.id,
    payment_schedule_id: null,
    amount: 0,
    payment_method: 'transfer',
    bank_name: '',
    reference_number: '',
    notes: '',
});

function openPaymentModal(schedule) {
    selectedSchedule.value = schedule;
    paymentForm.payment_schedule_id = schedule.id;
    paymentForm.amount = schedule.amount;
    const label = schedule.label || 'Unit Properti';
    paymentForm.notes = label.toLowerCase().startsWith('pembayaran') ? label : `Pembayaran ${label}`;
    showPaymentModal.value = true;
}

function submitPayment() {
    paymentForm.post('/transactions', {
        onSuccess: () => {
            showPaymentModal.value = false;
            paymentForm.reset();
        }
    });
}

function deleteTransaction(id) {
    if (confirm('Hapus catatan transaksi ini? Status cicilan akan disesuaikan kembali.')) {
        router.delete(`/transactions/${id}`);
    }
}

// Flexible Payment Schedule Management
const showScheduleModal = ref(false);
const showAddRowModal = ref(false);
const editingScheduleRow = ref(null);

const regenForm = useForm({
    payment_scheme: props.booking.payment_scheme || 'cash_installment',
    installment_months: props.booking.installment_months || 60,
    dp_amount: props.booking.dp_amount || 0,
    dp_installment_months: props.booking.dp_installment_months || 0,
});

function submitRegenSchedule() {
    regenForm.post(`/bookings/${props.booking.id}/regenerate-schedule`, {
        preserveScroll: true,
        onSuccess: () => { showScheduleModal.value = false; }
    });
}

const addRowForm = useForm({
    label: '',
    amount: '',
    due_date: '',
});

function submitAddRow() {
    addRowForm.post(`/bookings/${props.booking.id}/schedules`, {
        preserveScroll: true,
        onSuccess: () => { showAddRowModal.value = false; addRowForm.reset(); }
    });
}

const editRowForm = useForm({
    label: '',
    amount: '',
    due_date: '',
    status: 'upcoming',
});

function openEditRow(schedule) {
    editingScheduleRow.value = schedule;
    editRowForm.label = schedule.label;
    editRowForm.amount = schedule.amount;
    editRowForm.due_date = schedule.due_date;
    editRowForm.status = schedule.status;
}

function submitEditRow() {
    if (!editingScheduleRow.value) return;
    editRowForm.put(`/payment-schedules/${editingScheduleRow.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { editingScheduleRow.value = null; }
    });
}

function deleteRow(schedule) {
    if (confirm(`Hapus baris tagihan "${schedule.label}"?`)) {
        router.delete(`/payment-schedules/${schedule.id}`, { preserveScroll: true });
    }
}

function sendEmailRow(schedule) {
    if (!props.booking.lead?.email) {
        return alert('Konsumen (Lead) ini belum memiliki alamat email yang terdaftar.');
    }
    if (confirm(`Kirimkan invoice tagihan "${schedule.label}" ke email ${props.booking.lead.email}?`)) {
        router.post(`/payment-schedules/${schedule.id}/send-email`, {}, { preserveScroll: true });
    }
}

const showReasonModal = ref(false);
const actionType = ref(''); // 'reject' or 'cancel'
const reason = ref('');

const approve = () => {
    if (confirm('Apakah Anda yakin ingin menyetujui booking ini? Status unit akan berubah menjadi Booked.')) {
        router.post(`/bookings/${props.booking.id}/approve`);
    }
};

const openReasonModal = (type) => {
    actionType.value = type;
    showReasonModal.value = true;
};

const submitAction = () => {
    if (!reason.value) return alert('Alasan harus diisi.');
    router.post(`/bookings/${props.booking.id}/${actionType.value}`, { reason: reason.value }, {
        onSuccess: () => {
            showReasonModal.value = false;
            reason.value = '';
        }
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};

const statusColors = {
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-rose-100 text-rose-700',
    completed: 'bg-blue-100 text-blue-700',
};

const copyTrackingLink = () => {
    const url = `${window.location.origin}/track/${props.booking.tracking_token}`;
    navigator.clipboard.writeText(url);
    alert('Link pelacakan berhasil disalin!');
};

const sendWaTxReceipt = (tx) => {
    const name = props.booking.lead?.name || 'Konsumen';
    const amountStr = formatCurrency(tx.amount);
    const unitStr = props.booking.unit ? `Blok ${props.booking.unit.block} No. ${props.booking.unit.number}` : 'Unit';
    const projStr = props.booking.unit?.project?.name || 'Homi Developer';
    const rawPhone = props.booking.lead?.phone || '';
    const phone = rawPhone.replace(/\D/g, '').replace(/^0/, '62');
    const receiptUrl = `${window.location.origin}/finance/transactions/${tx.id}/receipt`;

    const message = `Halo Bapak/Ibu *${name}*,\n\nBerikut adalah Kwitansi Pembayaran Resmi (TTD & Cap Stempel) untuk unit *${unitStr}* (*${projStr}*) sebesar *${amountStr}*:\n${receiptUrl}\n\nTerima kasih,\n*Keuangan Homi Developer*`;

    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank');
};

const whatsappReminderLink = computed(() => {
    const url = `${window.location.origin}/track/${props.booking?.tracking_token || ''}`;
    const projName = props.booking?.unit?.project?.name || props.booking?.project?.name || 'Homi Developer';
    const leadName = props.booking?.lead?.name || 'Konsumen';
    const leadPhone = (props.booking?.lead?.phone || '').replace(/\D/g, '');
    const message = `Halo Bapak/Ibu ${leadName}, ini dari tim sales ${projName}. Berikut adalah link untuk memantau progres pesanan dan riwayat pembayaran unit Anda: ${url}. Terima kasih.`;
    return `https://wa.me/${leadPhone}?text=${encodeURIComponent(message)}`;
});

// Document Upload Logic
const docForm = useForm({
    type: 'ktp',
    file: null,
});

const handleFileChange = (e) => {
    docForm.file = e.target.files[0];
};

const submitDocument = () => {
    if (!docForm.file) return alert('Silakan pilih berkas terlebih dahulu.');
    docForm.post(`/bookings/${props.booking.id}/documents`, {
        preserveScroll: true,
        onSuccess: () => {
            docForm.reset();
            const fileInput = document.getElementById('doc-file-input');
            if (fileInput) fileInput.value = '';
        }
    });
};

const deleteDocument = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus dokumen ini?')) {
        router.delete(`/booking-documents/${id}`, {
            preserveScroll: true
        });
    }
};

const docTypeLabels = {
    ktp: '🪪 KTP Pemesan',
    kk: '👨‍👩‍👧‍👦 Kartu Keluarga (KK)',
    npwp: '💳 NPWP Pemesan',
    payment_proof: '🧾 Bukti Bayar UTJ/DP',
    other: '📁 Dokumen Tambahan'
};
</script>

<template>
    <Head :title="`Booking: ${booking.spk_number}`" />
    <CrmLayout>
        <template #breadcrumb>
            <Link href="/bookings" class="text-slate-400 hover:text-blue-600">Booking</Link>
            <span class="mx-2 text-slate-300">/</span>
            <span class="text-slate-900 font-bold">{{ booking.spk_number }}</span>
        </template>

        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 mb-8">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white rounded-2xl border border-slate-100 flex items-center justify-center text-xl shadow-sm">
                    📋
                </div>
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ booking.spk_number }}</h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span :class="statusColors[booking.status]" class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider">
                            {{ booking.status }}
                        </span>
                        <span class="text-xs text-slate-400">Dibuat oleh {{ booking.booked_by?.name || 'Staff' }} pada {{ new Date(booking.booking_date).toLocaleString('id-ID') }}</span>
                    </div>
                </div>
            </div>
            
            <div v-if="booking.status === 'pending'" class="flex gap-2">
                <button @click="approve" class="px-6 py-2.5 bg-emerald-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-emerald-500/20 hover:-translate-y-0.5 transition-all">
                    Approve
                </button>
                <button @click="openReasonModal('reject')" class="px-6 py-2.5 bg-rose-50 text-rose-600 text-sm font-bold rounded-xl border border-rose-100 hover:bg-rose-100 transition-all">
                    Reject
                </button>
            </div>
            <div v-else-if="booking.status === 'approved'" class="flex flex-wrap gap-2">
                <button v-if="booking.transactions?.length > 0" @click="showReceiptsModal = true" class="px-5 py-2.5 bg-emerald-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-emerald-600/20 hover:-translate-y-0.5 transition-all flex items-center gap-2">
                    <span>📄</span>
                    <span>Daftar Kwitansi PDF ({{ booking.transactions.length }})</span>
                </button>
                <button @click="openSprTemplateModal" class="px-5 py-2.5 bg-amber-500 text-white text-sm font-bold rounded-xl shadow-lg shadow-amber-500/20 hover:-translate-y-0.5 transition-all flex items-center gap-2">
                    ⚙️ Edit Template SPR
                </button>
                <button @click="showSpkPreview = true" class="px-5 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl shadow-lg hover:-translate-y-0.5 transition-all flex items-center gap-2">
                    👁️ Tinjau SPR
                </button>
                <a :href="`/bookings/${booking.id}/spk`" target="_blank" class="px-5 py-2.5 bg-slate-900 text-white text-sm font-bold rounded-xl shadow-lg hover:-translate-y-0.5 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Download SPR (PDF)
                </a>
                <button @click="openReasonModal('cancel')" class="px-4 py-2.5 text-slate-400 text-xs font-bold hover:text-rose-600 transition-all">
                    Batalkan Pesanan
                </button>
                <button @click="deleteBooking" class="px-4 py-2.5 text-rose-500 text-xs font-bold hover:bg-rose-50 rounded-xl transition-all border border-rose-200">
                    🗑️ Hapus Booking
                </button>
            </div>
        </div>

        <!-- QUICK ACTIONS BAR -->
        <div class="bg-white rounded-2xl border border-blue-100 p-4 mb-8 flex flex-wrap items-center justify-between gap-4 shadow-sm shadow-blue-500/5">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.826L10.172 13.828a4 4 0 005.656 0l4-4a4 4 0 10-5.656-5.656l-1.102 1.101m-.758 4.826L12 12"/></svg>
                </div>
                <div>
                    <p class="text-xs font-black text-slate-900 uppercase tracking-tight">Customer Tracking Link</p>
                    <p class="text-[10px] text-slate-400 font-bold">Bagikan link ini agar konsumen bisa pantau progres & pembayaran.</p>
                </div>
            </div>
            <div class="flex gap-2">
                <button @click="copyTrackingLink" class="px-4 py-2 bg-slate-100 text-slate-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-slate-200 transition-all">Salin Link</button>
                <a :href="whatsappReminderLink" target="_blank" class="px-4 py-2 bg-emerald-500 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-emerald-600 transition-all shadow-lg shadow-emerald-500/20 flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.335-1.662c1.72.937 3.659 1.432 5.631 1.433h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Kirim WA
                </a>
            </div>
        </div>

        <!-- KPR PROGRESS (ONLY FOR KPR SCHEME) -->
        <KprProgress v-if="booking.payment_scheme === 'kpr' && booking.status !== 'pending'" :booking="booking" />

        <!-- REASON MODAL -->
        <div v-if="showReasonModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showReasonModal = false"></div>
            <div class="relative bg-white rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl p-8">
                <h3 class="text-xl font-black text-slate-900 mb-2 uppercase tracking-tight">{{ actionType === 'reject' ? 'Tolak' : 'Batalkan' }} Booking</h3>
                <p class="text-xs text-slate-500 mb-6">Berikan alasan mengapa pesanan ini {{ actionType === 'reject' ? 'ditolak' : 'dibatalkan' }}.</p>
                
                <div class="space-y-4">
                    <textarea v-model="reason" rows="3" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-rose-500/20" placeholder="Contoh: Dokumen tidak lengkap..."></textarea>
                    
                    <div class="flex gap-3">
                        <button @click="submitAction" class="flex-1 py-4 bg-rose-600 text-white font-black rounded-2xl shadow-xl hover:-translate-y-1 transition-all uppercase text-xs tracking-widest">
                            KONFIRMASI {{ actionType }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- DETAIL KONSUMEN & UNIT -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-6">Informasi Transaksi</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Unit Info -->
                        <div class="space-y-4">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Data Unit</p>
                            <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                                <h3 class="text-sm font-black text-slate-900">{{ booking.unit?.project?.name }}</h3>
                                <p class="text-xs text-slate-500 mt-1">Blok {{ booking.unit?.block }}{{ booking.unit?.number }} • Tipe {{ booking.unit?.unit_type?.name }}</p>
                                <div class="mt-4 flex items-center justify-between pt-4 border-t border-slate-200">
                                    <span class="text-xs text-slate-400">Harga Unit</span>
                                    <span class="text-sm font-black text-slate-900">{{ formatCurrency(booking.final_price) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Customer Info -->
                        <div class="space-y-4">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Data Konsumen Utama</p>
                            <div class="space-y-2 text-xs">
                                <div>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase">Nama Pemesan</p>
                                    <p class="text-sm font-bold text-slate-900">{{ booking.lead?.name }}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase">NIK KTP</p>
                                        <p class="font-bold text-slate-800">{{ booking.buyer_nik || booking.lead?.identity_number || '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase">NPWP</p>
                                        <p class="font-bold text-slate-800">{{ booking.buyer_npwp || booking.lead?.npwp || '-' }}</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase">Pekerjaan</p>
                                        <p class="font-bold text-slate-800">{{ booking.buyer_job || booking.lead?.job || '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase">No. HP / WA</p>
                                        <p class="font-bold text-slate-800">{{ booking.lead?.phone }}</p>
                                    </div>
                                </div>

                                <div v-if="booking.secondary_name" class="mt-3 p-3 bg-amber-50 rounded-xl border border-amber-200/70 space-y-1">
                                    <p class="text-[9px] font-black text-amber-700 uppercase tracking-wider">Pemesan 2 / Penanggung Jawab Pembayaran</p>
                                    <p class="text-xs font-bold text-slate-900">{{ booking.secondary_name }} ({{ booking.secondary_relationship || 'Penanggung Jawab' }})</p>
                                    <p class="text-[10px] text-slate-600">NIK: {{ booking.secondary_nik || '-' }} • Telp: {{ booking.secondary_phone || '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PAYMENT SCHEDULE -->
                <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-slate-100">
                        <div>
                            <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                <span>📅</span> <span>Jadwal Pembayaran</span>
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Skema: <span class="font-bold text-amber-600 uppercase">{{ booking.payment_scheme }}</span> 
                                <span v-if="booking.installment_months"> ({{ booking.installment_months }} Bulan / {{ (booking.installment_months / 12).toFixed(1) }} Thn)</span>
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button @click="showScheduleModal = true" class="px-3 py-1.5 bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold rounded-xl hover:bg-amber-100 transition-colors flex items-center gap-1">
                                <span>⚙️</span> <span>Atur Skema / Tenor</span>
                            </button>
                            <button @click="showAddRowModal = true" class="px-3 py-1.5 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition-colors flex items-center gap-1">
                                <span>➕</span> <span>Tambah Tagihan</span>
                            </button>
                        </div>
                    </div>
                    
                    <div v-if="booking.payment_schedules?.length" class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50">
                                <tr>
                                    <th class="py-3 px-4">Tagihan</th>
                                    <th class="py-3 px-4">Jatuh Tempo</th>
                                    <th class="py-3 px-4 text-right">Jumlah</th>
                                    <th class="py-3 px-4 text-center">Status</th>
                                    <th class="py-3 px-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="schedule in booking.payment_schedules" :key="schedule.id" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-4 py-3">
                                        <p class="font-black text-slate-900 text-xs">{{ schedule.label }}</p>
                                        <p class="text-[10px] text-slate-400">#{{ schedule.installment_number }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 font-medium text-xs">
                                        {{ new Date(schedule.due_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}
                                    </td>
                                    <td class="px-4 py-3 font-black text-slate-900 text-xs text-right">
                                        Rp {{ Number(schedule.amount).toLocaleString('id-ID') }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span :class="statusColors[schedule.status]" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                            {{ schedule.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-1.5">
                                        <button v-if="schedule.status !== 'paid'" @click="openPaymentModal(schedule)" class="px-2.5 py-1 bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-blue-700">Bayar</button>
                                        <div v-else class="inline-flex items-center gap-1.5 mr-2">
                                            <span class="text-[10px] text-emerald-600 font-black uppercase tracking-widest">Lunas ✅</span>
                                            <a 
                                                v-if="getScheduleTransaction(schedule)"
                                                :href="`/finance/transactions/${getScheduleTransaction(schedule).id}/receipt`" 
                                                target="_blank" 
                                                class="px-2 py-0.5 bg-emerald-50 border border-emerald-300 text-emerald-700 hover:bg-emerald-100 font-bold text-[10px] rounded-lg transition-all inline-flex items-center gap-1 shadow-sm"
                                                title="Cetak & Download Kwitansi PDF Resmi (TTD & Cap)"
                                            >
                                                <span>📄 Kwitansi PDF</span>
                                            </a>
                                        </div>

                                        <button @click="sendEmailRow(schedule)" class="p-1 text-slate-400 hover:text-blue-600 transition-colors" title="Kirim Invoice Email Tagihan Ke Konsumen">✉️</button>
                                        <button @click="openEditRow(schedule)" class="p-1 text-slate-400 hover:text-amber-600 transition-colors" title="Edit Baris">✏️</button>
                                        <button @click="deleteRow(schedule)" class="p-1 text-slate-400 hover:text-rose-600 transition-colors" title="Hapus Baris Tagihan Ini">🗑️</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-10">
                        <p class="text-xs text-slate-400">Jadwal pembayaran akan otomatis digenerate setelah booking disetujui.</p>
                    </div>
                </div>

                <!-- TRANSACTION HISTORY -->
                <div class="bg-white rounded-3xl border border-slate-100 p-8 shadow-sm mb-8">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-900 mb-6">Transaction History</h3>
                    <div v-if="booking.transactions?.length" class="space-y-4">
                        <div v-for="tx in booking.transactions" :key="tx.id" class="flex flex-col sm:flex-row sm:items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100 gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-lg shadow-sm">💰</div>
                                <div>
                                    <p class="text-xs font-black text-slate-900 uppercase tracking-tight">{{ tx.payment_method }} - {{ tx.bank_name || 'Cash' }}</p>
                                    <p class="text-[10px] text-slate-400">{{ tx.notes || 'Pembayaran' }} • {{ new Date(tx.created_at).toLocaleDateString('id-ID') }}</p>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap items-center gap-2 justify-end">
                                <span class="text-sm font-black text-slate-900 mr-2">Rp {{ Number(tx.amount).toLocaleString('id-ID') }}</span>
                                
                                <a :href="`/finance/transactions/${tx.id}/receipt`" target="_blank" class="px-2.5 py-1 bg-white border border-slate-200 text-slate-700 text-[10px] font-black rounded-lg hover:bg-slate-100 transition-all flex items-center gap-1 shadow-sm">
                                    <span>📄</span> <span>Kwitansi TTD & Cap</span>
                                </a>

                                <button @click="sendWaTxReceipt(tx)" class="px-2.5 py-1 bg-emerald-600 text-white text-[10px] font-black rounded-lg hover:bg-emerald-700 transition-all shadow-sm flex items-center gap-1">
                                    <span>💬</span> <span>Kirim WA</span>
                                </button>

                                <a v-if="tx.wet_receipt_file" :href="`/storage/${tx.wet_receipt_file}`" target="_blank" class="px-2.5 py-1 bg-amber-500 text-white text-[10px] font-black rounded-lg hover:bg-amber-600 transition-all shadow-sm flex items-center gap-1">
                                    <span>📎</span> <span>Kwitansi Basah</span>
                                </a>

                                <button @click="deleteTransaction(tx.id)" class="text-[10px] font-black text-rose-500 uppercase tracking-tighter hover:underline ml-1">Hapus</button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-10 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-100">
                        <p class="text-[11px] text-slate-400 font-bold uppercase tracking-widest">Belum ada transaksi tercatat.</p>
                    </div>
                </div>
            </div>

            <!-- SUMMARY SIDEBAR -->
            <div class="space-y-6">
                <div class="bg-slate-900 rounded-2xl p-6 text-white shadow-xl">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-blue-400 mb-6">Finansial</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-slate-400">Harga Dasar</span>
                            <span class="text-xs font-black">{{ formatCurrency(booking.base_price) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-slate-400">Pajak PPN (11%)</span>
                            <span class="text-xs font-black">{{ formatCurrency(booking.ppn_amount) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-slate-400">BPHTB + AJB/BBN</span>
                            <span class="text-xs font-black">{{ formatCurrency(Number(booking.bphtb_amount) + Number(booking.ajb_bbn_amount)) }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-white/10">
                            <span class="text-xs text-white font-black">Total All-in</span>
                            <span class="text-lg font-black text-blue-400">{{ formatCurrency(booking.final_price) }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-4 border-t border-white/10">
                            <span class="text-xs text-slate-400">Booking Fee (UTJ)</span>
                            <span class="text-sm font-black text-emerald-400">{{ formatCurrency(booking.booking_fee) }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-4 border-t border-white/10">
                            <span class="text-xs text-slate-400">Total Harga</span>
                            <span class="text-lg font-black">{{ formatCurrency(booking.final_price) }}</span>
                        </div>
                    </div>
                </div>

                <!-- COMMISSION BOX -->
                <div v-if="booking.commission_amount > 0" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 mb-4">Komisi Sales</h3>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-lg font-black text-blue-600">{{ formatCurrency(booking.commission_amount) }}</p>
                            <p class="text-[10px] text-slate-400 font-bold uppercase">{{ booking.commission_status }}</p>
                        </div>
                        <span v-if="booking.commission_status === 'paid'" class="w-8 h-8 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center">✓</span>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 mb-4">Catatan Internal</h3>
                    <p class="text-xs text-slate-500 italic leading-relaxed">
                        {{ booking.notes || 'Tidak ada catatan khusus.' }}
                    </p>
                </div>

                <!-- DOKUMEN PERSYARATAN KONSUMEN -->
                <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm space-y-5">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center justify-between">
                        <span>📁 Berkas Persyaratan</span>
                        <span class="text-[9px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded font-black">{{ booking.documents?.length || 0 }} File</span>
                    </h3>

                    <!-- Upload Form -->
                    <form @submit.prevent="submitDocument" class="bg-slate-50 p-4 rounded-xl border border-slate-100 space-y-3">
                        <span class="text-[9px] font-black text-slate-400 uppercase">Unggah Berkas Baru</span>
                        <div class="grid grid-cols-2 gap-2">
                            <select v-model="docForm.type" class="px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-[10px] font-black text-slate-700 focus:ring-1 focus:ring-blue-500 cursor-pointer">
                                <option value="ktp">🪪 KTP Pemesan</option>
                                <option value="kk">👨‍👩‍👧‍👦 Kartu Keluarga</option>
                                <option value="npwp">💳 NPWP</option>
                                <option value="payment_proof">🧾 Bukti Bayar</option>
                                <option value="other">📁 Dokumen Lain</option>
                            </select>
                            <input id="doc-file-input" type="file" @change="handleFileChange" class="hidden" accept=".jpg,.jpeg,.png,.pdf" />
                            <button type="button" @click="document.getElementById('doc-file-input').click()" class="px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-[10px] font-black text-slate-600 hover:bg-slate-100 text-center truncate">
                                {{ docForm.file ? docForm.file.name : '📎 Pilih File' }}
                            </button>
                        </div>
                        <button type="submit" :disabled="docForm.processing || !docForm.file" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white text-[9px] font-black uppercase tracking-widest rounded-lg transition-all shadow-md shadow-blue-500/10 disabled:opacity-40">
                            {{ docForm.processing ? 'Mengunggah...' : 'Unggah Dokumen' }}
                        </button>
                    </form>

                    <!-- Document List -->
                    <div class="space-y-2">
                        <div v-for="doc in booking.documents" :key="doc.id" class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100 text-xs">
                            <div class="min-w-0 flex-1 pr-2">
                                <p class="text-[9px] font-black text-slate-400 uppercase">{{ docTypeLabels[doc.type] }}</p>
                                <a :href="`/storage/${doc.file_path}`" target="_blank" class="font-bold text-blue-600 hover:underline block truncate mt-0.5">{{ doc.name }}</a>
                            </div>
                            <button @click="deleteDocument(doc.id)" class="text-rose-500 hover:bg-rose-50 p-1.5 rounded-lg transition-colors shrink-0">
                                🗑️
                            </button>
                        </div>
                        <div v-if="!booking.documents?.length" class="text-center py-6 text-slate-400 italic text-[10px] font-bold bg-slate-50 rounded-xl border border-dashed border-slate-200">
                            Belum ada dokumen persyaratan diunggah.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </CrmLayout>

    <!-- PAYMENT MODAL -->
    <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showPaymentModal = false"></div>
        <div class="relative bg-white rounded-[2.5rem] w-full max-w-md overflow-hidden shadow-2xl animate-in zoom-in duration-200 p-10">
            <h3 class="text-xl font-black text-slate-900 mb-2 uppercase tracking-tight">Catat Pembayaran</h3>
            <p class="text-xs text-slate-500 mb-8">{{ selectedSchedule?.label }}</p>

            <form @submit.prevent="submitPayment" class="space-y-5">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Jumlah Pembayaran (IDR)</label>
                    <input v-model="paymentForm.amount" type="number" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-black focus:ring-2 focus:ring-blue-600/20" />
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Metode</label>
                    <select v-model="paymentForm.payment_method" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-black focus:ring-2 focus:ring-blue-600/20">
                        <option value="transfer">Transfer Bank</option>
                        <option value="cash">Tunai / Cash</option>
                        <option value="cheque">Cek / Giro</option>
                    </select>
                </div>
                <div v-if="paymentForm.payment_method === 'transfer'">
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Nama Bank & Ref</label>
                    <div class="grid grid-cols-2 gap-3">
                        <input v-model="paymentForm.bank_name" type="text" placeholder="Bank" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-black focus:ring-2 focus:ring-blue-600/20" />
                        <input v-model="paymentForm.reference_number" type="text" placeholder="No. Ref" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-black focus:ring-2 focus:ring-blue-600/20" />
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Catatan</label>
                    <textarea v-model="paymentForm.notes" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-black focus:ring-2 focus:ring-blue-600/20" rows="2"></textarea>
                </div>
                <div class="pt-4">
                    <button type="submit" :disabled="paymentForm.processing"
                        class="w-full py-5 bg-blue-600 text-white font-black rounded-[1.5rem] shadow-xl shadow-blue-600/20 hover:-translate-y-1 transition-all active:scale-95 disabled:opacity-50">
                        SIMPAN PEMBAYARAN
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- SPK / SPR PREVIEW MODAL -->
    <teleport to="body">
        <div v-if="showSpkPreview" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showSpkPreview = false"></div>
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[92vh] flex flex-col p-6 md:p-8">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100 shrink-0">
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-widest text-slate-900">Pratinjau Surat Pemesanan Rumah (SPR)</h3>
                        <p class="text-[10px] text-slate-400">Pratinjau dokumen sesuai template DNA Umala/Andara & Pengaturan Control Room.</p>
                    </div>
                    <button @click="showSpkPreview = false" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-400 font-bold">&times;</button>
                </div>

                <!-- Live Document Stream Iframe -->
                <div class="flex-1 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-inner">
                    <iframe :src="`/bookings/${booking.id}/spk/view?html=1`" class="w-full h-full min-h-[500px] border-0 rounded-2xl"></iframe>
                </div>

                <div class="mt-4 flex justify-end gap-3 shrink-0 pt-2">
                    <button @click="showSpkPreview = false" class="px-6 py-3 text-xs font-bold text-slate-500 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all">Tutup</button>
                    <a :href="`/bookings/${booking.id}/spk`" target="_blank" class="px-6 py-3 bg-slate-900 text-white text-xs font-bold rounded-xl shadow-lg hover:shadow-slate-800 transition-all flex items-center gap-2">
                        📥 Download PDF Resmi
                    </a>
                </div>
            </div>
        </div>

        <!-- REGENERATE SCHEDULE MODAL -->
        <div v-if="showScheduleModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showScheduleModal = false"></div>
            <div class="relative bg-white rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl p-8 animate-in zoom-in duration-150">
                <div class="flex justify-between items-center mb-6 pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <span>⚙️</span> <span>Atur Skema & Tenor Cash Bertahap</span>
                    </h3>
                    <button @click="showScheduleModal = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitRegenSchedule" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Skema Pembayaran</label>
                        <select v-model="regenForm.payment_scheme" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-amber-500/20">
                            <option value="cash_installment">Cash Bertahap / In-House</option>
                            <option value="cash">Cash Keras</option>
                            <option value="kpr">KPR Bank</option>
                        </select>
                    </div>

                    <div v-if="regenForm.payment_scheme === 'cash_installment'">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tenor Cicilan (Bulan / Tahun)</label>
                        <div class="grid grid-cols-2 gap-3">
                            <select v-model.number="regenForm.installment_months" class="px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-amber-500/20">
                                <option :value="6">6 Bulan (0.5 Tahun)</option>
                                <option :value="12">12 Bulan (1 Tahun)</option>
                                <option :value="24">24 Bulan (2 Tahun)</option>
                                <option :value="36">36 Bulan (3 Tahun)</option>
                                <option :value="48">48 Bulan (4 Tahun)</option>
                                <option :value="60">60 Bulan (5 Tahun)</option>
                                <option :value="72">72 Bulan (6 Tahun)</option>
                            </select>
                            <input v-model.number="regenForm.installment_months" type="number" min="1" max="360" placeholder="Custom (Bulan)" class="px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-amber-500/20" />
                        </div>
                        <p class="text-[10px] text-amber-600 mt-1 font-bold">Total Durasi: {{ regenForm.installment_months }} Bulan ({{ (regenForm.installment_months / 12).toFixed(1) }} Tahun)</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Uang Muka / DP (Rp)</label>
                            <input v-model.number="regenForm.dp_amount" type="number" placeholder="0" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tenor DP (Bulan)</label>
                            <input v-model.number="regenForm.dp_installment_months" type="number" placeholder="0" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" />
                        </div>
                    </div>

                    <div class="p-3 bg-amber-50 rounded-xl border border-amber-100 text-[10px] text-amber-800 leading-relaxed font-medium">
                        ⚠️ **Perhatian**: Meng-generate ulang jadwal akan memperbarui seluruh baris tagihan yang belum lunas disesuaikan dengan tenor & skema pembayaran baru.
                    </div>

                    <div class="pt-3 flex justify-end gap-2">
                        <button type="button" @click="showScheduleModal = false" class="px-4 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                        <button type="submit" :disabled="regenForm.processing" class="px-6 py-2.5 bg-amber-600 text-white text-xs font-black rounded-xl shadow-lg hover:bg-amber-700 uppercase">
                            GENERATE ULANG JADWAL
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ADD SCHEDULE ROW MODAL -->
        <div v-if="showAddRowModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showAddRowModal = false"></div>
            <div class="relative bg-white rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-8">
                <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-4">Tambah Baris Tagihan Baru</h3>
                <form @submit.prevent="submitAddRow" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Nama / Label Tagihan</label>
                        <input v-model="addRowForm.label" type="text" placeholder="misal: Balloon Payment Th-1" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" required />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Jumlah Nominal (Rp)</label>
                        <input v-model.number="addRowForm.amount" type="number" placeholder="50000000" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" required />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Tanggal Jatuh Tempo</label>
                        <input v-model="addRowForm.due_date" type="date" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" required />
                    </div>
                    <div class="pt-3 flex justify-end gap-2">
                        <button type="button" @click="showAddRowModal = false" class="px-4 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                        <button type="submit" :disabled="addRowForm.processing" class="px-6 py-2.5 bg-slate-900 text-white text-xs font-black rounded-xl shadow-lg uppercase">
                            SIMPAN BARIS
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- EDIT SCHEDULE ROW MODAL -->
        <div v-if="editingScheduleRow" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="editingScheduleRow = null"></div>
            <div class="relative bg-white rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-8">
                <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-4">Edit Baris Tagihan</h3>
                <form @submit.prevent="submitEditRow" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Nama / Label Tagihan</label>
                        <input v-model="editRowForm.label" type="text" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" required />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Jumlah Nominal (Rp)</label>
                        <input v-model.number="editRowForm.amount" type="number" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" required />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Tanggal Jatuh Tempo</label>
                        <input v-model="editRowForm.due_date" type="date" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" required />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Status Tagihan</label>
                        <select v-model="editRowForm.status" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold">
                            <option value="upcoming">Upcoming (Belum Bayar)</option>
                            <option value="paid">Paid (Lunas)</option>
                            <option value="overdue">Overdue (Jatuh Tempo)</option>
                            <option value="partial">Partial (Sebagian)</option>
                        </select>
                    </div>
                    <div class="pt-3 flex justify-end gap-2">
                        <button type="button" @click="editingScheduleRow = null" class="px-4 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                        <button type="submit" :disabled="editRowForm.processing" class="px-6 py-2.5 bg-blue-600 text-white text-xs font-black rounded-xl shadow-lg uppercase">
                            SIMPAN PERUBAHAN
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- EDIT SPR TEMPLATE MODAL (PER BOOKING) -->
        <div v-if="showSprTemplateModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showSprTemplateModal = false"></div>
            <div class="relative bg-white rounded-3xl w-full max-w-3xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
                <!-- Modal Header -->
                <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wider text-amber-400">⚙️ Edit Setting & Parameter SPR / Kwitansi</h3>
                        <p class="text-xs text-slate-400">Kustomisasi Khusus Unit {{ booking.unit?.block }}{{ booking.unit?.number }} ({{ booking.lead?.name }})</p>
                    </div>
                    <button @click="showSprTemplateModal = false" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
                </div>

                <!-- Tabs Bar -->
                <div class="flex border-b border-slate-100 bg-slate-50 px-6 gap-2 pt-2 overflow-x-auto">
                    <button type="button" @click="activeSprTab = 'bank'" :class="activeSprTab === 'bank' ? 'bg-white text-blue-600 border-b-2 border-blue-600 font-black shadow-sm' : 'text-slate-500 font-bold hover:text-slate-800'" class="px-4 py-2.5 text-xs rounded-t-xl transition-all shrink-0">
                        💳 Bank Developer (Per-Baris LOV)
                    </button>
                    <button type="button" @click="activeSprTab = 'consumer'" :class="activeSprTab === 'consumer' ? 'bg-white text-blue-600 border-b-2 border-blue-600 font-black shadow-sm' : 'text-slate-500 font-bold hover:text-slate-800'" class="px-4 py-2.5 text-xs rounded-t-xl transition-all shrink-0">
                        👤 Data Konsumen & Pemesan 2
                    </button>
                    <button type="button" @click="activeSprTab = 'terms'" :class="activeSprTab === 'terms' ? 'bg-white text-blue-600 border-b-2 border-blue-600 font-black shadow-sm' : 'text-slate-500 font-bold hover:text-slate-800'" class="px-4 py-2.5 text-xs rounded-t-xl transition-all shrink-0">
                        📋 Catatan-catatan (Syarat)
                    </button>
                    <button type="button" @click="activeSprTab = 'signatures'" :class="activeSprTab === 'signatures' ? 'bg-white text-blue-600 border-b-2 border-blue-600 font-black shadow-sm' : 'text-slate-500 font-bold hover:text-slate-800'" class="px-4 py-2.5 text-xs rounded-t-xl transition-all shrink-0">
                        🖊️ Penanda Tangan (4 TTD)
                    </button>
                    <button type="button" @click="activeSprTab = 'special_offer'" :class="activeSprTab === 'special_offer' ? 'bg-white text-purple-600 border-b-2 border-purple-600 font-black shadow-sm' : 'text-slate-500 font-bold hover:text-slate-800'" class="px-4 py-2.5 text-xs rounded-t-xl transition-all shrink-0">
                        🎁 Special Offer & Benefit (Hal 2)
                    </button>
                    <button type="button" @click="activeSprTab = 'receipt'" :class="activeSprTab === 'receipt' ? 'bg-white text-emerald-600 border-b-2 border-emerald-600 font-black shadow-sm' : 'text-slate-500 font-bold hover:text-slate-800'" class="px-4 py-2.5 text-xs rounded-t-xl transition-all shrink-0">
                        🧾 Parameter Kwitansi
                    </button>
                </div>

                <!-- Modal Body Scrollable -->
                <div class="p-6 overflow-y-auto space-y-4 flex-1">
                    <form id="spr-template-form" @submit.prevent="submitSprTemplate">
                        <!-- TAB 1: BANK DEVELOPER LOV & NO SPR -->
                        <div v-if="activeSprTab === 'bank'" class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Nomor Surat SPR</label>
                                <input v-model="sprTemplateForm.spk_number" type="text" placeholder="002/SPR-ALN/VIII/2026" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold text-slate-800" />
                                <p class="text-[10px] text-slate-400 mt-1">Format penomoran surat khusus untuk unit booking ini.</p>
                            </div>

                            <!-- LOV PER-BARIS PEMBAYARAN -->
                            <div class="p-4 bg-blue-50/40 rounded-2xl border border-blue-100 space-y-3">
                                <span class="text-[10px] font-black text-blue-900 uppercase tracking-wider block">🏦 PER-BARIS REKENING BANK PENCAIRAN (UTJ / DP / CICILAN)</span>
                                
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-700 uppercase mb-1">1. Rekening Booking Fee (UTJ)</label>
                                    <select v-model="sprTemplateForm.bank_account_utj_id" @change="handleUtjBankChange" class="w-full px-3 py-2 bg-white border border-blue-200 rounded-xl text-xs font-bold text-slate-800">
                                        <option value="">-- Gunakan Bank Default --</option>
                                        <option v-for="b in bank_accounts_all" :key="b.id" :value="b.id">
                                            {{ b.bank_name }} - {{ b.account_number }} (a.n {{ b.account_holder }})
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold text-slate-700 uppercase mb-1">2. Rekening Uang Muka (DP)</label>
                                    <select v-model="sprTemplateForm.bank_account_dp_id" @change="handleDpBankChange" class="w-full px-3 py-2 bg-white border border-blue-200 rounded-xl text-xs font-bold text-slate-800">
                                        <option value="">-- Gunakan Bank Default --</option>
                                        <option v-for="b in bank_accounts_all" :key="b.id" :value="b.id">
                                            {{ b.bank_name }} - {{ b.account_number }} (a.n {{ b.account_holder }})
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold text-slate-700 uppercase mb-1">3. Rekening Cicilan Bertahap / Pelunasan KPR</label>
                                    <select v-model="sprTemplateForm.bank_account_installment_id" @change="handleInstallmentBankChange" class="w-full px-3 py-2 bg-white border border-blue-200 rounded-xl text-xs font-bold text-slate-800">
                                        <option value="">-- Gunakan Bank Default --</option>
                                        <option v-for="b in bank_accounts_all" :key="b.id" :value="b.id">
                                            {{ b.bank_name }} - {{ b.account_number }} (a.n {{ b.account_holder }})
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-slate-100">
                                <label class="block text-[10px] font-black text-slate-600 uppercase mb-1">Rekening Bank Utama (Default)</label>
                                <select v-model="sprTemplateForm.bank_account_id" @change="handleBankAccountChange" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 cursor-pointer">
                                    <option value="">-- Pilih Dari Rekening Bank Terdaftar (LOV) --</option>
                                    <option v-for="b in bank_accounts_all" :key="b.id" :value="b.id">
                                        {{ b.bank_name }} - {{ b.account_number }} (a.n {{ b.account_holder }})
                                    </option>
                                </select>
                            </div>

                            <!-- INFORMASI LEGALITAS & SERTIFIKAT UNIT -->
                            <div class="p-4 bg-emerald-50/60 rounded-2xl border border-emerald-200/80 space-y-3">
                                <span class="text-[10px] font-black text-emerald-900 uppercase tracking-widest block">📜 Status & No. Sertifikat Unit Properti</span>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-emerald-900 uppercase mb-1">Status Sertifikat</label>
                                        <input v-model="sprTemplateForm.unit_certificate_status" type="text" placeholder="SHM / HGB / Dalam Proses" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-emerald-900 uppercase mb-1">No. Sertifikat</label>
                                        <input v-model="sprTemplateForm.unit_certificate_number" type="text" placeholder="No. Sertifikat Hak Milik..." class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB CONSUMER: DATA KONSUMEN & PEMESAN 2 -->
                        <div v-if="activeSprTab === 'consumer'" class="space-y-4">
                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                                <span class="text-[10px] font-black text-blue-600 uppercase tracking-widest block">👤 Pemesan Utama</span>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">NIK KTP</label>
                                        <input v-model="sprTemplateForm.buyer_nik" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">NPWP</label>
                                        <input v-model="sprTemplateForm.buyer_npwp" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Pekerjaan</label>
                                        <input v-model="sprTemplateForm.buyer_job" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Alamat KTP</label>
                                        <input v-model="sprTemplateForm.buyer_address" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 bg-amber-50/60 rounded-2xl border border-amber-200/80 space-y-3">
                                <span class="text-[10px] font-black text-amber-800 uppercase tracking-widest block">🤝 Penanggung Jawab / Pemesan 2</span>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-amber-900 uppercase mb-1">Nama Pemesan 2</label>
                                        <input v-model="sprTemplateForm.secondary_name" type="text" placeholder="Nama Lengkap" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-amber-900 uppercase mb-1">NIK KTP Pemesan 2</label>
                                        <input v-model="sprTemplateForm.secondary_nik" type="text" placeholder="NIK KTP" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-amber-900 uppercase mb-1">NPWP Pemesan 2</label>
                                        <input v-model="sprTemplateForm.secondary_npwp" type="text" placeholder="NPWP Pemesan 2" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-amber-900 uppercase mb-1">Hubungan</label>
                                        <input v-model="sprTemplateForm.secondary_relationship" type="text" placeholder="Orang Tua / Pasangan" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-amber-900 uppercase mb-1">No. HP / WA Pemesan 2</label>
                                        <input v-model="sprTemplateForm.secondary_phone" type="text" placeholder="08..." class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-amber-900 uppercase mb-1">Alamat KTP Pemesan 2</label>
                                        <input v-model="sprTemplateForm.secondary_address" type="text" placeholder="Jl. Iskandarsyah..." class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-amber-900 uppercase mb-1">Email Pemesan 2</label>
                                        <input v-model="sprTemplateForm.secondary_email" type="email" placeholder="email@domain.com" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: CATATAN-CATATAN (SYARAT & KETENTUAN) -->
                        <div v-if="activeSprTab === 'terms'" class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black text-slate-700 uppercase">Daftar Poin Catatan-catatan (SPR)</span>
                                <button type="button" @click="addTermItem" class="px-3 py-1.5 bg-blue-50 text-blue-600 text-xs font-bold rounded-lg hover:bg-blue-100 transition-all">
                                    + Tambah Poin Catatan
                                </button>
                            </div>

                            <div v-for="(term, idx) in sprTemplateForm.spr_terms_conditions" :key="idx" class="flex items-start gap-2">
                                <span class="w-6 h-6 bg-slate-100 rounded-lg flex items-center justify-center text-xs font-bold text-slate-600 mt-1 flex-shrink-0">{{ idx + 1 }}</span>
                                <textarea v-model="sprTemplateForm.spr_terms_conditions[idx]" rows="2" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium resize-y focus:ring-2 focus:ring-blue-500/20"></textarea>
                                <button type="button" @click="removeTermItem(idx)" class="p-2 text-rose-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 flex-shrink-0 mt-1">
                                    🗑️
                                </button>
                            </div>
                        </div>

                        <!-- TAB 3: PENANDA TANGAN (4 SLOT TTD) -->
                        <div v-if="activeSprTab === 'signatures'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                                <span class="text-[10px] font-black text-blue-600 uppercase tracking-widest block">Slot TTD 1 (Sales / Staff)</span>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1">Judul Jabatan</label>
                                    <input v-model="sprTemplateForm.sig1_title" type="text" placeholder="AGENT COORDINATOR" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1">Nama Pejabat</label>
                                    <input v-model="sprTemplateForm.sig1_name" type="text" placeholder="Maulizar Hamid" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                </div>
                            </div>

                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                                <span class="text-[10px] font-black text-blue-600 uppercase tracking-widest block">Slot TTD 2 (Management / Direktur)</span>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1">Judul Jabatan</label>
                                    <input v-model="sprTemplateForm.sig2_title" type="text" placeholder="DIREKTUR" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1">Nama Pejabat</label>
                                    <input v-model="sprTemplateForm.sig2_name" type="text" placeholder="Ch. Bramantyo P. S" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                </div>
                            </div>

                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                                <span class="text-[10px] font-black text-blue-600 uppercase tracking-widest block">Slot TTD 3 (Sales Agent)</span>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1">Judul Jabatan</label>
                                    <input v-model="sprTemplateForm.sig3_title" type="text" placeholder="SALES" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1">Nama Pejabat</label>
                                    <input v-model="sprTemplateForm.sig3_name" type="text" placeholder="Mawardi KanaProject" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                </div>
                            </div>

                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                                <span class="text-[10px] font-black text-blue-600 uppercase tracking-widest block">Slot TTD 4 (Pemesan / Penanggung Jawab)</span>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1">Judul Jabatan</label>
                                    <input v-model="sprTemplateForm.sig4_title" type="text" placeholder="Penanggung Jawab (Orang Tua)" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1">Nama Pejabat</label>
                                    <input v-model="sprTemplateForm.sig4_name" type="text" placeholder="Fatimah Rafiuddin" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                </div>
                            </div>

                            <!-- PENGATURAN TANGGAL & TTD OVERRIDES -->
                            <div class="col-span-1 md:col-span-2 p-4 bg-blue-50/60 rounded-2xl border border-blue-200/80 space-y-3">
                                <span class="text-[10px] font-black text-blue-900 uppercase tracking-widest block">📅 Tanggal TTD SPR & Override Jadwal Pembayaran</span>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-700 uppercase mb-1">Kota Penandatanganan SPR</label>
                                        <input v-model="sprTemplateForm.sigs_city" type="text" placeholder="misal: Jakarta Selatan" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-700 uppercase mb-1">Tanggal TTD SPR (Di Atas Kolom TTD)</label>
                                        <input v-model="sprTemplateForm.spr_date" type="date" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-blue-100">
                                    <span class="text-[10px] font-black text-blue-900 uppercase tracking-wider block mb-2">🗓️ Custom Tanggal Jadwal Pembayaran di Tabel SPR (Opsional Override)</span>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-600 mb-1">Tgl UTJ / Booking Fee</label>
                                            <input v-model="sprTemplateForm.spr_schedule_dates.utj_date" type="text" placeholder="misal: 25 Agustus 2026" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-600 mb-1">Tgl DP / Uang Muka</label>
                                            <input v-model="sprTemplateForm.spr_schedule_dates.dp_date" type="text" placeholder="misal: 25 September 2026" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-600 mb-1">Tgl Pelunasan / Akad KPR</label>
                                            <input v-model="sprTemplateForm.spr_schedule_dates.installment_date" type="text" placeholder="misal: Saat Akad Kredit" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 5: SPECIAL OFFER & BENEFIT (LAMPIRAN HALAMAN 2) -->
                        <div v-if="activeSprTab === 'special_offer'" class="space-y-4">
                            <div class="p-4 bg-purple-50/60 rounded-2xl border border-purple-200/80 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-black text-purple-900 uppercase tracking-widest block">🎁 Pengaturan Halaman 2 Special Offer & Benefit</span>
                                    <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-purple-900">
                                        <input v-model="sprTemplateForm.spr_special_offer.enabled" type="checkbox" class="w-4 h-4 text-purple-600 rounded" />
                                        <span>Aktifkan Halaman Lampiran Special Offer</span>
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-700 uppercase mb-1">Judul Dokumen Special Offer</label>
                                        <input v-model="sprTemplateForm.spr_special_offer.title" type="text" placeholder="Special Offer & Benefit..." class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-700 uppercase mb-1">Masa Berlaku Promo</label>
                                        <input v-model="sprTemplateForm.spr_special_offer.promo_valid_until" type="text" placeholder="30 September 2026" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                </div>
                            </div>

                            <!-- SPECIAL BONUS FURNITURE LIST -->
                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-black text-slate-700 uppercase">🛋️ Special Bonus Furniture</span>
                                    <button type="button" @click="addBonusItem" class="px-3 py-1 bg-purple-100 text-purple-700 text-xs font-bold rounded-lg hover:bg-purple-200 transition-all">
                                        + Tambah Bonus Item
                                    </button>
                                </div>
                                <div v-for="(item, idx) in sprTemplateForm.special_bonus_items" :key="'bonus-' + idx" class="flex items-center gap-2">
                                    <span class="text-xs text-slate-400 font-bold w-5 text-right">{{ idx + 1 }}.</span>
                                    <input v-model="sprTemplateForm.special_bonus_items[idx]" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold" />
                                    <button type="button" @click="removeBonusItem(idx)" class="p-1.5 text-rose-500 hover:bg-rose-100 rounded-lg">
                                        🗑️
                                    </button>
                                </div>
                            </div>

                            <!-- SPECIAL GRAND LAUNCHING PACKAGE LIST -->
                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-black text-slate-700 uppercase">📦 Special Grand Launching Package</span>
                                    <button type="button" @click="addPackageItem" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded-lg hover:bg-blue-200 transition-all">
                                        + Tambah Paket Item
                                    </button>
                                </div>
                                <div v-for="(pkg, idx) in sprTemplateForm.special_package_items" :key="'pkg-' + idx" class="flex items-center gap-2">
                                    <span class="text-xs text-slate-400 font-bold w-5 text-right">{{ idx + 1 }}.</span>
                                    <input v-model="sprTemplateForm.special_package_items[idx]" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold" />
                                    <button type="button" @click="removePackageItem(idx)" class="p-1.5 text-rose-500 hover:bg-rose-100 rounded-lg">
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 6: SETTING PARAMETER KWITANSI -->
                        <div v-if="activeSprTab === 'receipt'" class="space-y-4">
                            <div class="p-4 bg-emerald-50/80 border border-emerald-200 rounded-2xl">
                                <h4 class="text-xs font-black text-emerald-900 uppercase tracking-wider flex items-center gap-1.5 mb-1">
                                    <span>🧾</span> <span>Kustomisasi Parameter Kwitansi Pembayaran</span>
                                </h4>
                                <p class="text-xs text-emerald-700">
                                    Atur format penomoran, lokasi/kota penerbitan, penanda tangan (TTD & Cap Stempel), serta catatan khusus yang akan tampil saat cetak/kirim Kwitansi untuk booking ini.
                                </p>
                            </div>

                            <!-- Pilihan Slot TTD & Stempel -->
                            <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                                <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider">✒️ Sumber TTD & Cap Stempel Kwitansi</label>
                                <select v-model="sprTemplateForm.receipt_settings.receipt_sig_slot" @change="onReceiptSigSlotChange" class="w-full px-4 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-bold text-slate-800 focus:ring-1 focus:ring-emerald-500">
                                    <option value="sig1">TTD Slot 1 ({{ sprTemplateForm.sig1_title || 'TTD 1' }} - {{ sprTemplateForm.sig1_name || 'Petugas' }})</option>
                                    <option value="sig2">TTD Slot 2 ({{ sprTemplateForm.sig2_title || 'TTD 2' }} - {{ sprTemplateForm.sig2_name || 'Direktur' }})</option>
                                    <option value="sig3">TTD Slot 3 ({{ sprTemplateForm.sig3_title || 'TTD 3' }} - {{ sprTemplateForm.sig3_name || 'Sales' }})</option>
                                    <option value="sig4">TTD Slot 4 ({{ sprTemplateForm.sig4_title || 'TTD 4' }} - {{ sprTemplateForm.sig4_name || 'Lainnya' }})</option>
                                </select>
                                <p class="text-[10px] text-slate-500">
                                    Gambar tanda tangan digital & stempel akan otomatis diambil dari slot TTD yang dipilih di atas (diatur pada tab <b>Penanda Tangan 4 TTD</b>).
                                </p>
                            </div>

                            <!-- Format Nomor & Override Kwitansi -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Prefix / Awalan Format No. Kwitansi</label>
                                    <input v-model="sprTemplateForm.receipt_settings.receipt_number_prefix" type="text" placeholder="Contoh: KW/ALN atau KW/PROJECT" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-1 focus:ring-emerald-500" />
                                    <p class="text-[10px] text-slate-400 mt-1">Jika diisi, nomor otomatis menjadi: Prefix/Tahun/ID (Contoh: KW/ALN/2026/0001)</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Nomor Kwitansi Override Manual (Opsional)</label>
                                    <input v-model="sprTemplateForm.receipt_settings.receipt_number_custom" type="text" placeholder="Contoh: KW-SPECIAL-001" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-1 focus:ring-emerald-500" />
                                    <p class="text-[10px] text-slate-400 mt-1">Jika diisi, nomor ini meng-override seluruh nomor kwitansi otomatis pada unit ini.</p>
                                </div>
                            </div>

                            <!-- Header Label & Kota -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Sub-Judul / Label Kwitansi</label>
                                    <input v-model="sprTemplateForm.receipt_settings.receipt_header_title" type="text" placeholder="Bukti Pembayaran Resmi" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-1 focus:ring-emerald-500" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Kota Penerbitan Kwitansi</label>
                                    <input v-model="sprTemplateForm.receipt_settings.receipt_city" type="text" placeholder="Contoh: Jakarta Selatan" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-1 focus:ring-emerald-500" />
                                </div>
                            </div>

                            <!-- Penanda Tangan Kwitansi -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Jabatan Penanda Tangan Kwitansi</label>
                                    <input v-model="sprTemplateForm.receipt_settings.receipt_sig_title" type="text" placeholder="Contoh: Kasir & Keuangan" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-1 focus:ring-emerald-500" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Nama Penanda Tangan Kwitansi</label>
                                    <input v-model="sprTemplateForm.receipt_settings.receipt_sig_name" type="text" placeholder="Contoh: Keuangan / Budi Santoso" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-1 focus:ring-emerald-500" />
                                </div>
                            </div>

                            <!-- Catatan / Syarat Ketentuan Kwitansi -->
                            <div>
                                <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Catatan Kaki / Catatan Penting Kwitansi</label>
                                <textarea v-model="sprTemplateForm.receipt_settings.receipt_notes" rows="3" placeholder="Contoh: Pembayaran dianggap sah apabila telah dibubuhi stempel & TTD resmi." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:ring-1 focus:ring-emerald-500"></textarea>
                                <p class="text-[10px] text-slate-400 mt-1">Catatan ini akan tampil di bagian bawah lembar kwitansi cetak.</p>
                            </div>

                            <!-- Live Preview Box -->
                            <div class="mt-4 p-4 bg-white border border-slate-200 rounded-2xl shadow-sm">
                                <div class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-1">
                                    <span>👁️</span> <span>Preview Parameter Kwitansi</span>
                                </div>
                                <div class="p-3 bg-slate-50 rounded-xl text-xs space-y-1.5">
                                    <div class="flex justify-between border-b border-slate-200 pb-1">
                                        <span class="font-bold text-slate-600">Sub-Judul:</span>
                                        <span class="font-black text-slate-800">{{ sprTemplateForm.receipt_settings.receipt_header_title || 'Bukti Pembayaran Resmi' }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-slate-200 pb-1">
                                        <span class="font-bold text-slate-600">No. Kwitansi Sample:</span>
                                        <span class="font-mono font-bold text-emerald-700">
                                            {{ sprTemplateForm.receipt_settings.receipt_number_custom || ((sprTemplateForm.receipt_settings.receipt_number_prefix || ('KW/' + (booking.unit?.project?.code || 'ALN'))) + '/2026/0001') }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between border-b border-slate-200 pb-1">
                                        <span class="font-bold text-slate-600">TTD Oleh:</span>
                                        <span class="font-bold text-slate-800">{{ sprTemplateForm.receipt_settings.receipt_city || 'Jakarta' }}, {{ sprTemplateForm.receipt_settings.receipt_sig_name || 'Keuangan' }} ({{ sprTemplateForm.receipt_settings.receipt_sig_title || 'Kasir & Keuangan' }})</span>
                                    </div>
                                    <div v-if="sprTemplateForm.receipt_settings.receipt_notes" class="pt-1">
                                        <span class="font-bold text-slate-600 block">Catatan:</span>
                                        <p class="text-[11px] text-slate-500 italic">{{ sprTemplateForm.receipt_settings.receipt_notes }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" @click="showSprTemplateModal = false" class="px-4 py-2.5 bg-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-300 transition-all">Batal</button>
                    <button type="submit" form="spr-template-form" :disabled="sprTemplateForm.processing" class="px-6 py-2.5 bg-blue-600 text-white text-xs font-black rounded-xl shadow-lg shadow-blue-500/20 uppercase hover:-translate-y-0.5 transition-all">
                        {{ sprTemplateForm.processing ? 'MEMPROSES...' : 'SIMPAN TEMPLATE KHUSUS BOOKING INI' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- DAFTAR KWITANSI MODAL -->
        <div v-if="showReceiptsModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-2xl w-full p-6 space-y-5 shadow-2xl">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>📄</span> <span>Daftar Kwitansi & Invoice Pembayaran Resmi</span>
                        </h3>
                        <p class="text-xs text-slate-500">Unduh atau cetak bukti kwitansi sah untuk konsumen {{ booking.lead?.name }}</p>
                    </div>
                    <button @click="showReceiptsModal = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <div class="space-y-3 max-h-[60vh] overflow-y-auto pr-1">
                    <div v-for="tx in booking.transactions" :key="tx.id" class="p-4 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white text-sm">{{ tx.notes || 'Pembayaran Unit Properti' }}</div>
                            <div class="text-xs text-slate-500 mt-0.5 flex items-center gap-2">
                                <span>🗓️ {{ new Date(tx.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}</span>
                                <span>•</span>
                                <span>💳 {{ tx.payment_method?.toUpperCase() }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="font-black text-emerald-600 dark:text-emerald-400 text-sm mr-2">{{ formatCurrency(tx.amount) }}</span>
                            <a :href="`/finance/transactions/${tx.id}/receipt`" target="_blank" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-1.5">
                                <span>📄</span> <span>Cetak / Download PDF</span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                    <button @click="showReceiptsModal = false" class="px-5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold rounded-xl transition-all">Tutup</button>
                </div>
            </div>
        </div>
    </teleport>
</template>
