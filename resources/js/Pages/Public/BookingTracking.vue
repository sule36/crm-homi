<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    booking: Object
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};

const totalPaid = computed(() => {
    return props.booking.transactions.reduce((sum, tx) => sum + Number(tx.amount), 0);
});

const progressPercentage = computed(() => {
    return Math.min(100, (totalPaid.value / props.booking.final_price) * 100);
});

const statusColors = {
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-rose-100 text-rose-700',
    completed: 'bg-blue-100 text-blue-700',
    paid: 'bg-emerald-500 text-white',
    unpaid: 'bg-slate-100 text-slate-500',
};
</script>

<template>
    <Head :title="`Pelacakan Pesanan: ${booking.spk_number}`" />
    
    <div class="min-h-screen bg-slate-50 font-sans text-slate-900 pb-20">
        <!-- HEADER -->
        <div class="bg-white border-b border-slate-100 sticky top-0 z-10">
            <div class="max-w-xl mx-auto px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-black">H</div>
                    <span class="font-black tracking-tight text-slate-900">HOMI Portal</span>
                </div>
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Customer Access</span>
            </div>
        </div>

        <div class="max-w-xl mx-auto px-6 pt-8 space-y-6">
            <!-- WELCOME CARD -->
            <div class="bg-gradient-to-br from-slate-900 to-indigo-950 rounded-[2rem] p-8 text-white shadow-2xl relative overflow-hidden">
                <div class="relative z-10">
                    <p class="text-xs font-bold text-blue-400 uppercase tracking-widest mb-2">Halo, {{ booking.lead.name }}</p>
                    <h1 class="text-2xl font-black mb-6">Progres Pesanan Anda</h1>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between items-end">
                            <span class="text-xs text-slate-400">Pembayaran Masuk</span>
                            <span class="text-lg font-black text-emerald-400">{{ formatCurrency(totalPaid) }}</span>
                        </div>
                        <div class="w-full bg-white/10 h-3 rounded-full overflow-hidden">
                            <div class="h-full bg-emerald-400 rounded-full transition-all duration-1000" :style="{ width: progressPercentage + '%' }"></div>
                        </div>
                        <div class="flex justify-between text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                            <span>Sisa: {{ formatCurrency(booking.final_price - totalPaid) }}</span>
                            <span>{{ Math.round(progressPercentage) }}% Lunas</span>
                        </div>
                    </div>
                </div>
                <!-- Decoration -->
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-blue-500/10 rounded-full blur-3xl"></div>
            </div>

            <!-- UNIT INFO -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-xl">🏠</div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900">{{ booking.unit.project.name }}</h3>
                        <p class="text-xs text-slate-500">Unit {{ booking.unit.block }}{{ booking.unit.number }} • Tipe {{ booking.unit.unit_type.name }}</p>
                    </div>
                </div>
            </div>

            <!-- KPR PROGRESS FOR CUSTOMER -->
            <div v-if="booking.payment_scheme === 'kpr'" class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-6">Progres KPR di Bank</h3>
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-500">Bank</span>
                        <span class="text-xs font-black text-slate-900">{{ booking.kpr_bank_name || '-' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-500">Status Terakhir</span>
                        <span class="px-2 py-1 bg-blue-50 text-blue-600 rounded text-[10px] font-black uppercase">{{ booking.kpr_status || 'Dalam Proses' }}</span>
                    </div>
                    <!-- Small Stepper for Mobile -->
                    <div class="flex gap-1.5 h-1.5 mt-4">
                        <div v-for="i in 7" :key="i" 
                            :class="i <= (['application','bi_checking','interview','appraisal','sp3k','akad','cair'].indexOf(booking.kpr_status) + 1) ? 'bg-blue-600' : 'bg-slate-100'"
                            class="flex-1 rounded-full transition-all duration-500">
                        </div>
                    </div>
                </div>
            </div>

            <!-- PAYMENT SCHEDULE -->
            <div class="space-y-4">
                <h2 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] px-2">Jadwal & Riwayat</h2>
                
                <div v-for="(schedule, index) in booking.payment_schedules" :key="schedule.id" 
                    class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center justify-between group transition-all active:scale-95">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xs font-black" 
                            :class="schedule.status === 'paid' ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-400'">
                            {{ index + 1 }}
                        </div>
                        <div>
                            <p class="text-sm font-black text-slate-900">{{ schedule.label }}</p>
                            <p class="text-[10px] text-slate-400 font-bold uppercase">{{ schedule.status === 'paid' ? 'Sudah Dibayar' : 'Jatuh Tempo: ' + new Date(schedule.due_date).toLocaleDateString('id-ID') }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-black text-slate-900">{{ formatCurrency(schedule.amount) }}</p>
                        <span v-if="schedule.status === 'paid'" class="text-[10px] font-black text-emerald-500 uppercase tracking-widest">Lunas ✅</span>
                        <span v-else class="text-[10px] font-black text-slate-300 uppercase tracking-widest">Belum</span>
                    </div>
                </div>
            </div>

            <!-- ACTION FOOTER -->
            <div class="pt-6">
                <a :href="`/bookings/${booking.id}/spk/view?token=${booking.tracking_token}`" class="w-full py-5 bg-white border-2 border-slate-900 text-slate-900 font-black rounded-2xl flex items-center justify-center gap-3 shadow-xl hover:-translate-y-1 transition-all active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    LIHAT / DOWNLOAD SPK
                </a>
                <p class="text-center text-[10px] text-slate-400 mt-6 font-medium">Data diperbarui otomatis oleh sistem HOMI CRM.<br/>Hubungi sales kami jika ada ketidaksesuaian data.</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap');
.font-sans { font-family: 'Outfit', sans-serif; }
</style>
