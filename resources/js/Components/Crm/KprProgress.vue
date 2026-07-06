<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    booking: Object
});

const kprStages = [
    { id: 'application', name: 'Pemberkasan', icon: '📝' },
    { id: 'bi_checking', name: 'BI Checking', icon: '🔍' },
    { id: 'interview', name: 'Wawancara', icon: '🗣️' },
    { id: 'appraisal', name: 'Appraisal', icon: '🏠' },
    { id: 'sp3k', name: 'SP3K', icon: '📜' },
    { id: 'akad', name: 'Akad Kredit', icon: '🤝' },
    { id: 'cair', name: 'Pencairan', icon: '💰' },
];

const currentStageIndex = kprStages.findIndex(s => s.id === props.booking.kpr_status) || 0;

const showUpdateModal = ref(false);
const form = useForm({
    kpr_status: props.booking.kpr_status || 'application',
    kpr_bank_name: props.booking.kpr_bank_name || '',
    kpr_plafon_amount: props.booking.kpr_plafon_amount || 0,
    kpr_notes: props.booking.kpr_notes || '',
});

const submitUpdate = () => {
    form.post(route('bookings.updateKpr', props.booking.id), {
        onSuccess: () => showUpdateModal.value = false
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};
</script>

<template>
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden mb-8">
        <div class="p-8 border-b border-slate-50 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-black text-slate-900 uppercase tracking-tight">KPR Progress Tracking</h3>
                <p class="text-xs text-slate-500 mt-1">Pantau proses pengajuan kredit konsumen ke Bank.</p>
            </div>
            <button @click="showUpdateModal = true" class="px-5 py-2.5 bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:-translate-y-1 transition-all">
                UPDATE STATUS KPR
            </button>
        </div>

        <div class="p-8">
            <!-- STEPPER -->
            <div class="relative flex justify-between">
                <!-- Line Background -->
                <div class="absolute top-5 left-0 right-0 h-0.5 bg-slate-100 -z-0"></div>
                <div class="absolute top-5 left-0 h-0.5 bg-blue-600 -z-0 transition-all duration-1000" :style="{ width: (currentStageIndex / (kprStages.length - 1) * 100) + '%' }"></div>

                <div v-for="(stage, index) in kprStages" :key="stage.id" class="relative z-10 flex flex-col items-center group">
                    <div :class="[
                        index <= currentStageIndex ? 'bg-blue-600 text-white border-blue-600 shadow-lg shadow-blue-500/20' : 'bg-white text-slate-300 border-slate-200'
                    ]" class="w-10 h-10 rounded-full border-2 flex items-center justify-center text-sm font-black transition-all duration-500">
                        <span v-if="index < currentStageIndex">✓</span>
                        <span v-else>{{ stage.icon }}</span>
                    </div>
                    <p :class="index <= currentStageIndex ? 'text-slate-900' : 'text-slate-400'" class="text-[10px] font-black uppercase tracking-wider mt-3 whitespace-nowrap">{{ stage.name }}</p>
                </div>
            </div>

            <!-- DETAILS GRID -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-12 bg-slate-50 p-6 rounded-3xl border border-slate-100">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Bank Pelaksana</p>
                    <p class="text-sm font-black text-slate-900">{{ booking.kpr_bank_name || 'Belum Ditentukan' }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Plafon Disetujui</p>
                    <p class="text-sm font-black text-emerald-600">{{ formatCurrency(booking.kpr_plafon_amount) }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Status Terakhir</p>
                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-[10px] font-black uppercase">{{ booking.kpr_status || 'Application' }}</span>
                </div>
            </div>
        </div>

        <!-- UPDATE MODAL -->
        <div v-if="showUpdateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showUpdateModal = false"></div>
            <div class="relative bg-white rounded-[2.5rem] w-full max-w-md overflow-hidden shadow-2xl animate-in zoom-in duration-200 p-10">
                <h3 class="text-xl font-black text-slate-900 mb-8 uppercase tracking-tight">Update Progres KPR</h3>
                <form @submit.prevent="submitUpdate" class="space-y-5">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Tahap Sekarang</label>
                        <select v-model="form.kpr_status" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-black focus:ring-2 focus:ring-blue-600/20">
                            <option v-for="stage in kprStages" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Nama Bank</label>
                        <input v-model="form.kpr_bank_name" type="text" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-black focus:ring-2 focus:ring-blue-600/20" placeholder="Contoh: Bank BTN, Mandiri, dll" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Plafon Kredit (IDR)</label>
                        <input v-model="form.kpr_plafon_amount" type="number" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-black focus:ring-2 focus:ring-blue-600/20" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Catatan Analis Bank</label>
                        <textarea v-model="form.kpr_notes" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-black focus:ring-2 focus:ring-blue-600/20" rows="3"></textarea>
                    </div>
                    <div class="pt-4">
                        <button type="submit" :disabled="form.processing"
                            class="w-full py-5 bg-blue-600 text-white font-black rounded-[1.5rem] shadow-xl shadow-blue-600/20 hover:-translate-y-1 transition-all active:scale-95 disabled:opacity-50">
                            SIMPAN PERUBAHAN
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
