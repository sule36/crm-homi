<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import KprProgress from '@/Components/Crm/KprProgress.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { buildScheduleSimulation, createDefaultCustomTerms, addMonthsIso } from '@/utils/bookingSchedule';

const props = defineProps({
    booking: Object,
    bank_accounts_all: {
        type: Array,
        default: () => []
    },
    units: {
        type: Array,
        default: () => []
    },
    signatureConfig: {
        type: Object,
        default: () => ({})
    },
    standardBookingFee: {
        type: Number,
        default: 17000000,
    },
});

const showPaymentModal = ref(false);
const selectedSchedule = ref(null);
const showSpkPreview = ref(false);
const sprPreviewTimestamp = ref(Date.now());
function openSpkPreview() {
    sprPreviewTimestamp.value = Date.now();
    showSpkPreview.value = true;
}
const showSprTemplateModal = ref(false);
const showReceiptsModal = ref(false);
const showChangeBookingUnitModal = ref(false);
const activeSprTab = ref('bank');

const changeBookingUnitForm = useForm({
    unit_id: props.booking.unit_id || '',
    reason: '',
});

function openChangeBookingUnitModal() {
    changeBookingUnitForm.unit_id = props.booking.unit_id || '';
    changeBookingUnitForm.reason = '';
    showChangeBookingUnitModal.value = true;
}

function submitChangeBookingUnit() {
    changeBookingUnitForm.post(`/bookings/${props.booking.id}/change-unit`, {
        preserveScroll: true,
        onSuccess: () => {
            showChangeBookingUnitModal.value = false;
        }
    });
}

// Unified Financial & Tax Management opens the 'financial' tab inside SPR Template Modal
function openFinancialModal() {
    openSprTemplateModal('financial', true);
}

const schemeLabels = {
    cash: 'Cash Keras (Tunai)',
    cash_installment: 'Cash Bertahap / In-House',
    kpr: 'KPR Bank',
};

const totalPaidAmount = computed(() => {
    const txSum = (props.booking?.transactions || []).reduce((acc, tx) => acc + Number(tx.amount || 0), 0);
    const paidSchedSum = (props.booking?.payment_schedules || [])
        .filter(s => s.status === 'paid')
        .reduce((acc, s) => acc + Number(s.amount || 0), 0);
    return Math.max(txSum, paidSchedSum);
});

const remainingBalance = computed(() => {
    return Math.max(0, Number(props.booking?.final_price || 0) - totalPaidAmount.value);
});

const paymentProgressPct = computed(() => {
    const finalPrice = Number(props.booking?.final_price || 0);
    if (finalPrice <= 0) return 0;
    return Math.min(100, Math.round((totalPaidAmount.value / finalPrice) * 100));
});

function getScheduleTransaction(schedule) {
    if (!props.booking?.transactions) return null;
    return props.booking.transactions.find(tx => tx.payment_schedule_id === schedule.id);
}

const effectiveUtjFormatted = Number(props.booking?.booking_fee || props.standardBookingFee || 17000000).toLocaleString('id-ID');

const defaultTerms = [
    "Pembeli menyatakan telah mengerti dan menyetujui serta akan tunduk kepada persyaratan dan ketentuan serta kebijakan yang ditetapkan oleh Pengembang dalam SPR",
    "Dalam hal pembelian rumah melalui KPR, jumlah DP dan persyaratan KPR lainnya tunduk pada ketentuan Bank pemberi KPR",
    "Dalam hal terjadi penolakan dari pihak Bank atau KPR tidak disetujui, maka Uang Tanda Jadi akan dikembalikan 100%",
    "Dalam hal KPR yang telah disetujui oleh Bank, maka Akad Kredit wajib dilaksanakan selambat-lambatnya 1 bulan sejak diterimanya down payment oleh Pengembang",
    "Dalam hal terjadi pembatalan sepihak oleh pembeli dalam masa pembangunan unit sesuai pilihan pembeli dalam SPR ini, maka seluruh pembayaran dari Pembeli akan hangus 100%",
    "Pembeli diperkenankan untuk memilih cara pembayaran selain KPR dengan syarat dan ketentuan dari Pengembang",
    "Pembayaran segala bentuk cicilan kepada Pengembang yang melebihi waktu yang telah ditentukan dalam SPR ini, akan dikenakan denda sebesar 1% per hari dengan denda maksimal 5% dari jumlah kewajiban yang terlambat",
    "Pembeli tidak diperkenankan untuk mengalihkan pembelian tanah dan bangunan, pengalihan pembelian akan dikenakan denda sebesar 2.5% dari harga jual final",
    `Nilai Uang Tanda Jadi ditetapkan sebesar Rp. ${effectiveUtjFormatted},-`,
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
    title: (isSoObj && rawSo.title) ? rawSo.title : ('Special Offer & Benefit ' + (props.booking.unit?.project?.name || 'Proyek Hunian')),
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
    sig4_name: props.booking.sig4_name || props.booking.lead?.name || '',
    sigs_city: props.booking.sigs_city || props.booking.spr_signatures?.city || 'Jakarta Selatan',
    receipt_settings: (props.booking.receipt_settings && typeof props.booking.receipt_settings === 'object')
        ? {
            receipt_sig_slot: props.booking.receipt_settings.receipt_sig_slot || 'sig1',
            receipt_number_prefix: props.booking.receipt_settings.receipt_number_prefix || '',
            receipt_number_custom: props.booking.receipt_settings.receipt_number_custom || '',
            receipt_bank_custom: props.booking.receipt_settings.receipt_bank_custom || '',
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
            receipt_bank_custom: '',
            receipt_city: props.booking.sigs_city || 'Jakarta Selatan',
            receipt_sig_title: props.booking.sig1_title || 'Kasir & Keuangan',
            receipt_sig_name: props.booking.sig1_name || '',
            receipt_header_title: 'Bukti Pembayaran Resmi',
            receipt_notes: 'Pembayaran ini dianggap sah apabila telah dibubuhi stempel & TTD resmi.',
        },
    base_price: Number(props.booking.base_price) || 0,
    free_ppn: Number(props.booking.ppn_amount) === 0,
    free_bphtb: Number(props.booking.bphtb_amount) === 0,
    free_ajb: Number(props.booking.ajb_bbn_amount) === 0,
    ppn_amount: Number(props.booking.ppn_amount) || 0,
    bphtb_amount: Number(props.booking.bphtb_amount) || 0,
    ajb_bbn_amount: Number(props.booking.ajb_bbn_amount) || 0,
    other_legal_fees: Number(props.booking.other_legal_fees) || 0,
    final_price: Number(props.booking.final_price) || 0,
    sync_schedules: true,
});

function handleSprBasePriceChange() {
    if (sprTemplateForm.free_ppn) {
        sprTemplateForm.ppn_amount = 0;
    } else {
        sprTemplateForm.ppn_amount = Math.round(Number(sprTemplateForm.base_price || 0) * 0.11);
    }
    if (sprTemplateForm.free_bphtb) {
        sprTemplateForm.bphtb_amount = 0;
    } else {
        sprTemplateForm.bphtb_amount = Math.max(0, Math.round((Number(sprTemplateForm.base_price || 0) - 60000000) * 0.05));
    }
    if (sprTemplateForm.free_ajb) {
        sprTemplateForm.ajb_bbn_amount = 0;
    } else {
        sprTemplateForm.ajb_bbn_amount = Math.round(Number(sprTemplateForm.base_price || 0) * 0.01);
    }
    recalculateSprFinancialTotal();
}

function toggleSprFreePpn() {
    if (sprTemplateForm.free_ppn) {
        sprTemplateForm.ppn_amount = 0;
    } else {
        sprTemplateForm.ppn_amount = Math.round(Number(sprTemplateForm.base_price || 0) * 0.11);
    }
    recalculateSprFinancialTotal();
}

function toggleSprFreeBphtb() {
    if (sprTemplateForm.free_bphtb) {
        sprTemplateForm.bphtb_amount = 0;
    } else {
        sprTemplateForm.bphtb_amount = Math.max(0, Math.round((Number(sprTemplateForm.base_price || 0) - 60000000) * 0.05));
    }
    recalculateSprFinancialTotal();
}

function toggleSprFreeAjb() {
    if (sprTemplateForm.free_ajb) {
        sprTemplateForm.ajb_bbn_amount = 0;
    } else {
        sprTemplateForm.ajb_bbn_amount = Math.round(Number(sprTemplateForm.base_price || 0) * 0.01);
    }
    recalculateSprFinancialTotal();
}

function setSprFinancialAllFree() {
    sprTemplateForm.free_ppn = true;
    sprTemplateForm.free_bphtb = true;
    sprTemplateForm.free_ajb = true;
    sprTemplateForm.ppn_amount = 0;
    sprTemplateForm.bphtb_amount = 0;
    sprTemplateForm.ajb_bbn_amount = 0;
    sprTemplateForm.other_legal_fees = 0;
    sprTemplateForm.final_price = Number(sprTemplateForm.base_price || 0);
}

function setSprFinancialStandardTaxes() {
    sprTemplateForm.free_ppn = false;
    sprTemplateForm.free_bphtb = false;
    sprTemplateForm.free_ajb = false;
    const base = Number(sprTemplateForm.base_price || 0);
    sprTemplateForm.ppn_amount = Math.round(base * 0.11);
    sprTemplateForm.bphtb_amount = Math.max(0, Math.round((base - 60000000) * 0.05));
    sprTemplateForm.ajb_bbn_amount = Math.round(base * 0.01);
    recalculateSprFinancialTotal();
}

function recalculateSprFinancialTotal() {
    sprTemplateForm.final_price = Number(sprTemplateForm.base_price || 0) +
        Number(sprTemplateForm.ppn_amount || 0) +
        Number(sprTemplateForm.bphtb_amount || 0) +
        Number(sprTemplateForm.ajb_bbn_amount || 0) +
        Number(sprTemplateForm.other_legal_fees || 0);
}

function onReceiptSigSlotChange() {
    const slot = sprTemplateForm.receipt_settings.receipt_sig_slot;
    if (slot === 'finance') {
        sprTemplateForm.receipt_settings.receipt_sig_title = props.signatureConfig?.finance_title || 'Finance & Kasir';
        sprTemplateForm.receipt_settings.receipt_sig_name = props.signatureConfig?.finance_name || 'Keuangan';
    } else if (slot === 'sig1') {
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

function openSprTemplateModal(tab = 'bank', autoSyncSchedules = false) {
    activeSprTab.value = typeof tab === 'string' ? tab : 'bank';
    sprTemplateForm.base_price = Number(props.booking.base_price) || 0;
    sprTemplateForm.free_ppn = Number(props.booking.ppn_amount) === 0;
    sprTemplateForm.free_bphtb = Number(props.booking.bphtb_amount) === 0;
    sprTemplateForm.free_ajb = Number(props.booking.ajb_bbn_amount) === 0;
    sprTemplateForm.ppn_amount = Number(props.booking.ppn_amount) || 0;
    sprTemplateForm.bphtb_amount = Number(props.booking.bphtb_amount) || 0;
    sprTemplateForm.ajb_bbn_amount = Number(props.booking.ajb_bbn_amount) || 0;
    sprTemplateForm.other_legal_fees = Number(props.booking.other_legal_fees) || 0;
    sprTemplateForm.final_price = Number(props.booking.final_price) || 0;
    sprTemplateForm.sync_schedules = Boolean(autoSyncSchedules);
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
    payment_date: '',
    payment_method: 'transfer',
    bank_name: '',
    reference_number: '',
    notes: '',
});

function openPaymentModal(schedule) {
    selectedSchedule.value = schedule;
    paymentForm.payment_schedule_id = schedule.id;
    paymentForm.amount = schedule.amount;
    paymentForm.payment_date = schedule.due_date 
        ? new Date(schedule.due_date).toISOString().split('T')[0] 
        : new Date().toISOString().split('T')[0];
    const label = schedule.label || 'Unit Properti';
    paymentForm.notes = label.toLowerCase().startsWith('pembayaran') ? label : `Pembayaran ${label}`;

    // Auto-fill target bank from booking's spr_bank_info
    const bInfo = props.booking.spr_bank_info || {};
    const schedLabel = (schedule.label || '').toLowerCase();
    let detectedBank = bInfo.main || bInfo;
    if ((schedLabel.includes('utj') || schedLabel.includes('booking')) && bInfo.utj?.bank_name) {
        detectedBank = bInfo.utj;
    } else if ((schedLabel.includes('dp') || schedLabel.includes('down payment')) && bInfo.dp?.bank_name) {
        detectedBank = bInfo.dp;
    } else if ((schedLabel.includes('cicilan') || schedLabel.includes('angsuran')) && bInfo.installment?.bank_name) {
        detectedBank = bInfo.installment;
    }

    if (detectedBank?.bank_name) {
        paymentForm.bank_name = detectedBank.account_number 
            ? `${detectedBank.bank_name} - ${detectedBank.account_number}` 
            : detectedBank.bank_name;
    } else {
        paymentForm.bank_name = '';
    }
    paymentForm.bank_account_id = props.booking.bank_account_id || null;

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

const existingUnpaidUnitSchedules = (props.booking.payment_schedules || []).filter(
    s => s.status !== 'paid' && s.installment_number !== 0 && s.installment_number !== 99
);
const paidUnitSchedules = computed(() =>
    (props.booking.payment_schedules || []).filter(
        s => s.status === 'paid' && s.installment_number !== 0 && s.installment_number !== 99
    )
);
const paidUnitAmount = computed(() =>
    paidUnitSchedules.value.reduce((sum, s) => sum + Math.round(Number(s.amount) || 0), 0)
);

const initialRegenCustom = existingUnpaidUnitSchedules.map(s => ({
    label: s.label || 'DP',
    amount: Math.round(Number(s.amount) || 0),
    due_date: s.due_date ? String(s.due_date).slice(0, 10) : '',
}));

const regenForm = useForm({
    payment_scheme: props.booking.payment_scheme || 'cash_installment',
    schedule_mode: 'standard',
    custom_schedules: initialRegenCustom,
    installment_months: props.booking.installment_months || 0,
    dp_amount: props.booking.dp_amount || 0,
    dp_installment_months: props.booking.dp_installment_months || 0,
});

const regenBookingDate = computed(() => {
    const raw = props.booking.booking_date;
    return raw ? String(raw).slice(0, 10) : new Date().toISOString().slice(0, 10);
});

const regenSimulationResult = computed(() => {
    const basePrice = Math.max(0, Math.round(Number(props.booking.base_price) || 0));
    const finalPrice = Math.max(0, Math.round(Number(props.booking.final_price) || 0));
    // Deduct already-paid non-UTJ unit rows so simulation only allocates remaining balance
    const effectiveBookingFee = Math.round(Number(props.booking.booking_fee) || 0) + paidUnitAmount.value;
    return buildScheduleSimulation({
        payment_scheme: regenForm.payment_scheme,
        schedule_mode: regenForm.schedule_mode,
        custom_schedules: regenForm.custom_schedules,
        booking_fee: effectiveBookingFee,
        base_price: basePrice,
        final_price: finalPrice,
        ppn_amount: props.booking.ppn_amount || 0,
        bphtb_amount: props.booking.bphtb_amount || 0,
        ajb_bbn_amount: props.booking.ajb_bbn_amount || 0,
        other_legal_fees: props.booking.other_legal_fees || 0,
        dp_amount: regenForm.dp_amount,
        dp_installment_months: regenForm.dp_installment_months,
        installment_months: regenForm.installment_months,
        booking_date: regenBookingDate.value,
    });
});

const simulatedRegenRows = computed(() => {
    const rows = [...regenSimulationResult.value.rows];
    if (paidUnitAmount.value > 0 && rows.length > 0) {
        // Restore true UTJ + paid items visual clarity
        rows[0] = {
            ...rows[0],
            amount: Math.round(Number(props.booking.booking_fee) || 0),
        };
        const paidRows = paidUnitSchedules.value.map(s => ({
            label: `${s.label} (Sudah Lunas)`,
            tag: 'Lunas',
            amount: Math.round(Number(s.amount) || 0),
        }));
        rows.splice(1, 0, ...paidRows);
    }
    return rows;
});

const switchRegenMode = (mode) => {
    regenForm.schedule_mode = mode;
    if (mode === 'custom_terms' && (!Array.isArray(regenForm.custom_schedules) || regenForm.custom_schedules.length === 0)) {
        regenForm.custom_schedules = createDefaultCustomTerms(regenSimulationResult.value.unitAfterUtj, regenBookingDate.value);
    }
};

const addRegenCustomTerm = () => {
    const n = (regenForm.custom_schedules?.length || 0) + 1;
    regenForm.custom_schedules.push({
        label: `DP ${n}`,
        amount: 0,
        due_date: addMonthsIso(regenBookingDate.value, n),
    });
};

const removeRegenCustomTerm = (idx) => {
    if (regenForm.custom_schedules.length <= 1) return;
    regenForm.custom_schedules.splice(idx, 1);
};

const autoFillRegenTermRemainder = (idx) => {
    const target = regenSimulationResult.value.unitAfterUtj;
    const otherSum = regenForm.custom_schedules.reduce((acc, row, rIdx) => {
        return rIdx === idx ? acc : acc + Math.max(0, Math.round(Number(row.amount) || 0));
    }, 0);
    regenForm.custom_schedules[idx].amount = Math.max(0, target - otherSum);
};

const applyRegenPreset3Terms = () => {
    regenForm.custom_schedules = createDefaultCustomTerms(regenSimulationResult.value.unitAfterUtj, regenBookingDate.value);
};

const regenMismatchError = ref('');

function submitRegenSchedule() {
    regenMismatchError.value = '';
    if (regenForm.payment_scheme === 'cash_installment' && regenForm.schedule_mode === 'custom_terms') {
        if (Math.abs(regenSimulationResult.value.customDiff) > 100) {
            regenMismatchError.value = `Total termin custom belum pas dengan sisa harga unit (selisih Rp ${Math.abs(regenSimulationResult.value.customDiff).toLocaleString('id-ID')}). Gunakan "Isi Sisa".`;
            return;
        }
    }
    regenForm.transform((data) => ({
        ...data,
        custom_schedules: (data.payment_scheme === 'cash_installment' && data.schedule_mode === 'custom_terms')
            ? data.custom_schedules
            : [],
    })).post(`/bookings/${props.booking.id}/regenerate-schedule`, {
        preserveScroll: true,
        onSuccess: () => { 
            showScheduleModal.value = false; 
            sprPreviewTimestamp.value = Date.now();
        }
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
        onSuccess: () => { 
            showAddRowModal.value = false; 
            addRowForm.reset(); 
            sprPreviewTimestamp.value = Date.now();
        }
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
        onSuccess: () => { 
            editingScheduleRow.value = null; 
            sprPreviewTimestamp.value = Date.now();
        }
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

const resetCustomerSignature = () => {
    const leadName = props.booking.lead?.name || 'Konsumen';
    if (confirm(`Reset tanda tangan digital konsumen untuk booking ini? Tanda tangan lama akan dihapus dan nama TTD akan disesuaikan dengan data profil terbaru (${leadName}).`)) {
        router.post(`/bookings/${props.booking.id}/reset-signature`, { role: 'customer' }, {
            preserveScroll: true,
            onSuccess: () => {
                alert('Tanda tangan digital konsumen berhasil direset.');
            }
        });
    }
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
                        <span class="text-xs text-slate-500">
                            👤 Agen: <strong class="text-slate-800">{{ booking.bookedBy?.name || booking.booked_by?.name || booking.lead?.assigned_to_user?.name || 'Staff' }}</strong>
                            <template v-if="booking.inhousePic">
                                · 🏠 PIC In-House: <strong class="text-blue-700">{{ booking.inhousePic.name }}</strong>
                            </template>
                            · 📅 {{ new Date(booking.booking_date).toLocaleDateString('id-ID') }}
                        </span>
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
                <button v-if="booking.transactions?.length > 0" @click="showReceiptsModal = true" class="px-4 py-2.5 bg-emerald-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-600/20 hover:-translate-y-0.5 transition-all flex items-center gap-1.5">
                    <span>📄</span>
                    <span>Kwitansi ({{ booking.transactions.length }})</span>
                </button>
                <button @click="openSprTemplateModal('bank')" class="px-4 py-2.5 bg-amber-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-amber-500/20 hover:-translate-y-0.5 transition-all flex items-center gap-1.5">
                    ⚙️ Edit Setting SPR / Kwitansi
                </button>
                <button @click="openSpkPreview" class="px-4 py-2.5 bg-blue-600 text-white text-xs font-bold rounded-xl shadow-lg hover:-translate-y-0.5 transition-all flex items-center gap-1.5">
                    👁️ Pratinjau SPR
                </button>
                <button v-if="booking.customer_signed_at || booking.sig4_image" @click="resetCustomerSignature" class="px-3.5 py-2.5 bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold rounded-xl shadow-sm hover:bg-rose-100 transition-all flex items-center gap-1.5 cursor-pointer" title="Reset tanda tangan konsumen agar bisa ditandatangani ulang">
                    <span>🔄</span> <span>Reset TTD</span>
                </button>
                <a :href="`/bookings/${booking.id}/spk`" target="_blank" class="px-4 py-2.5 bg-slate-900 text-white text-xs font-bold rounded-xl shadow-lg hover:-translate-y-0.5 transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Download SPR (PDF)
                </a>
                <button @click="openReasonModal('cancel')" class="px-3.5 py-2.5 text-slate-400 text-xs font-bold hover:text-rose-600 transition-all">
                    Batalkan
                </button>
                <button @click="deleteBooking" class="px-3.5 py-2.5 text-rose-500 text-xs font-bold hover:bg-rose-50 rounded-xl transition-all border border-rose-200">
                    🗑️ Hapus
                </button>
            </div>
        </div>

        <!-- END-TO-END DEAL JOURNEY & CONNECTED RECORDS -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 mb-6 shadow-xs space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded text-[10px] font-black uppercase tracking-wider">Alur End-to-End</span>
                    <h2 class="text-xs font-black text-slate-800 uppercase tracking-wider">Jejak Relasi Transaksi & Portal Konsumen</h2>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold text-slate-400 hidden sm:inline">Link Tracking Pembeli:</span>
                    <button @click="copyTrackingLink" class="px-3 py-1.5 bg-slate-100 text-slate-700 text-[10px] font-black uppercase tracking-wider rounded-xl hover:bg-slate-200 transition-all cursor-pointer">
                        🔗 Salin Link Tracking
                    </button>
                    <a :href="whatsappReminderLink" target="_blank" class="px-3 py-1.5 bg-emerald-500 text-white text-[10px] font-black uppercase tracking-wider rounded-xl hover:bg-emerald-600 transition-all shadow-sm flex items-center gap-1.5">
                        <span>💬</span> <span>Kirim Tracking WA</span>
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <!-- 1. Lead -->
                <div class="p-3 rounded-xl border border-slate-200 bg-slate-50/70 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black text-slate-400 uppercase">1. Profil Lead</span>
                        <span class="text-xs">👤</span>
                    </div>
                    <div class="my-1.5">
                        <Link v-if="booking.lead_id" :href="`/leads/${booking.lead_id}`" class="text-xs font-black text-blue-700 hover:underline block truncate">
                            {{ booking.lead?.name || 'Konsumen' }} →
                        </Link>
                        <p v-else class="text-xs font-black text-slate-800 truncate">{{ booking.lead?.name || 'Konsumen' }}</p>
                        <p class="text-[10px] text-slate-500 font-semibold truncate">📱 {{ booking.lead?.phone || '-' }}</p>
                    </div>
                    <span class="text-[9px] font-bold text-slate-500">Status Lead: <strong class="uppercase text-slate-700">{{ booking.lead?.status || 'booking' }}</strong></span>
                </div>

                <!-- 2. Reservasi (UTJ) -->
                <div class="p-3 rounded-xl border flex flex-col justify-between" :class="booking.reservation ? 'border-emerald-200 bg-emerald-50/50' : 'border-slate-200 bg-slate-50/40'">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black uppercase" :class="booking.reservation ? 'text-emerald-700' : 'text-slate-400'">2. Reservasi (UTJ)</span>
                        <span class="text-xs">🔖</span>
                    </div>
                    <template v-if="booking.reservation">
                        <div class="my-1.5">
                            <Link :href="`/reservations/${booking.reservation.id}`" class="text-xs font-mono font-black text-emerald-800 hover:underline block truncate">
                                {{ booking.reservation.reservation_number }} →
                            </Link>
                            <p class="text-[11px] font-black text-emerald-700 font-mono mt-0.5">{{ formatCurrency(booking.reservation.amount) }}</p>
                        </div>
                        <div class="flex items-center justify-between pt-1 border-t border-emerald-200/60">
                            <span class="text-[9px] font-bold text-emerald-700 uppercase">✓ Terkredit ke UTJ</span>
                            <a :href="`/reservations/${booking.reservation.id}/receipt`" target="_blank" class="text-[9px] font-black text-slate-700 hover:text-blue-600 underline">📄 Kwitansi</a>
                        </div>
                    </template>
                    <template v-else>
                        <p class="text-[11px] text-slate-400 italic my-2">Tanpa Reservasi Awal (Langsung Booking)</p>
                        <span class="text-[9px] text-slate-400 font-semibold">UTJ: {{ formatCurrency(booking.booking_fee) }}</span>
                    </template>
                </div>

                <!-- 3. Negosiasi -->
                <div class="p-3 rounded-xl border flex flex-col justify-between" :class="booking.negotiation ? 'border-purple-200 bg-purple-50/50' : 'border-slate-200 bg-slate-50/40'">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black uppercase" :class="booking.negotiation ? 'text-purple-700' : 'text-slate-400'">3. Form Negosiasi</span>
                        <span class="text-xs">🤝</span>
                    </div>
                    <template v-if="booking.negotiation">
                        <div class="my-1.5">
                            <Link :href="`/negotiations/${booking.negotiation.id}`" class="text-xs font-mono font-black text-purple-800 hover:underline block truncate">
                                {{ booking.negotiation.negotiation_number || ('NEGO-' + booking.negotiation.id) }} →
                            </Link>
                            <p class="text-[11px] font-black text-purple-700 font-mono mt-0.5">
                                {{ formatCurrency(booking.negotiation.counter_price || booking.negotiation.offered_price || booking.final_price) }}
                            </p>
                        </div>
                        <div class="flex items-center justify-between pt-1 border-t border-purple-200/60">
                            <span class="text-[9px] font-bold text-purple-700 uppercase">✓ Deal Disetujui</span>
                            <a :href="`/negotiations/${booking.negotiation.id}/pdf`" target="_blank" class="text-[9px] font-black text-slate-700 hover:text-blue-600 underline">📄 PDF Nego</a>
                        </div>
                    </template>
                    <template v-else>
                        <p class="text-[11px] text-slate-400 italic my-2">Tanpa Form Negosiasi (Harga Pricelist)</p>
                        <span class="text-[9px] text-slate-400 font-semibold">Deal: {{ formatCurrency(booking.final_price) }}</span>
                    </template>
                </div>

                <!-- 4. Booking & SPR (Active) -->
                <div class="p-3 rounded-xl border-2 border-blue-500 bg-blue-50/50 flex flex-col justify-between shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black text-blue-700 uppercase">4. Booking & SPR</span>
                        <span class="px-1.5 py-0.5 bg-blue-600 text-white rounded text-[8px] font-black uppercase">Aktif</span>
                    </div>
                    <div class="my-1.5">
                        <p class="text-xs font-mono font-black text-slate-900 truncate">{{ booking.spk_number }}</p>
                        <p class="text-[10px] text-blue-700 font-bold mt-0.5">Terbayar {{ paymentProgressPct }}% ({{ formatCurrency(totalPaidAmount) }})</p>
                    </div>
                    <div class="flex items-center justify-between pt-1 border-t border-blue-200/60">
                        <button type="button" @click="openSpkPreview" class="text-[9px] font-black text-blue-700 hover:underline cursor-pointer">👁️ Preview</button>
                        <a :href="`/bookings/${booking.id}/spk`" target="_blank" class="text-[9px] font-black text-slate-800 hover:text-blue-600 underline">📥 Unduh SPR</a>
                    </div>
                </div>

                <!-- 5. Komisi Penjualan -->
                <div class="p-3 rounded-xl border border-slate-200 bg-slate-50/70 flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black text-slate-400 uppercase">5. Komisi Agen</span>
                        <span class="text-xs">💸</span>
                    </div>
                    <div class="my-1.5">
                        <p class="text-xs font-black text-slate-900 font-mono">
                            {{ booking.commission_amount > 0 ? formatCurrency(booking.commission_amount) : 'Mengikuti Skema' }}
                        </p>
                        <p class="text-[10px] text-slate-500 font-semibold truncate">👤 {{ booking.bookedBy?.name || '-' }}</p>
                    </div>
                    <Link href="/commissions" class="text-[9px] font-black text-blue-600 hover:underline">Buka Dashboard Komisi →</Link>
                </div>
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
                            <div class="flex items-center justify-between">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Data Unit</p>
                                <button v-if="booking.status !== 'cancelled' && booking.status !== 'rejected'" @click="openChangeBookingUnitModal" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg text-[11px] font-bold flex items-center gap-1 cursor-pointer transition-colors">
                                    <span>🔄</span> Ganti Unit
                                </button>
                            </div>
                            <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                                <h3 class="text-sm font-black text-slate-900">{{ booking.unit?.project?.name }}</h3>
                                <p class="text-xs text-slate-600 font-bold mt-1">{{ booking.unit?.label || (`Blok ${booking.unit?.block} No. ${booking.unit?.number}`) }} • Tipe {{ booking.unit?.unit_type?.name }}</p>
                                <div class="mt-4 flex items-center justify-between pt-4 border-t border-slate-200">
                                    <span class="text-xs text-slate-400">Harga Kesepakatan</span>
                                    <span class="text-sm font-black text-slate-900 font-mono">{{ formatCurrency(booking.final_price) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Customer Info -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Data Konsumen Utama</p>
                                <Link v-if="booking.lead_id" :href="`/leads/${booking.lead_id}`" class="text-[11px] font-bold text-blue-600 hover:underline">
                                    Profil Lead →
                                </Link>
                            </div>
                            <div class="space-y-2 text-xs">
                                <div>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase">Nama Pemesan</p>
                                    <Link v-if="booking.lead_id" :href="`/leads/${booking.lead_id}`" class="text-sm font-bold text-slate-900 hover:text-blue-600 transition-colors">
                                        {{ booking.lead?.name }}
                                    </Link>
                                    <p v-else class="text-sm font-bold text-slate-900">{{ booking.lead?.name }}</p>
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

                                <div class="mt-3 p-3 bg-slate-50 rounded-xl border border-slate-200/80 space-y-1.5">
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-wider">Penugasan Penjualan & PIC</p>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-slate-500">Agen Closing:</span>
                                        <span class="font-bold text-slate-800">{{ booking.bookedBy?.name || '-' }}</span>
                                    </div>
                                    <div v-if="booking.inhousePic" class="flex items-center justify-between text-xs pt-1 border-t border-slate-200/60">
                                        <span class="text-slate-500">PIC In-House Developer:</span>
                                        <span class="font-bold text-blue-700">{{ booking.inhousePic.name }}</span>
                                    </div>
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
                                Skema: <span class="font-bold text-amber-600 uppercase">{{ schemeLabels[booking.payment_scheme] || booking.payment_scheme }}</span> 
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
                <div class="bg-slate-900 rounded-2xl p-6 text-white shadow-xl relative overflow-hidden">
                    <div class="flex items-center justify-between mb-5">
                        <div class="flex items-center gap-2">
                            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-blue-400">Finansial</h3>
                            <span v-if="Number(booking.ppn_amount) === 0 && (Number(booking.bphtb_amount) + Number(booking.ajb_bbn_amount)) === 0" class="text-[9px] bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 px-2 py-0.5 rounded-full font-black uppercase tracking-wider">
                                Free All-in
                            </span>
                        </div>
                        <button type="button" @click="openFinancialModal" class="px-2.5 py-1.5 bg-blue-600 hover:bg-blue-500 active:scale-95 text-white text-[10px] font-black rounded-xl transition-all shadow-md shadow-blue-500/20 flex items-center gap-1 cursor-pointer">
                            <span>✏️</span> <span>Atur Biaya & Pajak</span>
                        </button>
                    </div>
                    <div class="space-y-3.5">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-400">Harga Dasar</span>
                            <span class="font-black">{{ formatCurrency(booking.base_price) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-400 flex items-center gap-1.5">
                                Pajak PPN (11%)
                                <span v-if="Number(booking.ppn_amount) === 0" class="text-[9px] bg-emerald-500/20 text-emerald-400 px-1.5 py-0.5 rounded font-black">FREE</span>
                            </span>
                            <span class="font-black" :class="Number(booking.ppn_amount) === 0 ? 'text-emerald-400' : ''">
                                {{ Number(booking.ppn_amount) === 0 ? 'Rp 0 (Free)' : formatCurrency(booking.ppn_amount) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-400 flex items-center gap-1.5">
                                BPHTB + AJB/BBN
                                <span v-if="(Number(booking.bphtb_amount) + Number(booking.ajb_bbn_amount)) === 0" class="text-[9px] bg-emerald-500/20 text-emerald-400 px-1.5 py-0.5 rounded font-black">FREE</span>
                            </span>
                            <span class="font-black" :class="(Number(booking.bphtb_amount) + Number(booking.ajb_bbn_amount)) === 0 ? 'text-emerald-400' : ''">
                                {{ (Number(booking.bphtb_amount) + Number(booking.ajb_bbn_amount)) === 0 ? 'Rp 0 (Free)' : formatCurrency(Number(booking.bphtb_amount) + Number(booking.ajb_bbn_amount)) }}
                            </span>
                        </div>
                        <div v-if="Number(booking.other_legal_fees) > 0" class="flex justify-between items-center text-xs">
                            <span class="text-slate-400">Biaya Lainnya</span>
                            <span class="font-black">{{ formatCurrency(booking.other_legal_fees) }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-white/10">
                            <span class="text-xs text-white font-black">Total All-in (SPR)</span>
                            <span class="text-lg font-black text-blue-400 font-mono">{{ formatCurrency(booking.final_price) }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-3 border-t border-white/10">
                            <span class="text-xs text-slate-400">Booking Fee (UTJ)</span>
                            <span class="text-xs font-black text-slate-200 font-mono">{{ formatCurrency(booking.booking_fee) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-400">Sudah Dibayar (Lunas)</span>
                            <span class="font-black text-emerald-400 font-mono">{{ formatCurrency(totalPaidAmount) }}</span>
                        </div>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-slate-400">Sisa Piutang / Tagihan</span>
                            <span class="font-black text-amber-400 font-mono">{{ formatCurrency(remainingBalance) }}</span>
                        </div>
                        <div class="pt-2 space-y-1.5">
                            <div class="flex justify-between text-[10px] font-bold">
                                <span class="text-slate-400">Progres Pelunasan</span>
                                <span class="text-emerald-400 font-black">{{ paymentProgressPct }}%</span>
                            </div>
                            <div class="w-full h-2 bg-white/10 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-blue-500 to-emerald-400 rounded-full transition-all" :style="{ width: `${paymentProgressPct}%` }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- COMMISSION BOX -->
                <div v-if="booking.commission_amount > 0" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Komisi Sales</h3>
                        <Link href="/commissions" class="text-[10px] font-bold text-blue-600 hover:underline">Lihat Semua Komisi →</Link>
                    </div>
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
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Tanggal Pembayaran / Kwitansi</label>
                    <input v-model="paymentForm.payment_date" type="date" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-black focus:ring-2 focus:ring-blue-600/20" />
                    <p class="text-[10px] text-slate-400 mt-1">Otomatis mengikuti tanggal jatuh tempo jadwal tagihan di sistem.</p>
                </div>
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
                    <iframe :src="`/bookings/${booking.id}/spk/view?html=1&_t=${sprPreviewTimestamp}`" class="w-full h-full min-h-[500px] border-0 rounded-2xl"></iframe>
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
            <div class="relative bg-white rounded-3xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl p-8 animate-in zoom-in duration-150">
                <div class="flex justify-between items-center mb-6 pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <span>⚙️</span> <span>Atur Skema & Termin Pembayaran</span>
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

                    <div v-if="regenForm.payment_scheme === 'cash_installment'" class="flex items-center justify-between gap-2 bg-slate-50 p-2.5 rounded-2xl border border-slate-200">
                        <span class="text-[10px] font-black text-slate-600 uppercase pl-2">Mode Cash Bertahap:</span>
                        <div class="inline-flex bg-white p-1 rounded-xl border border-slate-200 shadow-sm">
                            <button
                                type="button"
                                @click="switchRegenMode('standard')"
                                :class="regenForm.schedule_mode === 'standard' ? 'bg-slate-900 text-white' : 'text-slate-600'"
                                class="px-3 py-1 rounded-lg text-[10px] font-black uppercase transition-all"
                            >
                                DP + Cicilan Flat
                            </button>
                            <button
                                type="button"
                                @click="switchRegenMode('custom_terms')"
                                :class="regenForm.schedule_mode === 'custom_terms' ? 'bg-amber-600 text-white' : 'text-slate-600'"
                                class="px-3 py-1 rounded-lg text-[10px] font-black uppercase transition-all"
                            >
                                Pecah Termin Custom
                            </button>
                        </div>
                    </div>

                    <template v-if="!(regenForm.payment_scheme === 'cash_installment' && regenForm.schedule_mode === 'custom_terms')">
                        <div v-if="regenForm.payment_scheme === 'cash_installment'">
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tenor Sisa Cicilan (Bulan / Tahun)</label>
                            <div class="grid grid-cols-2 gap-3">
                                <select v-model.number="regenForm.installment_months" class="px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-amber-500/20">
                                    <option :value="0">0 Bulan (Tanpa Cicilan / 1x Pelunasan Sisa)</option>
                                    <option :value="3">3 Bulan (0.25 Tahun)</option>
                                    <option :value="6">6 Bulan (0.5 Tahun)</option>
                                    <option :value="12">12 Bulan (1 Tahun)</option>
                                    <option :value="24">24 Bulan (2 Tahun)</option>
                                    <option :value="36">36 Bulan (3 Tahun)</option>
                                    <option :value="48">48 Bulan (4 Tahun)</option>
                                    <option :value="60">60 Bulan (5 Tahun)</option>
                                    <option :value="72">72 Bulan (6 Tahun)</option>
                                </select>
                                <input v-model.number="regenForm.installment_months" type="number" min="0" max="360" placeholder="Custom (Bulan)" class="px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-amber-500/20" />
                            </div>
                            <p class="text-[10px] text-amber-600 mt-1 font-bold">
                                {{ regenForm.installment_months > 0 ? `Total Durasi Cicilan: ${regenForm.installment_months} Bulan (${(regenForm.installment_months / 12).toFixed(1)} Tahun)` : 'Jika masih ada sisa harga setelah DP, otomatis dibuat 1x Pelunasan Sisa Cash Bertahap' }}
                            </p>
                        </div>

                        <div v-if="regenForm.payment_scheme !== 'cash'" class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Total Uang Muka / DP Keseluruhan (Rp)</label>
                                <input v-model.number="regenForm.dp_amount" type="number" placeholder="0" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" />
                                <p class="text-[9px] text-slate-400 mt-1">Dibagi rata sebanyak Berapa Kali DP. Untuk DP berbeda nominal, pilih Pecah Termin Custom.</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Berapa Kali DP (Bulan)</label>
                                <input v-model.number="regenForm.dp_installment_months" type="number" min="0" max="60" placeholder="1 (atau 2, 3)" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" />
                                <p class="text-[9px] text-slate-400 mt-1">Isi 3 untuk dibagi jadi DP 1, DP 2, DP 3</p>
                            </div>
                        </div>
                    </template>

                    <!-- Custom Terms Mode Inside Regenerate Modal -->
                    <div v-else class="space-y-3 p-3.5 bg-amber-50/40 border border-amber-200 rounded-2xl">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                            <div>
                                <span class="text-slate-500 font-semibold">Sisa Harga Unit Target:</span>
                                <span class="ml-1.5 font-mono font-black text-slate-900">Rp {{ Number(regenSimulationResult.unitAfterUtj).toLocaleString('id-ID') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="applyRegenPreset3Terms" class="px-2.5 py-1 bg-white hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-lg text-[10px] font-black">
                                    Preset 3 Termin
                                </button>
                                <button type="button" @click="addRegenCustomTerm" class="px-2.5 py-1 bg-slate-900 text-white rounded-lg text-[10px] font-black">
                                    + Tambah Termin
                                </button>
                            </div>
                        </div>

                        <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                            <div v-for="(term, tIdx) in regenForm.custom_schedules" :key="tIdx" class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center bg-white p-2 rounded-xl border border-slate-200">
                                <div class="sm:col-span-4">
                                    <input v-model="term.label" type="text" placeholder="DP 1" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold" />
                                </div>
                                <div class="sm:col-span-4 flex gap-1">
                                    <input v-model.number="term.amount" type="number" min="0" class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono font-bold" />
                                    <button type="button" @click="autoFillRegenTermRemainder(tIdx)" class="px-2 py-1 bg-amber-100 hover:bg-amber-200 text-amber-900 rounded-lg text-[9px] font-black shrink-0">
                                        Isi Sisa
                                    </button>
                                </div>
                                <div class="sm:col-span-3">
                                    <input v-model="term.due_date" type="date" class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-[11px]" />
                                </div>
                                <div class="sm:col-span-1 flex justify-end">
                                    <button type="button" @click="removeRegenCustomTerm(tIdx)" :disabled="regenForm.custom_schedules.length <= 1" class="p-1 text-slate-400 hover:text-rose-600 disabled:opacity-30 text-xs font-black">✕</button>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 rounded-xl border text-xs"
                            :class="Math.abs(regenSimulationResult.customDiff) <= 100 ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900'"
                        >
                            <span>Total: <strong class="font-mono">Rp {{ Number(regenSimulationResult.customTermsTotal).toLocaleString('id-ID') }}</strong></span>
                            <span class="font-black text-[11px]">
                                {{ Math.abs(regenSimulationResult.customDiff) <= 100 ? '✅ 100% Pas' : (regenSimulationResult.customDiff > 0 ? `Kurang Rp ${Number(regenSimulationResult.customDiff).toLocaleString('id-ID')}` : `Kelebihan Rp ${Number(Math.abs(regenSimulationResult.customDiff)).toLocaleString('id-ID')}`) }}
                            </span>
                        </div>
                        <p v-if="regenMismatchError || regenForm.errors.custom_schedules" class="text-xs font-bold text-rose-600">
                            {{ regenMismatchError || regenForm.errors.custom_schedules }}
                        </p>
                    </div>

                    <!-- Live Simulation Preview -->
                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-2">
                        <div class="flex items-center justify-between text-[10px] font-black text-slate-500 uppercase tracking-wider">
                            <span>Simulasi Jadwal Yang Akan Dibuat:</span>
                            <span class="text-blue-600">{{ simulatedRegenRows.length }} Baris</span>
                        </div>
                        <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                            <div v-for="(sim, sIdx) in simulatedRegenRows" :key="sIdx" class="flex items-center justify-between p-2 bg-white rounded-xl border border-slate-100 text-xs">
                                <div>
                                    <span class="font-bold text-slate-800">{{ sim.label }}</span>
                                    <span class="text-[10px] text-slate-400 ml-1.5 font-medium">({{ sim.tag }})</span>
                                </div>
                                <span class="font-mono font-black text-slate-900">Rp {{ Number(sim.amount).toLocaleString('id-ID') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-amber-50 rounded-xl border border-amber-100 text-[10px] text-amber-800 leading-relaxed font-medium">
                        ⚠️ **Perhatian**: Meng-generate ulang jadwal akan memperbarui seluruh baris tagihan yang belum lunas disesuaikan dengan tenor & skema pembayaran di atas.
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
                    <button type="button" @click="activeSprTab = 'financial'" :class="activeSprTab === 'financial' ? 'bg-white text-emerald-600 border-b-2 border-emerald-600 font-black shadow-sm' : 'text-slate-500 font-bold hover:text-slate-800'" class="px-4 py-2.5 text-xs rounded-t-xl transition-all shrink-0 flex items-center gap-1.5">
                        <span>💰</span> <span>Finansial & Pajak (Free PPN / BPHTB)</span>
                    </button>
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
                        <!-- TAB FINANSIAL & BEBAS BIAYA (PPN, BPHTB, DLL) -->
                        <div v-if="activeSprTab === 'financial'" class="space-y-5">
                            <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-200 space-y-2">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div>
                                        <h4 class="text-xs font-black uppercase text-emerald-950 flex items-center gap-1.5">
                                            <span>⚡</span> Preset Cepat Biaya & Pajak
                                        </h4>
                                        <p class="text-[11px] text-emerald-800 mt-0.5">Tentukan apakah transaksi ini bebas biaya (All-in Developer) atau menerapkan pajak standar.</p>
                                    </div>
                                    <div class="flex gap-2 shrink-0">
                                        <button type="button" @click="setSprFinancialAllFree" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black shadow-sm transition">
                                            ✨ Semua Free (All-in)
                                        </button>
                                        <button type="button" @click="setSprFinancialStandardTaxes" class="px-3 py-1.5 bg-white border border-emerald-300 text-emerald-800 hover:bg-emerald-100 rounded-xl text-xs font-bold transition">
                                            Hitung Pajak Standar
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Harga Dasar Unit Properti (Rp)</label>
                                    <input v-model.number="sprTemplateForm.base_price" @input="handleSprBasePriceChange" type="number" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-black text-slate-900" />
                                </div>

                                <!-- PPN (11%) -->
                                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <label class="text-[10px] font-black text-slate-700 uppercase">Pajak PPN (11%)</label>
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input v-model="sprTemplateForm.free_ppn" @change="toggleSprFreePpn" type="checkbox" class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500 cursor-pointer" />
                                            <span class="text-[11px] font-bold text-emerald-700">Free PPN</span>
                                        </label>
                                    </div>
                                    <input v-model.number="sprTemplateForm.ppn_amount" :disabled="sprTemplateForm.free_ppn" @input="recalculateSprFinancialTotal" type="number" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold disabled:bg-slate-100 disabled:text-slate-400" />
                                </div>

                                <!-- BPHTB (5%) -->
                                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <label class="text-[10px] font-black text-slate-700 uppercase">BPHTB (5%)</label>
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input v-model="sprTemplateForm.free_bphtb" @change="toggleSprFreeBphtb" type="checkbox" class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500 cursor-pointer" />
                                            <span class="text-[11px] font-bold text-emerald-700">Free BPHTB</span>
                                        </label>
                                    </div>
                                    <input v-model.number="sprTemplateForm.bphtb_amount" :disabled="sprTemplateForm.free_bphtb" @input="recalculateSprFinancialTotal" type="number" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold disabled:bg-slate-100 disabled:text-slate-400" />
                                </div>

                                <!-- AJB/BBN -->
                                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <label class="text-[10px] font-black text-slate-700 uppercase">AJB / BBN / Notaris</label>
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input v-model="sprTemplateForm.free_ajb" @change="toggleSprFreeAjb" type="checkbox" class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500 cursor-pointer" />
                                            <span class="text-[11px] font-bold text-emerald-700">Free AJB/BBN</span>
                                        </label>
                                    </div>
                                    <input v-model.number="sprTemplateForm.ajb_bbn_amount" :disabled="sprTemplateForm.free_ajb" @input="recalculateSprFinancialTotal" type="number" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold disabled:bg-slate-100 disabled:text-slate-400" />
                                </div>

                                <!-- Biaya Lainnya -->
                                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5">
                                    <label class="block text-[10px] font-black text-slate-700 uppercase">Biaya Lainnya (Opsional)</label>
                                    <input v-model.number="sprTemplateForm.other_legal_fees" @input="recalculateSprFinancialTotal" type="number" placeholder="0" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                </div>
                            </div>

                            <!-- Total Preview Banner -->
                            <div class="p-4 bg-slate-900 rounded-2xl text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-black text-blue-400 uppercase tracking-widest">Total Harga All-in Kesepakatan</p>
                                    <p class="text-xl font-black text-white mt-0.5">{{ formatCurrency(sprTemplateForm.final_price) }}</p>
                                    <p v-if="sprTemplateForm.free_ppn && sprTemplateForm.free_bphtb" class="text-[10px] text-emerald-400 font-bold mt-1">
                                        ✓ Skema Bebas Pajak & Surat Aktif (Harga All-in = Harga Dasar)
                                    </p>
                                </div>
                                <div class="shrink-0">
                                    <label class="flex items-center gap-2 cursor-pointer bg-white/10 px-3 py-2 rounded-xl border border-white/20">
                                        <input v-model="sprTemplateForm.sync_schedules" type="checkbox" class="w-4 h-4 text-blue-500 rounded border-white/30" />
                                        <span class="text-xs font-bold text-white">Sinkronkan ke Jadwal Pembayaran</span>
                                    </label>
                                </div>
                            </div>
                        </div>

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
                                    <option value="finance">💳 Finance & Kasir (Settings: {{ signatureConfig?.finance_name || signatureConfig?.finance_title || 'Finance' }})</option>
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
                                    <input v-model="sprTemplateForm.receipt_settings.receipt_number_prefix" type="text" placeholder="Contoh: KW/ALC atau KW/ALN" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-1 focus:ring-emerald-500" />
                                    <p class="text-[10px] text-slate-400 mt-1">Cukup isi kode proyek (contoh: <b>KW/ALC</b>). Sistem otomatis menambahkan kode jadwal tagihan (<b>UTJ</b>, <b>DP1</b>, <b>CICILAN-1</b>, dst.) & nomor unit/booking.</p>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Nomor Kwitansi Override Manual (Opsional)</label>
                                    <input v-model="sprTemplateForm.receipt_settings.receipt_number_custom" type="text" placeholder="Contoh: KW-SPECIAL-001" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-1 focus:ring-emerald-500" />
                                    <p class="text-[10px] text-slate-400 mt-1">Jika diisi, nomor ini meng-override seluruh nomor kwitansi otomatis pada unit ini (bagian kode DP/Cicilan tetap otomatis menyesuaikan).</p>
                                </div>

                                <!-- Rekening Tujuan Kwitansi -->
                                <div class="col-span-1 md:col-span-2">
                                    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Rekening Tujuan Khusus Kwitansi (Opsional Override)</label>
                                    <input v-model="sprTemplateForm.receipt_settings.receipt_bank_custom" type="text" placeholder="Kosongkan jika ingin otomatis mengikuti Bank Developer di tab Bank Developer (Contoh: BRI - 012001004640307)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-1 focus:ring-emerald-500" />
                                    <p class="text-[10px] text-slate-400 mt-1">Secara default, kwitansi otomatis membaca rekening dari tab <b>Bank Developer (Per-Baris LOV)</b>.</p>
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
                                    <div class="flex flex-col sm:flex-row sm:justify-between border-b border-slate-200 pb-1 gap-1">
                                        <span class="font-bold text-slate-600 shrink-0">Sample No. Kwitansi:</span>
                                        <span class="font-mono font-bold text-emerald-700 text-[11px] sm:text-right">
                                            <template v-if="sprTemplateForm.receipt_settings.receipt_number_custom">
                                                {{ sprTemplateForm.receipt_settings.receipt_number_custom }}
                                            </template>
                                            <template v-else>
                                                {{ (sprTemplateForm.receipt_settings.receipt_number_prefix || ('KW/' + (booking.unit?.project?.code || 'ALN'))).replace(/\/+$/, '') }}/CICILAN-1/{{ String(booking.id || 1).padStart(3, '0') }}
                                                <span class="text-slate-400 font-sans font-normal ml-1">(Otomatis Cicilan 1)</span>
                                            </template>
                                        </span>
                                    </div>
                                    <div class="flex justify-between border-b border-slate-200 pb-1">
                                        <span class="font-bold text-slate-600">TTD Oleh:</span>
                                        <span class="font-bold text-slate-800">{{ sprTemplateForm.receipt_settings.receipt_city || 'Jakarta' }}, {{ sprTemplateForm.receipt_settings.receipt_sig_name || 'Keuangan' }} ({{ sprTemplateForm.receipt_settings.receipt_sig_title || 'Kasir & Keuangan' }})</span>
                                    </div>
                                    <div class="flex justify-between border-b border-slate-200 pb-1">
                                        <span class="font-bold text-slate-600">Rekening Tujuan Kwitansi:</span>
                                        <span class="font-bold text-blue-700 text-xs text-right">
                                            💳 {{ sprTemplateForm.receipt_settings.receipt_bank_custom || (sprTemplateForm.spr_bank_info?.bank_name ? (sprTemplateForm.spr_bank_info.bank_name + ' - ' + (sprTemplateForm.spr_bank_info.account_number || '') + (sprTemplateForm.spr_bank_info.account_holder ? ' a.n ' + sprTemplateForm.spr_bank_info.account_holder : '')) : 'Mengikuti Bank Developer') }}
                                        </span>
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
                                <span>🗓️ {{ new Date(tx.payment_schedule?.paid_date || tx.payment_schedule?.due_date || tx.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}</span>
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

    <!-- CHANGE BOOKING UNIT MODAL -->
    <teleport to="body">
        <div v-if="showChangeBookingUnitModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-4 animate-in zoom-in duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>🔄</span> Pindah / Ganti Unit Booking
                        </h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Pindahkan pemesanan (SPK/SPR) konsumen ke nomor unit lain.</p>
                    </div>
                    <button @click="showChangeBookingUnitModal = false" class="text-slate-400 hover:text-slate-700 text-lg">✕</button>
                </div>

                <!-- Current Unit Box -->
                <div class="p-3.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Unit Saat Ini</p>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-sm font-black text-slate-800 dark:text-white">{{ booking.unit?.label || booking.unit?.code || `Blok ${booking.unit?.block} No. ${booking.unit?.number}` }}</span>
                        <span class="text-xs font-bold font-mono text-emerald-600">Rp {{ Number(booking.final_price || 0).toLocaleString('id-ID') }}</span>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-0.5">{{ booking.unit?.project?.name }} • Tipe {{ booking.unit?.unit_type?.name || 'Standard' }}</p>
                </div>

                <form @submit.prevent="submitChangeBookingUnit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Pilih Unit Baru Tujuan <span class="text-rose-500">*</span>
                        </label>
                        <select v-model="changeBookingUnitForm.unit_id" required class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/20">
                            <option value="" disabled>-- Pilih Unit Pengganti (Available) --</option>
                            <option v-for="u in units" :key="u.id" :value="u.id" :disabled="u.status !== 'available' && u.id !== booking.unit_id">
                                {{ u.block }} {{ u.number }} - {{ u.unit_type?.name || 'Standard' }} (Rp {{ Number(u.final_price || u.price || 0).toLocaleString('id-ID') }}) [{{ u.status }}]
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Alasan Perpindahan Unit (Opsional)</label>
                        <textarea v-model="changeBookingUnitForm.reason" rows="2" placeholder="Alasan customer meminta ubah unit..." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-medium focus:ring-2 focus:ring-indigo-500/20 resize-none"></textarea>
                    </div>

                    <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl text-[11px] text-amber-800 dark:text-amber-300 space-y-1">
                        <p class="font-bold flex items-center gap-1"><span>⚠️</span> Ketentuan Ganti Unit Booking:</p>
                        <p>1. Unit lama akan otomatis dilepaskan kembali menjadi status <strong>Available</strong>.</p>
                        <p>2. Unit baru akan dikunci menjadi status booking (<strong>{{ booking.status === 'approved' ? 'Booked' : 'Hold' }}</strong>).</p>
                        <p>3. Riwayat perpindahan akan dicatat pada Lead Activity.</p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showChangeBookingUnitModal = false" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                        <button type="submit" :disabled="changeBookingUnitForm.processing || changeBookingUnitForm.unit_id === booking.unit_id" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-black shadow-lg shadow-indigo-600/20 transition-all disabled:opacity-40">
                            🔄 Konfirmasi Pindah Unit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </teleport>
</template>
