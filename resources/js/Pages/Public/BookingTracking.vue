<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, nextTick } from 'vue';

const props = defineProps({
    booking: Object
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value || 0);
};

const totalPaid = computed(() => {
    return (props.booking.transactions || []).reduce((sum, tx) => sum + Number(tx.amount || 0), 0);
});

const progressPercentage = computed(() => {
    const price = props.booking.final_price || props.booking.unit_price || 1;
    return Math.min(100, Math.round((totalPaid.value / price) * 100));
});

const kprSteps = [
    { key: 'application', label: 'Pengajuan' },
    { key: 'bi_checking', label: 'BI Checking' },
    { key: 'interview', label: 'Wawancara' },
    { key: 'appraisal', label: 'Appraisal' },
    { key: 'sp3k', label: 'SP3K' },
    { key: 'akad', label: 'Akad KPR' },
    { key: 'cair', label: 'Pencairan' },
];

const currentKprStepIndex = computed(() => {
    const status = props.booking.kpr_status || 'application';
    const idx = kprSteps.findIndex(s => s.key === status);
    return idx >= 0 ? idx : 0;
});

const openSalesWa = () => {
    const agent = props.booking.booked_by || props.booking.bookedBy;
    const phone = agent?.phone ? agent.phone.replace(/[^0-9]/g, '') : '';
    const formattedPhone = phone.startsWith('0') ? '62' + phone.slice(1) : phone;
    const msg = `Halo ${agent?.name || 'Sales'}, saya ${props.booking.lead?.name || 'Konsumen'} (Unit ${props.booking.unit?.block}${props.booking.unit?.number}). Saya mau bertanya info terkait pesanan saya.`;
    
    if (formattedPhone) {
        window.open(`https://wa.me/${formattedPhone}?text=${encodeURIComponent(msg)}`, '_blank');
    } else {
        alert('Nomor WhatsApp Sales Agent tidak tersedia.');
    }
};

// Signature feature
const showSignModal = ref(false);
const signRole = ref('customer'); // 'customer' or 'agent'
const signerName = ref('');
const isSubmitting = ref(false);
const sigCanvas = ref(null);
const isDrawing = ref(false);
const hasDrawn = ref(false);
const signSuccessMsg = ref('');

const getCoordinates = (e) => {
    const canvas = sigCanvas.value;
    if (!canvas) return { x: 0, y: 0 };
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    
    let clientX = e.clientX;
    let clientY = e.clientY;
    if (e.touches && e.touches.length > 0) {
        clientX = e.touches[0].clientX;
        clientY = e.touches[0].clientY;
    }
    return {
        x: (clientX - rect.left) * scaleX,
        y: (clientY - rect.top) * scaleY
    };
};

const startDrawing = (e) => {
    isDrawing.value = true;
    const { x, y } = getCoordinates(e);
    const ctx = sigCanvas.value.getContext('2d');
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#0f172a';
    ctx.beginPath();
    ctx.moveTo(x, y);
};

const draw = (e) => {
    if (!isDrawing.value || !sigCanvas.value) return;
    const { x, y } = getCoordinates(e);
    const ctx = sigCanvas.value.getContext('2d');
    ctx.lineTo(x, y);
    ctx.stroke();
    hasDrawn.value = true;
};

const stopDrawing = () => {
    if (isDrawing.value && sigCanvas.value) {
        isDrawing.value = false;
        const ctx = sigCanvas.value.getContext('2d');
        ctx.closePath();
    }
};

const clearSignature = () => {
    if (sigCanvas.value) {
        const ctx = sigCanvas.value.getContext('2d');
        ctx.clearRect(0, 0, sigCanvas.value.width, sigCanvas.value.height);
        hasDrawn.value = false;
    }
};

const openSignModal = (role) => {
    signRole.value = role;
    if (role === 'customer') {
        signerName.value = props.booking.sig4_name || props.booking.lead?.name || '';
    } else {
        const agent = props.booking.booked_by || props.booking.bookedBy;
        signerName.value = props.booking.sig3_name || agent?.name || 'Sales Agent';
    }
    showSignModal.value = true;
    hasDrawn.value = false;
    nextTick(() => {
        clearSignature();
    });
};

const closeSignModal = () => {
    showSignModal.value = false;
    hasDrawn.value = false;
};

const submitSignature = () => {
    if (!hasDrawn.value || !sigCanvas.value || !signerName.value) return;
    const signatureDataUrl = sigCanvas.value.toDataURL('image/png');
    
    isSubmitting.value = true;
    router.post(`/track/${props.booking.tracking_token}/sign`, {
        role: signRole.value,
        signature: signatureDataUrl,
        signer_name: signerName.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            showSignModal.value = false;
            signSuccessMsg.value = `Tanda tangan ${signRole.value === 'customer' ? 'Konsumen' : 'Sales Agent'} berhasil disimpan ke dokumen SPR!`;
            setTimeout(() => {
                signSuccessMsg.value = '';
            }, 5000);
        },
        onError: (err) => {
            isSubmitting.value = false;
            alert(err?.message || 'Gagal menyimpan tanda tangan.');
        }
    });
};

const resolveSigUrl = (path) => {
    if (!path) return '';
    if (path.startsWith('data:image')) return path;
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    return path.startsWith('/') ? path : `/${path}`;
};

const formatDate = (rawDate) => {
    if (!rawDate) return '';
    try {
        return new Date(rawDate).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    } catch (e) {
        return rawDate;
    }
};
</script>

<template>
    <Head :title="`Pelacakan Pesanan Unit ${booking.unit?.block}${booking.unit?.number} - ${booking.lead?.name}`" />

    <div class="min-h-screen bg-slate-100 font-sans text-slate-900 pb-28">
        <!-- Top Sticky Header -->
        <div class="bg-slate-900 text-white sticky top-0 z-20 shadow-md">
            <div class="max-w-md mx-auto px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-gradient-to-tr from-blue-600 to-indigo-500 rounded-xl flex items-center justify-center font-black text-white text-sm shadow-sm">
                        H
                    </div>
                    <div>
                        <div class="text-xs font-black tracking-tight text-white">HOMI Customer Portal</div>
                        <div class="text-[10px] text-slate-400 font-semibold leading-none mt-0.5">
                            {{ booking.unit?.project?.name || 'Proyek Properti' }}
                        </div>
                    </div>
                </div>

                <div class="shrink-0">
                    <span v-if="booking.status === 'approved' || booking.status === 'completed'" class="px-2.5 py-1 bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 text-[10px] font-extrabold rounded-full">
                        🟢 {{ booking.status === 'completed' ? 'Selesai / Lunas' : 'Booking Disetujui' }}
                    </span>
                    <span v-else class="px-2.5 py-1 bg-amber-500/20 text-amber-300 border border-amber-500/40 text-[10px] font-extrabold rounded-full">
                        ⏳ {{ booking.status }}
                    </span>
                </div>
            </div>
        </div>

        <div class="max-w-md mx-auto px-4 pt-4 space-y-4">
            <!-- Toast Feedback Banner -->
            <div v-if="signSuccessMsg" class="p-3 bg-emerald-600 text-white text-xs font-bold rounded-2xl shadow-lg flex items-center justify-between animate-in fade-in slide-in-from-top duration-300">
                <div class="flex items-center gap-2">
                    <span>✅</span>
                    <span>{{ signSuccessMsg }}</span>
                </div>
                <button @click="signSuccessMsg = ''" class="text-white/80 hover:text-white font-bold">&times;</button>
            </div>

            <!-- Compact Greeting & Hero Summary Card -->
            <div class="bg-gradient-to-br from-indigo-900 via-slate-900 to-blue-950 rounded-3xl p-5 text-white shadow-xl relative overflow-hidden">
                <div class="relative z-10 space-y-3">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-blue-300">KONSUMEN TERDAFTAR</span>
                            <h1 class="text-xl font-black text-white mt-0.5">Halo, {{ booking.lead?.name || 'Konsumen' }} 👋</h1>
                        </div>
                        <div class="text-right">
                            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider block">NO. SPK / SPR</span>
                            <span class="text-xs font-mono font-bold text-amber-300">{{ booking.spk_number }}</span>
                        </div>
                    </div>

                    <!-- Unit Info Box -->
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl p-3.5 border border-white/10 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-500/20 text-blue-300 rounded-xl flex items-center justify-center text-lg shrink-0">
                                🏡
                            </div>
                            <div>
                                <div class="text-xs font-black text-white">Blok {{ booking.unit?.block }} No. {{ booking.unit?.number }}</div>
                                <div class="text-[10px] text-slate-300 font-medium">Tipe {{ booking.unit?.unit_type?.name || '-' }} • {{ booking.payment_scheme?.toUpperCase() }}</div>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-[9px] text-slate-400 uppercase font-bold">Harga Net</div>
                            <div class="text-xs font-black text-white font-mono">{{ formatCurrency(booking.final_price || booking.unit_price) }}</div>
                        </div>
                    </div>

                    <!-- Payment Progress Bar -->
                    <div class="space-y-1.5 pt-1">
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-[11px] text-slate-300 font-medium">Total Masuk</span>
                            <span class="font-black text-emerald-400 font-mono">{{ formatCurrency(totalPaid) }}</span>
                        </div>
                        <div class="w-full bg-white/10 h-2.5 rounded-full overflow-hidden p-0.5">
                            <div class="h-full bg-gradient-to-r from-emerald-400 to-teal-300 rounded-full transition-all duration-700" :style="{ width: progressPercentage + '%' }"></div>
                        </div>
                        <div class="flex justify-between text-[10px] font-bold text-slate-400">
                            <span>Sisa: {{ formatCurrency(Math.max(0, (booking.final_price || booking.unit_price) - totalPaid)) }}</span>
                            <span class="text-emerald-400 font-black">{{ progressPercentage }}% Lunas</span>
                        </div>
                    </div>
                </div>

                <!-- Abstract Decorative Circle -->
                <div class="absolute -right-8 -bottom-8 w-32 h-32 bg-blue-500/10 rounded-full blur-2xl pointer-events-none"></div>
            </div>

            <!-- KPR Progress Stepper (Compact Card) -->
            <div v-if="booking.payment_scheme === 'kpr'" class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-base">🏦</span>
                        <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider">Progres KPR {{ booking.kpr_bank_name ? ('- ' + booking.kpr_bank_name) : '' }}</h2>
                    </div>
                    <span class="px-2 py-0.5 bg-blue-50 text-blue-700 font-extrabold text-[10px] rounded-md uppercase">
                        {{ kprSteps[currentKprStepIndex]?.label || booking.kpr_status }}
                    </span>
                </div>

                <!-- Progress Pills -->
                <div class="grid grid-cols-7 gap-1 mb-2">
                    <div 
                        v-for="(st, i) in kprSteps" 
                        :key="st.key"
                        class="h-1.5 rounded-full transition-all duration-300"
                        :class="i <= currentKprStepIndex ? 'bg-blue-600' : 'bg-slate-100'"
                        :title="st.label"
                    ></div>
                </div>

                <div class="flex justify-between text-[9px] text-slate-400 font-bold px-0.5">
                    <span>Pengajuan</span>
                    <span>Akad KPR</span>
                    <span>Pencairan</span>
                </div>
            </div>

            <!-- Payment Schedules & History (Compact List) -->
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200 space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                        <span>📋</span> <span>Jadwal & Riwayat Pembayaran</span>
                    </h2>
                    <span class="text-[10px] font-bold text-slate-400">{{ booking.payment_schedules?.length || 0 }} Tahapan</span>
                </div>

                <div class="space-y-2">
                    <div 
                        v-for="(sched, idx) in booking.payment_schedules" 
                        :key="sched.id"
                        class="flex items-center justify-between p-2.5 rounded-xl border transition-all"
                        :class="sched.status === 'paid' ? 'bg-emerald-50/50 border-emerald-200' : 'bg-slate-50/50 border-slate-100'"
                    >
                        <div class="flex items-center gap-3">
                            <div 
                                class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-black shrink-0"
                                :class="sched.status === 'paid' ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-600'"
                            >
                                {{ sched.status === 'paid' ? '✓' : (idx + 1) }}
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-900">{{ sched.label }}</div>
                                <div class="text-[10px] text-slate-500">
                                    <span v-if="sched.status === 'paid'" class="text-emerald-700 font-semibold">Telah Dibayar</span>
                                    <span v-else>Jatuh Tempo: {{ new Date(sched.due_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <div class="text-xs font-black font-mono text-slate-900">{{ formatCurrency(sched.amount) }}</div>
                            <div>
                                <span v-if="sched.status === 'paid'" class="px-2 py-0.5 bg-emerald-100 text-emerald-800 text-[9px] font-black rounded-md uppercase">LUNAS</span>
                                <span v-else class="px-2 py-0.5 bg-amber-100 text-amber-800 text-[9px] font-black rounded-md uppercase">PENDING</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DIGITAL E-SIGNATURE SECTION (SPR DOCUMENT SIGNING) -->
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200 space-y-3.5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                        <span>✍️</span> <span>Tanda Tangan Digital Dokumen SPR</span>
                    </h2>
                    <span class="px-2 py-0.5 bg-blue-50 text-blue-700 font-black text-[9px] rounded-md uppercase tracking-wider">
                        E-Sign Resmi
                    </span>
                </div>

                <p class="text-[11px] text-slate-500 leading-relaxed">
                    Agent dan konsumen dapat menandatangani dokumen Surat Pemesanan Rumah (SPR) langsung di sini. Tanda tangan akan otomatis tercetak pada dokumen PDF resmi.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <!-- 1. Customer Signature Card -->
                    <div 
                        class="border rounded-2xl p-3.5 flex flex-col justify-between transition-all"
                        :class="booking.sig4_image ? 'bg-emerald-50/40 border-emerald-300' : 'bg-slate-50/60 border-slate-200'"
                    >
                        <div>
                            <div class="flex items-center justify-between gap-1 mb-1.5">
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">1. Konsumen / Pembeli</span>
                                <span v-if="booking.sig4_image" class="px-2 py-0.5 bg-emerald-100 text-emerald-800 text-[9px] font-black rounded-md flex items-center gap-1">
                                    <span>✓</span> <span>SUDAH TTD</span>
                                </span>
                                <span v-else class="px-2 py-0.5 bg-amber-100 text-amber-800 text-[9px] font-black rounded-md">
                                    BELUM TTD
                                </span>
                            </div>
                            <div class="text-xs font-black text-slate-900 truncate">
                                {{ booking.sig4_name || booking.lead?.name || 'Konsumen' }}
                            </div>
                            <div class="text-[10px] text-slate-400 mt-0.5">
                                <span v-if="booking.customer_signed_at">Ditandatangani: {{ formatDate(booking.customer_signed_at) }}</span>
                                <span v-else>Menunggu tanda tangan pembeli</span>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div v-if="booking.sig4_image" class="mb-2 p-1.5 bg-white rounded-xl border border-emerald-200 flex items-center justify-center shadow-inner">
                                <img :src="resolveSigUrl(booking.sig4_image)" alt="TTD Konsumen" class="h-14 max-w-full object-contain" />
                            </div>
                            <button 
                                @click="openSignModal('customer')"
                                class="w-full py-2.5 px-3 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 active:scale-95 shadow-sm"
                                :class="booking.sig4_image ? 'bg-slate-100 hover:bg-slate-200 text-slate-700' : 'bg-blue-600 hover:bg-blue-700 text-white'"
                            >
                                <span>✍️</span>
                                <span>{{ booking.sig4_image ? 'Tanda Tangani Ulang' : 'Tanda Tangani Sebagai Konsumen' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- 2. Sales Agent Signature Card -->
                    <div 
                        class="border rounded-2xl p-3.5 flex flex-col justify-between transition-all"
                        :class="booking.sig3_image ? 'bg-emerald-50/40 border-emerald-300' : 'bg-slate-50/60 border-slate-200'"
                    >
                        <div>
                            <div class="flex items-center justify-between gap-1 mb-1.5">
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">2. Sales Agent</span>
                                <span v-if="booking.sig3_image" class="px-2 py-0.5 bg-emerald-100 text-emerald-800 text-[9px] font-black rounded-md flex items-center gap-1">
                                    <span>✓</span> <span>SUDAH TTD</span>
                                </span>
                                <span v-else class="px-2 py-0.5 bg-amber-100 text-amber-800 text-[9px] font-black rounded-md">
                                    BELUM TTD
                                </span>
                            </div>
                            <div class="text-xs font-black text-slate-900 truncate">
                                {{ booking.sig3_name || booking.booked_by?.name || booking.bookedBy?.name || 'Sales Agent' }}
                            </div>
                            <div class="text-[10px] text-slate-400 mt-0.5">
                                <span v-if="booking.agent_signed_at">Ditandatangani: {{ formatDate(booking.agent_signed_at) }}</span>
                                <span v-else>Menunggu tanda tangan agent</span>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div v-if="booking.sig3_image" class="mb-2 p-1.5 bg-white rounded-xl border border-emerald-200 flex items-center justify-center shadow-inner">
                                <img :src="resolveSigUrl(booking.sig3_image)" alt="TTD Sales Agent" class="h-14 max-w-full object-contain" />
                            </div>
                            <button 
                                @click="openSignModal('agent')"
                                class="w-full py-2.5 px-3 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 active:scale-95 shadow-sm"
                                :class="booking.sig3_image ? 'bg-slate-100 hover:bg-slate-200 text-slate-700' : 'bg-emerald-600 hover:bg-emerald-700 text-white'"
                            >
                                <span>✍️</span>
                                <span>{{ booking.sig3_image ? 'Tanda Tangani Ulang' : 'Tanda Tangani Sebagai Agent' }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Disclaimer -->
            <div class="text-center text-[10px] text-slate-400 py-2 leading-relaxed">
                Portal Pelacakan Resmi Konsumen • HOMI CRM<br/>
                Perlu bantuan? Hubungi Sales Agent Anda di bawah ini.
            </div>
        </div>

        <!-- Sticky Bottom Action Footer (Mobile Friendly & Passwordless Document Access) -->
        <div class="fixed bottom-0 left-0 right-0 bg-white/90 backdrop-blur-md border-t border-slate-200 p-3 z-30 shadow-2xl">
            <div class="max-w-md mx-auto grid grid-cols-2 gap-2.5">
                <!-- Direct Passwordless SPR Viewer with Token -->
                <a 
                    :href="`/bookings/${booking.id}/spk/view?token=${booking.tracking_token}&pdf=1`" 
                    target="_blank"
                    class="py-3 px-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-black flex items-center justify-center gap-1.5 shadow-md active:scale-95 transition-all"
                >
                    <span>📄</span>
                    <span>Download SPR PDF</span>
                </a>

                <!-- WhatsApp Sales Agent -->
                <button 
                    @click="openSalesWa" 
                    class="py-3 px-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black flex items-center justify-center gap-1.5 shadow-md active:scale-95 transition-all"
                >
                    <span>💬</span>
                    <span>Chat Sales Agent</span>
                </button>
            </div>
        </div>

        <!-- SIGNATURE CANVAS MODAL -->
        <teleport to="body">
            <div v-if="showSignModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm animate-in fade-in duration-150">
                <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-5 sm:p-6 space-y-4 shadow-2xl relative">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div>
                            <h3 class="text-sm font-black text-slate-900 flex items-center gap-1.5">
                                <span>✍️</span>
                                <span>Tanda Tangan Digital ({{ signRole === 'customer' ? 'Konsumen' : 'Sales Agent' }})</span>
                            </h3>
                            <p class="text-[10px] text-slate-400 mt-0.5">Goreskan tanda tangan Anda pada kotak kanvas di bawah.</p>
                        </div>
                        <button @click="closeSignModal" class="w-8 h-8 rounded-xl bg-slate-100 text-slate-400 hover:text-slate-600 flex items-center justify-center font-bold text-sm">&times;</button>
                    </div>

                    <!-- Signer Name Input -->
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Nama Penanda Tangan *</label>
                        <input 
                            v-model="signerName" 
                            type="text" 
                            required 
                            placeholder="Masukkan nama lengkap" 
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all"
                        />
                    </div>

                    <!-- Canvas Drawing Area -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10px] font-black uppercase text-slate-500">Area Tanda Tangan</span>
                            <button @click="clearSignature" type="button" class="text-[10px] font-bold text-rose-600 hover:text-rose-700 hover:underline flex items-center gap-1">
                                <span>🔄</span> <span>Bersihkan Kanvas</span>
                            </button>
                        </div>
                        <div class="border-2 border-dashed border-slate-300 rounded-2xl bg-slate-50/70 p-1 relative overflow-hidden select-none" style="touch-action: none;">
                            <canvas 
                                ref="sigCanvas" 
                                width="380" 
                                height="180" 
                                class="w-full h-[180px] bg-white rounded-xl cursor-crosshair block"
                                @mousedown="startDrawing" 
                                @mousemove="draw" 
                                @mouseup="stopDrawing" 
                                @mouseleave="stopDrawing" 
                                @touchstart.passive="startDrawing" 
                                @touchmove.prevent="draw" 
                                @touchend="stopDrawing"
                            ></canvas>
                            <div v-if="!hasDrawn" class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-slate-300 gap-1">
                                <span class="text-2xl">✍️</span>
                                <span class="text-[11px] font-medium">Goreskan jari / kursor tanda tangan di sini</span>
                            </div>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1.5 italic">
                            *Tanda tangan ini sah secara digital dan akan langsung dicantumkan pada dokumen SPR.
                        </p>
                    </div>

                    <!-- Modal Actions -->
                    <div class="flex gap-2.5 pt-2 border-t border-slate-100">
                        <button 
                            type="button" 
                            @click="closeSignModal" 
                            class="flex-1 py-3 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-all"
                        >
                            Batal
                        </button>
                        <button 
                            type="button" 
                            @click="submitSignature" 
                            :disabled="isSubmitting || !hasDrawn || !signerName"
                            class="flex-2 py-3 px-4 bg-slate-900 hover:bg-slate-800 disabled:opacity-40 text-white font-black text-xs rounded-xl shadow-lg transition-all flex items-center justify-center gap-1.5"
                        >
                            <span v-if="isSubmitting">Menyimpan...</span>
                            <span v-else>💾 Simpan ke Dokumen SPR</span>
                        </button>
                    </div>
                </div>
            </div>
        </teleport>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
.font-sans { font-family: 'Plus Jakarta Sans', sans-serif; }
</style>
