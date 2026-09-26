<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, useForm, usePage, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    unit: Object,
    lead: Object,
    reservation: Object,
    negotiation: Object,
    availableNegotiations: {
        type: Array,
        default: () => []
    },
    reservedAmount: [Number, String],
    defaultFreePpn: {
        type: Boolean,
        default: true,
    },
    defaultFreeLegal: {
        type: Boolean,
        default: true,
    },
    availableUnits: Array,
    leads: Array,
    agents: Array,
});

const page = usePage();
const currentUser = computed(() => page.props.auth.user);

const initialBasePrice = props.negotiation?.offered_price || props.negotiation?.counter_price || props.unit?.final_price || '';
const initialBookingFee = props.reservedAmount || props.reservation?.amount || '';
const initialDpAmount = props.negotiation?.dp_amount || '';
const initialInstallmentMonths = props.negotiation?.installment_months || 12;
const initialSpecialBonusItems = (Array.isArray(props.negotiation?.special_bonus_items) && props.negotiation.special_bonus_items.length > 0)
    ? [...props.negotiation.special_bonus_items]
    : [];

const form = useForm({
    reservation_id: props.reservation?.id || '',
    negotiation_id: props.negotiation?.id || '',
    unit_id: props.unit?.id || props.reservation?.unit_id || props.negotiation?.unit_id || '',
    lead_id: props.lead?.id || props.reservation?.lead_id || props.negotiation?.lead_id || '',
    booked_by: props.lead?.assigned_to || props.negotiation?.creator_id || page.props.auth.user.id || '',
    booking_date: new Date().toISOString().substring(0, 10),
    booking_fee: initialBookingFee,
    base_price: initialBasePrice,
    free_ppn: props.defaultFreePpn !== undefined ? props.defaultFreePpn : true,
    free_legal: props.defaultFreeLegal !== undefined ? props.defaultFreeLegal : true,
    ppn_amount: 0,
    bphtb_amount: 0,
    ajb_bbn_amount: 0,
    other_legal_fees: 0,
    final_price: initialBasePrice,
    payment_scheme: props.negotiation?.payment_scheme || 'kpr',
    dp_amount: initialDpAmount,
    dp_installment_months: 3,
    installment_months: initialInstallmentMonths,
    buyer_nik: props.lead?.identity_number || '',
    buyer_npwp: props.lead?.npwp || '',
    buyer_address: props.lead?.address || '',
    buyer_job: props.lead?.job || '',
    has_secondary_buyer: false,
    secondary_name: '',
    secondary_nik: '',
    secondary_phone: '',
    secondary_relationship: 'Orang Tua',
    secondary_address: '',
    secondary_email: '',
    has_custom_signatures: false,
    sig1_title: '',
    sig1_name: '',
    sig2_title: '',
    sig2_name: '',
    sig3_title: '',
    sig3_name: '',
    sig4_title: '',
    sig4_name: '',
    special_bonus_items: initialSpecialBonusItems,
    notes: props.negotiation?.notes || '',
});

const calculateTaxes = () => {
    const base = Number(form.base_price) || 0;
    
    // PPN 11%
    if (form.free_ppn) {
        form.ppn_amount = 0;
    } else {
        form.ppn_amount = Math.round(base * 0.11);
    }
    
    // BPHTB & AJB
    if (form.free_legal) {
        form.bphtb_amount = 0;
        form.ajb_bbn_amount = 0;
    } else {
        const npoptkp = 60000000;
        form.bphtb_amount = Math.max(0, Math.round((base - npoptkp) * 0.05));
        form.ajb_bbn_amount = Math.round(base * 0.01);
    }
    
    updateTotal();
};

const handleLeadChange = () => {
    const selectedLead = props.leads.find(l => l.id === form.lead_id);
    if (selectedLead) {
        if (selectedLead.assigned_to) {
            form.booked_by = selectedLead.assigned_to;
        }
        if (selectedLead.identity_number) form.buyer_nik = selectedLead.identity_number;
        if (selectedLead.npwp) form.buyer_npwp = selectedLead.npwp;
        if (selectedLead.address) form.buyer_address = selectedLead.address;
        if (selectedLead.job) form.buyer_job = selectedLead.job;
    }
};

const updateTotal = () => {
    form.final_price = Number(form.base_price) + 
                       Number(form.ppn_amount) + 
                       Number(form.bphtb_amount) + 
                       Number(form.ajb_bbn_amount) + 
                       Number(form.other_legal_fees);
};

const selectedUnit = computed(() => {
    return props.availableUnits.find(u => u.id === form.unit_id) || props.unit;
});

const handleUnitChange = () => {
    if (selectedUnit.value) {
        // If current base_price is empty or matches previous unit price, sync it
        if (!form.base_price || form.base_price == props.unit?.final_price) {
            form.base_price = selectedUnit.value.final_price;
        }
        calculateTaxes();
    } else {
        form.base_price = '';
        form.ppn_amount = 0;
        form.bphtb_amount = 0;
        form.ajb_bbn_amount = 0;
        form.final_price = 0;
    }
};

const setAllFree = () => {
    form.free_ppn = true;
    form.free_legal = true;
    form.ppn_amount = 0;
    form.bphtb_amount = 0;
    form.ajb_bbn_amount = 0;
    form.other_legal_fees = 0;
    updateTotal();
};

const setStandardTaxes = () => {
    form.free_ppn = false;
    form.free_legal = false;
    calculateTaxes();
};

// Apply a selected negotiation document deal
const applyNegotiation = (nego) => {
    if (!nego) return;
    form.negotiation_id = nego.id;
    if (nego.unit_id) form.unit_id = nego.unit_id;
    const agreedPrice = nego.offered_price || nego.counter_price || '';
    if (agreedPrice) {
        form.base_price = agreedPrice;
    }
    if (nego.payment_scheme) form.payment_scheme = nego.payment_scheme;
    if (nego.dp_amount) form.dp_amount = nego.dp_amount;
    if (nego.installment_months) {
        form.installment_months = nego.installment_months;
        form.dp_installment_months = Math.min(nego.installment_months, 3);
    }
    if (Array.isArray(nego.special_bonus_items) && nego.special_bonus_items.length > 0) {
        form.special_bonus_items = [...nego.special_bonus_items];
    }
    if (nego.notes) form.notes = nego.notes;
    setAllFree();
};

const resetToBrochurePrice = () => {
    if (selectedUnit.value?.final_price) {
        form.base_price = selectedUnit.value.final_price;
        calculateTaxes();
    }
};

// Bonus items management
const newBonusInput = ref('');
const suggestedBonuses = ['Kitchen Set', 'Canopy Minimalis', 'AC 2 Unit', 'Smart Door Lock', 'Water Heater', 'Cashback 50 Juta'];

const addBonusItem = (itemText) => {
    const text = (typeof itemText === 'string' ? itemText : newBonusInput.value).trim();
    if (text && !form.special_bonus_items.includes(text)) {
        form.special_bonus_items.push(text);
    }
    newBonusInput.value = '';
};

const removeBonusItem = (index) => {
    form.special_bonus_items.splice(index, 1);
};

// Calculations for Deal & Savings
const brochurePrice = computed(() => Number(selectedUnit.value?.final_price || 0));
const currentDealPrice = computed(() => Number(form.base_price || 0));
const discountSavings = computed(() => {
    if (brochurePrice.value > 0 && currentDealPrice.value > 0 && currentDealPrice.value < brochurePrice.value) {
        return brochurePrice.value - currentDealPrice.value;
    }
    return 0;
});
const discountPercent = computed(() => {
    if (brochurePrice.value > 0 && discountSavings.value > 0) {
        return ((discountSavings.value / brochurePrice.value) * 100).toFixed(1);
    }
    return 0;
});

// Calculate initial taxes if unit is pre-selected and not free
if (props.unit || props.negotiation) {
    if (form.free_ppn && form.free_legal) {
        form.ppn_amount = 0;
        form.bphtb_amount = 0;
        form.ajb_bbn_amount = 0;
        updateTotal();
    } else {
        calculateTaxes();
    }
}

const submit = () => {
    form.post('/bookings');
};

const formatCurrency = (value) => {
    if (!value && value !== 0) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};
</script>

<template>
    <Head title="Buat Booking & Terbitkan SPR" />
    <CrmLayout>
        <template #breadcrumb>
            <Link href="/bookings" class="text-slate-400 hover:text-blue-600 transition-colors">Booking</Link>
            <span class="mx-2 text-slate-300">/</span>
            <span class="text-slate-900 font-bold">Baru & Terbitkan SPR</span>
        </template>

        <div class="max-w-4xl mx-auto pb-12">
            <div class="mb-6">
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-blue-100 text-blue-800">Tahap Booking & SPR</span>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800">Terkoneksi ke Dokumen SPR</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Formulir Booking & Kunci Harga Kesepakatan</h1>
                <p class="text-sm text-slate-500 mt-1">Konfirmasi harga kesepakatan akhir, skema pembayaran, dan data konsumen untuk diterbitkan menjadi Surat Pesanan Rumah (SPR) resmi.</p>
            </div>

            <!-- RESERVATION CONVERSION BADGE -->
            <div v-if="props.reservation" class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs text-emerald-900 flex items-start gap-3 shadow-sm">
                <span class="text-2xl">🔖</span>
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <h4 class="font-black text-sm text-emerald-950">Dikonversi dari Reservasi #{{ props.reservation.reservation_number }}</h4>
                        <span class="px-2 py-0.5 bg-emerald-200/60 text-emerald-900 rounded font-black text-[10px]">UTJ TERSEDIA</span>
                    </div>
                    <p class="mt-0.5">Pemohon: <strong>{{ props.reservation.client_name }}</strong> · Kredit Biaya Reservasi: <strong class="font-mono text-emerald-800 text-sm">{{ formatCurrency(props.reservation.amount) }}</strong></p>
                    <p class="text-[11px] text-emerald-700 mt-1 font-semibold">
                        ✅ Nominal reservasi sebesar <strong>{{ formatCurrency(props.reservation.amount) }}</strong> otomatis terkunci sebagai Booking Fee (UTJ) dan langsung dicatat Lunas di jadwal SPR.
                    </p>
                </div>
            </div>

            <!-- NEGOTIATION CONVERSION BADGE -->
            <div v-if="props.negotiation" class="mb-6 p-4 bg-purple-50 border border-purple-200 rounded-2xl text-xs text-purple-900 flex items-start gap-3 shadow-sm">
                <span class="text-2xl">🤝</span>
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <h4 class="font-black text-sm text-purple-950">Harga Kesepakatan Negosiasi #{{ props.negotiation.negotiation_number || ('NEGO-' + props.negotiation.token) }}</h4>
                        <span class="px-2 py-0.5 bg-purple-200/60 text-purple-900 rounded font-black text-[10px]">DISETUJUI</span>
                    </div>
                    <p class="mt-0.5">
                        Calon Pembeli: <strong>{{ props.negotiation.client_name }}</strong> · 
                        Harga Deal: <strong class="font-mono text-purple-900 text-sm font-black">{{ formatCurrency(props.negotiation.offered_price || props.negotiation.counter_price) }}</strong>
                    </p>
                    <p class="text-[11px] text-purple-700 mt-1 font-semibold">
                        💡 Harga kesepakatan negosiasi ini otomatis diterapkan pada kolom Harga Dasar dan menetapkan paket <strong>Free PPN & Free Legalitas (All-in)</strong>.
                    </p>
                </div>
            </div>

            <!-- SELECT FROM OTHER AVAILABLE NEGOTIATIONS -->
            <div v-if="props.availableNegotiations && props.availableNegotiations.length > 0 && !props.negotiation" class="mb-6 p-4 bg-indigo-50/90 border border-indigo-200 rounded-2xl text-xs text-indigo-950 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-bold flex items-center gap-1.5 text-indigo-900">
                        <span>🤝</span> <span>Ditemukan {{ props.availableNegotiations.length }} Riwayat Kesepakatan Negosiasi</span>
                    </span>
                    <span class="text-[10px] text-indigo-600 font-semibold">Klik untuk terapkan harga nego:</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <button type="button" v-for="neg in props.availableNegotiations" :key="neg.id" 
                        @click="applyNegotiation(neg)"
                        :class="form.negotiation_id === neg.id ? 'bg-indigo-600 text-white border-indigo-600 ring-2 ring-indigo-300' : 'bg-white text-slate-800 hover:bg-indigo-100/60 border-slate-200'"
                        class="p-2.5 rounded-xl border text-left transition-all cursor-pointer flex flex-col justify-between shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="font-black text-xs">#{{ neg.negotiation_number || ('NEGO-' + neg.token) }}</span>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase" :class="neg.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">{{ neg.status }}</span>
                        </div>
                        <div class="mt-1 flex items-baseline justify-between">
                            <span class="text-[11px]">Harga Kesepakatan:</span>
                            <span class="font-black text-sm text-indigo-700" :class="form.negotiation_id === neg.id ? '!text-white' : ''">{{ formatCurrency(neg.offered_price || neg.counter_price) }}</span>
                        </div>
                    </button>
                </div>
            </div>

            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- MAIN FORM -->
                <div class="md:col-span-2 space-y-6">
                    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-5 flex items-center gap-2">
                            <span class="w-7 h-7 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 text-xs">📋</span>
                            Detail Pesanan & Konsumen
                        </h2>

                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Pilih Konsumen (Lead) <span class="text-rose-500">*</span></label>
                                    <select v-model="form.lead_id" @change="handleLeadChange" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                        <option value="">Pilih Lead...</option>
                                        <option v-for="l in leads" :key="l.id" :value="l.id">{{ l.name }} ({{ l.phone }})</option>
                                    </select>
                                    <p v-if="form.errors.lead_id" class="text-xs text-rose-500 mt-1">{{ form.errors.lead_id }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Tanggal Booking (Backdate Support) <span class="text-rose-500">*</span></label>
                                    <input v-model="form.booking_date" type="date" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20" />
                                    <p v-if="form.errors.booking_date" class="text-xs text-rose-500 mt-1">{{ form.errors.booking_date }}</p>
                                </div>
                            </div>

                            <!-- PILIH UNIT -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Pilih Unit Properti <span class="text-rose-500">*</span></label>
                                <select v-model="form.unit_id" @change="handleUnitChange" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                    <option value="">Pilih Unit...</option>
                                    <option v-for="u in availableUnits" :key="u.id" :value="u.id">
                                        {{ u.project?.code }} - {{ u.block }}{{ u.number }} (Brosur: {{ formatCurrency(u.final_price) }})
                                    </option>
                                </select>
                                <p v-if="form.errors.unit_id" class="text-xs text-rose-500 mt-1">{{ form.errors.unit_id }}</p>
                            </div>

                            <!-- SECTION 1: KESEPAKATAN HARGA TERAKHIR -->
                            <div class="pt-6 border-t border-slate-100 space-y-4">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div>
                                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                            <span>🏷️</span> Kesepakatan Harga Terakhir (Terkoneksi ke SPR)
                                        </h3>
                                        <p class="text-[11px] text-slate-500">Harga deal yang disetujui bersama oleh Developer dan Konsumen.</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button v-if="props.negotiation" type="button" @click="applyNegotiation(props.negotiation)" class="px-2.5 py-1 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 rounded-lg text-[11px] font-bold transition-all flex items-center gap-1 cursor-pointer">
                                            <span>🤝</span> <span>Terapkan Harga Nego</span>
                                        </button>
                                        <button type="button" @click="resetToBrochurePrice" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded-lg text-[11px] font-bold transition-all cursor-pointer">
                                            <span>↺</span> <span>Gunakan Harga Brosur</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- COMPARISON CARD: BROSUR VS DEAL AKHIR -->
                                <div class="p-4 bg-gradient-to-br from-slate-50 to-blue-50/40 border border-blue-100 rounded-2xl">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <!-- Harga Brosur -->
                                        <div class="bg-white p-3.5 rounded-xl border border-slate-200">
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Harga Brosur / Master Stok</p>
                                            <p class="text-base font-black text-slate-700 mt-0.5">{{ formatCurrency(brochurePrice) }}</p>
                                            <p class="text-[10px] text-slate-400 mt-1">Harga resmi pricelist sebelum negosiasi</p>
                                        </div>

                                        <!-- Harga Kesepakatan Terakhir -->
                                        <div class="bg-white p-3.5 rounded-xl border-2 border-blue-500 shadow-sm relative">
                                            <div class="flex items-center justify-between">
                                                <p class="text-[10px] font-black text-blue-700 uppercase tracking-wider">Harga Kesepakatan Terakhir (Deal)</p>
                                                <span v-if="discountSavings > 0" class="px-1.5 py-0.5 bg-emerald-100 text-emerald-800 rounded text-[9px] font-black">
                                                    Hemat {{ discountPercent }}%
                                                </span>
                                            </div>
                                            <div class="mt-1.5">
                                                <input v-model="form.base_price" @input="calculateTaxes" type="number" 
                                                    placeholder="Contoh: 3750000000"
                                                    class="w-full px-3 py-1.5 border border-slate-300 rounded-lg text-base font-black text-blue-900 focus:ring-2 focus:ring-blue-500/20" />
                                            </div>
                                            <p class="text-[11px] font-bold text-blue-600 mt-1">{{ formatCurrency(form.base_price) }}</p>
                                        </div>
                                    </div>

                                    <!-- DISKON INFO -->
                                    <div v-if="discountSavings > 0" class="mt-3 px-3 py-2 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between text-xs text-emerald-900">
                                        <span class="flex items-center gap-1.5 font-bold">
                                            <span>🎉</span> <span>Selisih Diskon Kesepakatan:</span>
                                        </span>
                                        <span class="font-black text-sm text-emerald-700">-{{ formatCurrency(discountSavings) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 2: BIAYA PAJAK, LEGALITAS & PPN -->
                            <div class="pt-6 border-t border-slate-100 space-y-4">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div>
                                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                            <span>💰</span> Pajak & Legalitas (PPN & BPHTB)
                                        </h3>
                                        <p class="text-[11px] text-slate-500">Tentukan apakah kesepakatan bersifat All-in Free Developer atau dikenakan pajak terpisah.</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="setAllFree" class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-black transition-all flex items-center gap-1 cursor-pointer">
                                            <span>⚡</span> <span>Semua Free (All-in)</span>
                                        </button>
                                        <button type="button" @click="setStandardTaxes" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded-xl text-xs font-bold transition-all cursor-pointer">
                                            Hitung Pajak Standar
                                        </button>
                                    </div>
                                </div>

                                <!-- Free Toggles Box -->
                                <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                                    <label class="flex items-center gap-2.5 cursor-pointer">
                                        <input v-model="form.free_ppn" @change="calculateTaxes" type="checkbox" class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500 cursor-pointer" />
                                        <span class="text-xs font-bold text-slate-800">
                                            🏷️ Free PPN (11%) — <span class="text-emerald-700 font-black">Ditanggung Developer (Rp 0 di SPR)</span>
                                        </span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer">
                                        <input v-model="form.free_legal" @change="calculateTaxes" type="checkbox" class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500 cursor-pointer" />
                                        <span class="text-xs font-bold text-slate-800">
                                            📜 Free BPHTB & Biaya Surat (AJB / BBN) — <span class="text-emerald-700 font-black">Ditanggung Developer (Rp 0 di SPR)</span>
                                        </span>
                                    </label>
                                </div>

                                <div class="grid grid-cols-3 gap-3">
                                    <div>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <label class="text-xs font-bold text-slate-700">PPN (11%)</label>
                                            <span v-if="form.free_ppn" class="text-[9px] font-black text-emerald-600 uppercase bg-emerald-100 px-1.5 py-0.5 rounded">Free</span>
                                        </div>
                                        <input v-model="form.ppn_amount" :disabled="form.free_ppn" @input="updateTotal" type="number" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500/20 disabled:bg-slate-100 disabled:text-slate-400 font-bold" />
                                    </div>
                                    <div>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <label class="text-xs font-bold text-slate-700">BPHTB (5%)</label>
                                            <span v-if="form.free_legal" class="text-[9px] font-black text-emerald-600 uppercase bg-emerald-100 px-1.5 py-0.5 rounded">Free</span>
                                        </div>
                                        <input v-model="form.bphtb_amount" :disabled="form.free_legal" @input="updateTotal" type="number" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500/20 disabled:bg-slate-100 disabled:text-slate-400 font-bold" />
                                    </div>
                                    <div>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <label class="text-xs font-bold text-slate-700">Biaya AJB/BBN</label>
                                            <span v-if="form.free_legal" class="text-[9px] font-black text-emerald-600 uppercase bg-emerald-100 px-1.5 py-0.5 rounded">Free</span>
                                        </div>
                                        <input v-model="form.ajb_bbn_amount" :disabled="form.free_legal" @input="updateTotal" type="number" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500/20 disabled:bg-slate-100 disabled:text-slate-400 font-bold" />
                                    </div>
                                </div>
                            </div>

                            <!-- TOTAL HARGA AKHIR KESEPAKATAN -->
                            <div class="bg-gradient-to-r from-blue-900 to-indigo-900 p-5 rounded-2xl text-white shadow-md flex items-center justify-between">
                                <div>
                                    <p class="text-[10px] font-black text-blue-300 uppercase tracking-widest">Total Harga Pengikat Jual Beli di Dokumen SPR</p>
                                    <p class="text-2xl font-black mt-0.5">{{ formatCurrency(form.final_price) }}</p>
                                    <p class="text-[11px] text-blue-200 mt-1 font-semibold">
                                        {{ form.free_ppn && form.free_legal ? '✨ Termasuk Bebas PPN, BPHTB & Biaya Legalitas (All-in)' : 'Tunduk pada pajak & legalitas sesuai rincian di atas' }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="px-3 py-1 bg-white/10 rounded-full text-xs font-bold">SPR Resmi</span>
                                </div>
                            </div>

                            <!-- SECTION 3: SKEMA PEMBAYARAN, UTJ & DP -->
                            <div class="pt-6 border-t border-slate-100 space-y-4">
                                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                    <span>💳</span> Skema Pembayaran & Uang Muka (DP)
                                </h3>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Booking Fee / UTJ <span class="text-rose-500">*</span></label>
                                        <input v-model="form.booking_fee" type="number" placeholder="10000000" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-500/20" />
                                        <p v-if="props.reservation" class="text-[10px] text-emerald-700 font-bold mt-1">
                                            🔖 Otomatis memotong kredit reservasi {{ formatCurrency(props.reservation.amount) }}
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Skema Pembayaran <span class="text-rose-500">*</span></label>
                                        <select v-model="form.payment_scheme" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                            <option value="kpr">KPR (Kredit Pemilikan Rumah)</option>
                                            <option value="cash">Cash Keras (Pelunasan 14 Hari)</option>
                                            <option value="cash_installment">Cash Bertahap / In-House</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- DETAIL KHUSUS KPR -->
                                <div v-if="form.payment_scheme === 'kpr'" class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                                    <h4 class="text-[11px] font-black text-slate-700 uppercase tracking-wider">Ketentuan DP KPR</h4>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Nominal DP (Down Payment)</label>
                                            <input v-model="form.dp_amount" type="number" placeholder="Contoh: 50000000 (0 jika DP 0%)" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-bold focus:ring-2 focus:ring-blue-500/20" />
                                            <p class="text-[10px] text-slate-400 mt-1">Isi 0 jika program promo DP 0%</p>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Cicilan DP (Bulan)</label>
                                            <input v-model="form.dp_installment_months" type="number" placeholder="3" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-bold focus:ring-2 focus:ring-blue-500/20" />
                                            <p class="text-[10px] text-slate-400 mt-1">Tenor angsuran DP sebelum akad KPR</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- DETAIL KHUSUS CASH BERTAHAP -->
                                <div v-if="form.payment_scheme === 'cash_installment'" class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                                    <h4 class="text-[11px] font-black text-slate-700 uppercase tracking-wider">Tenor Cicilan In-House</h4>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Tenor Cicilan (Bulan)</label>
                                            <input v-model="form.installment_months" type="number" placeholder="12 / 24 / 36" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-bold focus:ring-2 focus:ring-blue-500/20" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">DP Awal (Jika Ada)</label>
                                            <input v-model="form.dp_amount" type="number" placeholder="0" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-bold focus:ring-2 focus:ring-blue-500/20" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 4: BONUS KHUSUS KESEPAKATAN (HALAMAN 2 SPR) -->
                            <div class="pt-6 border-t border-slate-100 space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                                            <span>🎁</span> Bonus Khusus Kesepakatan (Tercetak di Lampiran Halaman 2 SPR)
                                        </h3>
                                        <p class="text-[11px] text-slate-500">Item bonus yang dijanjikan ke konsumen akan dicetak di dokumen SPR resmi.</p>
                                    </div>
                                </div>

                                <!-- Bonus Chips Input -->
                                <div class="p-4 bg-amber-50/40 border border-amber-200/70 rounded-2xl space-y-3">
                                    <div class="flex gap-2">
                                        <input v-model="newBonusInput" @keydown.enter.prevent="addBonusItem" type="text" placeholder="Ketik bonus khusus (e.g. Canopy, Smart Lock, AC)..." class="flex-1 px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-amber-500/20" />
                                        <button type="button" @click="addBonusItem" class="px-3 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold cursor-pointer">
                                            + Tambah
                                        </button>
                                    </div>

                                    <!-- Quick Suggestions -->
                                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                        <span class="text-[10px] font-bold text-amber-900">Pilihan cepat:</span>
                                        <button v-for="sug in suggestedBonuses" :key="sug" type="button" @click="addBonusItem(sug)" class="px-2 py-0.5 bg-white hover:bg-amber-100 border border-amber-200 text-amber-900 rounded-lg text-[10px] font-semibold transition-colors cursor-pointer">
                                            + {{ sug }}
                                        </button>
                                    </div>

                                    <!-- Active Bonus Items -->
                                    <div v-if="form.special_bonus_items.length > 0" class="pt-2 flex flex-wrap gap-2">
                                        <span v-for="(b, idx) in form.special_bonus_items" :key="idx" class="inline-flex items-center gap-1 px-3 py-1 bg-amber-100/90 text-amber-950 font-bold rounded-lg text-xs border border-amber-300">
                                            <span>✓ {{ b }}</span>
                                            <button type="button" @click="removeBonusItem(idx)" class="text-amber-800 hover:text-rose-600 font-black ml-1 cursor-pointer">×</button>
                                        </span>
                                    </div>
                                    <p v-else class="text-[11px] text-slate-400 italic">Belum ada bonus khusus yang ditambahkan.</p>
                                </div>
                            </div>

                            <!-- KELENGKAPAN DATA KONSUMEN (SPR REQUIREMENTS) -->
                            <div class="pt-6 border-t border-slate-100 space-y-3">
                                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-3">
                                    <h3 class="text-[10px] font-black text-slate-600 uppercase tracking-widest flex items-center gap-1.5">
                                        <span>👤</span> <span>Kelengkapan Data Konsumen (Ter-cetak di SPR)</span>
                                    </h3>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">NIK (No. KTP)</label>
                                            <input v-model="form.buyer_nik" type="text" placeholder="3171xxxxxxxxxxxx" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-blue-500/20" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">NPWP</label>
                                            <input v-model="form.buyer_npwp" type="text" placeholder="09.xxx.xxx.x-xxx.xxx" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-blue-500/20" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Pekerjaan</label>
                                            <input v-model="form.buyer_job" type="text" placeholder="Karyawan / Wiraswasta" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-blue-500/20" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Alamat KTP</label>
                                            <input v-model="form.buyer_address" type="text" placeholder="Jl. Raya No. XX..." class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-blue-500/20" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PEMESAN TAMBAHAN / PENANGGUNG JAWAB PEMBAYARAN -->
                            <div class="p-4 bg-amber-50/50 rounded-2xl border border-amber-200/60 space-y-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.has_secondary_buyer" type="checkbox" class="w-4 h-4 text-amber-600 rounded border-slate-300 focus:ring-amber-500" />
                                    <span class="text-xs font-bold text-slate-800">➕ Tambahkan Pemesan 2 / Penanggung Jawab Pembayaran (e.g. Orang Tua / Pasangan / Anak)</span>
                                </label>

                                <div v-if="form.has_secondary_buyer" class="grid grid-cols-2 gap-3 pt-2">
                                    <div>
                                        <label class="block text-[10px] font-bold text-amber-700 uppercase mb-1">Nama Pemesan 2 / Penanggung Jawab</label>
                                        <input v-model="form.secondary_name" type="text" placeholder="Nama Lengkap Penanggung Jawab" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-amber-500/20" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-amber-700 uppercase mb-1">Hubungan</label>
                                        <select v-model="form.secondary_relationship" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-amber-500/20">
                                            <option value="Orang Tua">Orang Tua</option>
                                            <option value="Anak">Anak</option>
                                            <option value="Suami">Suami</option>
                                            <option value="Istri">Istri</option>
                                            <option value="Pasangan">Pasangan</option>
                                            <option value="Kerabat">Kerabat / Rekan</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-amber-700 uppercase mb-1">NIK Pemesan 2</label>
                                        <input v-model="form.secondary_nik" type="text" placeholder="NIK KTP Penanggung Jawab" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-amber-500/20" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-amber-700 uppercase mb-1">No. HP / WA Pemesan 2</label>
                                        <input v-model="form.secondary_phone" type="text" placeholder="08xxxxxxxxxx" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-amber-500/20" />
                                    </div>
                                </div>
                            </div>

                            <!-- KUSTOMISASI TTD SPR KHUSUS BOOKING INI -->
                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.has_custom_signatures" type="checkbox" class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500" />
                                    <span class="text-xs font-bold text-slate-800">🖋️ Kustomisasi Nama Penanda Tangan SPR (Opsional Khusus Booking Ini)</span>
                                </label>

                                <div v-if="form.has_custom_signatures" class="grid grid-cols-2 gap-3 pt-2">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Judul TTD 1</label>
                                        <input v-model="form.sig1_title" type="text" placeholder="AGENT COORDINATOR" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Nama TTD 1</label>
                                        <input v-model="form.sig1_name" type="text" placeholder="Maulizar Hamid" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Judul TTD 2</label>
                                        <input v-model="form.sig2_title" type="text" placeholder="DIREKTUR" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Nama TTD 2</label>
                                        <input v-model="form.sig2_name" type="text" placeholder="Ch. Bramantyo P. S" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Judul TTD 3</label>
                                        <input v-model="form.sig3_title" type="text" placeholder="SALES" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Nama TTD 3</label>
                                        <input v-model="form.sig3_name" type="text" placeholder="Mawardi KanaProject" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Judul TTD 4</label>
                                        <input v-model="form.sig4_title" type="text" placeholder="Penanggung Jawab" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Nama TTD 4</label>
                                        <input v-model="form.sig4_name" type="text" placeholder="Nama Penanggung Jawab" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs" />
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Catatan Tambahan Kesepakatan</label>
                                <textarea v-model="form.notes" rows="2" placeholder="Catatan tambahan yang akan disimpan di riwayat booking..." class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 resize-none"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SIDEBAR INFO & LIVE SPR SUMMARY -->
                <div class="space-y-6">
                    <!-- UNIT SUMMARY -->
                    <div class="bg-slate-900 rounded-2xl p-6 text-white shadow-xl">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-blue-400">Ringkasan Unit</h3>
                            <span v-if="selectedUnit" class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-blue-500/20 text-blue-300">
                                Blok {{ selectedUnit.block }}{{ selectedUnit.number }}
                            </span>
                        </div>

                        <div v-if="selectedUnit" class="space-y-4">
                            <div>
                                <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Proyek</p>
                                <p class="text-sm font-bold">{{ selectedUnit.project?.name }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Kavling</p>
                                    <p class="text-sm font-bold">{{ selectedUnit.block }}{{ selectedUnit.number }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Tipe</p>
                                    <p class="text-sm font-bold">{{ selectedUnit.unit_type?.name }}</p>
                                </div>
                            </div>
                            <div class="pt-3 border-t border-white/10 space-y-1">
                                <div class="flex justify-between text-xs">
                                    <span class="text-slate-400">Harga Brosur:</span>
                                    <span class="font-bold text-slate-300">{{ formatCurrency(brochurePrice) }}</span>
                                </div>
                                <div class="flex justify-between text-xs">
                                    <span class="text-blue-300 font-bold">Harga Kesepakatan:</span>
                                    <span class="font-black text-blue-400">{{ formatCurrency(form.base_price) }}</span>
                                </div>
                                <div v-if="discountSavings > 0" class="flex justify-between text-xs text-emerald-400 font-semibold">
                                    <span>Hemat / Diskon:</span>
                                    <span>-{{ formatCurrency(discountSavings) }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 opacity-50">
                            <p class="text-xs">Silakan pilih unit terlebih dahulu</p>
                        </div>
                    </div>

                    <!-- SPR LIVE CONNECTION SUMMARY BOX -->
                    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-5 text-xs text-blue-950 space-y-3 shadow-xs">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">📄</span>
                            <div>
                                <h4 class="font-black text-xs text-blue-950 uppercase tracking-wider">Koneksi ke Dokumen SPR</h4>
                                <p class="text-[10px] text-blue-700">Data otomatis dicetak di Surat Pesanan Rumah:</p>
                            </div>
                        </div>

                        <div class="space-y-1.5 pt-1 text-[11px]">
                            <div class="flex justify-between py-0.5 border-b border-blue-100">
                                <span class="text-blue-700">Harga Kesepakatan:</span>
                                <strong class="font-black text-blue-950">{{ formatCurrency(form.final_price) }}</strong>
                            </div>
                            <div class="flex justify-between py-0.5 border-b border-blue-100">
                                <span class="text-blue-700">Uang Tanda Jadi (UTJ):</span>
                                <strong class="font-bold text-emerald-700">{{ formatCurrency(form.booking_fee) }}</strong>
                            </div>
                            <div class="flex justify-between py-0.5 border-b border-blue-100">
                                <span class="text-blue-700">Skema Bayar:</span>
                                <strong class="font-bold uppercase">{{ form.payment_scheme }}</strong>
                            </div>
                            <div class="flex justify-between py-0.5 border-b border-blue-100">
                                <span class="text-blue-700">PPN & BPHTB:</span>
                                <strong class="font-bold text-emerald-700">{{ form.free_ppn && form.free_legal ? 'Free (All-in)' : 'Pajak Standar' }}</strong>
                            </div>
                            <div class="flex justify-between py-0.5">
                                <span class="text-blue-700">Bonus Khusus SPR:</span>
                                <strong class="font-bold text-blue-950">{{ form.special_bonus_items.length }} Item</strong>
                            </div>
                        </div>

                        <p class="text-[10px] text-blue-600 font-semibold bg-blue-100/70 p-2 rounded-xl">
                            💡 Setelah booking disetujui (Approve), status unit terkunci, jadwal bayar terbit, dan SPR dapat diunduh/ditandatangani digital.
                        </p>
                    </div>

                    <!-- SUBMIT BUTTON -->
                    <button type="submit" :disabled="form.processing"
                        class="w-full py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-black rounded-2xl shadow-xl shadow-blue-500/30 hover:-translate-y-0.5 transition-all active:scale-95 disabled:opacity-50 cursor-pointer text-sm">
                        {{ form.processing ? 'Menyimpan & Menyiapkan SPR...' : 'SIMPAN BOOKING & TERBITKAN SPR' }}
                    </button>
                    
                    <Link href="/bookings" class="block w-full py-2.5 text-center text-xs font-bold text-slate-400 hover:text-slate-600 transition-colors">Batal</Link>
                </div>
            </form>
        </div>
    </CrmLayout>
</template>
