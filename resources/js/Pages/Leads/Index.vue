<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    leads: Object,
    pipeline: Object,
    filters: Object,
    projects: Array,
    agents: Array,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const projectFilter = ref(props.filters?.project_id || '');
const sourceFilter = ref(props.filters?.source || '');
const viewMode = ref('table'); // 'table' or 'kanban'

let timeout;
watch(search, (val) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => applyFilters(), 400);
});
watch([statusFilter, projectFilter, sourceFilter], () => applyFilters());

function applyFilters() {
    router.get('/leads', {
        search: search.value, status: statusFilter.value,
        project_id: projectFilter.value, source: sourceFilter.value,
    }, { preserveState: true, replace: true });
}

function deleteLead(id) {
    if (confirm('Yakin ingin menghapus lead ini?')) {
        router.delete(`/leads/${id}`);
    }
}

// Kanban columns
const kanbanColumns = [
    { key: 'new', label: 'Baru', color: 'blue', icon: '🆕' },
    { key: 'contacted', label: 'Dihubungi', color: 'cyan', icon: '📞' },
    { key: 'visited', label: 'Kunjungan', color: 'purple', icon: '🏠' },
    { key: 'negotiation', label: 'Negosiasi', color: 'amber', icon: '🤝' },
    { key: 'booking', label: 'Booking', color: 'emerald', icon: '📋' },
    { key: 'won', label: 'Won', color: 'green', icon: '🏆' },
    { key: 'lost', label: 'Lost', color: 'rose', icon: '❌' },
];

const kanbanLeads = computed(() => {
    const grouped = {};
    kanbanColumns.forEach(col => grouped[col.key] = []);
    props.leads.data.forEach(lead => {
        if (grouped[lead.status]) grouped[lead.status].push(lead);
    });
    return grouped;
});

function updateLeadStatus(leadId, newStatus) {
    router.put(`/leads/${leadId}`, { status: newStatus }, { preserveState: true });
}

// Quick add lead modal
const showAddModal = ref(false);
const addForm = useForm({
    name: '', phone: '', email: '', source: 'website', project_id: '', assigned_to: '', notes: '',
});

function submitLead() {
    addForm.post('/leads', {
        onSuccess: () => { showAddModal.value = false; addForm.reset(); },
    });
}

const sourceColors = {
    facebook: 'bg-blue-100 text-blue-700', instagram: 'bg-pink-100 text-pink-700',
    google: 'bg-red-100 text-red-700', tiktok: 'bg-slate-100 text-slate-700',
    walk_in: 'bg-amber-100 text-amber-700', referral: 'bg-purple-100 text-purple-700',
    broker: 'bg-cyan-100 text-cyan-700', website: 'bg-emerald-100 text-emerald-700',
    other: 'bg-gray-100 text-gray-700',
};

const statusColors = {
    new: 'bg-blue-100 text-blue-700', contacted: 'bg-cyan-100 text-cyan-700',
    visited: 'bg-purple-100 text-purple-700', negotiation: 'bg-amber-100 text-amber-700',
    booking: 'bg-emerald-100 text-emerald-700', won: 'bg-green-100 text-green-700',
    lost: 'bg-rose-100 text-rose-700',
};

function scoreColor(score) {
    if (score >= 8) return 'text-emerald-600 bg-emerald-50';
    if (score >= 5) return 'text-amber-600 bg-amber-50';
    return 'text-rose-600 bg-rose-50';
}
</script>

<template>
    <Head title="Leads" />
    <CrmLayout>
        <template #breadcrumb>Leads</template>

        <!-- HEADER -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Pipeline Leads</h1>
                <p class="text-sm text-slate-500 mt-1">Kelola prospek dan lacak konversi penjualan</p>
            </div>
            <div class="flex items-center gap-3">
                <!-- View Toggle -->
                <div class="flex bg-slate-100 rounded-xl p-0.5">
                    <button @click="viewMode = 'table'" :class="viewMode === 'table' ? 'bg-white shadow-sm' : ''" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all">📊 Tabel</button>
                    <button @click="viewMode = 'kanban'" :class="viewMode === 'kanban' ? 'bg-white shadow-sm' : ''" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all">📋 Kanban</button>
                </div>
                <button @click="showAddModal = true"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    Tambah Lead
                </button>
            </div>
        </div>

        <!-- PIPELINE SUMMARY -->
        <div class="grid grid-cols-3 sm:grid-cols-7 gap-2 mb-6">
            <div v-for="col in kanbanColumns" :key="col.key" class="bg-white rounded-xl border border-slate-100 p-3 text-center shadow-sm hover:shadow-md transition-all cursor-pointer"
                @click="statusFilter = statusFilter === col.key ? '' : col.key">
                <p class="text-lg font-black text-slate-900">{{ pipeline[col.key] || 0 }}</p>
                <p class="text-[9px] font-bold uppercase tracking-wider" :class="`text-${col.color}-600`">{{ col.label }}</p>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="flex flex-wrap gap-3 mb-6">
            <div class="relative flex-1 min-w-[200px] max-w-md">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input v-model="search" type="text" placeholder="Cari nama atau telepon..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
            </div>
            <select v-model="projectFilter" class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                <option value="">Semua Proyek</option>
                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <select v-model="sourceFilter" class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                <option value="">Semua Sumber</option>
                <option v-for="s in ['facebook','instagram','google','tiktok','walk_in','referral','broker','website']" :key="s" :value="s">{{ s.replace('_', ' ') }}</option>
            </select>
        </div>

        <!-- TABLE VIEW -->
        <div v-if="viewMode === 'table'" class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left px-4 py-3 text-[10px] font-black text-slate-500 uppercase tracking-wider">Lead</th>
                            <th class="text-left px-4 py-3 text-[10px] font-black text-slate-500 uppercase tracking-wider hidden md:table-cell">Proyek</th>
                            <th class="text-left px-4 py-3 text-[10px] font-black text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="text-left px-4 py-3 text-[10px] font-black text-slate-500 uppercase tracking-wider hidden lg:table-cell">Sumber</th>
                            <th class="text-left px-4 py-3 text-[10px] font-black text-slate-500 uppercase tracking-wider hidden lg:table-cell">Agen</th>
                            <th class="text-center px-4 py-3 text-[10px] font-black text-slate-500 uppercase tracking-wider">Skor</th>
                            <th class="text-right px-4 py-3 text-[10px] font-black text-slate-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="lead in leads.data" :key="lead.id" class="hover:bg-blue-50/30 transition-colors">
                            <td class="px-4 py-3">
                                <div>
                                    <Link :href="`/leads/${lead.id}`" class="hover:text-blue-600 font-bold text-slate-900 text-sm">
                                        {{ lead.name }}
                                    </Link>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-xs text-slate-500 font-medium">{{ lead.phone }}</span>
                                        <a :href="`https://wa.me/${lead.phone.replace(/^0/, '62').replace(/[^0-9]/g, '')}`" target="_blank"
                                            class="inline-flex items-center gap-0.5 text-[9px] font-black text-emerald-600 bg-emerald-50 hover:bg-emerald-100 px-1.5 py-0.5 rounded transition-all">
                                            💬 WhatsApp
                                        </a>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell">
                                <span class="text-xs font-medium text-slate-600">{{ lead.project?.name || '-' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <select :value="lead.status" @change="updateLeadStatus(lead.id, $event.target.value)"
                                    :class="statusColors[lead.status]"
                                    class="text-[10px] font-bold rounded-full px-2 py-0.5 border-0 cursor-pointer focus:ring-2 focus:ring-blue-500/20">
                                    <option v-for="col in kanbanColumns" :key="col.key" :value="col.key">{{ col.label }}</option>
                                </select>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell">
                                <span :class="sourceColors[lead.source]" class="text-[10px] font-bold px-2 py-0.5 rounded-full capitalize">{{ lead.source?.replace('_', ' ') }}</span>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell">
                                <span class="text-xs text-slate-600">{{ lead.assigned_to_user?.name || '-' }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="scoreColor(lead.score)" class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-xs font-black">{{ lead.score }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/leads/${lead.id}`" class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-blue-50 text-slate-400 hover:text-blue-600 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </Link>
                                    <button @click="deleteLead(lead.id)" class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-rose-50 text-slate-400 hover:text-rose-500 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty -->
            <div v-if="!leads.data.length" class="text-center py-16">
                <div class="text-4xl mb-3">👥</div>
                <p class="text-sm font-bold text-slate-500">Belum ada lead</p>
            </div>
        </div>

        <!-- KANBAN VIEW -->
        <div v-if="viewMode === 'kanban'" class="flex gap-4 overflow-x-auto pb-4 -mx-2 px-2">
            <div v-for="col in kanbanColumns.filter(c => c.key !== 'lost')" :key="col.key"
                class="flex-shrink-0 w-[280px] bg-slate-50/80 rounded-2xl p-3">
                <!-- Column Header -->
                <div class="flex items-center justify-between mb-3 px-1">
                    <div class="flex items-center gap-2">
                        <span class="text-sm">{{ col.icon }}</span>
                        <h3 class="text-xs font-black text-slate-700 uppercase tracking-wider">{{ col.label }}</h3>
                    </div>
                    <span class="w-5 h-5 bg-white rounded-md flex items-center justify-center text-[10px] font-black text-slate-600 shadow-sm">{{ kanbanLeads[col.key]?.length || 0 }}</span>
                </div>

                <!-- Cards -->
                <div class="space-y-2.5">
                    <div v-for="lead in kanbanLeads[col.key]" :key="lead.id"
                        class="bg-white rounded-xl p-3 border border-slate-100 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all cursor-pointer"
                        @click="$inertia.visit(`/leads/${lead.id}`)">
                        <div class="flex items-start justify-between mb-2">
                            <p class="text-sm font-bold text-slate-900 leading-tight">{{ lead.name }}</p>
                            <span :class="scoreColor(lead.score)" class="w-6 h-6 rounded-md flex items-center justify-center text-[10px] font-black shrink-0">{{ lead.score }}</span>
                        </div>
                        <p class="text-xs text-slate-500 mb-2">{{ lead.phone }}</p>
                        <div class="flex items-center justify-between">
                            <span :class="sourceColors[lead.source]" class="text-[9px] font-bold px-1.5 py-0.5 rounded capitalize">{{ lead.source?.replace('_', ' ') }}</span>
                            <span class="text-[10px] text-slate-400">{{ lead.assigned_to_user?.name?.split(' ')[0] || '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PAGINATION -->
        <div v-if="viewMode === 'table' && leads.links?.length > 3" class="flex justify-center gap-1 mt-8">
            <Link v-for="link in leads.links" :key="link.label" :href="link.url || '#'"
                :class="[link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-slate-600 hover:bg-slate-50', !link.url ? 'opacity-40 pointer-events-none' : '']"
                class="px-3.5 py-2 text-xs font-bold rounded-lg border border-slate-200 transition-all"
                v-html="link.label" />
        </div>

        <!-- ADD LEAD MODAL -->
        <teleport to="body">
            <div v-if="showAddModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showAddModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="sticky top-0 bg-white px-6 py-4 border-b border-slate-100 flex items-center justify-between rounded-t-2xl z-10">
                        <h2 class="text-lg font-black text-slate-900">Tambah Lead Baru</h2>
                        <button @click="showAddModal = false" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors">&times;</button>
                    </div>

                    <form @submit.prevent="submitLead" class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama <span class="text-rose-500">*</span></label>
                                <input v-model="addForm.name" type="text" placeholder="Nama lengkap" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                                <p v-if="addForm.errors.name" class="text-xs text-rose-500 mt-1">{{ addForm.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Telepon <span class="text-rose-500">*</span></label>
                                <input v-model="addForm.phone" type="text" placeholder="08xxxxxxxxxx" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                                <p v-if="addForm.errors.phone" class="text-xs text-rose-500 mt-1">{{ addForm.errors.phone }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Email</label>
                                <input v-model="addForm.email" type="email" placeholder="email@example.com" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Sumber <span class="text-rose-500">*</span></label>
                                <select v-model="addForm.source" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                    <option v-for="s in ['facebook','instagram','google','tiktok','walk_in','referral','broker','website','other']" :key="s" :value="s">{{ s.replace('_', ' ') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Proyek</label>
                                <select v-model="addForm.project_id" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                    <option value="">Pilih Proyek</option>
                                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Agen</label>
                                <select v-model="addForm.assigned_to" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                    <option value="">Auto-assign (Round Robin)</option>
                                    <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.name }}</option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Catatan</label>
                                <textarea v-model="addForm.notes" rows="3" placeholder="Catatan awal tentang lead..." class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 resize-none"></textarea>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showAddModal = false" class="px-5 py-2.5 text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">Batal</button>
                            <button type="submit" :disabled="addForm.processing"
                                class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 transition-all disabled:opacity-50">
                                {{ addForm.processing ? 'Menyimpan...' : 'Simpan Lead' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>
    </CrmLayout>
</template>
