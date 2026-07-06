<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    agents: Array,
    showAllTime: Boolean,
});

function toggleAllTime() {
    router.get('/agent-monitoring', { all_time: !props.showAllTime }, { preserveState: true });
}

function toggleStatus(agentId, currentStatus) {
    router.post(`/agent-monitoring/${agentId}/toggle`, {
        is_accepting_leads: !currentStatus
    }, { preserveScroll: true });
}

function updateCapacity(agentId, currentCapacity) {
    const newCap = prompt("Masukkan kapasitas maksimal lead aktif:", currentCapacity);
    if (newCap !== null && !isNaN(newCap)) {
        router.post(`/agent-monitoring/${agentId}/capacity`, {
            lead_capacity: parseInt(newCap)
        }, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Monitor Agen" />
    <CrmLayout>
        <template #breadcrumb>Monitor Agen</template>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Monitoring Beban Kerja Agen</h1>
                <p class="text-sm text-slate-500 mt-1">Pantau lead yang masuk secara real-time dan atur penerimaan (Online/Offline).</p>
            </div>
            
            <div class="flex items-center gap-3">
                <button @click="toggleAllTime" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-bold shadow-sm hover:bg-slate-50 transition-all">
                    {{ showAllTime ? 'Lihat Hari Ini Saja' : 'Lihat Semua Waktu' }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
            <div v-for="agent in agents" :key="agent.id" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <!-- Agent Header -->
                <div class="p-5 border-b border-slate-100 flex items-start justify-between bg-slate-50/50">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-black shadow-md">
                            {{ agent.name.charAt(0) }}
                        </div>
                        <div>
                            <h3 class="font-black text-slate-900">{{ agent.name }}</h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="relative flex h-2.5 w-2.5">
                                    <span v-if="agent.is_accepting_leads" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5" :class="agent.is_accepting_leads ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                                </span>
                                <span class="text-[10px] font-bold uppercase tracking-wider" :class="agent.is_accepting_leads ? 'text-emerald-600' : 'text-slate-500'">
                                    {{ agent.is_accepting_leads ? 'Menerima Lead' : 'Offline' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <button @click="toggleStatus(agent.id, agent.is_accepting_leads)" 
                        :class="agent.is_accepting_leads ? 'bg-rose-100 text-rose-700 hover:bg-rose-200' : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors">
                        {{ agent.is_accepting_leads ? 'Matikan' : 'Nyalakan' }}
                    </button>
                </div>

                <!-- Workload Stats -->
                <div class="p-4 grid grid-cols-2 gap-4 border-b border-slate-100">
                    <div class="text-center">
                        <p class="text-xs font-bold text-slate-500 mb-1 uppercase">Lead Aktif Saat Ini</p>
                        <p class="text-2xl font-black" :class="agent.active_leads_count >= agent.lead_capacity ? 'text-rose-600' : 'text-slate-900'">
                            {{ agent.active_leads_count }}
                        </p>
                    </div>
                    <div class="text-center group cursor-pointer" @click="updateCapacity(agent.id, agent.lead_capacity)">
                        <p class="text-xs font-bold text-slate-500 mb-1 uppercase group-hover:text-blue-600 transition-colors">Kapasitas Maksimal ✎</p>
                        <p class="text-2xl font-black text-slate-900">{{ agent.lead_capacity }}</p>
                    </div>
                </div>

                <!-- Leads Received List -->
                <div class="p-4 flex-1 overflow-y-auto max-h-[300px] bg-slate-50/30">
                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3">
                        Lead Diterima ({{ showAllTime ? 'Semua Waktu' : 'Hari Ini' }}): <span class="text-blue-600">{{ agent.leads.length }}</span>
                    </h4>
                    
                    <div v-if="agent.leads.length === 0" class="text-center py-6 text-slate-400">
                        <p class="text-sm">Belum ada lead masuk.</p>
                    </div>

                    <div class="space-y-2">
                        <div v-for="lead in agent.leads" :key="lead.id" class="bg-white border border-slate-100 rounded-xl p-3 shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-sm font-bold text-slate-900 truncate">{{ lead.name }}</p>
                                <span class="text-xs font-mono font-bold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">{{ lead.time }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span v-if="lead.source === 'instagram'" class="text-pink-500 text-xs font-bold">Instagram</span>
                                    <span v-else-if="lead.source === 'facebook'" class="text-blue-500 text-xs font-bold">Facebook</span>
                                    <span v-else-if="lead.source === 'website'" class="text-emerald-500 text-xs font-bold">Website</span>
                                    <span v-else class="text-slate-500 text-xs font-bold capitalize">{{ lead.source }}</span>
                                </div>
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full capitalize" 
                                      :class="lead.status === 'new' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'">
                                    {{ lead.status }}
                                </span>
                            </div>
                            <div v-if="showAllTime" class="text-[10px] text-slate-400 mt-1">
                                {{ lead.date }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </CrmLayout>
</template>
