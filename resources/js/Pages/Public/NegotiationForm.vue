<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';

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
    custom_layout_options: nego.value.custom_layout_options || [],
    custom_layout_notes: nego.value.custom_layout_notes || '',
    client_signature: nego.value.client_signature || '',
    notes: nego.value.notes || '',
});

// Interactive Custom Layout Options List
const availableLayoutOptions = [
    { id: 'dapur', label: '🍳 Dapur Terbuka / Extensi Belakang', desc: 'Pemindahan atau perluasan area dapur ke lahan sisa belakang.' },
    { id: 'kamar', label: '🛏️ Penambahan Kamar Tidur / Ruang Kerja', desc: 'Penambahan sekat ruangan kamar tambahan.' },
    { id: 'kanopi', label: '🚗 Kanopi Carport & Stepping Stone', desc: 'Pemasangan atap kanopi carport & sikat carport.' },
    { id: 'km', label: '🚿 Ubah Tata Letak Kamar Mandi', desc: 'Penyesuaian posisi kran, shower, atau kloset duduk.' },
    { id: 'granit', label: '🧱 Upgrade Keramik ke Granit 60x60', desc: 'Penggantian lantai keramik standar dengan granit tile.' },
    { id: 'listrik', label: '⚡ Tambah Saklar/AC & Stopkontak', desc: 'Instalasi titik kelistrikan baru di ruangan tertentu.' },
    { id: 'fasad', label: '🎨 Modifikasi Cat & Ornamen Fasad', desc: 'Penyesuaian warna cat atau aksen ornamen tampak depan.' },
];

function toggleLayoutOption(label) {
    if (!Array.isArray(form.custom_layout_options)) {
        form.custom_layout_options = [];
    }
    const idx = form.custom_layout_options.indexOf(label);
    if (idx > -1) {
        form.custom_layout_options.splice(idx, 1);
    } else {
        form.custom_layout_options.push(label);
    }
}

function isLayoutOptionSelected(label) {
    return Array.isArray(form.custom_layout_options) && form.custom_layout_options.includes(label);
}

// Digital Signature Canvas Logic
const sigCanvas = ref(null);
const isDrawing = ref(false);
const hasSignature = ref(Boolean(nego.value.client_signature));

function startDrawing(e) {
    isDrawing.value = true;
    draw(e);
}

function stopDrawing() {
    if (isDrawing.value && sigCanvas.value) {
        isDrawing.value = false;
        const ctx = sigCanvas.value.getContext('2d');
        ctx.beginPath();
        form.client_signature = sigCanvas.value.toDataURL('image/png');
        hasSignature.value = true;
    }
}

function draw(e) {
    if (!isDrawing.value || !sigCanvas.value) return;
    const canvas = sigCanvas.value;
    const ctx = canvas.getContext('2d');
    const rect = canvas.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    const x = clientX - rect.left;
    const y = clientY - rect.top;

    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#0f172a';

    ctx.lineTo(x, y);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(x, y);
}

function clearSignature() {
    if (!sigCanvas.value) return;
    const ctx = sigCanvas.value.getContext('2d');
    ctx.clearRect(0, 0, sigCanvas.value.width, sigCanvas.value.height);
    form.client_signature = '';
    hasSignature.value = false;
}

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

// PDF Export Actions
function downloadPdf() {
    window.open(`/nego/${nego.value.token}/pdf?download=1`, '_blank');
}

function openPdfInline() {
    window.open(`/nego/${nego.value.token}/pdf`, '_blank');
}

function sharePdfWhatsApp() {
    const pdfUrl = `${window.location.origin}/nego/${nego.value.token}/pdf`;
    const msg = `Halo, berikut dokumen *Surat Hasil Pengajuan & Negosiasi Resmi* unit *${nego.value.unit?.code || ''}*:\n\n🔗 Ringkasan & Form Web: ${window.location.origin}/nego/${nego.value.token}\n📄 Download PDF Resmi: ${pdfUrl}\n\nTerima kasih.`;
    const phone = (nego.value.client_phone || '').replace(/[^0-9]/g, '');
    const waPhone = phone.startsWith('0') ? '62' + phone.substring(1) : phone;
    window.open(`https://wa.me/${waPhone}?text=${encodeURIComponent(msg)}`, '_blank');
}

// Agent computation
const assignedAgent = computed(() => {
    return nego.value.lead?.assigned_to || nego.value.lead?.assignedTo || nego.value.creator;
});

const agentCompany = computed(() => {
    return assignedAgent.value?.broker_company?.name || nego.value.lead?.broker_company?.name || null;
});

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
        const annualRate = 0.05;
        const monthlyRate = annualRate / 12;
        const emi = (loanPrincipal * monthlyRate * Math.pow(1 + monthlyRate, months)) / (Math.pow(1 + monthlyRate, months) - 1);
        return isNaN(emi) ? null : Math.round(emi);
    } else if (form.payment_scheme === 'cash_bertahap') {
        return Math.round(loanPrincipal / months);
    }
    return null;
});

const standardInclusions = [
    { title: 'Legalitas Resmi', desc: 'Sertifikat (SHM/HGB), PBG/IMB, & PBB Pecah' },
    { title: 'Spesifikasi Premium', desc: 'Struktur Beton Bertulang, Atap Baja Ringan' },
    { title: 'Fasilitas Terpasang', desc: 'Sambungan PLN (2200W) & Air Bersih' },
    { title: 'Garansi Bangunan', desc: 'Free Jaminan Pemeliharaan 3 Bulan Pertama' },
];

function formatCurrency(val) {
    if (!val) return 'Rp 0';
    return 'Rp ' + Number(val).toLocaleString('id-ID');
}
</script>

<template>
    <Head :title="`Form Negosiasi - ${negotiation.unit?.code || negotiation.unit?.number || 'Unit'}`" />

    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-blue-50/40 text-slate-800 font-sans selection:bg-blue-600 selection:text-white pb-16">

        <!-- TOP BAR HEADER -->
        <header class="border-b border-slate-200/80 bg-white/90 backdrop-blur-md sticky top-0 z-50 shadow-xs">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div v-if="settings?.company_logo" class="shrink-0 bg-slate-50 p-1.5 rounded-xl border border-slate-200">
                        <img :src="`/storage/${settings.company_logo}`" class="h-8 max-w-[130px] object-contain" />
                    </div>
                    <div v-else class="w-9 h-9 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-xl flex items-center justify-center text-white font-black text-sm shadow-md shrink-0">
                        H
                    </div>
                    <div>
                        <h1 class="text-sm font-black text-slate-900 tracking-tight">{{ settings?.company_name || 'Homi Developer' }}</h1>
                        <p class="text-[10px] font-bold text-slate-500">Portal Pengajuan Negosiasi Resmi</p>
                    </div>
                </div>

                <!-- PDF Export & Verified Badge -->
                <div class="flex items-center gap-2">
                    <button @click="openPdfInline" title="Cetak atau Lihat Dokumen PDF Pengajuan Resmi" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl flex items-center gap-1.5 shadow-sm transition-all">
                        <span>📄</span> PDF
                    </button>
                    <div class="hidden sm:flex items-center gap-1.5 px-3 py-1 bg-emerald-50 border border-emerald-200 rounded-full text-emerald-700 text-xs font-bold">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Resmi Konsumen
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-3xl mx-auto px-4 sm:px-6 pt-6 space-y-6">
            <!-- AGENT BANNER CARD -->
            <div v-if="assignedAgent" class="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 flex items-center justify-between gap-4 shadow-sm">
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 font-black text-lg shrink-0">
                        👤
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-black uppercase tracking-wider text-blue-600">Agent Pendamping Survey</span>
                            <span v-if="agentCompany" class="text-[10px] font-bold text-slate-500">({{ agentCompany }})</span>
                        </div>
                        <h2 class="text-sm font-black text-slate-900">{{ assignedAgent.name }}</h2>
                        <p class="text-xs text-slate-500">{{ assignedAgent.phone || assignedAgent.email || 'Siap mendampingi proses negosiasi Anda' }}</p>
                    </div>
                </div>
                <a v-if="assignedAgent.phone" :href="`https://wa.me/${assignedAgent.phone.replace(/[^0-9]/g, '')}?text=Halo%20${encodeURIComponent(assignedAgent.name)},%20saya%20sedang%20mengisi%20form%20negosiasi%20unit%20${encodeURIComponent(nego.unit?.code || nego.unit?.number || '')}`" target="_blank" class="shrink-0 px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 font-bold text-xs rounded-xl flex items-center gap-1.5 transition-all">
                    <span>💬</span> Chat WA
                </a>
            </div>

            <!-- UNIT PROPERTY CARD -->
            <div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-7 shadow-sm relative overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6 relative z-10">
                    <div class="space-y-3">
                        <div class="inline-flex items-center gap-2 px-3 py-1 bg-blue-50 border border-blue-100 rounded-full text-blue-700 text-xs font-bold">
                            🏢 {{ nego.project?.name || 'Proyek Hunian' }}
                        </div>
                        <div>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                                Unit {{ nego.unit?.code || nego.unit?.number || nego.unit?.block || 'Impian' }}
                            </h2>
                            <p class="text-xs sm:text-sm font-medium text-slate-500 mt-1">
                                Tipe: {{ nego.unit?.unit_type?.name || nego.unit?.type || 'Hunian Modern' }}
                            </p>
                        </div>

                        <!-- SPECS BADGES -->
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <div v-if="nego.unit?.unit_type?.building_area || nego.unit?.building_area" class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 font-semibold flex items-center gap-1">
                                🏠 LB: {{ nego.unit?.unit_type?.building_area || nego.unit?.building_area }} m²
                            </div>
                            <div v-if="nego.unit?.unit_type?.surface_area || nego.unit?.surface_area" class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 font-semibold flex items-center gap-1">
                                📐 LT: {{ nego.unit?.unit_type?.surface_area || nego.unit?.surface_area }} m²
                            </div>
                            <div v-if="nego.unit?.unit_type?.bedrooms" class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 font-semibold flex items-center gap-1">
                                🛏️ {{ nego.unit.unit_type.bedrooms }} KT
                            </div>
                            <div v-if="nego.unit?.unit_type?.bathrooms" class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 font-semibold flex items-center gap-1">
                                🚿 {{ nego.unit.unit_type.bathrooms }} KM
                            </div>
                        </div>
                    </div>

                    <!-- PRICE BOX -->
                    <div class="sm:text-right shrink-0 bg-gradient-to-br from-blue-50/60 to-indigo-50/40 border border-blue-100 rounded-2xl p-4 sm:p-5">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Harga Resmi Listing</p>
                        <p class="text-xl sm:text-2xl font-black text-blue-700 font-mono mt-1">
                            {{ formatCurrency(nego.unit_listed_price) }}
                        </p>
                        <p class="text-[10px] text-slate-400 mt-1">*Harga dasar belum termasuk diskon negosiasi</p>
                    </div>
                </div>
            </div>

            <!-- FASILITAS STANDAR TERMASUK -->
            <div class="bg-gradient-to-br from-emerald-50/40 via-white to-slate-50 border border-emerald-200/80 rounded-3xl p-6 sm:p-7 shadow-sm space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-lg font-black shrink-0">
                        🎁
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider">Fasilitas Standar Terdaftar dari Developer</h3>
                        <p class="text-xs text-slate-500">Berikut adalah fasilitas & kelengkapan yang sudah termasuk pada harga normal:</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div v-for="(inc, idx) in standardInclusions" :key="idx" class="p-3.5 bg-white border border-slate-200/80 rounded-2xl flex items-start gap-3">
                        <span class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center font-black text-[10px] shrink-0 mt-0.5">✓</span>
                        <div>
                            <p class="text-xs font-black text-slate-900">{{ inc.title }}</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">{{ inc.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EXPIRED STATE -->
            <div v-if="isExpired && nego.status !== 'approved'" class="bg-rose-50 border border-rose-200 rounded-3xl p-8 text-center">
                <div class="w-14 h-14 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-3">
                    ⏰
                </div>
                <h3 class="text-base font-black text-rose-900">Form Negosiasi Kedaluwarsa</h3>
                <p class="text-xs text-rose-600 mt-1 max-w-md mx-auto">
                    Batas waktu pengajuan link ini telah berakhir. Silakan minta agent Anda untuk mengirimkan tautan pengajuan baru.
                </p>
            </div>

            <!-- SUCCESS STATE AFTER SUBMIT -->
            <div v-else-if="flash.success && !isCounterOffer" class="bg-emerald-50 border border-emerald-200 rounded-3xl p-8 text-center space-y-4">
                <div class="w-14 h-14 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-3xl mx-auto">
                    🎉
                </div>
                <h3 class="text-lg font-black text-emerald-900">{{ flash.success }}</h3>
                <p class="text-xs text-emerald-700 max-w-md mx-auto">
                    Pengajuan negosiasi Anda telah resmi diterima oleh sistem CRM Developer. Tim kami akan meninjau dan memberikan tanggapan secepatnya.
                </p>

                <!-- DOWNLOAD & SHARE ACTIONS -->
                <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                    <button @click="downloadPdf" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center gap-2">
                        <span>📄</span> Download Dokumen PDF
                    </button>
                    <button @click="sharePdfWhatsApp" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center gap-2">
                        <span>💬</span> Kirim Dokumen PDF via WA
                    </button>
                </div>
            </div>

            <!-- SUBMITTED / IN REVIEW STATE (STEPPER & SUMMARY) -->
            <div v-else-if="isSubmitted && !isCounterOffer" class="space-y-6">
                <!-- STATUS STEPPER -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-wider text-slate-500 mb-6 text-center">Status Alur Pengajuan Anda</p>
                    <div class="grid grid-cols-3 gap-2 relative">
                        <div class="text-center space-y-2">
                            <div class="w-10 h-10 rounded-full bg-emerald-100 border border-emerald-400 text-emerald-700 font-black text-sm flex items-center justify-center mx-auto">
                                ✓
                            </div>
                            <p class="text-xs font-bold text-emerald-700">1. Terkirim</p>
                        </div>
                        <div class="text-center space-y-2">
                            <div :class="['w-10 h-10 rounded-full flex items-center justify-center mx-auto font-black text-sm border', nego.status === 'approved' || nego.status === 'rejected' ? 'bg-emerald-100 border-emerald-400 text-emerald-700' : 'bg-amber-100 border-amber-400 text-amber-700 animate-pulse']">
                                2
                            </div>
                            <p :class="['text-xs font-bold', nego.status === 'approved' || nego.status === 'rejected' ? 'text-emerald-700' : 'text-amber-700']">2. Peninjauan</p>
                        </div>
                        <div class="text-center space-y-2">
                            <div :class="['w-10 h-10 rounded-full flex items-center justify-center mx-auto font-black text-sm border', nego.status === 'approved' ? 'bg-emerald-100 border-emerald-400 text-emerald-700' : nego.status === 'rejected' ? 'bg-rose-100 border-rose-400 text-rose-700' : 'bg-slate-100 border-slate-300 text-slate-400']">
                                3
                            </div>
                            <p :class="['text-xs font-bold', nego.status === 'approved' ? 'text-emerald-700' : nego.status === 'rejected' ? 'text-rose-700' : 'text-slate-400']">3. Keputusan</p>
                        </div>
                    </div>
                </div>

                <!-- SUMMARY BOX -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Ringkasan Pengajuan Anda</h3>
                            <p class="text-[10px] text-slate-500 font-mono font-bold mt-0.5">No. Pengajuan: {{ nego.negotiation_number || `NEGO-${nego.token}` }}</p>
                        </div>
                        <span :class="['px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider', nego.status === 'approved' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : nego.status === 'rejected' ? 'bg-rose-100 text-rose-800 border border-rose-300' : 'bg-amber-100 text-amber-800 border border-amber-300']">
                            {{ nego.status === 'approved' ? 'Disetujui' : nego.status === 'rejected' ? 'Ditolak' : 'Sedang Ditinjau' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100 flex justify-between items-center">
                            <span class="text-slate-500 font-medium">Harga Pengajuan:</span>
                            <span class="font-black text-emerald-700 font-mono text-sm">{{ formatCurrency(nego.offered_price) }}</span>
                        </div>
                        <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100 flex justify-between items-center">
                            <span class="text-slate-500 font-medium">Skema Pembayaran:</span>
                            <span class="font-bold text-slate-800 uppercase">{{ { cash_keras: 'Cash Keras', cash_bertahap: 'Cash Bertahap', kpr: 'KPR Bank' }[nego.payment_scheme] || '-' }}</span>
                        </div>
                        <div v-if="nego.dp_amount" class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100 flex justify-between items-center">
                            <span class="text-slate-500 font-medium">Rencana DP:</span>
                            <span class="font-bold text-slate-800 font-mono">{{ formatCurrency(nego.dp_amount) }}</span>
                        </div>
                        <div v-if="nego.installment_months" class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100 flex justify-between items-center">
                            <span class="text-slate-500 font-medium">Tenor Cicilan:</span>
                            <span class="font-bold text-slate-800">{{ nego.installment_months }} Bulan</span>
                        </div>
                    </div>

                    <!-- CUSTOM LAYOUT SUMMARY IN SUBMITTED VIEW -->
                    <div v-if="nego.custom_layout_options && nego.custom_layout_options.length > 0" class="p-4 bg-blue-50/70 border border-blue-200 rounded-2xl space-y-2">
                        <p class="text-[10px] font-black uppercase text-blue-700">🏗️ Modifikasi Custom Layout yang Diajukan:</p>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="(opt, idx) in nego.custom_layout_options" :key="idx" class="px-2.5 py-1 bg-white border border-blue-200 rounded-lg text-xs font-bold text-slate-800">
                                {{ opt }}
                            </span>
                        </div>
                        <p v-if="nego.custom_layout_notes" class="text-xs text-slate-700 italic pt-1 border-t border-blue-100">
                            "{{ nego.custom_layout_notes }}"
                        </p>
                    </div>

                    <div v-if="nego.special_requests" class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-1">
                        <p class="text-[10px] font-black uppercase text-slate-500">Permintaan Khusus Tambahan:</p>
                        <p class="text-xs text-slate-800 whitespace-pre-line font-medium">{{ nego.special_requests }}</p>
                    </div>

                    <!-- ACTION PDF & SHARE BUTTONS -->
                    <div class="flex flex-wrap gap-3 pt-3 border-t border-slate-100">
                        <button @click="downloadPdf" class="flex-1 py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-2xl shadow-md transition-all flex items-center justify-center gap-2">
                            <span>📄</span> Download Proposal PDF
                        </button>
                        <button @click="sharePdfWhatsApp" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-2xl shadow-md transition-all flex items-center justify-center gap-2">
                            <span>💬</span> Bagikan PDF via WA
                        </button>
                    </div>
                </div>
            </div>

            <!-- COUNTER OFFER STATE INTERFACE -->
            <div v-else-if="isCounterOffer" class="bg-gradient-to-b from-purple-50 via-white to-white border border-purple-200 rounded-3xl p-6 sm:p-7 shadow-sm space-y-6">
                <div class="text-center space-y-2">
                    <div class="w-12 h-12 bg-purple-100 text-purple-700 rounded-2xl flex items-center justify-center text-2xl mx-auto border border-purple-200">
                        🔄
                    </div>
                    <h3 class="text-lg sm:text-xl font-black text-slate-900">Penawaran Balik (Counter Offer) Developer</h3>
                    <p class="text-xs text-purple-700 max-w-md mx-auto">
                        Developer telah meninjau pengajuan Anda dan memberikan penawaran harga resmi terbaik.
                    </p>
                </div>

                <!-- COMPARISON BOXES -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl text-center">
                        <p class="text-[10px] font-black text-slate-400 uppercase">Pengajuan Anda</p>
                        <p class="text-base font-black text-slate-400 line-through font-mono mt-1">{{ formatCurrency(nego.offered_price) }}</p>
                    </div>
                    <div class="p-4 bg-purple-100/70 border border-purple-300 rounded-2xl text-center shadow-xs">
                        <p class="text-[10px] font-black text-purple-700 uppercase">Penawaran Developer</p>
                        <p class="text-lg font-black text-purple-900 font-mono mt-1">{{ formatCurrency(nego.counter_price) }}</p>
                    </div>
                </div>

                <div v-if="nego.counter_notes" class="p-4 bg-purple-50 border border-purple-200 rounded-2xl text-xs text-purple-900 italic">
                    💬 <span class="font-bold uppercase tracking-wider text-purple-700">Pesan Developer:</span> {{ nego.counter_notes }}
                </div>

                <!-- ACTION BUTTONS -->
                <div class="space-y-3 pt-2">
                    <button @click="respondToCounter('accepted')" :disabled="counterForm.processing" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm rounded-2xl shadow-md transition-all disabled:opacity-40 flex items-center justify-center gap-2">
                        <span>✅</span> Terima Penawaran {{ formatCurrency(nego.counter_price) }}
                    </button>

                    <div class="p-5 bg-white border border-slate-200 rounded-2xl space-y-3 shadow-xs">
                        <p class="text-xs font-black text-slate-700 uppercase tracking-wider">Atau Ajukan Angka Revisi Terakhir:</p>
                        <div class="space-y-3">
                            <input v-model="counterForm.offered_price" type="number" min="1" placeholder="Nominal revisi Anda (Rp)..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-purple-500" />
                            <textarea v-model="counterForm.notes" rows="2" placeholder="Catatan tambahan revisi..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-purple-500"></textarea>
                            <button @click="submitRevised" :disabled="counterForm.processing || !counterForm.offered_price" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-xl shadow-md transition-all disabled:opacity-40">
                                🔄 Kirim Penawaran Revisi
                            </button>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button @click="downloadPdf" class="flex-1 py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl transition-all flex items-center justify-center gap-1.5">
                            <span>📄</span> Unduh PDF
                        </button>
                        <button @click="respondToCounter('rejected')" :disabled="counterForm.processing" class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs rounded-xl transition-all disabled:opacity-40">
                            Tolak Penawaran
                        </button>
                    </div>
                </div>
            </div>

            <!-- DRAFT STATE - FORM INPUT -->
            <div v-else-if="isDraft" class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-8 shadow-sm space-y-8">
                <div>
                    <h3 class="text-xl font-black text-slate-900 tracking-tight">Form Pengajuan Harga & Ketentuan</h3>
                    <p class="text-xs text-slate-500 mt-1">Isi data pribadi, rencana pembayaran, dan pengajuan custom layout denah di bawah ini.</p>
                </div>

                <form @submit.prevent="submitForm" class="space-y-7">
                    <!-- SECTION 1: PERSONAL DATA -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 pb-2 border-b border-slate-100">
                            <span class="w-6 h-6 rounded-lg bg-blue-50 border border-blue-200 text-blue-600 flex items-center justify-center font-black text-xs">1</span>
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-900">Data Identitas Pembeli</h4>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Lengkap Pembeli <span class="text-rose-500">*</span></label>
                            <input v-model="form.client_name" type="text" required placeholder="Masukkan nama sesuai KTP" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all" />
                            <p v-if="form.errors.client_name" class="text-xs text-rose-500 mt-1">{{ form.errors.client_name }}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">No. WhatsApp / HP Active <span class="text-rose-500">*</span></label>
                                <input v-model="form.client_phone" type="tel" required placeholder="08xxxxxxxxxx" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Alamat Email (Opsional)</label>
                                <input v-model="form.client_email" type="email" placeholder="nama@email.com" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm font-bold text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all" />
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: NEGOTIATION OFFER -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 pb-2 border-b border-slate-100">
                            <span class="w-6 h-6 rounded-lg bg-blue-50 border border-blue-200 text-blue-600 flex items-center justify-center font-black text-xs">2</span>
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-900">Pengajuan Harga & Skema Pembayaran</h4>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Harga yang Anda Ajukan (Rp) <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-black text-slate-400">Rp</span>
                                <input v-model="form.offered_price" type="number" required min="1" placeholder="Masukkan harga penawaran Anda..." class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-base font-black text-emerald-700 font-mono placeholder-slate-400 focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all" />
                            </div>
                            <p v-if="form.errors.offered_price" class="text-xs text-rose-500 mt-1">{{ form.errors.offered_price }}</p>

                            <div v-if="priceDifference" class="mt-2.5 px-4 py-2.5 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between text-xs">
                                <span class="text-emerald-800 font-bold">✨ Potongan Penawaran:</span>
                                <span class="font-black text-emerald-700 font-mono">
                                    -{{ formatCurrency(priceDifference.diff) }} (Hemat {{ priceDifference.pct }}%)
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-2">Pilih Skema Pembayaran <span class="text-rose-500">*</span></label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div @click="form.payment_scheme = 'kpr'" :class="['p-4 rounded-2xl border cursor-pointer transition-all space-y-1.5', form.payment_scheme === 'kpr' ? 'bg-blue-50/80 border-blue-500 ring-2 ring-blue-500/20' : 'bg-slate-50 border-slate-200 hover:border-slate-300']">
                                    <div class="text-xl">🏦</div>
                                    <p class="text-xs font-black text-slate-900">KPR Bank</p>
                                    <p class="text-[10px] text-slate-500 leading-snug">Fasilitas pembiayaan kredit melalui bank partner.</p>
                                </div>

                                <div @click="form.payment_scheme = 'cash_keras'" :class="['p-4 rounded-2xl border cursor-pointer transition-all space-y-1.5', form.payment_scheme === 'cash_keras' ? 'bg-emerald-50/80 border-emerald-500 ring-2 ring-emerald-500/20' : 'bg-slate-50 border-slate-200 hover:border-slate-300']">
                                    <div class="text-xl">💰</div>
                                    <p class="text-xs font-black text-slate-900">Cash Keras</p>
                                    <p class="text-[10px] text-slate-500 leading-snug">Pembayaran pelunasan tunai dalam 30 hari.</p>
                                </div>

                                <div @click="form.payment_scheme = 'cash_bertahap'" :class="['p-4 rounded-2xl border cursor-pointer transition-all space-y-1.5', form.payment_scheme === 'cash_bertahap' ? 'bg-purple-50/80 border-purple-500 ring-2 ring-purple-500/20' : 'bg-slate-50 border-slate-200 hover:border-slate-300']">
                                    <div class="text-xl">📅</div>
                                    <p class="text-xs font-black text-slate-900">Cash Bertahap</p>
                                    <p class="text-[10px] text-slate-500 leading-snug">Cicilan bertahap langsung ke developer.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: CATATAN PENGAJUAN & CUSTOM LAYOUT -->
                    <div class="space-y-6">
                        <div class="flex items-center gap-2 pb-2 border-b border-slate-100">
                            <span class="w-6 h-6 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-600 flex items-center justify-center font-black text-xs">3</span>
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-900">Catatan Pengajuan & Custom Layout</h4>
                        </div>

                        <!-- 3.1 OPSI MODIFIKASI DENAH -->
                        <div class="space-y-3">
                            <h5 class="text-xs font-black text-slate-800 flex items-center gap-1.5">
                                <span class="px-2 py-0.5 bg-indigo-100 text-indigo-800 rounded font-mono text-[10px]">3.1</span>
                                Opsi Modifikasi Denah / Custom Layout
                            </h5>
                            <p class="text-xs text-slate-500">Pilih opsi modifikasi denah yang Anda harapkan (centang pada opsi di bawah):</p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div v-for="opt in availableLayoutOptions" :key="opt.id" @click="toggleLayoutOption(opt.label)" :class="['p-3.5 rounded-2xl border cursor-pointer transition-all flex items-start gap-3 select-none', isLayoutOptionSelected(opt.label) ? 'bg-indigo-50/90 border-indigo-500 ring-2 ring-indigo-500/20' : 'bg-slate-50 border-slate-200 hover:border-slate-300']">
                                    <div :class="['w-5 h-5 rounded-lg border flex items-center justify-center font-black text-xs shrink-0 mt-0.5 transition-all', isLayoutOptionSelected(opt.label) ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-slate-300 bg-white']">
                                        ✓
                                    </div>
                                    <div>
                                        <p class="text-xs font-black text-slate-900">{{ opt.label }}</p>
                                        <p class="text-[10px] text-slate-500 mt-0.5 leading-snug">{{ opt.desc }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3.2 DETAIL CATATAN PENYESUAIAN DENAH -->
                        <div class="space-y-2 pt-2 border-t border-slate-100">
                            <h5 class="text-xs font-black text-slate-800 flex items-center gap-1.5">
                                <span class="px-2 py-0.5 bg-indigo-100 text-indigo-800 rounded font-mono text-[10px]">3.2</span>
                                Detail Catatan Penyesuaian Denah & Tata Letak
                            </h5>
                            <textarea v-model="form.custom_layout_notes" rows="3" placeholder="Tuliskan spesifikasi penyesuaian denah yang Anda harapkan, contoh:&#10;Dapur dipindah ke halaman belakang sisa 2.5m, kamar mandi utama ingin kloset duduk merek Toto, keramik diganti granit 60x60..." class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all"></textarea>
                        </div>

                        <!-- 3.3 PERMINTAAN KHUSUS & TANDA TANGAN DIGITAL -->
                        <div class="space-y-4 pt-2 border-t border-slate-100">
                            <h5 class="text-xs font-black text-slate-800 flex items-center gap-1.5">
                                <span class="px-2 py-0.5 bg-indigo-100 text-indigo-800 rounded font-mono text-[10px]">3.3</span>
                                Permintaan Khusus, Tanda Tangan Digital & Catatan Tambahan
                            </h5>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Permintaan Khusus Tambahan</label>
                                <textarea v-model="form.special_requests" rows="3" placeholder="Tuliskan permintaan khusus Anda di luar fasilitas standar..." class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"></textarea>
                            </div>

                            <!-- E-SIGN DIGITAL CANVAS -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-bold text-slate-700">Tanda Tangan Digital Pembeli (Opsional)</label>
                                    <button type="button" @click="clearSignature" class="text-[10px] font-bold text-rose-500 hover:underline">Hapus Tanda Tangan</button>
                                </div>
                                <div class="border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50 p-2 text-center relative overflow-hidden">
                                    <canvas ref="sigCanvas" width="400" height="120" @mousedown="startDrawing" @mousemove="draw" @mouseup="stopDrawing" @mouseleave="stopDrawing" @touchstart.prevent="startDrawing" @touchmove.prevent="draw" @touchend.prevent="stopDrawing" class="w-full h-28 bg-white rounded-xl border border-slate-200 touch-none cursor-crosshair"></canvas>
                                    <p v-if="!hasSignature" class="text-[10px] text-slate-400 pointer-events-none absolute inset-0 flex items-center justify-center">
                                        ✍️ Goreskan tanda tangan Anda di dalam kotak ini
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Catatan Tambahan untuk Developer</label>
                                <textarea v-model="form.notes" rows="2" placeholder="Catatan tambahan mengenai permohonan kunjungan atau syarat khusus..." class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-xs font-medium text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SUBMIT BUTTON -->
                    <button type="submit" :disabled="form.processing" class="w-full py-4 bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-600 hover:from-blue-700 hover:to-indigo-700 text-white font-black text-base rounded-2xl shadow-lg shadow-blue-500/25 transition-all transform active:scale-[0.99] disabled:opacity-40 flex items-center justify-center gap-2">
                        <span>📋</span>
                        <span>{{ form.processing ? 'Sedang Mengirim Pengajuan...' : 'Kirim Pengajuan Negosiasi Resmi' }}</span>
                    </button>
                </form>
            </div>

            <!-- FOOTER -->
            <footer class="text-center py-6 text-slate-400 text-xs space-y-1">
                <p>Sistem Pengajuan Negosiasi Resmi &copy; {{ new Date().getFullYear() }} {{ settings?.company_name || 'Homi Developer CRM' }}</p>
                <p class="text-[10px] text-slate-350">Dokumen PDF resmi dapat langsung diunduh setelah pengajuan dikirim.</p>
            </footer>
        </main>
    </div>
</template>
