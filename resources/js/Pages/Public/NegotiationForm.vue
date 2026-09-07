<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    negotiation: Object,
    settings: Object,
});

const page = usePage();
const flash = computed(() => page.props.flash || {});
const nego = computed(() => props.negotiation);

const isExpired = computed(() => nego.value.expired_at && new Date(nego.value.expired_at) < new Date());
const isSubmitted = computed(() => ['pending', 'reviewed', 'approved', 'rejected'].includes(nego.value.status));
const isCounterOffer = computed(() => nego.value.status === 'counter_offer');
const isDraft = computed(() => nego.value.status === 'draft');

// Form state
const form = useForm({
    client_name: nego.value.client_name || '',
    client_phone: nego.value.client_phone || '',
    client_email: nego.value.client_email || '',
    offered_price: nego.value.offered_price || '',
    payment_scheme: nego.value.payment_scheme || 'kpr',
    dp_amount: nego.value.dp_amount || '',
    installment_months: nego.value.installment_months || (nego.value.payment_scheme === 'kpr' ? 120 : 12),
    special_requests: nego.value.special_requests || '',
    notes: nego.value.notes || '',
});

function submitForm() {
    form.post(`/nego/${nego.value.token}`, { preserveScroll: true });
}

// Counter Response Form
const counterForm = useForm({
    response: '',
    offered_price: '',
    notes: '',
});

function respondToCounter(response) {
    counterForm.response = response;
    if (response === 'accepted') {
        counterForm.offered_price = nego.value.counter_price;
    }
    counterForm.post(`/nego/${nego.value.token}/respond`, { preserveScroll: true });
}

function submitRevised() {
    counterForm.response = 'revised';
    counterForm.post(`/nego/${nego.value.token}/respond`, { preserveScroll: true });
}

// Quick Bonus Request Chips
const availableBonuses = [
    '🚗 Free Canopy Carport',
    '❄️ Free AC 1 Unit',
    '🍳 Free Kitchen Set',
    '📜 Bebas Biaya BPHTB & AJB',
    '⚡ Upgrade Daya Listrik',
    '💧 Tandon Air & Pompa Booster',
];

const selectedBonuses = ref([]);

function toggleBonus(bonus) {
    if (selectedBonuses.value.includes(bonus)) {
        selectedBonuses.value = selectedBonuses.value.filter(b => b !== bonus);
    } else {
        selectedBonuses.value.push(bonus);
    }
    updateSpecialRequestsText();
}

function updateSpecialRequestsText() {
    const customText = form.special_requests
        .split('\n')
        .filter(line => !availableBonuses.some(b => line.includes(b)))
        .join('\n')
        .trim();

    const bonusText = selectedBonuses.value.join('\n');
    form.special_requests = [bonusText, customText].filter(Boolean).join('\n');
}

// Calculations & Simulators
const priceDifference = computed(() => {
    const listed = Number(nego.value.unit_listed_price || 0);
    const offered = Number(form.offered_price || 0);
    if (!offered || offered >= listed) return null;
    const diff = listed - offered;
    const pct = ((diff / listed) * 100).toFixed(1);
    return { diff, pct };
});

const estimatedMonthlyInstallment = computed(() => {
    const offered = Number(form.offered_price || nego.value.unit_listed_price || 0);
    const dp = Number(form.dp_amount || 0);
    const months = Number(form.installment_months || 0);

    if (!offered || !months || months <= 0) return null;

    const loanPrincipal = Math.max(0, offered - dp);

    if (form.payment_scheme === 'kpr') {
        // Simple KPR estimation at ~5% p.a. fixed
        const annualRate = 0.05;
        const monthlyRate = annualRate / 12;
        const emi = (loanPrincipal * monthlyRate * Math.pow(1 + monthlyRate, months)) / (Math.pow(1 + monthlyRate, months) - 1);
        return isNaN(emi) ? null : Math.round(emi);
    } else if (form.payment_scheme === 'cash_bertahap') {
        // Flat monthly installment for direct developer
        return Math.round(loanPrincipal / months);
    }
    return null;
});

// Helpers
function formatCurrency(val) {
    if (!val) return 'Rp 0';
    return 'Rp ' + Number(val).toLocaleString('id-ID');
}
</script>

<template>
    <Head :title="`Form Negosiasi - ${negotiation.unit?.code || negotiation.unit?.number || 'Unit'}`" />

    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans selection:bg-indigo-500 selection:text-white pb-16">
        <!-- BACKGROUND DECORATION -->
        <div class="fixed inset-0 overflow-hidden pointer-events-none z-0 opacity-40">
            <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600 rounded-full blur-[140px]"></div>
            <div class="absolute top-1/3 -right-40 w-96 h-96 bg-blue-600 rounded-full blur-[140px]"></div>
            <div class="absolute -bottom-40 left-1/3 w-96 h-96 bg-purple-600 rounded-full blur-[140px]"></div>
        </div>

        <div class="relative z-10">
            <!-- TOP BAR HEADER -->
            <header class="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-xl sticky top-0 z-50">
                <div class="max-w-3xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div v-if="settings?.company_logo" class="shrink-0 bg-slate-800/60 p-1.5 rounded-xl border border-slate-700">
                            <img :src="`/storage/${settings.company_logo}`" class="h-8 max-w-[130px] object-contain" />
                        </div>
                        <div v-else class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-blue-600 rounded-xl flex items-center justify-center text-white font-black text-sm shadow-lg shadow-indigo-500/20 shrink-0">
                            H
                        </div>
                        <div>
                            <h1 class="text-sm font-black text-white tracking-wide">{{ settings?.company_name || 'Homi Developer' }}</h1>
                            <p class="text-[10px] font-bold text-slate-400">Portal Resmi Pengajuan Negosiasi Unit</p>
                        </div>
                    </div>

                    <!-- Verified Badge -->
                    <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 bg-emerald-500/10 border border-emerald-500/30 rounded-full text-emerald-400 text-xs font-bold">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        Form Likuid & Resmi
                    </div>
                </div>
            </header>

            <main class="max-w-3xl mx-auto px-4 sm:px-6 pt-6 space-y-6">
                <!-- ISSUING AGENT BANNER CARD -->
                <div v-if="nego.creator" class="bg-gradient-to-r from-slate-900 via-slate-900/90 to-indigo-950/60 border border-slate-800/80 rounded-2xl p-4 sm:p-5 flex items-center justify-between gap-4 shadow-xl">
                    <div class="flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 font-black text-lg shrink-0">
                            👤
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-black uppercase tracking-wider text-indigo-400">Agent Pendamping</span>
                                <span v-if="nego.creator?.broker_company" class="text-[10px] font-bold text-slate-400">({{ nego.creator.broker_company.name }})</span>
                            </div>
                            <h2 class="text-sm font-black text-white">{{ nego.creator.name }}</h2>
                            <p class="text-xs text-slate-400">{{ nego.creator.phone || nego.creator.email || 'Siap membantu negosiasi Anda' }}</p>
                        </div>
                    </div>
                    <a v-if="nego.creator.phone" :href="`https://wa.me/${nego.creator.phone.replace(/[^0-9]/g, '')}?text=Halo%20${encodeURIComponent(nego.creator.name)},%20saya%20sedang%20mengisi%20form%20negosiasi%20unit%20${encodeURIComponent(nego.unit?.code || '')}`" target="_blank" class="shrink-0 px-3.5 py-2 bg-emerald-600/20 hover:bg-emerald-600/30 border border-emerald-500/40 text-emerald-300 font-bold text-xs rounded-xl flex items-center gap-1.5 transition-all">
                        <span>💬</span> Chat WA
                    </a>
                </div>

                <!-- UNIT PROPERTY CARD SHOWCASE -->
                <div class="bg-gradient-to-b from-slate-900 to-slate-900/90 border border-slate-800 rounded-3xl p-6 sm:p-7 shadow-2xl relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6 relative z-10">
                        <div class="space-y-3">
                            <div class="inline-flex items-center gap-2 px-3 py-1 bg-indigo-500/10 border border-indigo-500/30 rounded-full text-indigo-300 text-xs font-bold">
                                🏢 {{ nego.project?.name || 'Proyek Hunian' }}
                            </div>
                            <div>
                                <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                                    Unit {{ nego.unit?.code || nego.unit?.number || nego.unit?.block || 'Impian' }}
                                </h2>
                                <p class="text-xs sm:text-sm font-medium text-slate-400 mt-1">
                                    Tipe: {{ nego.unit?.unit_type?.name || nego.unit?.type || 'Hunian Modern' }}
                                </p>
                            </div>

                            <!-- PROPERTY SPECS BADGES -->
                            <div class="flex flex-wrap items-center gap-2 pt-2">
                                <div v-if="nego.unit?.unit_type?.building_area || nego.unit?.building_area" class="px-3 py-1.5 bg-slate-800/80 border border-slate-700/60 rounded-xl text-xs text-slate-300 font-semibold flex items-center gap-1">
                                    🏠 LB: {{ nego.unit?.unit_type?.building_area || nego.unit?.building_area }} m²
                                </div>
                                <div v-if="nego.unit?.unit_type?.surface_area || nego.unit?.surface_area" class="px-3 py-1.5 bg-slate-800/80 border border-slate-700/60 rounded-xl text-xs text-slate-300 font-semibold flex items-center gap-1">
                                    📐 LT: {{ nego.unit?.unit_type?.surface_area || nego.unit?.surface_area }} m²
                                </div>
                                <div v-if="nego.unit?.unit_type?.bedrooms" class="px-3 py-1.5 bg-slate-800/80 border border-slate-700/60 rounded-xl text-xs text-slate-300 font-semibold flex items-center gap-1">
                                    🛏️ {{ nego.unit.unit_type.bedrooms }} KT
                                </div>
                                <div v-if="nego.unit?.unit_type?.bathrooms" class="px-3 py-1.5 bg-slate-800/80 border border-slate-700/60 rounded-xl text-xs text-slate-300 font-semibold flex items-center gap-1">
                                    🚿 {{ nego.unit.unit_type.bathrooms }} KM
                                </div>
                                <div v-if="nego.unit?.facing_direction" class="px-3 py-1.5 bg-slate-800/80 border border-slate-700/60 rounded-xl text-xs text-slate-300 font-semibold flex items-center gap-1">
                                    🧭 Hadap {{ nego.unit.facing_direction }}
                                </div>
                            </div>
                        </div>

                        <!-- PRICE HIGHLIGHT BOX -->
                        <div class="sm:text-right shrink-0 bg-slate-800/50 border border-slate-700/80 rounded-2xl p-4 sm:p-5 backdrop-blur-md">
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Harga Resmi Listing</p>
                            <p class="text-xl sm:text-2xl font-black text-emerald-400 font-mono mt-1">
                                {{ formatCurrency(nego.unit_listed_price) }}
                            </p>
                            <p class="text-[10px] text-slate-400 mt-1">*Harga dasar belum termasuk diskon negosiasi</p>
                        </div>
                    </div>
                </div>

                <!-- EXPIRED STATE -->
                <div v-if="isExpired && nego.status !== 'approved'" class="bg-rose-950/40 border border-rose-800/80 rounded-3xl p-8 text-center backdrop-blur-xl">
                    <div class="w-16 h-16 bg-rose-500/20 text-rose-400 rounded-full flex items-center justify-center text-3xl mx-auto mb-4 border border-rose-500/30">
                        ⏰
                    </div>
                    <h3 class="text-lg font-black text-rose-200">Form Negosiasi Kedaluwarsa</h3>
                    <p class="text-xs text-rose-400 mt-2 max-w-md mx-auto">
                        Batas waktu pengajuan untuk link ini telah berakhir. Silakan minta agent Anda untuk mengirimkan tautan pengajuan yang baru.
                    </p>
                </div>

                <!-- SUCCESS STATE AFTER SUBMIT -->
                <div v-else-if="flash.success && !isCounterOffer" class="bg-emerald-950/40 border border-emerald-800/80 rounded-3xl p-8 text-center backdrop-blur-xl space-y-4">
                    <div class="w-16 h-16 bg-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center text-3xl mx-auto border border-emerald-500/30">
                        🎉
                    </div>
                    <h3 class="text-xl font-black text-emerald-200">{{ flash.success }}</h3>
                    <p class="text-xs text-emerald-400 max-w-md mx-auto">
                        Pengajuan negosiasi Anda telah resmi diterima oleh sistem. Tim management developer akan meninjau dan memberikan keputusan segera.
                    </p>
                </div>

                <!-- SUBMITTED / IN REVIEW STATE (STEPPER & SUMMARY) -->
                <div v-else-if="isSubmitted && !isCounterOffer" class="space-y-6">
                    <!-- STATUS STEPPER -->
                    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
                        <p class="text-xs font-black uppercase tracking-wider text-slate-400 mb-6 text-center">Status Alur Pengajuan Anda</p>
                        <div class="grid grid-cols-3 gap-2 relative">
                            <div class="text-center space-y-2">
                                <div class="w-10 h-10 rounded-full bg-emerald-500/20 border border-emerald-500 text-emerald-400 font-black text-sm flex items-center justify-center mx-auto">
                                    ✓
                                </div>
                                <p class="text-xs font-bold text-emerald-400">1. Terkirim</p>
                            </div>
                            <div class="text-center space-y-2">
                                <div :class="['w-10 h-10 rounded-full flex items-center justify-center mx-auto font-black text-sm border', nego.status === 'approved' || nego.status === 'rejected' ? 'bg-emerald-500/20 border-emerald-500 text-emerald-400' : 'bg-amber-500/20 border-amber-500 text-amber-400 animate-pulse']">
                                    2
                                </div>
                                <p :class="['text-xs font-bold', nego.status === 'approved' || nego.status === 'rejected' ? 'text-emerald-400' : 'text-amber-400']">2. Peninjauan</p>
                            </div>
                            <div class="text-center space-y-2">
                                <div :class="['w-10 h-10 rounded-full flex items-center justify-center mx-auto font-black text-sm border', nego.status === 'approved' ? 'bg-emerald-500/20 border-emerald-500 text-emerald-400' : nego.status === 'rejected' ? 'bg-rose-500/20 border-rose-500 text-rose-400' : 'bg-slate-800 border-slate-700 text-slate-500']">
                                    3
                                </div>
                                <p :class="['text-xs font-bold', nego.status === 'approved' ? 'text-emerald-400' : nego.status === 'rejected' ? 'text-rose-400' : 'text-slate-500']">3. Keputusan</p>
                            </div>
                        </div>
                    </div>

                    <!-- SUMMARY BOX -->
                    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                            <h3 class="text-sm font-black text-white uppercase tracking-wider">Ringkasan Pengajuan</h3>
                            <span :class="['px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider', nego.status === 'approved' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : nego.status === 'rejected' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30']">
                                {{ nego.status === 'approved' ? 'Disetujui' : nego.status === 'rejected' ? 'Ditolak' : 'Sedang Ditinjau' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div class="p-3.5 bg-slate-800/50 rounded-2xl border border-slate-800 flex justify-between items-center">
                                <span class="text-slate-400 font-medium">Harga Pengajuan:</span>
                                <span class="font-black text-emerald-400 font-mono text-sm">{{ formatCurrency(nego.offered_price) }}</span>
                            </div>
                            <div class="p-3.5 bg-slate-800/50 rounded-2xl border border-slate-800 flex justify-between items-center">
                                <span class="text-slate-400 font-medium">Skema Pembayaran:</span>
                                <span class="font-bold text-white uppercase">{{ { cash_keras: 'Cash Keras', cash_bertahap: 'Cash Bertahap', kpr: 'KPR Bank' }[nego.payment_scheme] || '-' }}</span>
                            </div>
                            <div v-if="nego.dp_amount" class="p-3.5 bg-slate-800/50 rounded-2xl border border-slate-800 flex justify-between items-center">
                                <span class="text-slate-400 font-medium">Rencana DP:</span>
                                <span class="font-bold text-indigo-300 font-mono">{{ formatCurrency(nego.dp_amount) }}</span>
                            </div>
                            <div v-if="nego.installment_months" class="p-3.5 bg-slate-800/50 rounded-2xl border border-slate-800 flex justify-between items-center">
                                <span class="text-slate-400 font-medium">Tenor Cicilan:</span>
                                <span class="font-bold text-white">{{ nego.installment_months }} Bulan</span>
                            </div>
                        </div>

                        <div v-if="nego.special_requests" class="p-4 bg-slate-800/40 rounded-2xl border border-slate-800 space-y-1">
                            <p class="text-[10px] font-black uppercase text-slate-400">Permintaan Khusus / Bonus:</p>
                            <p class="text-xs text-slate-200 whitespace-pre-line font-medium">{{ nego.special_requests }}</p>
                        </div>
                    </div>
                </div>

                <!-- COUNTER OFFER STATE INTERFACE -->
                <div v-else-if="isCounterOffer" class="bg-gradient-to-b from-purple-950/60 to-slate-900 border border-purple-800/80 rounded-3xl p-6 sm:p-7 shadow-2xl space-y-6">
                    <div class="text-center space-y-2">
                        <div class="w-14 h-14 bg-purple-500/20 text-purple-300 rounded-2xl flex items-center justify-center text-2xl mx-auto border border-purple-500/40">
                            🔄
                        </div>
                        <h3 class="text-lg sm:text-xl font-black text-white">Penawaran Balik (Counter Offer) Developer</h3>
                        <p class="text-xs text-purple-300 max-w-md mx-auto">
                            Developer telah meninjau pengajuan Anda dan memberikan angka penawaran resmi terbaik.
                        </p>
                    </div>

                    <!-- COMPARISON BOXES -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 bg-slate-900/80 border border-slate-800 rounded-2xl text-center">
                            <p class="text-[10px] font-black text-slate-400 uppercase">Pengajuan Anda</p>
                            <p class="text-base font-black text-slate-400 line-through font-mono mt-1">{{ formatCurrency(nego.offered_price) }}</p>
                        </div>
                        <div class="p-4 bg-purple-900/40 border border-purple-500/50 rounded-2xl text-center shadow-lg shadow-purple-500/10">
                            <p class="text-[10px] font-black text-purple-300 uppercase">Penawaran Developer</p>
                            <p class="text-lg font-black text-emerald-400 font-mono mt-1">{{ formatCurrency(nego.counter_price) }}</p>
                        </div>
                    </div>

                    <div v-if="nego.counter_notes" class="p-4 bg-purple-900/30 border border-purple-700/50 rounded-2xl text-xs text-purple-200 italic">
                        💬 <span class="font-bold uppercase tracking-wider text-purple-300">Pesan Developer:</span> {{ nego.counter_notes }}
                    </div>

                    <!-- ACTION BUTTONS -->
                    <div class="space-y-3 pt-2">
                        <button @click="respondToCounter('accepted')" :disabled="counterForm.processing" class="w-full py-4 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-black text-sm rounded-2xl shadow-xl shadow-emerald-600/25 transition-all disabled:opacity-40 flex items-center justify-center gap-2">
                            <span>✅</span> Terima Penawaran {{ formatCurrency(nego.counter_price) }}
                        </button>

                        <div class="p-5 bg-slate-900 border border-slate-800 rounded-2xl space-y-4">
                            <p class="text-xs font-black text-slate-300 uppercase tracking-wider">Atau Ajukan Angka Revisi Terakhir:</p>
                            <div class="space-y-3">
                                <input v-model="counterForm.offered_price" type="number" min="1" placeholder="Nominal revisi Anda (Rp)..." class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-sm font-bold text-white placeholder-slate-500 focus:ring-2 focus:ring-purple-500" />
                                <textarea v-model="counterForm.notes" rows="2" placeholder="Catatan tambahan revisi..." class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-xs font-medium text-white placeholder-slate-500 focus:ring-2 focus:ring-purple-500"></textarea>
                                <button @click="submitRevised" :disabled="counterForm.processing || !counterForm.offered_price" class="w-full py-3 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-lg transition-all disabled:opacity-40">
                                    🔄 Kirim Penawaran Revisi
                                </button>
                            </div>
                        </div>

                        <button @click="respondToCounter('rejected')" :disabled="counterForm.processing" class="w-full py-3 bg-slate-800/80 hover:bg-slate-800 text-slate-400 font-bold text-xs rounded-xl transition-all disabled:opacity-40">
                            Tolak Penawaran Ini
                        </button>
                    </div>
                </div>

                <!-- DRAFT STATE - FORM INPUT -->
                <div v-else-if="isDraft" class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-8">
                    <div>
                        <h3 class="text-xl font-black text-white tracking-tight">Form Pengajuan Harga & Ketentuan</h3>
                        <p class="text-xs text-slate-400 mt-1">Lengkapi data pribadi dan rencana pembayaran yang Anda harapkan.</p>
                    </div>

                    <form @submit.prevent="submitForm" class="space-y-7">
                        <!-- SECTION 1: PERSONAL DATA -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 pb-2 border-b border-slate-800">
                                <span class="w-6 h-6 rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center font-black text-xs">1</span>
                                <h4 class="text-xs font-black uppercase tracking-wider text-slate-300">Data Identitas Pembeli</h4>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1.5">Nama Lengkap Pembeli <span class="text-rose-500">*</span></label>
                                <input v-model="form.client_name" type="text" required placeholder="Masukkan nama sesuai KTP" class="w-full px-4 py-3.5 bg-slate-950 border border-slate-800 rounded-2xl text-sm font-bold text-white placeholder-slate-600 focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all" />
                                <p v-if="form.errors.client_name" class="text-xs text-rose-400 mt-1">{{ form.errors.client_name }}</p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 mb-1.5">No. WhatsApp / HP Active <span class="text-rose-500">*</span></label>
                                    <input v-model="form.client_phone" type="tel" required placeholder="08xxxxxxxxxx" class="w-full px-4 py-3.5 bg-slate-950 border border-slate-800 rounded-2xl text-sm font-bold text-white placeholder-slate-600 focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 mb-1.5">Alamat Email (Opsional)</label>
                                    <input v-model="form.client_email" type="email" placeholder="nama@email.com" class="w-full px-4 py-3.5 bg-slate-950 border border-slate-800 rounded-2xl text-sm font-bold text-white placeholder-slate-600 focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all" />
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 2: NEGOTIATION OFFER -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 pb-2 border-b border-slate-800">
                                <span class="w-6 h-6 rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center font-black text-xs">2</span>
                                <h4 class="text-xs font-black uppercase tracking-wider text-slate-300">Pengajuan Harga & Pembayaran</h4>
                            </div>

                            <!-- OFFER PRICE INPUT WITH LIVE DIFFERENCE CALCULATION -->
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1.5">Harga yang Anda Ajukan (Rp) <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-black text-slate-500">Rp</span>
                                    <input v-model="form.offered_price" type="number" required min="1" placeholder="Contoh: 450000000" class="w-full pl-12 pr-4 py-3.5 bg-slate-950 border border-slate-800 rounded-2xl text-base font-black text-emerald-400 font-mono placeholder-slate-600 focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all" />
                                </div>
                                <p v-if="form.errors.offered_price" class="text-xs text-rose-400 mt-1">{{ form.errors.offered_price }}</p>

                                <!-- LIVE SELISIH BADGE -->
                                <div v-if="priceDifference" class="mt-2.5 px-4 py-2.5 bg-emerald-500/10 border border-emerald-500/30 rounded-xl flex items-center justify-between text-xs">
                                    <span class="text-emerald-300 font-bold">✨ Estimasi Hemat / Potongan:</span>
                                    <span class="font-black text-emerald-400 font-mono">
                                        -{{ formatCurrency(priceDifference.diff) }} ({{ priceDifference.pct }}%)
                                    </span>
                                </div>
                            </div>

                            <!-- INTERACTIVE PAYMENT SCHEME SELECTION -->
                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-2">Pilih Skema Pembayaran <span class="text-rose-500">*</span></label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <!-- KPR BANK -->
                                    <div @click="form.payment_scheme = 'kpr'; form.installment_months = 120" :class="['p-4 rounded-2xl border cursor-pointer transition-all space-y-1.5', form.payment_scheme === 'kpr' ? 'bg-indigo-600/20 border-indigo-500 ring-2 ring-indigo-500/50' : 'bg-slate-950 border-slate-800 hover:border-slate-700']">
                                        <div class="text-xl">🏦</div>
                                        <p class="text-xs font-black text-white">KPR Bank</p>
                                        <p class="text-[10px] text-slate-400 leading-snug">Cicilan bulanan melalui fasilitas bank pilihan.</p>
                                    </div>

                                    <!-- CASH KERAS -->
                                    <div @click="form.payment_scheme = 'cash_keras'; form.installment_months = 1; form.dp_amount = ''" :class="['p-4 rounded-2xl border cursor-pointer transition-all space-y-1.5', form.payment_scheme === 'cash_keras' ? 'bg-emerald-600/20 border-emerald-500 ring-2 ring-emerald-500/50' : 'bg-slate-950 border-slate-800 hover:border-slate-700']">
                                        <div class="text-xl">💰</div>
                                        <p class="text-xs font-black text-white">Cash Keras</p>
                                        <p class="text-[10px] text-slate-400 leading-snug">Pembayaran pelunasan tunai dalam 30 hari.</p>
                                    </div>

                                    <!-- CASH BERTAHAP -->
                                    <div @click="form.payment_scheme = 'cash_bertahap'; form.installment_months = 12" :class="['p-4 rounded-2xl border cursor-pointer transition-all space-y-1.5', form.payment_scheme === 'cash_bertahap' ? 'bg-purple-600/20 border-purple-500 ring-2 ring-purple-500/50' : 'bg-slate-950 border-slate-800 hover:border-slate-700']">
                                        <div class="text-xl">📅</div>
                                        <p class="text-xs font-black text-white">Cash Bertahap</p>
                                        <p class="text-[10px] text-slate-400 leading-snug">Cicilan langsung ke developer tanpa bank.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- DP AND TENOR INPUTS (IF APPLICABLE) -->
                            <div v-if="form.payment_scheme !== 'cash_keras'" class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 mb-1.5">Rencana Nominal Uang Muka (DP)</label>
                                    <input v-model="form.dp_amount" type="number" min="0" placeholder="Nominal DP (Rp)" class="w-full px-4 py-3.5 bg-slate-950 border border-slate-800 rounded-2xl text-sm font-bold text-white placeholder-slate-600 focus:ring-2 focus:ring-indigo-500 transition-all" />
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 mb-1.5">
                                        Tenor Cicilan {{ form.payment_scheme === 'kpr' ? '(Bulan)' : '(Bulan / Kali)' }}
                                    </label>
                                    <select v-model="form.installment_months" class="w-full px-4 py-3.5 bg-slate-950 border border-slate-800 rounded-2xl text-sm font-bold text-white focus:ring-2 focus:ring-indigo-500 cursor-pointer">
                                        <template v-if="form.payment_scheme === 'kpr'">
                                            <option :value="60">5 Tahun (60 Bulan)</option>
                                            <option :value="120">10 Tahun (120 Bulan)</option>
                                            <option :value="180">15 Tahun (180 Bulan)</option>
                                            <option :value="240">20 Tahun (240 Bulan)</option>
                                            <option :value="300">25 Tahun (300 Bulan)</option>
                                        </template>
                                        <template v-else>
                                            <option :value="6">6 Bulan (6x Cicilan)</option>
                                            <option :value="12">12 Bulan (12x Cicilan)</option>
                                            <option :value="18">18 Bulan (18x Cicilan)</option>
                                            <option :value="24">24 Bulan (24x Cicilan)</option>
                                            <option :value="36">36 Bulan (36x Cicilan)</option>
                                        </template>
                                    </select>
                                </div>
                            </div>

                            <!-- ESTIMATED MONTHLY INSTALLMENT SIMULATOR CARD -->
                            <div v-if="estimatedMonthlyInstallment" class="p-4 bg-indigo-950/40 border border-indigo-800/60 rounded-2xl flex items-center justify-between">
                                <div>
                                    <p class="text-[10px] font-black uppercase text-indigo-300">Estimasi Cicilan per Bulan</p>
                                    <p class="text-xs text-slate-400 mt-0.5">*Perhitungan indikatif & belum mengikat</p>
                                </div>
                                <p class="text-lg font-black text-indigo-300 font-mono">
                                    ~{{ formatCurrency(estimatedMonthlyInstallment) }}<span class="text-xs font-normal text-slate-400">/bln</span>
                                </p>
                            </div>
                        </div>

                        <!-- SECTION 3: BONUS REQUEST CHIPS & NOTES -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 pb-2 border-b border-slate-800">
                                <span class="w-6 h-6 rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center font-black text-xs">3</span>
                                <h4 class="text-xs font-black uppercase tracking-wider text-slate-300">Permintaan Khusus & Bonus Tambahan</h4>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-2">Pilih Permintaan Bonus (Klik untuk menambah):</label>
                                <div class="flex flex-wrap gap-2">
                                    <button v-for="bonus in availableBonuses" :key="bonus" type="button" @click="toggleBonus(bonus)" :class="['px-3.5 py-2 rounded-xl text-xs font-bold transition-all border', selectedBonuses.includes(bonus) ? 'bg-indigo-600 text-white border-indigo-500 shadow-md shadow-indigo-600/30' : 'bg-slate-950 text-slate-400 border-slate-800 hover:border-slate-700']">
                                        {{ bonus }} {{ selectedBonuses.includes(bonus) ? '✓' : '+' }}
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1.5">Rincian Permintaan Khusus</label>
                                <textarea v-model="form.special_requests" rows="3" placeholder="Tuliskan permintaan khusus lainnya jika ada..." class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-2xl text-xs font-medium text-white placeholder-slate-600 focus:ring-2 focus:ring-indigo-500 transition-all"></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-400 mb-1.5">Catatan Tambahan untuk Developer</label>
                                <textarea v-model="form.notes" rows="2" placeholder="Catatan tambahan mengenai jadwal kunjungan, syarat, atau permohonan khusus..." class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-2xl text-xs font-medium text-white placeholder-slate-600 focus:ring-2 focus:ring-indigo-500 transition-all"></textarea>
                            </div>
                        </div>

                        <!-- SUBMIT BUTTON -->
                        <button type="submit" :disabled="form.processing" class="w-full py-4 bg-gradient-to-r from-indigo-600 via-blue-600 to-indigo-600 hover:from-indigo-500 hover:to-indigo-500 text-white font-black text-base rounded-2xl shadow-xl shadow-indigo-600/30 transition-all transform active:scale-[0.99] disabled:opacity-40 flex items-center justify-center gap-2">
                            <span>📋</span>
                            <span>{{ form.processing ? 'Sedang Mengirim Pengajuan...' : 'Kirim Pengajuan Negosiasi Resmi' }}</span>
                        </button>
                    </form>
                </div>

                <!-- FOOTER -->
                <footer class="text-center py-6 text-slate-500 text-xs">
                    <p>Sistem Pengajuan Negosiasi Resmi &copy; {{ new Date().getFullYear() }} {{ settings?.company_name || 'Homi Developer CRM' }}</p>
                </footer>
            </main>
        </div>
    </div>
</template>
