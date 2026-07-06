<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import draggable from 'vuedraggable';

const props = defineProps({
    leadsByStatus: Object
});

const statuses = [
    { id: 'new', name: 'New Lead', color: 'bg-blue-500', icon: '✨' },
    { id: 'contacted', name: 'Contacted', color: 'bg-amber-500', icon: '📞' },
    { id: 'visited', name: 'Visit / Survey', color: 'bg-purple-500', icon: '🏠' },
    { id: 'negotiation', name: 'Negotiation', color: 'bg-indigo-500', icon: '🤝' },
    { id: 'booking', name: 'Booking', color: 'bg-emerald-500', icon: '💰' },
    { id: 'won', name: 'Closed Won', color: 'bg-green-600', icon: '🏆' },
    { id: 'lost', name: 'Closed Lost', color: 'bg-slate-500', icon: '❌' },
];

// Initialize local columns
const columns = ref(statuses.map(s => ({
    ...s,
    leads: props.leadsByStatus[s.id] || []
})));

function onMove(evt) {
    const leadId = evt.item._underlying_node.id;
    const newStatus = evt.to.getAttribute('data-status');
    
    router.patch(`/leads/${leadId}/status`, {
        status: newStatus
    }, {
        preserveScroll: true,
        onSuccess: () => {
            // Success notification could go here
        }
    });
}
</script>

<template>
    <Head title="Lead Pipeline" />
    <CrmLayout>
        <template #breadcrumb>Marketing / Pipeline</template>

        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Sales Funnel</h1>
                <p class="text-sm text-slate-500 mt-1">Pantau pergerakan calon pembeli dari prospek hingga closing.</p>
            </div>
            <div class="flex gap-2">
                <Link href="/leads" class="px-4 py-2 bg-slate-100 text-slate-600 text-xs font-black uppercase tracking-widest rounded-xl hover:bg-slate-200 transition-all">Daftar List</Link>
                <Link href="/leads/create" class="px-6 py-2 bg-slate-900 text-white text-xs font-black uppercase tracking-widest rounded-xl shadow-lg hover:-translate-y-0.5 transition-all">+ Add Lead</Link>
            </div>
        </div>

        <div class="flex gap-6 overflow-x-auto pb-8 -mx-4 px-4 min-h-[calc(100vh-250px)]">
            <div v-for="column in columns" :key="column.id" class="flex-shrink-0 w-80">
                <!-- Column Header -->
                <div class="mb-4 flex items-center justify-between px-2">
                    <div class="flex items-center gap-2">
                        <span :class="column.color" class="w-2 h-2 rounded-full"></span>
                        <h3 class="font-black text-slate-900 text-xs uppercase tracking-widest">{{ column.name }}</h3>
                        <span class="px-2 py-0.5 bg-slate-100 text-[10px] font-black text-slate-500 rounded-lg">{{ column.leads.length }}</span>
                    </div>
                </div>

                <!-- Draggable Area -->
                <draggable 
                    v-model="column.leads" 
                    group="leads" 
                    @add="onMove"
                    item-key="id"
                    :data-status="column.id"
                    class="space-y-3 min-h-[500px] p-2 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-100"
                    ghost-class="opacity-50"
                    drag-class="rotate-2"
                >
                    <template #item="{ element }">
                        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm hover:shadow-xl hover:ring-2 hover:ring-blue-500/20 transition-all cursor-grab active:cursor-grabbing group">
                            <div class="flex items-start justify-between mb-3">
                                <Link :href="`/leads/${element.id}`" class="text-sm font-black text-slate-900 group-hover:text-blue-600 transition-colors">{{ element.name }}</Link>
                                <span class="text-[10px] font-black px-2 py-0.5 bg-slate-50 text-slate-400 rounded-lg">#{{ element.id }}</span>
                            </div>
                            
                            <div class="space-y-2">
                                <div class="flex items-center gap-1.5 text-[11px] text-slate-500 font-medium">
                                    <span class="w-4 text-center">🏢</span>
                                    {{ element.project?.name || 'No Project' }}
                                </div>
                                <div class="flex items-center gap-1.5 text-[11px] text-slate-500 font-medium">
                                    <span class="w-4 text-center">📞</span>
                                    {{ element.phone }}
                                </div>
                            </div>

                            <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between">
                                <div class="flex -space-x-2">
                                    <div class="w-6 h-6 rounded-full bg-blue-100 border-2 border-white flex items-center justify-center text-[10px] font-black text-blue-600 uppercase" :title="element.assigned_to?.name">
                                        {{ (element.assigned_to?.name || 'U').charAt(0) }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" v-if="element.score >= 70"></span>
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-tighter">Score: {{ element.score }}%</span>
                                </div>
                            </div>
                        </div>
                    </template>
                </draggable>
            </div>
        </div>
    </CrmLayout>
</template>

<style scoped>
::-webkit-scrollbar {
    height: 8px;
}
::-webkit-scrollbar-track {
    background: transparent;
}
::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
