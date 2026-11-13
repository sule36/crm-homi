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
    broker_companies: Array,
    dutyAgents: {
        type: Object,
        default: () => ({})
    },
    candidateDutyAgents: {
        type: Array,
        default: () => []
    },
    upcomingDutySchedules: {
        type: Array,
        default: () => []
    }
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
    { key: 'reservation', label: 'Reservasi Unit', color: 'teal', icon: '🔖' },
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
    router.put(`/leads/${leadId}`, { status: newStatus }, { 
        preserveState: true,
        onSuccess: () => {
            if (newStatus === 'reservation') {
                if (confirm('Status prospek berhasil diubah ke "Reservasi Unit"! Apakah Anda ingin langsung memilih unit kavling & membuat data Reservasi resmi agar tercatat di menu Reservasi Unit?')) {
                    router.visit(`/reservations/create?lead_id=${leadId}`);
                }
            }
        }
    });
}

const activeProjectId = computed(() => projectFilter.value || (props.projects?.[0]?.id ? String(props.projects[0].id) : ''));
const currentDutyAgent = computed(() => {
    if (activeProjectId.value && props.dutyAgents) {
        return props.dutyAgents[activeProjectId.value] || null;
    }
    return null;
});

// Candidate pool for duty agents (including Master Lead sub-agents)
const candidateDutyAgentsList = computed(() => {
    if (props.candidateDutyAgents && props.candidateDutyAgents.length > 0) {
        return props.candidateDutyAgents;
    }
    return (props.agents || []).map(a => {
        const isMl = Boolean(a.master_lead_id);
        const isInhouse = !a.broker_company_id && !a.master_lead_id;
        return {
            id: a.id,
            name: a.name,
            agent_type: a.agent_type,
            category: isInhouse ? 'inhouse' : (isMl ? 'master_lead_sub_agent' : 'agency'),
            category_label: isInhouse ? 'Sales In-House Developer' : (isMl ? `Sub-Agent ML (${a.master_lead?.name || 'Master Lead'})` : 'Mitra Agency'),
            master_lead_name: a.master_lead?.name,
            broker_company_name: a.broker_company?.name,
        };
    });
});

const showSetDutyModal = ref(false);
const setDutyTab = ref('daily'); // 'daily' | 'weekly' | 'upcoming'
const setDutyForm = useForm({
    project_id: '',
    duty_date: new Date().toISOString().split('T')[0],
    user_id: '',
    shift: 'full_day',
    notes: 'Jaga Kantor Pemasaran',
});

const setWeeklyForm = useForm({
    project_id: '',
    mode: 'single',
    user_id: '',
    start_date: '',
    end_date: '',
    shift: 'full_day',
    notes: 'Jadwal Mingguan',
});

function openSetDutyModal(projectId) {
    const targetPid = projectId || activeProjectId.value || (props.projects?.[0]?.id || '');
    setDutyForm.project_id = targetPid;
    setDutyForm.duty_date = new Date().toISOString().split('T')[0];
    setDutyForm.user_id = props.dutyAgents?.[targetPid]?.id || (candidateDutyAgentsList.value[0]?.id || '');

    // Setup weekly default dates (Monday to Sunday)
    const now = new Date();
    const day = now.getDay();
    const diffToMon = now.getDate() - day + (day === 0 ? -6 : 1);
    const mon = new Date(now.setDate(diffToMon));
    const sun = new Date(mon);
    sun.setDate(mon.getDate() + 6);

    setWeeklyForm.project_id = targetPid;
    setWeeklyForm.user_id = setDutyForm.user_id;
    setWeeklyForm.start_date = mon.toISOString().split('T')[0];
    setWeeklyForm.end_date = sun.toISOString().split('T')[0];

    showSetDutyModal.value = true;
}

function submitSetDailyDuty() {
    setDutyForm.post('/duty-schedules/set-daily', {
        preserveScroll: true,
        onSuccess: () => {
            showSetDutyModal.value = false;
        }
    });
}

function submitSetWeeklyDuty() {
    setWeeklyForm.project_id = setDutyForm.project_id;
    setWeeklyForm.post('/duty-schedules/set-weekly', {
        preserveScroll: true,
        onSuccess: () => {
            showSetDutyModal.value = false;
        }
    });
}

// Quick add lead modal
const showAddModal = ref(false);
const addForm = useForm({
    name: '', 
    phone: '', 
    email: '', 
    source: 'walk_in', 
    project_id: props.projects?.[0]?.id || '', 
    broker_company_id: '', 
    assigned_to: '', 
    inhouse_pic_id: '',
    notes: '',
});

const inhouseAgents = computed(() => {
    return candidateDutyAgentsList.value.filter(a => a.category === 'inhouse' || a.category === 'master_lead_sub_agent');
});

const brokerAgents = computed(() => {
    return (props.agents || []).filter(a => a.broker_company_id || a.agent_type === 'agency_agent' || a.agent_type === 'independent');
});

// Dynamic LOV filtering based on selected source & broker_company_id
const availableAgentsForLead = computed(() => {
    const source = addForm.source;
    const allAgents = props.agents || [];

    // Scenario 1: Source is Kantor Agency (Broker)
    if (['broker', 'agent'].includes(source)) {
        if (addForm.broker_company_id) {
            return allAgents.filter(a => a.broker_company_id === addForm.broker_company_id);
        }
        return allAgents.filter(a => a.broker_company_id || a.agent_type === 'agency_agent');
    }

    // Scenario 2: Source is Freelance / Independent Agent
    if (source === 'independent') {
        return allAgents.filter(a => a.agent_type === 'independent');
    }

    // Scenario 3: Source is Referral
    if (source === 'referral') {
        return allAgents.filter(a => a.agent_type === 'independent' || a.agent_type === 'agency_agent');
    }

    // Scenario 4: Direct / In-House Sales Sources
    return allAgents.filter(a => !a.broker_company_id || a.agent_type === 'inhouse');
});

watch(() => addForm.source, (newSource) => {
    if (!['broker', 'agent'].includes(newSource)) {
        addForm.broker_company_id = '';
    }
    if (newSource === 'walk_in' && addForm.project_id && props.dutyAgents?.[addForm.project_id]) {
        addForm.assigned_to = props.dutyAgents[addForm.project_id].id;
    } else {
        addForm.assigned_to = '';
    }
    if (['broker', 'agent', 'independent'].includes(newSource) && addForm.project_id && props.dutyAgents?.[addForm.project_id]) {
        addForm.inhouse_pic_id = props.dutyAgents[addForm.project_id].id;
    }
});

watch(() => addForm.project_id, (newPid) => {
    if (newPid && addForm.source === 'walk_in' && props.dutyAgents?.[newPid]) {
        addForm.assigned_to = props.dutyAgents[newPid].id;
    }
    if (newPid && ['broker', 'agent', 'independent'].includes(addForm.source) && props.dutyAgents?.[newPid]) {
        addForm.inhouse_pic_id = props.dutyAgents[newPid].id;
    }
});

watch(() => addForm.broker_company_id, (newCompanyId) => {
    if (newCompanyId) {
        const subAgents = (props.agents || []).filter(a => a.broker_company_id === newCompanyId);
        if (subAgents.length > 0) {
            addForm.assigned_to = subAgents[0].id;
        } else {
            addForm.assigned_to = '';
        }
    }
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
    broker: 'bg-amber-500/10 text-amber-700 border border-amber-500/20 font-bold', 
    agency: 'bg-amber-500/10 text-amber-700 border border-amber-500/20 font-bold', 
    agent: 'bg-amber-500/10 text-amber-700 border border-amber-500/20 font-bold',
    independent: 'bg-emerald-500/10 text-emerald-700 border border-emerald-500/20 font-bold',
    inhouse: 'bg-blue-500/10 text-blue-700 border border-blue-500/20 font-bold',
    website: 'bg-emerald-100 text-emerald-700', other: 'bg-gray-100 text-gray-700',
};

function formatSourceLabel(lead) {
    if (!lead) return '-';
    const src = lead.source;
    if (src === 'broker' || src === 'agency' || src === 'agent') {
        if (lead.broker_company) {
            return `🏢 ${lead.broker_company.name}`;
        }
        return '🏢 Kantor Agency';
    }
    if (src === 'independent') return '💼 Agen Freelance';
    if (src === 'inhouse') return '🏠 Sales In-House';
    if (src === 'walk_in') return '🚶 Walk In';
    if (src === 'facebook') return '📘 Meta Ads';
    if (src === 'instagram') return '📸 Instagram';
    if (src === 'google') return '🔍 Google Ads';
    if (src === 'tiktok') return '🎵 TikTok';
    if (src === 'referral') return '🤝 Referensi';
    if (src === 'website') return '🌐 Website';
    return src ? src.replace('_', ' ') : 'Lain-lain';
}

function formatAgentLabel(lead) {
    if (!lead) return '-';
    let parts = [];
    if (lead.assigned_to_user) {
        let label = lead.assigned_to_user.name;
        if (lead.assigned_to_user.broker_company) {
            label += ` (${lead.assigned_to_user.broker_company.name})`;
        } else if (lead.broker_company) {
            label += ` (${lead.broker_company.name})`;
        }
        parts.push(label);
    } else if (lead.broker_company) {
        parts.push(`🏢 ${lead.broker_company.name}`);
    }

    if (lead.inhouse_pic_user) {
        parts.push(`🏠 PIC: ${lead.inhouse_pic_user.name}`);
    }

    return parts.length > 0 ? parts.join(' • ') : '-';
}

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

// FOLLOW-UP MONITORING & QUICK ACTION HELPERS
const activityIcons = {
    call: '📞',
    whatsapp: '💬',
    email: '✉️',
    visit: '🏠',
    meeting: '🤝',
    note: '📝',
    status_change: '🔄',
};

const activityLabels = {
    call: 'Telepon',
    whatsapp: 'WhatsApp',
    email: 'Email',
    visit: 'Visit Lokasi',
    meeting: 'Pertemuan',
    note: 'Catatan',
    status_change: 'Ganti Status',
};

function formatFollowUpTime(dateStr) {
    if (!dateStr) return null;
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Baru saja';
    if (diffMins < 60) return `${diffMins} mnt lalu`;
    if (diffHours < 24) return `${diffHours} jam lalu`;
    if (diffDays === 1) return 'Kemarin';
    if (diffDays < 7) return `${diffDays} hr lalu`;
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}

function getFollowUpFreshness(dateStr) {
    if (!dateStr) return 'stale'; // Belum pernah follow-up
    const diffDays = Math.floor((new Date() - new Date(dateStr)) / 86400000);
    if (diffDays <= 2) return 'active'; // 🟢 Hijau (<= 2 hari)
    if (diffDays <= 6) return 'warning'; // 🟡 Kuning (3-6 hari)
    return 'stale'; // 🔴 Merah (>= 7 hari)
}

// QUICK FOLLOW-UP MODAL STATE
const showQuickFollowUpModal = ref(false);
const selectedLeadForFollowUp = ref(null);
const quickFollowUpForm = useForm({
    type: 'whatsapp',
    status: '',
    description: '',
});

function openQuickFollowUp(lead) {
    selectedLeadForFollowUp.value = lead;
    quickFollowUpForm.type = 'whatsapp';
    quickFollowUpForm.status = lead.status;
    quickFollowUpForm.description = '';
    showQuickFollowUpModal.value = true;
}

function submitQuickFollowUp() {
    if (!selectedLeadForFollowUp.value) return;
    quickFollowUpForm.post(`/leads/${selectedLeadForFollowUp.value.id}/activity`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showQuickFollowUpModal.value = false;
            quickFollowUpForm.reset();
        }
    });
}
</script>

<template>
    <Head title="Leads" />
    <CrmLayout>
        <template #breadcrumb>Leads</template>

        <!-- HEADER -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <span class="text-blue-600">⚡</span> Lead & Transaction Workspace
                </h1>
                <p class="text-sm text-slate-500 mt-1">Satu ruang kerja transaksi client terintegrasi: Follow Up → Negosiasi → Reservasi → Booking → SPR → Closing</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <!-- Duty Agent Indicator Pill -->
                <div v-if="currentDutyAgent" class="flex items-center gap-2 px-3.5 py-1.5 bg-emerald-50 text-emerald-900 border border-emerald-200/80 rounded-xl text-xs font-bold shadow-xs">
                    <span class="text-sm">🛡️</span>
                    <span class="text-[11px] text-emerald-700">Jaga Hari Ini:</span>
                    <span class="font-black text-emerald-950 underline decoration-emerald-400 decoration-2">{{ currentDutyAgent.name }}</span>
                    <span v-if="currentDutyAgent.master_lead_name" class="text-[9px] bg-purple-100 text-purple-800 px-1.5 py-0.5 rounded font-black">👑 {{ currentDutyAgent.master_lead_name }}</span>
                    <button @click="openSetDutyModal(activeProjectId)" class="text-[10px] text-blue-600 hover:text-blue-800 font-black ml-1 hover:underline cursor-pointer">Ganti</button>
                </div>
                <button v-else-if="activeProjectId" @click="openSetDutyModal(activeProjectId)" class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-700 border border-dashed border-slate-300 rounded-xl text-xs font-bold transition-all cursor-pointer">
                    <span>📅</span>
                    <span>Set Agen Jaga Hari Ini</span>
                </button>

                <Link :href="`/agent-visits?project_id=${activeProjectId}`" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 rounded-xl text-xs font-bold transition-all shadow-xs" title="Buku Tamu & Monitoring Trafik Kedatangan Agen (PK & Konten)">
                    <span>📍</span>
                    <span>Trafik & Buku Tamu Agen</span>
                    <span class="px-1.5 py-0.2 bg-purple-200/80 text-purple-800 text-[9px] font-black rounded-full">PK & Konten</span>
                </Link>

                <Link :href="`/duty-schedules?project_id=${activeProjectId}`" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all" title="Atur Jadwal Harian / Mingguan / Bulanan">
                    <span>🗓️</span>
                    <span>Jadwal Piket</span>
                </Link>

                <!-- View Toggle -->
                <div class="flex bg-slate-100 rounded-xl p-0.5">
                    <button @click="viewMode = 'table'" :class="viewMode === 'table' ? 'bg-white shadow-sm' : ''" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all">📊 Tabel</button>
                    <button @click="viewMode = 'kanban'" :class="viewMode === 'kanban' ? 'bg-white shadow-sm' : ''" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all">📋 Kanban</button>
                </div>
                <button @click="showAddModal = true"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all cursor-pointer">
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
                <option v-for="s in ['facebook','instagram','google','tiktok','walk_in','referral','broker','independent','inhouse','website','other']" :key="s" :value="s">{{ s.replace('_', ' ') }}</option>
            </select>
        </div>

        <!-- TABLE VIEW -->
        <div v-if="viewMode === 'table'" class="bg-white rounded-2xl border border-slate-100 overflow-x-auto max-w-full touch-pan-x shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[700px]">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left px-4 py-3 text-[10px] font-black text-slate-500 uppercase tracking-wider">Lead</th>
                            <th class="text-left px-4 py-3 text-[10px] font-black text-slate-500 uppercase tracking-wider hidden md:table-cell">Proyek</th>
                            <th class="text-left px-4 py-3 text-[10px] font-black text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="text-left px-4 py-3 text-[10px] font-black text-slate-500 uppercase tracking-wider min-w-[240px]">Follow-Up Terakhir</th>
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

                            <!-- FOLLOW-UP TERAKHIR -->
                            <td class="px-4 py-3 min-w-[240px]">
                                <div v-if="lead.latest_activity" class="space-y-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700">
                                            <span>{{ activityIcons[lead.latest_activity.type] || '📝' }}</span>
                                            <span>{{ activityLabels[lead.latest_activity.type] || lead.latest_activity.type }}</span>
                                        </span>
                                        
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded"
                                            :class="{
                                                'text-emerald-700 bg-emerald-50 border border-emerald-200': getFollowUpFreshness(lead.latest_activity.created_at) === 'active',
                                                'text-amber-700 bg-amber-50 border border-amber-200': getFollowUpFreshness(lead.latest_activity.created_at) === 'warning',
                                                'text-rose-700 bg-rose-50 border border-rose-200': getFollowUpFreshness(lead.latest_activity.created_at) === 'stale',
                                            }">
                                            <span class="w-1.5 h-1.5 rounded-full"
                                                :class="{
                                                    'bg-emerald-500': getFollowUpFreshness(lead.latest_activity.created_at) === 'active',
                                                    'bg-amber-500': getFollowUpFreshness(lead.latest_activity.created_at) === 'warning',
                                                    'bg-rose-500': getFollowUpFreshness(lead.latest_activity.created_at) === 'stale',
                                                }"></span>
                                            {{ formatFollowUpTime(lead.latest_activity.created_at) }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-700 line-clamp-1 italic cursor-help" :title="lead.latest_activity.description">
                                        "{{ lead.latest_activity.description }}"
                                    </p>
                                    <div class="flex items-center justify-between text-[10px] text-slate-400">
                                        <span>Oleh: <strong class="text-slate-600">{{ lead.latest_activity.user?.name || 'In-House' }}</strong></span>
                                        <button @click="openQuickFollowUp(lead)" class="text-blue-600 hover:text-blue-800 font-bold hover:underline cursor-pointer">
                                            + Update
                                        </button>
                                    </div>
                                </div>
                                <div v-else class="flex items-center justify-between gap-2 p-1.5 rounded-lg bg-amber-50/70 border border-amber-200/60">
                                    <div class="flex items-center gap-1.5 text-[11px] text-amber-800 font-medium">
                                        <span class="text-xs">⚠️</span>
                                        <span>Belum ada follow-up</span>
                                    </div>
                                    <button @click="openQuickFollowUp(lead)" class="px-2 py-0.5 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-bold rounded shadow-xs cursor-pointer">
                                        + Follow-up
                                    </button>
                                </div>
                            </td>

                            <td class="px-4 py-3 hidden lg:table-cell">
                                <span :class="sourceColors[lead.source]" class="text-[10px] font-bold px-2.5 py-1 rounded-full border">
                                    {{ formatSourceLabel(lead) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 hidden lg:table-cell">
                                <span class="text-xs font-semibold text-slate-700">{{ formatAgentLabel(lead) }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="scoreColor(lead.score)" class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-xs font-black">{{ lead.score }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button @click="openQuickFollowUp(lead)" title="Catat Follow-up Cepat" class="px-2 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-bold rounded-lg border border-blue-200 transition-all flex items-center gap-1 cursor-pointer">
                                        <span>💬</span>
                                        <span class="hidden xl:inline">Follow-up</span>
                                    </button>
                                    <Link :href="`/leads/${lead.id}`" class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white text-xs font-bold rounded-lg shadow-sm hover:shadow transition-all">
                                        <span>⚡ Workspace</span>
                                    </Link>
                                    <button @click="deleteLead(lead.id)" title="Hapus Lead" class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-rose-50 text-slate-400 hover:text-rose-500 transition-colors">
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
                        <div class="flex items-start justify-between mb-1.5">
                            <p class="text-sm font-bold text-slate-900 leading-tight">{{ lead.name }}</p>
                            <span :class="scoreColor(lead.score)" class="w-6 h-6 rounded-md flex items-center justify-center text-[10px] font-black shrink-0">{{ lead.score }}</span>
                        </div>
                        <p class="text-xs text-slate-500 mb-2">{{ lead.phone }}</p>

                        <!-- Follow-up snippet in Kanban card -->
                        <div v-if="lead.latest_activity" class="bg-slate-50/90 rounded-xl p-2 mb-2 text-[11px] border border-slate-100 space-y-1">
                            <div class="flex items-center justify-between text-[10px]">
                                <span class="font-bold text-slate-700 flex items-center gap-1">
                                    <span>{{ activityIcons[lead.latest_activity.type] || '📝' }}</span>
                                    <span>{{ activityLabels[lead.latest_activity.type] || lead.latest_activity.type }}</span>
                                </span>
                                <span class="font-bold text-[10px]" :class="{
                                    'text-emerald-600': getFollowUpFreshness(lead.latest_activity.created_at) === 'active',
                                    'text-amber-600': getFollowUpFreshness(lead.latest_activity.created_at) === 'warning',
                                    'text-rose-600': getFollowUpFreshness(lead.latest_activity.created_at) === 'stale',
                                }">
                                    {{ formatFollowUpTime(lead.latest_activity.created_at) }}
                                </span>
                            </div>
                            <p class="text-slate-600 line-clamp-2 italic">"{{ lead.latest_activity.description }}"</p>
                        </div>
                        <div v-else class="text-[10px] text-amber-700 bg-amber-50 border border-amber-200/60 rounded-xl px-2 py-1 mb-2 font-medium flex items-center justify-between">
                            <span>⚠️ Belum follow-up</span>
                            <button @click.stop="openQuickFollowUp(lead)" class="text-blue-600 font-bold hover:underline cursor-pointer">
                                + Catat
                            </button>
                        </div>

                        <div class="flex items-center justify-between gap-1 pt-1 border-t border-slate-50">
                            <span :class="sourceColors[lead.source]" class="text-[9px] font-bold px-1.5 py-0.5 rounded border truncate max-w-[110px]">
                                {{ formatSourceLabel(lead) }}
                            </span>
                            <div class="flex items-center gap-1 shrink-0">
                                <button @click.stop="openQuickFollowUp(lead)" class="px-2 py-0.5 bg-blue-50 hover:bg-blue-600 text-blue-600 hover:text-white rounded text-[10px] font-bold transition-all cursor-pointer">
                                    + Follow-up
                                </button>
                            </div>
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
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Sumber Prospek <span class="text-rose-500">*</span></label>
                                <select v-model="addForm.source" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer font-bold">
                                    <option value="broker">🏢 Kantor Agency & Sub-Agent</option>
                                    <option value="independent">💼 Agen Freelance Independen</option>
                                    <option value="inhouse">🏠 Tim Sales In-House</option>
                                    <option value="walk_in">🚶 Walk In / Pameran</option>
                                    <option value="facebook">📘 Meta / Facebook Ads</option>
                                    <option value="instagram">📸 Instagram Ads</option>
                                    <option value="google">🔍 Google Ads</option>
                                    <option value="tiktok">🎵 TikTok Ads</option>
                                    <option value="referral">🤝 Referensi</option>
                                    <option value="website">🌐 Website</option>
                                    <option value="other">📁 Lain-lain</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Proyek</label>
                                <select v-model="addForm.project_id" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                    <option value="">Pilih Proyek</option>
                                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                            </div>

                            <!-- Pilihan Kantor Agency (Hanya jika sumber Kantor Agency) -->
                            <!-- Walk-In Duty Agent Alert -->
                            <div v-if="addForm.source === 'walk_in' && addForm.project_id && dutyAgents[addForm.project_id]" class="col-span-2 p-3 bg-emerald-50 rounded-2xl border border-emerald-200/80 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">🛡️</span>
                                    <div>
                                        <div class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Petugas Jaga Hari Ini (Piket)</div>
                                        <div class="text-xs font-black text-slate-900">{{ dutyAgents[addForm.project_id].name }}</div>
                                    </div>
                                </div>
                                <span class="text-[10px] bg-emerald-600 text-white font-bold px-2 py-0.5 rounded-md">Otomatis Ditugaskan</span>
                            </div>

                            <!-- Pilihan Kantor Agency (Hanya jika sumber Kantor Agency) -->
                            <div v-if="['broker', 'agent'].includes(addForm.source)" class="col-span-2 p-3 bg-amber-50 rounded-2xl border border-amber-200/60 space-y-1.5">
                                <label class="block text-xs font-bold text-amber-900">1. Pilih Kantor Agency (Broker Company) *</label>
                                <select v-model="addForm.broker_company_id" class="w-full px-4 py-2.5 border border-amber-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500/20 cursor-pointer font-bold bg-white text-amber-950">
                                    <option value="">-- Pilih Kantor Agency --</option>
                                    <option v-for="b in broker_companies" :key="b.id" :value="b.id">🏢 {{ b.name }} ({{ b.code || 'NO-CODE' }})</option>
                                </select>
                            </div>

                            <!-- Pilihan Agen / Sub-Agent LOV Dynamic -->
                            <div class="col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                    <span v-if="['broker', 'agent'].includes(addForm.source)">2. Pilih Sub-Agent di Kantor Terpilih (Penerima Komisi)</span>
                                    <span v-else-if="addForm.source === 'independent'">Pilih Agen Freelance Independen (Penerima Komisi)</span>
                                    <span v-else>Pilih Sales In-House Penanggung Jawab</span>
                                </label>

                                <select v-model="addForm.assigned_to" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer font-bold">
                                    <option value="">
                                        {{ ['broker', 'agent'].includes(addForm.source) ? '-- Auto-Assign Kantor / Semua Sub-Agent --' : (addForm.source === 'walk_in' && addForm.project_id && dutyAgents[addForm.project_id] ? `-- Otomatis: ${dutyAgents[addForm.project_id].name} (Jaga Hari Ini) --` : 'Auto-assign (Round Robin In-House)') }}
                                    </option>

                                    <option v-for="a in availableAgentsForLead" :key="a.id" :value="a.id">
                                        {{ a.agent_type === 'agency_agent' ? '🏢' : (a.agent_type === 'independent' ? '💼' : '🏠') }} 
                                        {{ a.name }} 
                                        <template v-if="a.broker_company"> ({{ a.broker_company.name }})</template>
                                    </option>
                                </select>

                                <p v-if="['broker', 'agent'].includes(addForm.source) && addForm.broker_company_id && availableAgentsForLead.length === 0" class="text-xs text-amber-600 mt-1 italic font-medium">
                                    Belum ada sub-agent terdaftar di kantor ini (Lead akan dialokasikan ke Kantor Agency).
                                </p>
                            </div>

                            <!-- Sales In-House Pendamping Developer untuk Tamu Broker -->
                            <div v-if="['broker', 'agent', 'independent'].includes(addForm.source)" class="col-span-2 p-3 bg-indigo-50/70 border border-indigo-200 rounded-2xl space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label class="block text-xs font-bold text-indigo-950">Sales In-House Pendamping Developer</label>
                                    <span v-if="addForm.project_id && dutyAgents[addForm.project_id]" class="text-[10px] text-indigo-700 font-bold">
                                        Default: Agen Jaga ({{ dutyAgents[addForm.project_id].name }})
                                    </span>
                                </div>
                                <select v-model="addForm.inhouse_pic_id" class="w-full px-4 py-2.5 border border-indigo-300 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500/20 cursor-pointer font-bold bg-white text-slate-800">
                                    <option value="">-- Tanpa Sales Pendamping / Pilih Nanti --</option>
                                    <option v-for="a in inhouseAgents" :key="a.id" :value="a.id">
                                        🏠 {{ a.name }} {{ (addForm.project_id && dutyAgents[addForm.project_id]?.id === a.id) ? '(⭐ Jaga Hari Ini)' : '' }}
                                    </option>
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

        <!-- SET DUTY AGENT MODAL -->
        <teleport to="body">
            <div v-if="showSetDutyModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showSetDutyModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                        <div>
                            <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                                <span>📅</span> Jadwal Jaga Kantor Pemasaran
                            </h2>
                            <p class="text-xs text-slate-400 mt-0.5">Tentukan Sales In-House atau Sub-Agent Master Lead yang bertugas piket</p>
                        </div>
                        <button @click="showSetDutyModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg cursor-pointer">✕</button>
                    </div>

                    <!-- TAB SELECTOR IN MODAL -->
                    <div class="flex bg-slate-100 p-1 rounded-xl mb-4">
                        <button 
                            type="button"
                            @click="setDutyTab = 'daily'" 
                            :class="[setDutyTab === 'daily' ? 'bg-white text-blue-600 shadow-xs' : 'text-slate-500', 'flex-1 py-1.5 text-xs font-black rounded-lg transition-all cursor-pointer']">
                            📆 Harian
                        </button>
                        <button 
                            type="button"
                            @click="setDutyTab = 'weekly'" 
                            :class="[setDutyTab === 'weekly' ? 'bg-white text-blue-600 shadow-xs' : 'text-slate-500', 'flex-1 py-1.5 text-xs font-black rounded-lg transition-all cursor-pointer']">
                            🗓️ Mingguan (Seminggu)
                        </button>
                        <button 
                            type="button"
                            @click="setDutyTab = 'upcoming'" 
                            :class="[setDutyTab === 'upcoming' ? 'bg-white text-blue-600 shadow-xs' : 'text-slate-500', 'flex-1 py-1.5 text-xs font-black rounded-lg transition-all cursor-pointer']">
                            📋 Terjadwal ({{ upcomingDutySchedules?.length || 0 }})
                        </button>
                    </div>

                    <!-- TAB 1: HARIAN -->
                    <form v-if="setDutyTab === 'daily'" @submit.prevent="submitSetDailyDuty" class="space-y-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Tanggal Piket <span class="text-rose-500">*</span></label>
                            <div class="flex items-center gap-2">
                                <input type="date" v-model="setDutyForm.duty_date" required class="flex-1 px-3 py-2 border border-slate-200 rounded-xl font-bold bg-slate-50 focus:bg-white text-slate-800" />
                                <button type="button" @click="setDutyForm.duty_date = new Date().toISOString().split('T')[0]" class="px-2.5 py-2 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-xl font-black">
                                    Hari Ini
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Proyek Perumahan <span class="text-rose-500">*</span></label>
                            <select v-model="setDutyForm.project_id" class="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold bg-slate-50 focus:bg-white text-slate-800">
                                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Petugas Jaga (In-House / Sub-Agent ML) <span class="text-rose-500">*</span></label>
                            <select v-model="setDutyForm.user_id" required class="w-full px-3 py-2.5 border border-slate-200 rounded-xl font-bold bg-slate-50 focus:bg-white text-slate-800">
                                <option value="">-- Pilih Petugas Jaga --</option>
                                <optgroup label="🏠 Sales In-House Developer">
                                    <option v-for="a in candidateDutyAgentsList.filter(a => a.category === 'inhouse')" :key="a.id" :value="a.id">
                                        🏠 {{ a.name }}
                                    </option>
                                </optgroup>
                                <optgroup label="👑 Sub-Agent & Tim Master Lead">
                                    <option v-for="a in candidateDutyAgentsList.filter(a => a.category === 'master_lead_sub_agent')" :key="a.id" :value="a.id">
                                        👑 {{ a.name }} (ML: {{ a.master_lead_name || 'Master Lead' }})
                                    </option>
                                </optgroup>
                                <optgroup label="💼 Mitra Agency / Freelance">
                                    <option v-for="a in candidateDutyAgentsList.filter(a => a.category === 'agency')" :key="a.id" :value="a.id">
                                        💼 {{ a.name }} ({{ a.broker_company_name || 'Agency' }})
                                    </option>
                                </optgroup>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Shift / Jam Jaga</label>
                            <select v-model="setDutyForm.shift" class="w-full px-3 py-2 border border-slate-200 rounded-xl font-medium bg-slate-50 focus:bg-white text-slate-800">
                                <option value="full_day">Full Day (Pagi - Sore / Tutup Kantor)</option>
                                <option value="pagi">Shift 1 (Pagi - Siang: 08:30 - 13:00)</option>
                                <option value="siang">Shift 2 (Siang - Sore: 13:00 - 18:00)</option>
                            </select>
                        </div>

                        <div class="p-3 bg-emerald-50 border border-emerald-200/80 rounded-2xl text-[11px] text-emerald-800 leading-relaxed">
                            🛡️ <strong>Otomatisasi Walk-In:</strong> Setiap tamu Walk-In baru yang masuk pada tanggal ini akan otomatis diarahkan ke Petugas Jaga yang dipilih di sini.
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                            <Link :href="`/duty-schedules?project_id=${setDutyForm.project_id}`" class="text-blue-600 hover:underline font-bold text-[11px]">
                                📅 Buka Kalender & Rotasi Bulanan →
                            </Link>
                            <div class="flex gap-2">
                                <button type="button" @click="showSetDutyModal = false" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl font-bold">Batal</button>
                                <button type="submit" :disabled="setDutyForm.processing" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-black shadow-md shadow-emerald-500/20 disabled:opacity-50">
                                    {{ setDutyForm.processing ? 'Menyimpan...' : 'Tetapkan Agen Jaga' }}
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- TAB 2: MINGGUAN -->
                    <form v-if="setDutyTab === 'weekly'" @submit.prevent="submitSetWeeklyDuty" class="space-y-4 text-xs">
                        <div class="p-3 bg-blue-50 border border-blue-200 rounded-2xl text-[11px] text-blue-900 leading-relaxed">
                            🗓️ <strong>Jadwal Mingguan:</strong> Tentukan 1 agen yang bertugas penuh selama 1 minggu (Senin s/d Minggu).
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Tanggal Mulai (Senin) *</label>
                                <input type="date" v-model="setWeeklyForm.start_date" required class="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold bg-slate-50" />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Tanggal Selesai (Minggu) *</label>
                                <input type="date" v-model="setWeeklyForm.end_date" required class="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold bg-slate-50" />
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Petugas Jaga Seminggu *</label>
                            <select v-model="setWeeklyForm.user_id" required class="w-full px-3 py-2.5 border border-slate-200 rounded-xl font-bold bg-slate-50 focus:bg-white text-slate-800">
                                <option value="">-- Pilih Petugas Jaga --</option>
                                <optgroup label="🏠 Sales In-House Developer">
                                    <option v-for="a in candidateDutyAgentsList.filter(a => a.category === 'inhouse')" :key="a.id" :value="a.id">
                                        🏠 {{ a.name }}
                                    </option>
                                </optgroup>
                                <optgroup label="👑 Sub-Agent & Tim Master Lead">
                                    <option v-for="a in candidateDutyAgentsList.filter(a => a.category === 'master_lead_sub_agent')" :key="a.id" :value="a.id">
                                        👑 {{ a.name }} (ML: {{ a.master_lead_name || 'Master Lead' }})
                                    </option>
                                </optgroup>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Shift</label>
                            <select v-model="setWeeklyForm.shift" class="w-full px-3 py-2 border border-slate-200 rounded-xl font-medium bg-slate-50">
                                <option value="full_day">Full Day (Pagi - Sore)</option>
                                <option value="pagi">Shift 1 (Pagi - Siang)</option>
                                <option value="siang">Shift 2 (Siang - Malam)</option>
                            </select>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                            <Link :href="`/duty-schedules?project_id=${setDutyForm.project_id}`" class="text-blue-600 hover:underline font-bold text-[11px]">
                                📅 Atur Roster Per Hari / Bulanan →
                            </Link>
                            <div class="flex gap-2">
                                <button type="button" @click="showSetDutyModal = false" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl font-bold">Batal</button>
                                <button type="submit" :disabled="setWeeklyForm.processing" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-black shadow-md disabled:opacity-50">
                                    {{ setWeeklyForm.processing ? 'Menyimpan...' : 'Simpan 1 Minggu' }}
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- TAB 3: UPCOMING ROSTER LIST -->
                    <div v-if="setDutyTab === 'upcoming'" class="space-y-3 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-700">Jadwal Terdaftar Proyek Ini</span>
                            <Link :href="`/duty-schedules?project_id=${activeProjectId}`" class="text-blue-600 font-bold hover:underline text-[11px]">
                                Buka Kalender Lengkap →
                            </Link>
                        </div>

                        <div class="max-h-64 overflow-y-auto space-y-2 pr-1">
                            <div 
                                v-for="item in upcomingDutySchedules" 
                                :key="item.id" 
                                :class="[item.is_today ? 'bg-emerald-50 border-emerald-300 ring-1 ring-emerald-400' : 'bg-slate-50 border-slate-200', 'p-3 rounded-2xl border flex items-center justify-between']">
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-black text-slate-900">{{ item.day_name }}, {{ item.date_formatted }}</span>
                                        <span v-if="item.is_today" class="px-1.5 py-0.2 bg-emerald-600 text-white text-[9px] font-black rounded">HARI INI</span>
                                    </div>
                                    <p class="text-[11px] text-slate-600 font-bold mt-0.5">
                                        {{ item.user_name }}
                                        <span class="text-[10px] text-slate-400 font-normal">({{ item.user_category }})</span>
                                    </p>
                                </div>
                                <span class="text-[10px] text-slate-500 font-medium">{{ item.shift_label }}</span>
                            </div>

                            <div v-if="!upcomingDutySchedules || upcomingDutySchedules.length === 0" class="text-center py-8 text-slate-400">
                                Belum ada jadwal terdaftar untuk beberapa hari ke depan.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </teleport>

        <!-- QUICK FOLLOW-UP MODAL (PENCATATAN & MONITORING UPDATE FOLLOW-UP) -->
        <teleport to="body">
            <div v-if="showQuickFollowUpModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showQuickFollowUpModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <div>
                            <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                                <span>⚡</span> Catat Follow-Up Prospek
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ selectedLeadForFollowUp?.name }} • {{ selectedLeadForFollowUp?.phone }}
                            </p>
                        </div>
                        <button @click="showQuickFollowUpModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg cursor-pointer">✕</button>
                    </div>

                    <form @submit.prevent="submitQuickFollowUp" class="space-y-4 text-xs">
                        <!-- Lead Card Info & Direct WhatsApp -->
                        <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200/80 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-black text-slate-900">{{ selectedLeadForFollowUp?.name }}</p>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Proyek: <strong>{{ selectedLeadForFollowUp?.project?.name || 'Semua Proyek' }}</strong> • 
                                    Status Saat Ini: <span class="font-bold capitalize">{{ selectedLeadForFollowUp?.status }}</span>
                                </p>
                            </div>
                            <a v-if="selectedLeadForFollowUp?.phone"
                                :href="`https://wa.me/${selectedLeadForFollowUp.phone.replace(/^0/, '62').replace(/[^0-9]/g, '')}?text=${encodeURIComponent('Halo Bpk/Ibu ' + selectedLeadForFollowUp.name + ', perkenalkan saya dari Tim Developer ' + (selectedLeadForFollowUp.project?.name || 'Homi') + '...')}`"
                                target="_blank"
                                class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold flex items-center gap-1.5 shadow-sm transition-all shrink-0">
                                <span>💬</span>
                                <span>Chat WA</span>
                            </a>
                        </div>

                        <!-- Interaction Media Type -->
                        <div>
                            <label class="block font-bold text-slate-700 mb-1.5">Jenis Aktivitas Kontak <span class="text-rose-500">*</span></label>
                            <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                                <button v-for="t in ['whatsapp', 'call', 'visit', 'meeting', 'note']" :key="t"
                                    type="button"
                                    @click="quickFollowUpForm.type = t"
                                    :class="quickFollowUpForm.type === t ? 'bg-blue-600 text-white font-black shadow-md' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 font-bold'"
                                    class="py-2.5 px-2 rounded-xl text-center text-xs transition-all capitalize cursor-pointer flex flex-col items-center gap-1">
                                    <span class="text-base">{{ activityIcons[t] }}</span>
                                    <span>{{ activityLabels[t] }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Update Status (Optional) -->
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Update Status Prospek (Opsional)</label>
                            <select v-model="quickFollowUpForm.status" class="w-full px-3 py-2.5 border border-slate-200 rounded-xl font-bold bg-slate-50 focus:bg-white text-slate-800">
                                <option v-for="col in kanbanColumns" :key="col.key" :value="col.key">
                                    {{ col.icon }} {{ col.label }} {{ col.key === selectedLeadForFollowUp?.status ? '(Status Saat Ini)' : '' }}
                                </option>
                            </select>
                            <p class="text-[10px] text-slate-400 mt-1">Status tahapan prospek akan otomatis diperbarui dan tercatat pada linimasa aktivitas.</p>
                        </div>

                        <!-- Description Notes -->
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Catatan & Hasil Komunikasi Follow-Up <span class="text-rose-500">*</span></label>
                            <textarea v-model="quickFollowUpForm.description" rows="3" required
                                placeholder="Contoh: Konsumen tertarik tipe 36 Hook, sudah dikirim price list dan simulasi KPR. Rencana survey lokasi hari Sabtu jam 10..."
                                class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 resize-none"></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                            <button type="button" @click="showQuickFollowUpModal = false" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl font-bold transition-colors">
                                Batal
                            </button>
                            <button type="submit" :disabled="quickFollowUpForm.processing"
                                class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-black rounded-xl shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 transition-all disabled:opacity-50 cursor-pointer">
                                {{ quickFollowUpForm.processing ? 'Menyimpan...' : 'Simpan Follow-Up' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>
    </CrmLayout>
</template>
