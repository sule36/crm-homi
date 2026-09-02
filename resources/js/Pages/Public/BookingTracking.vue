<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

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
    const agent = props.booking.booked_by;
    const phone = agent?.phone ? agent.phone.replace(/[^0-9]/g, '') : '';
    const formattedPhone = phone.startsWith('0') ? '62' + phone.slice(1) : phone;
    const msg = `Halo ${agent?.name || 'Sales'}, saya ${props.booking.lead?.name || 'Konsumen'} (Unit ${props.booking.unit?.block}${props.booking.unit?.number}). Saya mau bertanya info terkait pesanan saya.`;
    
    if (formattedPhone) {
        window.open(`https://wa.me/${formattedPhone}?text=${encodeURIComponent(msg)}`, '_blank');
    } else {
        alert('Nomor WhatsApp Sales Agent tidak tersedia.');
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
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
.font-sans { font-family: 'Plus Jakarta Sans', sans-serif; }
</style>
