<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    kpiData: Array,
    currentMonth: String,
});

const showModal = ref(false);
const targetForm = useForm({
    user_id: '',
    month: props.currentMonth,
    target_units: 0,
    target_revenue: 0,
});

const openModal = (agent) => {
    targetForm.user_id = agent.agent_id;
    targetForm.target_units = agent.target?.target_units || 0;
    targetForm.target_revenue = agent.target?.target_revenue || 0;
    showModal.value = true;
};

const submitTarget = () => {
    targetForm.post('/kpi/target', {
        onSuccess: () => showModal.value = false
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};
</script>

<template>
    <Head title="Sales Performance" />
    <CrmLayout>
        <template #breadcrumb>Performa Sales</template>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Performa & KPI Agen</h1>
                <p class="text-sm text-slate-500 mt-1">Pantau pencapaian target penjualan bulanan tim Anda.</p>
            </div>
            <div class="bg-white px-4 py-2 rounded-xl border border-slate-100 shadow-sm flex items-center gap-3">
                <span class="text-xs font-bold text-slate-400 uppercase">Periode:</span>
                <span class="text-sm font-black text-blue-600">{{ currentMonth }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <div v-for="agent in kpiData" :key="agent.agent_id" class="bg-white rounded-3xl border border-slate-100 p-6 shadow-sm group hover:shadow-xl hover:shadow-blue-500/5 transition-all">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-xs font-black shadow-lg">
                            {{ agent.agent_name.charAt(0) }}
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">{{ agent.agent_name }}</h3>
                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Sales Agent</p>
                        </div>
                    </div>
                    <button @click="openModal(agent)" class="text-[10px] font-black text-blue-600 bg-blue-50 px-2 py-1 rounded-lg hover:bg-blue-600 hover:text-white transition-all">SET TARGET</button>
                </div>

                <div class="space-y-6">
                    <!-- Progress Units -->
                    <div>
                        <div class="flex justify-between items-end mb-2">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Target Unit</span>
                            <span class="text-xs font-black text-slate-900">{{ agent.achieved.units }} / {{ agent.target?.target_units || 0 }}</span>
                        </div>
                        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-600 rounded-full transition-all duration-1000" :style="{ width: agent.percentage + '%' }"></div>
                        </div>
                        <p class="text-right text-[10px] font-black text-blue-600 mt-1">{{ agent.percentage }}% Achieved</p>
                    </div>

                    <!-- Stats Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100">
                            <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">Revenue</p>
                            <p class="text-xs font-black text-slate-900">{{ formatCurrency(agent.achieved.revenue) }}</p>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-2xl border border-slate-100">
                            <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">New Leads</p>
                            <p class="text-xs font-black text-slate-900">{{ agent.achieved.leads }} Lead</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SET TARGET MODAL -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showModal = false"></div>
            <div class="relative bg-white rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl animate-in zoom-in duration-200 p-8">
                <h3 class="text-xl font-black text-slate-900 mb-6 uppercase tracking-tight">Atur Target Sales</h3>
                <form @submit.prevent="submitTarget" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Target Penjualan (Unit)</label>
                        <input v-model="targetForm.target_units" type="number" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm font-black focus:ring-2 focus:ring-blue-600/20" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Target Omzet (Rupiah)</label>
                        <input v-model="targetForm.target_revenue" type="number" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm font-black focus:ring-2 focus:ring-blue-600/20" />
                    </div>
                    <div class="pt-4 flex gap-3">
                        <button type="submit" :disabled="targetForm.processing"
                            class="flex-1 py-4 bg-slate-900 text-white font-black rounded-2xl shadow-xl hover:-translate-y-1 transition-all">
                            SIMPAN TARGET
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </CrmLayout>
</template>
