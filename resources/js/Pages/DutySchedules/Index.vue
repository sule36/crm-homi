<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const props = defineProps({
    projects: Array,
    selectedProjectId: Number,
    selectedMonth: Number,
    selectedYear: Number,
    schedules: Array,
    todayAgents: Object,
    candidateAgents: Array,
    brokerCompanies: Array,
    agentVisits: Array,
    trafficAnalytics: Object,
    visitPeriodFilter: String,
    currentUser: Object,
});

// Active project filter
const activeProjectId = ref(props.selectedProjectId || (props.projects?.[0]?.id || ''));
const activeMonth = ref(props.selectedMonth || new Date().getMonth() + 1);
const activeYear = ref(props.selectedYear || new Date().getFullYear());

// Active Tab: 'daily' | 'weekly' | 'monthly' | 'roster' | 'visits'
const getQueryParam = (param) => {
    if (typeof window === 'undefined') return null;
    return new URLSearchParams(window.location.search).get(param);
};
const initialTab = getQueryParam('tab');
const activeTab = ref(['daily', 'weekly', 'monthly', 'roster', 'visits'].includes(initialTab) ? initialTab : 'daily');

// Watch project or month change to reload data
watch([activeProjectId, activeMonth, activeYear], ([pId, m, y]) => {
    router.get('/duty-schedules', {
        project_id: pId,
        month: m,
        year: y,
        tab: activeTab.value,
        visit_period: visitPeriodFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

// Currently selected project object
const currentProject = computed(() => {
    return (props.projects || []).find(p => p.id == activeProjectId.value) || props.projects?.[0] || null;
});

// Today's duty agent for the selected project
const todayDutyAgent = computed(() => {
    if (!activeProjectId.value || !props.todayAgents) return null;
    return props.todayAgents[activeProjectId.value] || null;
});

// Filter candidate agents by category tab if needed
const agentFilterCategory = ref('all'); // 'all', 'inhouse', 'master_lead', 'agency'
const filteredCandidateAgents = computed(() => {
    const list = props.candidateAgents || [];
    if (agentFilterCategory.value === 'all') return list;
    if (agentFilterCategory.value === 'inhouse') return list.filter(a => a.category === 'inhouse');
    if (agentFilterCategory.value === 'master_lead') return list.filter(a => a.category === 'master_lead_sub_agent');
    if (agentFilterCategory.value === 'agency') return list.filter(a => a.category === 'agency');
    return list;
});

// Quick agent search query
const agentSearchQuery = ref('');
const searchedCandidateAgents = computed(() => {
    const q = agentSearchQuery.value.toLowerCase().trim();
    if (!q) return filteredCandidateAgents.value;
    return filteredCandidateAgents.value.filter(a => 
        (a.name || '').toLowerCase().includes(q) || 
        (a.category_label || '').toLowerCase().includes(q) ||
        (a.master_lead_name || '').toLowerCase().includes(q) ||
        (a.broker_company_name || '').toLowerCase().includes(q)
    );
});

// -------------------------------------------------------------
// 1. HARIAN (DAILY FORM)
// -------------------------------------------------------------
const todayStr = new Date().toISOString().split('T')[0];
const dailyForm = useForm({
    project_id: activeProjectId.value,
    duty_date: todayStr,
    user_id: '',
    shift: 'full_day',
    notes: 'Jaga Kantor Pemasaran',
});

watch(activeProjectId, (newPid) => {
    dailyForm.project_id = newPid;
    weeklyForm.project_id = newPid;
    monthlyRotationForm.project_id = newPid;
});

function setDailyDateShortcut(daysOffset) {
    const d = new Date();
    d.setDate(d.getDate() + daysOffset);
    dailyForm.duty_date = d.toISOString().split('T')[0];
}

function submitDailyForm() {
    dailyForm.project_id = activeProjectId.value;
    dailyForm.post('/duty-schedules/set-daily', {
        preserveScroll: true,
        onSuccess: () => {
            dailyForm.notes = 'Jaga Kantor Pemasaran';
        }
    });
}

// -------------------------------------------------------------
// 2. MINGGUAN (WEEKLY FORM)
// -------------------------------------------------------------
// Calculate current week Monday to Sunday
function getMonday(d) {
    d = new Date(d);
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1);
    return new Date(d.setDate(diff));
}

const currentWeekMonday = ref(getMonday(new Date()));

const weekDays = computed(() => {
    const days = [];
    const dayNames = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    const mon = new Date(currentWeekMonday.value);

    for (let i = 0; i < 7; i++) {
        const d = new Date(mon);
        d.setDate(mon.getDate() + i);
        const dateStr = d.toISOString().split('T')[0];
        
        // Find if already scheduled
        const existing = (props.schedules || []).find(s => s.duty_date === dateStr);

        days.push({
            date: dateStr,
            dayName: dayNames[i],
            dateFormatted: d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }),
            isToday: dateStr === todayStr,
            existingUserId: existing?.user_id || '',
            existingUserName: existing?.user?.name || '',
            existingCategoryLabel: existing?.user?.category_label || '',
        });
    }
    return days;
});

const weeklyMode = ref('custom'); // 'single' | 'custom'

const weeklyForm = useForm({
    project_id: activeProjectId.value,
    mode: 'custom',
    user_id: '',
    start_date: '',
    end_date: '',
    shift: 'full_day',
    notes: 'Jadwal Mingguan',
    schedules: [],
});

// Initialize weekly schedules array
watch(weekDays, (days) => {
    weeklyForm.schedules = days.map(d => ({
        date: d.date,
        user_id: d.existingUserId || '',
        shift: 'full_day',
        notes: `Jaga ${d.dayName}`,
    }));
}, { immediate: true });

function navigateWeek(direction) {
    const d = new Date(currentWeekMonday.value);
    d.setDate(d.getDate() + (direction * 7));
    currentWeekMonday.value = d;
}

function copyMondayToAll() {
    const mondayUser = weeklyForm.schedules[0]?.user_id;
    if (!mondayUser) return;
    weeklyForm.schedules.forEach(item => {
        item.user_id = mondayUser;
    });
}

function submitWeeklyForm() {
    weeklyForm.project_id = activeProjectId.value;
    weeklyForm.mode = weeklyMode.value;

    if (weeklyMode.value === 'single') {
        weeklyForm.start_date = weekDays.value[0]?.date;
        weeklyForm.end_date = weekDays.value[6]?.date;
    }

    weeklyForm.post('/duty-schedules/set-weekly', {
        preserveScroll: true,
    });
}

// -------------------------------------------------------------
// 3. BULANAN (MONTHLY ROTATION & CALENDAR)
// -------------------------------------------------------------
const monthlyTab = ref('calendar'); // 'calendar' | 'rotation'

const monthlyRotationForm = useForm({
    project_id: activeProjectId.value,
    month: activeMonth.value,
    year: activeYear.value,
    mode: 'rotation',
    user_ids: [],
    rotation_pattern: 'daily', // 'daily' | 'weekly'
    skip_weekends: false,
    shift: 'full_day',
    notes: 'Rotasi Bulanan Tim Sales',
});

function toggleRotationAgent(userId) {
    const idx = monthlyRotationForm.user_ids.indexOf(userId);
    if (idx > -1) {
        monthlyRotationForm.user_ids.splice(idx, 1);
    } else {
        monthlyRotationForm.user_ids.push(userId);
    }
}

function selectAllInhouseForRotation() {
    const inhouseIds = (props.candidateAgents || [])
        .filter(a => a.category === 'inhouse')
        .map(a => a.id);
    monthlyRotationForm.user_ids = Array.from(new Set([...monthlyRotationForm.user_ids, ...inhouseIds]));
}

function selectAllMasterLeadForRotation() {
    const mlIds = (props.candidateAgents || [])
        .filter(a => a.category === 'master_lead_sub_agent')
        .map(a => a.id);
    monthlyRotationForm.user_ids = Array.from(new Set([...monthlyRotationForm.user_ids, ...mlIds]));
}

function clearRotationAgents() {
    monthlyRotationForm.user_ids = [];
}

function submitMonthlyRotation() {
    if (monthlyRotationForm.user_ids.length === 0) {
        alert('Silakan pilih minimal 1 agen untuk rotasi bulanan.');
        return;
    }
    monthlyRotationForm.project_id = activeProjectId.value;
    monthlyRotationForm.month = activeMonth.value;
    monthlyRotationForm.year = activeYear.value;
    monthlyRotationForm.post('/duty-schedules/set-monthly', {
        preserveScroll: true,
    });
}

// Monthly Calendar Matrix Generation
const calendarDays = computed(() => {
    const y = activeYear.value;
    const m = activeMonth.value;
    const firstDay = new Date(y, m - 1, 1);
    const lastDay = new Date(y, m, 0);
    const daysInMonth = lastDay.getDate();
    
    // Day of week for 1st day (0 = Sunday, 1 = Monday...)
    let startingDayOfWeek = firstDay.getDay();
    // Adjust to Monday-first: Mon=0, Tue=1, ... Sun=6
    startingDayOfWeek = startingDayOfWeek === 0 ? 6 : startingDayOfWeek - 1;

    const matrix = [];
    
    // Previous month padding
    for (let i = 0; i < startingDayOfWeek; i++) {
        matrix.push({ isPadding: true });
    }

    // Days in current month
    for (let day = 1; day <= daysInMonth; day++) {
        const monthPad = String(m).padStart(2, '0');
        const dayPad = String(day).padStart(2, '0');
        const dateStr = `${y}-${monthPad}-${dayPad}`;

        const sched = (props.schedules || []).find(s => s.duty_date === dateStr);

        matrix.push({
            isPadding: false,
            dayNumber: day,
            dateStr: dateStr,
            isToday: dateStr === todayStr,
            schedule: sched || null,
        });
    }

    return matrix;
});

// Quick Set Single Day from Calendar
const quickModalDate = ref('');
const showQuickSetModal = ref(false);
const quickForm = useForm({
    project_id: activeProjectId.value,
    duty_date: '',
    user_id: '',
    shift: 'full_day',
    notes: 'Jaga Pemasaran (Set via Kalender)',
});

function openQuickSet(dateStr, existingSchedule) {
    quickModalDate.value = dateStr;
    quickForm.project_id = activeProjectId.value;
    quickForm.duty_date = dateStr;
    quickForm.user_id = existingSchedule?.user_id || '';
    quickForm.shift = existingSchedule?.shift || 'full_day';
    quickForm.notes = existingSchedule?.notes || 'Jaga Pemasaran';
    showQuickSetModal.value = true;
}

function submitQuickSet() {
    quickForm.post('/duty-schedules/set-daily', {
        preserveScroll: true,
        onSuccess: () => {
            showQuickSetModal.value = false;
        }
    });
}

function deleteSchedule(schedId) {
    if (confirm('Yakin ingin menghapus jadwal piket ini?')) {
        router.delete(`/duty-schedules/${schedId}`, {
            preserveScroll: true,
        });
    }
}

// -------------------------------------------------------------
// 5. BUKU KUNJUNGAN & TRAFIK AGEN DI LOKASI (PK & KONTEN)
// -------------------------------------------------------------
const getLocalTime = () => {
    const d = new Date();
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
};

const isVisitModalOpen = ref(false);
const editingVisit = ref(null);
const visitAgentMode = ref('existing'); // 'existing' | 'manual'
const visitorSearchQuery = ref('');
const visitPeriodFilter = ref(props.visitPeriodFilter || 'month');
const visitPurposeFilter = ref('all');
const visitSearchQuery = ref('');

const visitForm = useForm({
    project_id: activeProjectId.value,
    visit_date: todayStr,
    visit_time: getLocalTime(),
    agent_id: '',
    agent_name: '',
    agent_phone: '',
    agency_name: '',
    broker_company_id: '',
    agent_type: 'agency_agent',
    purpose: 'pk', // 'pk', 'konten', 'survey_klien', 'koordinasi', 'lainnya'
    visitor_count: 1,
    notes: '',
    documentation_url: '',
});

function openAddVisitModal(defaultPurpose = 'pk') {
    editingVisit.value = null;
    visitAgentMode.value = 'existing';
    visitorSearchQuery.value = '';
    visitForm.reset();
    visitForm.project_id = activeProjectId.value;
    visitForm.visit_date = new Date().toISOString().split('T')[0];
    visitForm.visit_time = getLocalTime();
    visitForm.purpose = defaultPurpose;
    visitForm.visitor_count = 1;
    isVisitModalOpen.value = true;
}

function openEditVisitModal(visit) {
    editingVisit.value = visit;
    visitAgentMode.value = visit.agent_id ? 'existing' : 'manual';
    visitorSearchQuery.value = visit.agent_name || '';
    visitForm.project_id = visit.project_id;
    visitForm.visit_date = visit.visit_date;
    visitForm.visit_time = visit.visit_time || getLocalTime();
    visitForm.agent_id = visit.agent_id || '';
    visitForm.agent_name = visit.agent_name || '';
    visitForm.agent_phone = visit.agent_phone || '';
    visitForm.agency_name = visit.agency_name || '';
    visitForm.broker_company_id = '';
    visitForm.agent_type = visit.agent_type || 'agency_agent';
    visitForm.purpose = visit.purpose;
    visitForm.visitor_count = visit.visitor_count || 1;
    visitForm.notes = visit.notes || '';
    visitForm.documentation_url = visit.documentation_url || '';
    isVisitModalOpen.value = true;
}

function onSelectVisitorAgent(agent) {
    if (!agent) {
        visitForm.agent_id = '';
        return;
    }
    visitForm.agent_id = agent.id;
    visitForm.agent_name = agent.name;
    visitForm.agent_phone = agent.phone || '';
    visitForm.broker_company_id = agent.broker_company_id || '';
    visitForm.agent_type = agent.agent_type || 'agency_agent';
    visitForm.agency_name = agent.broker_company_name || 
        (agent.master_lead_name ? `Sub-Agent ML (${agent.master_lead_name})` : 
        (agent.category === 'inhouse' ? 'Sales In-House Developer' : 'Mitra Agen'));
}

function submitVisitForm() {
    visitForm.project_id = activeProjectId.value;
    if (editingVisit.value) {
        visitForm.put(`/agent-visits/${editingVisit.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isVisitModalOpen.value = false;
            }
        });
    } else {
        visitForm.post('/agent-visits', {
            preserveScroll: true,
            onSuccess: () => {
                isVisitModalOpen.value = false;
                visitForm.reset();
            }
        });
    }
}

function deleteVisit(visit) {
    if (confirm(`Apakah Anda yakin ingin menghapus catatan kedatangan agen ${visit.agent_name}?`)) {
        router.delete(`/agent-visits/${visit.id}`, {
            preserveScroll: true,
        });
    }
}

function changeVisitPeriod(period) {
    visitPeriodFilter.value = period;
    router.get('/duty-schedules', {
        project_id: activeProjectId.value,
        month: activeMonth.value,
        year: activeYear.value,
        tab: 'visits',
        visit_period: period,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const filteredVisitsList = computed(() => {
    let list = props.agentVisits || [];
    if (visitPurposeFilter.value !== 'all') {
        list = list.filter(v => v.purpose === visitPurposeFilter.value);
    }
    const q = visitSearchQuery.value.toLowerCase().trim();
    if (q) {
        list = list.filter(v => 
            (v.agent_name || '').toLowerCase().includes(q) ||
            (v.agency_name || '').toLowerCase().includes(q) ||
            (v.notes || '').toLowerCase().includes(q) ||
            (v.recorded_by_name || '').toLowerCase().includes(q)
        );
    }
    return list;
});

const maxDailyTrendTraffic = computed(() => {
    const list = props.trafficAnalytics?.daily_trend || [];
    return Math.max(...list.map(d => d.total), 1);
});

const monthNames = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

</script>

<template>
    <CrmLayout>
        <Head title="Manajemen Jadwal Jaga In-House & Master Lead" />

        <div class="space-y-6 max-w-7xl mx-auto pb-16">
            <!-- HEADER SECTION -->
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-2xl">📅</span>
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Jadwal Jaga Kantor Pemasaran</h1>
                        <span class="px-2.5 py-0.5 bg-blue-50 text-blue-700 text-xs font-black rounded-full border border-blue-200">In-House & Master Lead</span>
                    </div>
                    <p class="text-xs text-slate-500">
                        Atur penugasan petugas piket harian, mingguan, atau rotasi bulanan. Agen yang bertugas dapat login mandiri untuk mengupdate prospek transaksi.
                    </p>
                </div>

                <!-- PROJECT SELECTOR -->
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <label class="text-xs font-bold text-slate-500 shrink-0">Pilih Proyek:</label>
                    <select v-model="activeProjectId" class="w-full md:w-64 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-black text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                        <option v-for="p in projects" :key="p.id" :value="p.id">
                            🏗️ {{ p.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- STATUS CARDS & INTEGRITY NOTICE -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- CARD 1: TODAY'S DUTY AGENT -->
                <div class="bg-gradient-to-br from-emerald-500/10 via-emerald-50 to-white p-5 rounded-3xl border border-emerald-200/80 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">🛡️</span>
                            <span class="text-xs font-black text-emerald-900 uppercase tracking-wider">Petugas Jaga Hari Ini</span>
                        </div>
                        <span class="px-2 py-0.5 bg-emerald-600 text-white text-[10px] font-black rounded-full shadow-xs">HARI INI</span>
                    </div>
                    
                    <div v-if="todayDutyAgent" class="space-y-1">
                        <h3 class="text-lg font-black text-slate-900 flex items-center gap-1.5">
                            {{ todayDutyAgent.name }}
                        </h3>
                        <p class="text-xs text-emerald-700 font-semibold flex items-center gap-1">
                            <span v-if="todayDutyAgent.master_lead_name">👑 Sub-Agent ML: {{ todayDutyAgent.master_lead_name }}</span>
                            <span v-else>🏠 Sales In-House Developer</span>
                        </p>
                        <p class="text-[11px] text-slate-500 pt-1">
                            Semua lead tamu Walk-In hari ini otomatis diarahkan ke akun beliau.
                        </p>
                    </div>
                    <div v-else class="py-2">
                        <p class="text-xs font-bold text-amber-700 bg-amber-50 p-2.5 rounded-xl border border-amber-200">
                            ⚠️ Belum ada petugas jaga yang ditetapkan untuk hari ini.
                        </p>
                    </div>
                </div>

                <!-- CARD 2: MONTHLY FILL STATS -->
                <div class="bg-white p-5 rounded-3xl border border-slate-100 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-black text-slate-500 uppercase tracking-wider">Jadwal Bulan {{ monthNames[activeMonth - 1] }}</span>
                            <span class="text-lg">🗓️</span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-3xl font-black text-slate-900">{{ schedules?.length || 0 }}</span>
                            <span class="text-xs text-slate-400 font-bold">Hari Terjadwal</span>
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-2">
                        Proyek: <strong class="text-slate-700">{{ currentProject?.name }}</strong>
                    </p>
                </div>

                <!-- CARD 3: DATA INTEGRITY & CANDIDATE POOL NOTICE -->
                <div class="bg-gradient-to-br from-indigo-50/70 to-purple-50/50 p-5 rounded-3xl border border-indigo-100 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-1.5 text-xs font-black text-indigo-950 uppercase tracking-wider mb-1.5">
                            <span>🔒</span> Pemisahan Manajemen & Keamanan Data
                        </div>
                        <p class="text-[11px] text-indigo-900/80 leading-relaxed">
                            Sub-Agent dari <strong>Master Lead</strong> dapat dipilih bertugas piket tanpa mengubah komisi overriding maupun struktur kemitraan di CRM.
                        </p>
                    </div>
                    <div class="text-[11px] font-bold text-purple-700 pt-2 flex items-center gap-1">
                        <span>👥 Pool Agen Tersedia:</span>
                        <span class="underline decoration-purple-400">{{ candidateAgents?.length || 0 }} Agen Aktif</span>
                    </div>
                </div>
            </div>

            <!-- MAIN WORKSPACE TABS -->
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <!-- TAB NAVIGATION -->
                <div class="flex border-b border-slate-100 px-6 pt-4 bg-slate-50/50 gap-2 overflow-x-auto">
                    <button 
                        @click="activeTab = 'daily'"
                        :class="[
                            activeTab === 'daily' ? 'bg-white text-blue-600 border-b-2 border-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-800',
                            'px-5 py-3 text-xs font-black rounded-t-xl transition-all flex items-center gap-2 cursor-pointer'
                        ]">
                        <span>📆</span> Mode Harian (Set / Ganti Per Hari)
                    </button>

                    <button 
                        @click="activeTab = 'weekly'"
                        :class="[
                            activeTab === 'weekly' ? 'bg-white text-blue-600 border-b-2 border-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-800',
                            'px-5 py-3 text-xs font-black rounded-t-xl transition-all flex items-center gap-2 cursor-pointer'
                        ]">
                        <span>🗓️</span> Mode Mingguan (Set 1 Minggu Penuh)
                    </button>

                    <button 
                        @click="activeTab = 'monthly'"
                        :class="[
                            activeTab === 'monthly' ? 'bg-white text-blue-600 border-b-2 border-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-800',
                            'px-5 py-3 text-xs font-black rounded-t-xl transition-all flex items-center gap-2 cursor-pointer'
                        ]">
                        <span>📅</span> Mode Bulanan (Kalender & Rotasi)
                    </button>

                    <button 
                        @click="activeTab = 'roster'"
                        :class="[
                            activeTab === 'roster' ? 'bg-white text-blue-600 border-b-2 border-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-800',
                            'px-5 py-3 text-xs font-black rounded-t-xl transition-all flex items-center gap-2 cursor-pointer shrink-0'
                        ]">
                        <span>📋</span> Daftar Roster & Riwayat
                    </button>

                    <button 
                        @click="activeTab = 'visits'"
                        :class="[
                            activeTab === 'visits' ? 'bg-white text-blue-600 border-b-2 border-blue-600 shadow-xs' : 'text-slate-500 hover:text-slate-800',
                            'px-5 py-3 text-xs font-black rounded-t-xl transition-all flex items-center gap-2 cursor-pointer shrink-0'
                        ]">
                        <span>📍</span> Buku Kunjungan & Trafik Agen
                        <span v-if="(trafficAnalytics?.today_visits_count || 0) > 0" class="ml-1 px-2 py-0.5 text-[10px] font-black bg-rose-500 text-white rounded-full animate-pulse">
                            {{ trafficAnalytics.today_visits_count }} Hari Ini
                        </span>
                        <span v-else-if="(trafficAnalytics?.month_visits_count || 0) > 0" class="ml-1 px-2 py-0.5 text-[10px] font-black bg-blue-100 text-blue-700 rounded-full">
                            {{ trafficAnalytics.month_visits_count }} Bln Ini
                        </span>
                    </button>
                </div>

                <div class="p-6 md:p-8">
                    <!-- ========================================== -->
                    <!-- TAB 1: HARIAN (DAILY SETTING) -->
                    <!-- ========================================== -->
                    <div v-if="activeTab === 'daily'" class="max-w-2xl mx-auto space-y-6">
                        <div class="text-center mb-6">
                            <h2 class="text-lg font-black text-slate-900">Tetapkan Petugas Jaga Harian</h2>
                            <p class="text-xs text-slate-500 mt-1">Pilih tanggal dan tentukan Sales In-House atau Sub-Agent Master Lead yang bertugas</p>
                        </div>

                        <form @submit.prevent="submitDailyForm" class="space-y-5 bg-slate-50/80 p-6 rounded-3xl border border-slate-200/60">
                            <!-- DATE SELECTOR + SHORTCUTS -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                    Tanggal Jaga <span class="text-rose-500">*</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    <input 
                                        type="date" 
                                        v-model="dailyForm.duty_date" 
                                        required 
                                        class="flex-1 px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-blue-500/20"
                                    />
                                    <button 
                                        type="button" 
                                        @click="setDailyDateShortcut(0)" 
                                        class="px-3 py-2.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-black hover:bg-emerald-100 transition-all cursor-pointer">
                                        Hari Ini
                                    </button>
                                    <button 
                                        type="button" 
                                        @click="setDailyDateShortcut(1)" 
                                        class="px-3 py-2.5 bg-slate-100 text-slate-700 border border-slate-200 rounded-xl text-xs font-bold hover:bg-slate-200 transition-all cursor-pointer">
                                        Besok
                                    </button>
                                </div>
                            </div>

                            <!-- AGENT SELECTOR WITH MASTER LEAD / INHOUSE TAGS -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-bold text-slate-700">
                                        Pilih Petugas Jaga (In-House & Master Lead) <span class="text-rose-500">*</span>
                                    </label>
                                    <span class="text-[10px] text-slate-400 font-bold">Bisa dari Sub-Agent ML maupun In-House</span>
                                </div>

                                <select 
                                    v-model="dailyForm.user_id" 
                                    required 
                                    class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                    <option value="">-- Pilih Petugas Jaga --</option>
                                    
                                    <optgroup label="🏠 Sales In-House Developer">
                                        <option 
                                            v-for="a in candidateAgents.filter(a => a.category === 'inhouse')" 
                                            :key="a.id" 
                                            :value="a.id">
                                            🏠 {{ a.name }} (In-House)
                                        </option>
                                    </optgroup>

                                    <optgroup label="👑 Sub-Agent & Tim Master Lead">
                                        <option 
                                            v-for="a in candidateAgents.filter(a => a.category === 'master_lead_sub_agent')" 
                                            :key="a.id" 
                                            :value="a.id">
                                            👑 {{ a.name }} (Sub-Agent ML: {{ a.master_lead_name || 'Master Lead' }})
                                        </option>
                                    </optgroup>

                                    <optgroup label="💼 Mitra Agency & Freelance">
                                        <option 
                                            v-for="a in candidateAgents.filter(a => a.category === 'agency')" 
                                            :key="a.id" 
                                            :value="a.id">
                                            💼 {{ a.name }} ({{ a.broker_company_name || 'Agency' }})
                                        </option>
                                    </optgroup>
                                </select>
                            </div>

                            <!-- SHIFT SELECTION -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Shift / Jam Jaga</label>
                                <select 
                                    v-model="dailyForm.shift" 
                                    class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-800 cursor-pointer">
                                    <option value="full_day">Full Day (Pagi - Sore / Tutup Kantor Pemasaran)</option>
                                    <option value="pagi">Shift 1 (Pagi - Siang: 08:30 - 13:00)</option>
                                    <option value="siang">Shift 2 (Siang - Sore: 13:00 - 18:00)</option>
                                </select>
                            </div>

                            <!-- NOTES -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Catatan Operasional (Opsional)</label>
                                <input 
                                    type="text" 
                                    v-model="dailyForm.notes" 
                                    placeholder="Contoh: Jaga Pameran, Fokus Tamu Kavling Hook, dll."
                                    class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-800"
                                />
                            </div>

                            <button 
                                type="submit" 
                                :disabled="dailyForm.processing"
                                class="w-full py-3.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white rounded-2xl font-black text-sm shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5 transition-all disabled:opacity-50 cursor-pointer">
                                {{ dailyForm.processing ? 'Menyimpan...' : '✅ Tetapkan Petugas Jaga Harian' }}
                            </button>
                        </form>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 2: MINGGUAN (WEEKLY SETTING) -->
                    <!-- ========================================== -->
                    <div v-if="activeTab === 'weekly'" class="space-y-6">
                        <!-- WEEK NAVIGATOR -->
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200/80">
                            <div class="flex items-center gap-2">
                                <button 
                                    @click="navigateWeek(-1)" 
                                    class="px-3 py-1.5 bg-white text-slate-700 border border-slate-200 rounded-xl text-xs font-black hover:bg-slate-100 transition-all cursor-pointer">
                                    ← Minggu Sebelumnya
                                </button>
                                <button 
                                    @click="currentWeekMonday = getMonday(new Date())" 
                                    class="px-3 py-1.5 bg-white text-blue-600 border border-blue-200 rounded-xl text-xs font-black hover:bg-blue-50 transition-all cursor-pointer">
                                    Minggu Ini
                                </button>
                                <button 
                                    @click="navigateWeek(1)" 
                                    class="px-3 py-1.5 bg-white text-slate-700 border border-slate-200 rounded-xl text-xs font-black hover:bg-slate-100 transition-all cursor-pointer">
                                    Minggu Berikutnya →
                                </button>
                            </div>

                            <div class="text-center sm:text-right">
                                <span class="text-xs font-black text-slate-900">
                                    📅 Periode: {{ weekDays[0]?.dateFormatted }} — {{ weekDays[6]?.dateFormatted }} {{ activeYear }}
                                </span>
                            </div>
                        </div>

                        <!-- MODE SWITCHER -->
                        <div class="flex items-center justify-center gap-3">
                            <button 
                                type="button" 
                                @click="weeklyMode = 'custom'" 
                                :class="[
                                    weeklyMode === 'custom' ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-100 text-slate-600',
                                    'px-4 py-2 text-xs font-black rounded-xl transition-all cursor-pointer'
                                ]">
                                📋 Atur Per Hari (Senin s/d Minggu)
                            </button>
                            <button 
                                type="button" 
                                @click="weeklyMode = 'single'" 
                                :class="[
                                    weeklyMode === 'single' ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-100 text-slate-600',
                                    'px-4 py-2 text-xs font-black rounded-xl transition-all cursor-pointer'
                                ]">
                                👤 1 Agen untuk 1 Minggu Penuh
                            </button>
                        </div>

                        <!-- SUB-MODE 1: SINGLE AGENT FOR WHOLE WEEK -->
                        <div v-if="weeklyMode === 'single'" class="max-w-xl mx-auto bg-slate-50 p-6 rounded-3xl border border-slate-200/80 space-y-4">
                            <div class="text-center">
                                <h3 class="text-sm font-black text-slate-900">Pilih 1 Agen yang Bertugas 1 Minggu Penuh</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Agen ini akan ditugaskan dari hari Senin s/d Minggu sekaligus.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Petugas Jaga Seminggu</label>
                                <select v-model="weeklyForm.user_id" required class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-900">
                                    <option value="">-- Pilih Petugas Jaga --</option>
                                    <optgroup label="🏠 Sales In-House Developer">
                                        <option v-for="a in candidateAgents.filter(a => a.category === 'inhouse')" :key="a.id" :value="a.id">
                                            🏠 {{ a.name }}
                                        </option>
                                    </optgroup>
                                    <optgroup label="👑 Sub-Agent Master Lead">
                                        <option v-for="a in candidateAgents.filter(a => a.category === 'master_lead_sub_agent')" :key="a.id" :value="a.id">
                                            👑 {{ a.name }} (ML: {{ a.master_lead_name }})
                                        </option>
                                    </optgroup>
                                </select>
                            </div>

                            <button 
                                type="button" 
                                @click="submitWeeklyForm" 
                                :disabled="weeklyForm.processing || !weeklyForm.user_id"
                                class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-black rounded-xl shadow-md transition-all cursor-pointer disabled:opacity-50">
                                {{ weeklyForm.processing ? 'Menyimpan...' : '⚡ Terapkan Jadwal 1 Minggu Penuh' }}
                            </button>
                        </div>

                        <!-- SUB-MODE 2: CUSTOM DAY-BY-DAY ROSTER -->
                        <div v-else class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-slate-500 font-bold">Tentukan petugas jaga untuk tiap hari dalam minggu ini:</span>
                                <button 
                                    type="button" 
                                    @click="copyMondayToAll" 
                                    class="text-xs text-blue-600 hover:underline font-bold cursor-pointer">
                                    ⚡ Salin Agen Hari Senin ke Semua Hari
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-7 gap-3">
                                <div 
                                    v-for="(day, idx) in weekDays" 
                                    :key="day.date" 
                                    :class="[
                                        day.isToday ? 'border-emerald-300 ring-2 ring-emerald-500/20 bg-emerald-50/30' : 'border-slate-200 bg-white',
                                        'p-3 rounded-2xl border flex flex-col justify-between space-y-2.5 shadow-xs'
                                    ]">
                                    <div class="border-b pb-2 border-slate-100 flex items-center justify-between">
                                        <div>
                                            <p class="text-xs font-black text-slate-900">{{ day.dayName }}</p>
                                            <p class="text-[10px] text-slate-400 font-bold">{{ day.dateFormatted }}</p>
                                        </div>
                                        <span v-if="day.isToday" class="px-1.5 py-0.5 bg-emerald-600 text-white text-[9px] font-black rounded">HARI INI</span>
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Petugas Jaga</label>
                                        <select 
                                            v-if="weeklyForm.schedules[idx]"
                                            v-model="weeklyForm.schedules[idx].user_id" 
                                            class="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-800 focus:bg-white">
                                            <option value="">-- Libur / Kosong --</option>
                                            <optgroup label="🏠 In-House">
                                                <option v-for="a in candidateAgents.filter(a => a.category === 'inhouse')" :key="a.id" :value="a.id">
                                                    🏠 {{ a.name }}
                                                </option>
                                            </optgroup>
                                            <optgroup label="👑 Sub-Agent ML">
                                                <option v-for="a in candidateAgents.filter(a => a.category === 'master_lead_sub_agent')" :key="a.id" :value="a.id">
                                                    👑 {{ a.name }}
                                                </option>
                                            </optgroup>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Shift</label>
                                        <select 
                                            v-if="weeklyForm.schedules[idx]"
                                            v-model="weeklyForm.schedules[idx].shift" 
                                            class="w-full text-[11px] p-1.5 bg-slate-50 border border-slate-200 rounded-xl font-medium text-slate-700">
                                            <option value="full_day">Full Day</option>
                                            <option value="pagi">Shift 1 (Pagi)</option>
                                            <option value="siang">Shift 2 (Siang)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end pt-2">
                                <button 
                                    type="button" 
                                    @click="submitWeeklyForm" 
                                    :disabled="weeklyForm.processing"
                                    class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl text-xs font-black shadow-md hover:shadow-lg transition-all cursor-pointer disabled:opacity-50">
                                    {{ weeklyForm.processing ? 'Menyimpan...' : '💾 Simpan Jadwal Mingguan' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 3: BULANAN (MONTHLY CALENDAR & ROTATION) -->
                    <!-- ========================================== -->
                    <div v-if="activeTab === 'monthly'" class="space-y-6">
                        <!-- MONTH NAVIGATOR & SUB-TAB -->
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 border-b border-slate-100 pb-4">
                            <div class="flex items-center gap-3">
                                <select v-model="activeMonth" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-black text-slate-900 cursor-pointer">
                                    <option v-for="(mName, idx) in monthNames" :key="idx" :value="idx + 1">
                                        {{ mName }}
                                    </option>
                                </select>
                                <select v-model="activeYear" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-black text-slate-900 cursor-pointer">
                                    <option v-for="y in [2025, 2026, 2027, 2028]" :key="y" :value="y">{{ y }}</option>
                                </select>
                            </div>

                            <!-- SUB-TAB SWITCHER -->
                            <div class="flex bg-slate-100 p-1 rounded-xl">
                                <button 
                                    @click="monthlyTab = 'calendar'" 
                                    :class="[
                                        monthlyTab === 'calendar' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500',
                                        'px-3 py-1.5 text-xs font-bold rounded-lg transition-all cursor-pointer'
                                    ]">
                                    📅 Kalender Visual
                                </button>
                                <button 
                                    @click="monthlyTab = 'rotation'" 
                                    :class="[
                                        monthlyTab === 'rotation' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500',
                                        'px-3 py-1.5 text-xs font-bold rounded-lg transition-all cursor-pointer'
                                    ]">
                                    ⚡ Generator Rotasi Tim
                                </button>
                            </div>
                        </div>

                        <!-- SUB-VIEW 1: VISUAL CALENDAR GRID -->
                        <div v-if="monthlyTab === 'calendar'" class="space-y-4">
                            <div class="grid grid-cols-7 gap-2">
                                <div v-for="dayName in ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']" :key="dayName" class="text-center py-2 text-[11px] font-black text-slate-400 uppercase tracking-wider">
                                    {{ dayName }}
                                </div>

                                <div 
                                    v-for="(cell, i) in calendarDays" 
                                    :key="i" 
                                    :class="[
                                        cell.isPadding ? 'bg-transparent border-transparent pointer-events-none' : 
                                        cell.isToday ? 'bg-emerald-50/50 border-emerald-300 ring-2 ring-emerald-500/20' : 
                                        cell.schedule ? 'bg-white border-slate-200 hover:border-blue-400' : 'bg-slate-50/50 border-dashed border-slate-200 hover:bg-white',
                                        'min-h-[100px] p-2 rounded-2xl border transition-all flex flex-col justify-between text-xs'
                                    ]">
                                    <div v-if="!cell.isPadding" class="flex items-center justify-between">
                                        <span :class="[cell.isToday ? 'text-emerald-700 font-black' : 'text-slate-700 font-bold', 'text-xs']">
                                            {{ cell.dayNumber }}
                                        </span>
                                        <span v-if="cell.isToday" class="text-[9px] bg-emerald-600 text-white px-1 rounded font-black">HARI INI</span>
                                    </div>

                                    <div v-if="!cell.isPadding" class="my-auto">
                                        <div v-if="cell.schedule" class="p-1.5 rounded-xl bg-slate-50 border border-slate-100 space-y-1">
                                            <p class="font-black text-slate-900 text-[11px] truncate flex items-center gap-1">
                                                <span>{{ cell.schedule.user?.category === 'inhouse' ? '🏠' : (cell.schedule.user?.category === 'master_lead_sub_agent' ? '👑' : '💼') }}</span>
                                                {{ cell.schedule.user?.name }}
                                            </p>
                                            <p class="text-[9px] text-slate-400 truncate">{{ cell.schedule.shift_label }}</p>
                                        </div>
                                        <div v-else class="text-center py-1">
                                            <span class="text-[10px] text-slate-400 italic">Belum Diatur</span>
                                        </div>
                                    </div>

                                    <div v-if="!cell.isPadding" class="flex items-center justify-end gap-1 pt-1 border-t border-slate-100/60">
                                        <button 
                                            @click="openQuickSet(cell.dateStr, cell.schedule)"
                                            class="text-[10px] text-blue-600 hover:text-blue-800 font-black cursor-pointer">
                                            {{ cell.schedule ? 'Ganti' : '+ Set' }}
                                        </button>
                                        <button 
                                            v-if="cell.schedule" 
                                            @click="deleteSchedule(cell.schedule.id)"
                                            class="text-[10px] text-rose-500 hover:text-rose-700 font-bold ml-1 cursor-pointer"
                                            title="Hapus Jadwal">
                                            ✕
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SUB-VIEW 2: AUTO-ROTATION BUILDER -->
                        <div v-else class="max-w-3xl mx-auto space-y-6 bg-slate-50/70 p-6 md:p-8 rounded-3xl border border-slate-200">
                            <div>
                                <h3 class="text-base font-black text-slate-900">Generator Rotasi Bulanan Otomatis</h3>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Sistem akan otomatis membagi giliran piket untuk seluruh hari dalam bulan {{ monthNames[activeMonth - 1] }} {{ activeYear }} secara berurutan.
                                </p>
                            </div>

                            <!-- SELECT AGENTS TO ROTATE -->
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <label class="block text-xs font-black text-slate-800">
                                        1. Pilih Agen yang Dilibatkan dalam Rotasi ({{ monthlyRotationForm.user_ids.length }} Dipilih)
                                    </label>
                                    <div class="flex gap-2">
                                        <button type="button" @click="selectAllInhouseForRotation" class="text-[10px] text-emerald-700 font-bold hover:underline cursor-pointer">
                                            + Pilih Semua In-House
                                        </button>
                                        <button type="button" @click="selectAllMasterLeadForRotation" class="text-[10px] text-purple-700 font-bold hover:underline cursor-pointer">
                                            + Pilih Semua Sub-Agent ML
                                        </button>
                                        <button type="button" @click="clearRotationAgents" class="text-[10px] text-slate-400 font-bold hover:underline cursor-pointer">
                                            Reset
                                        </button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-60 overflow-y-auto p-1">
                                    <div 
                                        v-for="a in candidateAgents" 
                                        :key="a.id" 
                                        @click="toggleRotationAgent(a.id)"
                                        :class="[
                                            monthlyRotationForm.user_ids.includes(a.id) ? 'bg-blue-50 border-blue-300 ring-2 ring-blue-500/20' : 'bg-white border-slate-200 hover:border-slate-300',
                                            'p-3 rounded-2xl border flex items-center justify-between cursor-pointer transition-all'
                                        ]">
                                        <div class="flex items-center gap-2">
                                            <input 
                                                type="checkbox" 
                                                :checked="monthlyRotationForm.user_ids.includes(a.id)" 
                                                class="rounded text-blue-600 pointer-events-none"
                                            />
                                            <div>
                                                <p class="text-xs font-black text-slate-900">{{ a.name }}</p>
                                                <p class="text-[10px] text-slate-500 font-medium">
                                                    {{ a.category_label }}
                                                </p>
                                            </div>
                                        </div>
                                        <span :class="[
                                            a.category === 'inhouse' ? 'bg-emerald-100 text-emerald-800' : 'bg-purple-100 text-purple-800',
                                            'text-[9px] font-black px-2 py-0.5 rounded-full'
                                        ]">
                                            {{ a.category === 'inhouse' ? 'IN-HOUSE' : 'SUB-AGENT ML' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- PATTERN & WEEKENDS -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                <div>
                                    <label class="block text-xs font-black text-slate-800 mb-1.5">2. Pola Giliran</label>
                                    <select v-model="monthlyRotationForm.rotation_pattern" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800 cursor-pointer">
                                        <option value="daily">Bergantian Tiap Hari (Hari 1: Agen A, Hari 2: Agen B, ...)</option>
                                        <option value="weekly">Bergantian Tiap Minggu (Minggu 1: Agen A, Minggu 2: Agen B, ...)</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-black text-slate-800 mb-1.5">3. Hari Kerja / Weekend</label>
                                    <select v-model="monthlyRotationForm.skip_weekends" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800 cursor-pointer">
                                        <option :value="false">Setiap Hari Termasuk Sabtu & Minggu</option>
                                        <option :value="true">Hari Kerja Saja (Senin s/d Jumat, Weekend Libur)</option>
                                    </select>
                                </div>
                            </div>

                            <button 
                                type="button" 
                                @click="submitMonthlyRotation" 
                                :disabled="monthlyRotationForm.processing || monthlyRotationForm.user_ids.length === 0"
                                class="w-full py-3.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-2xl font-black text-xs shadow-lg shadow-purple-500/25 hover:shadow-purple-500/40 transition-all cursor-pointer disabled:opacity-50">
                                {{ monthlyRotationForm.processing ? 'Menghitung & Menerapkan...' : '⚡ Generate & Terapkan Rotasi Bulanan' }}
                            </button>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 4: DAFTAR ROSTER & RIWAYAT -->
                    <!-- ========================================== -->
                    <div v-if="activeTab === 'roster'" class="space-y-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-black text-slate-900">
                                Daftar Jadwal Aktif Bulan {{ monthNames[activeMonth - 1] }} {{ activeYear }}
                            </span>
                            <span class="text-xs text-slate-500 font-bold">Total: {{ schedules?.length || 0 }} Hari Terjadwal</span>
                        </div>

                        <div class="overflow-x-auto rounded-2xl border border-slate-100">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-100 text-slate-500 text-[10px] font-black uppercase tracking-wider">
                                    <tr>
                                        <th class="px-4 py-3">Tanggal & Hari</th>
                                        <th class="px-4 py-3">Petugas Jaga</th>
                                        <th class="px-4 py-3">Asal / Klasifikasi</th>
                                        <th class="px-4 py-3">Shift</th>
                                        <th class="px-4 py-3">Catatan</th>
                                        <th class="px-4 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr 
                                        v-for="s in schedules" 
                                        :key="s.id" 
                                        :class="[s.is_today ? 'bg-emerald-50/40 font-bold' : 'hover:bg-slate-50/50']">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <span v-if="s.is_today" class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                                                <div>
                                                    <p class="font-black text-slate-900">{{ s.day_name }}</p>
                                                    <p class="text-[10px] text-slate-500">{{ s.date_formatted }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 font-bold text-slate-900">
                                            {{ s.user?.name }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span :class="[
                                                s.user?.category === 'inhouse' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-purple-50 text-purple-700 border-purple-200',
                                                'px-2 py-0.5 rounded-full text-[10px] font-bold border'
                                            ]">
                                                {{ s.user?.category_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600 font-medium">
                                            {{ s.shift_label }}
                                        </td>
                                        <td class="px-4 py-3 text-slate-500 text-[11px]">
                                            {{ s.notes || '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button 
                                                @click="deleteSchedule(s.id)"
                                                class="text-rose-600 hover:text-rose-800 font-bold text-xs cursor-pointer">
                                                Hapus
                                            </button>
                                        </td>
                                    </tr>

                                    <tr v-if="!schedules || schedules.length === 0">
                                        <td colspan="6" class="px-4 py-8 text-center text-slate-400 font-medium">
                                            Belum ada jadwal jaga yang diatur untuk bulan ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- TAB 5: BUKU KUNJUNGAN & TRAFIK AGEN DI LOKASI -->
                    <!-- ========================================== -->
                    <div v-if="activeTab === 'visits'" class="space-y-6">
                        <!-- BANNER PETUGAS JAGA & CALL TO ACTION -->
                        <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-blue-950 text-white p-6 rounded-3xl shadow-lg border border-slate-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-5">
                            <div class="flex items-start gap-4">
                                <div class="w-14 h-14 rounded-2xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-2xl shrink-0 shadow-inner">
                                    <span>📍</span>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h2 class="text-lg font-black text-white tracking-tight">
                                            Buku Tamu Kedatangan Agen (PK & Konten)
                                        </h2>
                                        <span class="px-2.5 py-0.5 bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-[11px] font-bold rounded-full">
                                            Lokasi: {{ currentProject?.name }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-300 max-w-2xl leading-relaxed">
                                        Petugas jaga in-house bertanggung jawab mencatat kehadiran setiap agen (internal/eksternal) yang datang ke lokasi untuk Product Knowledge (PK) atau pembuatan konten media sosial.
                                    </p>
                                    <div class="pt-1.5 flex items-center gap-2 text-xs flex-wrap">
                                        <span class="text-slate-400 font-medium">Petugas Jaga Hari Ini:</span>
                                        <span v-if="todayDutyAgent" class="font-black text-emerald-400 bg-emerald-950/60 px-2 py-0.5 rounded-lg border border-emerald-800/80">
                                            🏠 {{ todayDutyAgent.name }}
                                        </span>
                                        <span v-else class="text-amber-400 italic">
                                            (Belum ada petugas jaga hari ini)
                                        </span>
                                        <span v-if="currentUser?.is_duty_today" class="px-2 py-0.5 bg-blue-500 text-white text-[10px] font-black rounded-full animate-bounce">
                                            ⭐ Giliran Anda Bertugas!
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto shrink-0">
                                <button 
                                    @click="openAddVisitModal('konten')"
                                    class="px-4 py-2.5 bg-purple-600/30 hover:bg-purple-600/50 text-purple-200 border border-purple-500/40 rounded-2xl text-xs font-black transition-all flex items-center gap-1.5 cursor-pointer">
                                    <span>🎬</span> + Agen Konten
                                </button>
                                <button 
                                    @click="openAddVisitModal('pk')"
                                    class="px-4 py-2.5 bg-blue-600/30 hover:bg-blue-600/50 text-blue-200 border border-blue-500/40 rounded-2xl text-xs font-black transition-all flex items-center gap-1.5 cursor-pointer">
                                    <span>📚</span> + Agen PK
                                </button>
                                <button 
                                    @click="openAddVisitModal('pk')"
                                    class="px-5 py-2.5 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white rounded-2xl text-xs font-black shadow-lg shadow-blue-500/25 transition-all flex items-center gap-2 cursor-pointer">
                                    <span>➕</span> Catat Kedatangan
                                </button>
                            </div>
                        </div>

                        <!-- STATISTIK & METRIK TRAFIK (HARIAN, MINGGUAN, BULANAN) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- METRIC 1: HARIAN -->
                            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-xs flex flex-col justify-between hover:border-blue-300 transition-all">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-black text-slate-500 uppercase tracking-wider">Trafik Hari Ini</span>
                                    <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold rounded-full">Real-Time</span>
                                </div>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-3xl font-black text-slate-900">{{ trafficAnalytics?.today_visits_count || 0 }}</span>
                                    <span class="text-xs font-bold text-slate-500">Kunjungan Agen</span>
                                </div>
                                <p class="text-[11px] text-slate-400 mt-2 flex items-center gap-1">
                                    <span>📍</span> Tanggal: {{ new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                                </p>
                            </div>

                            <!-- METRIC 2: MINGGUAN -->
                            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-xs flex flex-col justify-between hover:border-blue-300 transition-all">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-black text-slate-500 uppercase tracking-wider">Trafik Minggu Ini</span>
                                    <span class="px-2 py-0.5 bg-blue-50 text-blue-700 border border-blue-200 text-[10px] font-bold rounded-full">Senin - Minggu</span>
                                </div>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-3xl font-black text-blue-600">{{ trafficAnalytics?.week_visits_count || 0 }}</span>
                                    <span class="text-xs font-bold text-slate-500">Kunjungan Agen</span>
                                </div>
                                <p class="text-[11px] text-slate-400 mt-2 flex items-center gap-1">
                                    <span>🗓️</span> Minggu Berjalan
                                </p>
                            </div>

                            <!-- METRIC 3: BULANAN -->
                            <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-xs flex flex-col justify-between hover:border-blue-300 transition-all">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-black text-slate-500 uppercase tracking-wider">Trafik Bulan Ini</span>
                                    <span class="px-2 py-0.5 bg-purple-50 text-purple-700 border border-purple-200 text-[10px] font-bold rounded-full">{{ monthNames[activeMonth - 1] }}</span>
                                </div>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-3xl font-black text-purple-600">{{ trafficAnalytics?.month_visits_count || 0 }}</span>
                                    <span class="text-xs font-bold text-slate-500">Total Kunjungan</span>
                                </div>
                                <p class="text-[11px] text-slate-400 mt-2 flex items-center gap-1">
                                    <span>📅</span> Akumulasi {{ monthNames[activeMonth - 1] }} {{ activeYear }}
                                </p>
                            </div>

                            <!-- METRIC 4: KONTEN VS PK RATIO -->
                            <div class="bg-gradient-to-br from-purple-50/70 to-blue-50/50 p-5 rounded-3xl border border-purple-100 shadow-xs flex flex-col justify-between">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-black text-purple-900 uppercase tracking-wider">Aktivitas Utama</span>
                                    <span class="text-xs font-black text-purple-600">PK vs Konten</span>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between text-xs font-bold">
                                        <span class="text-purple-700 flex items-center gap-1">🎬 Konten: {{ trafficAnalytics?.monthly_purposes?.konten || 0 }}</span>
                                        <span class="text-blue-700 flex items-center gap-1">📚 PK: {{ trafficAnalytics?.monthly_purposes?.pk || 0 }}</span>
                                    </div>
                                    <!-- PROGRESS SPLIT BAR -->
                                    <div class="w-full h-2.5 bg-slate-200/80 rounded-full overflow-hidden flex">
                                        <div 
                                            class="bg-purple-600 h-full transition-all" 
                                            :style="{ 
                                                width: `${(trafficAnalytics?.month_visits_count > 0 ? (trafficAnalytics.monthly_purposes.konten / trafficAnalytics.month_visits_count) * 100 : 50)}%` 
                                            }">
                                        </div>
                                        <div 
                                            class="bg-blue-500 h-full transition-all" 
                                            :style="{ 
                                                width: `${(trafficAnalytics?.month_visits_count > 0 ? (trafficAnalytics.monthly_purposes.pk / trafficAnalytics.month_visits_count) * 100 : 50)}%` 
                                            }">
                                        </div>
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-500 mt-2 flex items-center justify-between">
                                    <span>🏡 Survey: {{ trafficAnalytics?.monthly_purposes?.survey_klien || 0 }}</span>
                                    <span>🤝 Koordinasi: {{ trafficAnalytics?.monthly_purposes?.koordinasi || 0 }}</span>
                                </p>
                            </div>
                        </div>

                        <!-- VISUAL CHART & LEADERBOARD GRID -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- CHART: 14 DAYS TREND -->
                            <div class="lg:col-span-2 bg-white p-6 rounded-3xl border border-slate-200/70 shadow-xs space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                                            <span>📊</span> Tren Trafik Kedatangan Agen (14 Hari Terakhir)
                                        </h3>
                                        <p class="text-xs text-slate-500 mt-0.5">Pantau fluktuasi kedatangan agen per hari untuk melihat hari paling ramai</p>
                                    </div>
                                    <div class="items-center gap-3 text-[11px] font-bold hidden sm:flex">
                                        <span class="flex items-center gap-1 text-blue-600"><span class="w-2.5 h-2.5 rounded-sm bg-blue-500"></span> PK</span>
                                        <span class="flex items-center gap-1 text-purple-600"><span class="w-2.5 h-2.5 rounded-sm bg-purple-600"></span> Konten</span>
                                        <span class="flex items-center gap-1 text-emerald-600"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-500"></span> Survey</span>
                                        <span class="flex items-center gap-1 text-amber-600"><span class="w-2.5 h-2.5 rounded-sm bg-amber-500"></span> Lainnya</span>
                                    </div>
                                </div>

                                <!-- BARS CONTAINER -->
                                <div class="pt-4 pb-2 border-b border-slate-100">
                                    <div class="h-44 flex items-end justify-between gap-1.5 md:gap-2 px-1">
                                        <div 
                                            v-for="(day, idx) in trafficAnalytics?.daily_trend" 
                                            :key="idx" 
                                            class="flex-1 flex flex-col items-center h-full justify-end group relative cursor-pointer">
                                            
                                            <!-- TOOLTIP ON HOVER -->
                                            <div class="absolute bottom-full mb-2 hidden group-hover:flex flex-col items-center z-20 pointer-events-none">
                                                <div class="bg-slate-900 text-white text-[11px] py-1.5 px-2.5 rounded-xl shadow-xl font-bold whitespace-nowrap space-y-0.5">
                                                    <p class="text-slate-300 font-medium">{{ day.date_formatted }} ({{ day.day_name }})</p>
                                                    <p class="text-emerald-400 font-black">Total: {{ day.total }} Agen</p>
                                                    <div class="text-[10px] text-slate-300 grid grid-cols-2 gap-x-2 pt-0.5 border-t border-slate-700">
                                                        <span>📚 PK: {{ day.pk }}</span>
                                                        <span>🎬 Konten: {{ day.konten }}</span>
                                                        <span>🏡 Survey: {{ day.survey }}</span>
                                                        <span>📍 Lainnya: {{ day.other }}</span>
                                                    </div>
                                                </div>
                                                <div class="w-2 h-2 bg-slate-900 rotate-45 -mt-1"></div>
                                            </div>

                                            <!-- VALUE LABEL -->
                                            <span 
                                                v-if="day.total > 0" 
                                                class="text-[10px] font-black text-slate-600 mb-1 group-hover:text-blue-600 transition-colors">
                                                {{ day.total }}
                                            </span>

                                            <!-- VERTICAL BAR -->
                                            <div 
                                                class="w-full max-w-[28px] rounded-t-lg overflow-hidden flex flex-col-reverse transition-all duration-300 group-hover:opacity-90"
                                                :style="{ 
                                                    height: day.total > 0 ? `${Math.max(Math.round((day.total / maxDailyTrendTraffic) * 100), 12)}%` : '6px' 
                                                }">
                                                <!-- PK SEGMENT -->
                                                <div 
                                                    v-if="day.pk > 0" 
                                                    class="bg-blue-500 w-full"
                                                    :style="{ height: `${(day.pk / day.total) * 100}%` }">
                                                </div>
                                                <!-- KONTEN SEGMENT -->
                                                <div 
                                                    v-if="day.konten > 0" 
                                                    class="bg-purple-600 w-full"
                                                    :style="{ height: `${(day.konten / day.total) * 100}%` }">
                                                </div>
                                                <!-- SURVEY SEGMENT -->
                                                <div 
                                                    v-if="day.survey > 0" 
                                                    class="bg-emerald-500 w-full"
                                                    :style="{ height: `${(day.survey / day.total) * 100}%` }">
                                                </div>
                                                <!-- OTHER SEGMENT -->
                                                <div 
                                                    v-if="day.other > 0" 
                                                    class="bg-amber-400 w-full"
                                                    :style="{ height: `${(day.other / day.total) * 100}%` }">
                                                </div>
                                                <!-- ZERO PLACEHOLDER -->
                                                <div v-if="day.total === 0" class="bg-slate-100 w-full h-full"></div>
                                            </div>

                                            <!-- DATE LABEL -->
                                            <span 
                                                :class="[
                                                    day.is_today ? 'bg-blue-600 text-white font-black px-1 rounded-sm' : 'text-slate-400 font-bold',
                                                    'text-[10px] mt-2 block truncate'
                                                ]">
                                                {{ day.is_today ? 'Hari ini' : day.day_name }}
                                            </span>
                                            <span class="text-[9px] text-slate-300 hidden sm:block">
                                                {{ day.date_formatted.split(' ')[0] }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- LEADERBOARD: TOP ACTIVE AGENTS -->
                            <div class="bg-white p-6 rounded-3xl border border-slate-200/70 shadow-xs space-y-4 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <h3 class="text-sm font-black text-slate-900 flex items-center gap-1.5">
                                            <span>🏆</span> Agen Paling Aktif ke Lokasi
                                        </h3>
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Bulan Ini</span>
                                    </div>
                                    <p class="text-xs text-slate-500">Melihat seberapa aktif agen datang untuk PK & bikin konten</p>
                                </div>

                                <div class="space-y-2.5 overflow-y-auto max-h-[220px] pr-1">
                                    <div 
                                        v-for="(agent, idx) in trafficAnalytics?.top_agents" 
                                        :key="idx" 
                                        class="flex items-center justify-between p-2.5 bg-slate-50 hover:bg-blue-50/50 rounded-2xl border border-slate-100 transition-all">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <!-- RANK BADGE -->
                                            <div 
                                                :class="[
                                                    idx === 0 ? 'bg-amber-400 text-amber-950 font-black ring-2 ring-amber-200' : 
                                                    idx === 1 ? 'bg-slate-300 text-slate-900 font-black' : 
                                                    idx === 2 ? 'bg-amber-600 text-white font-black' : 'bg-slate-100 text-slate-600 font-bold',
                                                    'w-6 h-6 rounded-full flex items-center justify-center text-xs shrink-0'
                                                ]">
                                                {{ idx + 1 }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-xs font-black text-slate-900 truncate">
                                                    {{ agent.agent_name }}
                                                </p>
                                                <p class="text-[10px] text-slate-500 truncate flex items-center gap-1">
                                                    <span>🏢 {{ agent.agency_name }}</span>
                                                    <span v-if="agent.agent_phone" class="text-slate-400">• {{ agent.agent_phone }}</span>
                                                </p>
                                            </div>
                                        </div>

                                        <div class="text-right shrink-0">
                                            <span class="text-xs font-black text-blue-700 bg-blue-50 px-2 py-0.5 rounded-lg border border-blue-200">
                                                {{ agent.total_visits }}x Hadir
                                            </span>
                                            <div class="text-[10px] text-slate-400 font-bold mt-1 flex items-center gap-1 justify-end">
                                                <span v-if="agent.konten_count > 0" class="text-purple-600 font-black">{{ agent.konten_count }} Konten</span>
                                                <span v-if="agent.pk_count > 0" class="text-blue-600 font-black">• {{ agent.pk_count }} PK</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="!trafficAnalytics?.top_agents || trafficAnalytics.top_agents.length === 0" class="text-center py-6 text-slate-400 text-xs">
                                        Belum ada aktivitas kunjungan agen yang tercatat bulan ini.
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                                    <span>Top Agensi:</span>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span v-for="ag in trafficAnalytics?.top_agencies" :key="ag.agency_name" class="px-2 py-0.5 bg-slate-100 rounded-md font-bold text-slate-700 text-[10px]">
                                            {{ ag.agency_name }} ({{ ag.total_visits }})
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- WORKSPACE TABLE: BUKU TAMU KEDATANGAN AGEN -->
                        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                            <!-- TABLE CONTROLS & FILTER BAR -->
                            <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                                <!-- PERIOD PRESETS -->
                                <div class="flex items-center gap-1 bg-white p-1 rounded-2xl border border-slate-200 shadow-xs overflow-x-auto w-full md:w-auto">
                                    <button 
                                        @click="changeVisitPeriod('today')" 
                                        :class="[
                                            visitPeriodFilter === 'today' ? 'bg-blue-600 text-white font-black shadow-xs' : 'text-slate-600 hover:text-slate-900 font-bold',
                                            'px-3.5 py-1.5 text-xs rounded-xl transition-all cursor-pointer'
                                        ]">
                                        Hari Ini
                                    </button>
                                    <button 
                                        @click="changeVisitPeriod('week')" 
                                        :class="[
                                            visitPeriodFilter === 'week' ? 'bg-blue-600 text-white font-black shadow-xs' : 'text-slate-600 hover:text-slate-900 font-bold',
                                            'px-3.5 py-1.5 text-xs rounded-xl transition-all cursor-pointer'
                                        ]">
                                        Minggu Ini
                                    </button>
                                    <button 
                                        @click="changeVisitPeriod('month')" 
                                        :class="[
                                            visitPeriodFilter === 'month' ? 'bg-blue-600 text-white font-black shadow-xs' : 'text-slate-600 hover:text-slate-900 font-bold',
                                            'px-3.5 py-1.5 text-xs rounded-xl transition-all cursor-pointer'
                                        ]">
                                        Bulan Ini
                                    </button>
                                    <button 
                                        @click="changeVisitPeriod('all')" 
                                        :class="[
                                            visitPeriodFilter === 'all' ? 'bg-blue-600 text-white font-black shadow-xs' : 'text-slate-600 hover:text-slate-900 font-bold',
                                            'px-3.5 py-1.5 text-xs rounded-xl transition-all cursor-pointer'
                                        ]">
                                        Semua
                                    </button>
                                </div>

                                <!-- PURPOSE FILTER & SEARCH -->
                                <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                                    <select 
                                        v-model="visitPurposeFilter" 
                                        class="px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 cursor-pointer">
                                        <option value="all">Semua Keperluan</option>
                                        <option value="pk">📚 Product Knowledge (PK)</option>
                                        <option value="konten">🎬 Bikin Konten Medsos</option>
                                        <option value="survey_klien">🏡 Survey Unit / Klien</option>
                                        <option value="koordinasi">🤝 Koordinasi / Ambil Brosur</option>
                                        <option value="lainnya">📍 Kunjungan Lainnya</option>
                                    </select>

                                    <div class="relative flex-1 md:w-60">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 text-xs">🔍</span>
                                        <input 
                                            type="text" 
                                            v-model="visitSearchQuery" 
                                            placeholder="Cari agen, agency, catatan..." 
                                            class="w-full pl-8 pr-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500/20"
                                        />
                                    </div>

                                    <button 
                                        @click="openAddVisitModal('pk')"
                                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black shadow-sm transition-all flex items-center gap-1.5 cursor-pointer">
                                        <span>+</span> Catat Agen
                                    </button>
                                </div>
                            </div>

                            <!-- VISIT LOG TABLE -->
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-50/80 text-slate-500 font-black uppercase tracking-wider border-b border-slate-100">
                                        <tr>
                                            <th class="px-5 py-3.5">Waktu & Tanggal</th>
                                            <th class="px-5 py-3.5">Nama Agen</th>
                                            <th class="px-5 py-3.5">Kantor / Agensi</th>
                                            <th class="px-5 py-3.5">Tujuan Kedatangan</th>
                                            <th class="px-5 py-3.5">Rombongan</th>
                                            <th class="px-5 py-3.5">Petugas Pencatat</th>
                                            <th class="px-5 py-3.5">Catatan / Link Konten</th>
                                            <th class="px-5 py-3.5 text-right">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr 
                                            v-for="v in filteredVisitsList" 
                                            :key="v.id" 
                                            :class="[v.is_today ? 'bg-emerald-50/30' : 'hover:bg-slate-50/60', 'transition-colors']">
                                            <td class="px-5 py-3.5 whitespace-nowrap">
                                                <div class="flex items-center gap-2">
                                                    <span v-if="v.is_today" class="w-2 h-2 rounded-full bg-emerald-500 shrink-0 animate-pulse"></span>
                                                    <div>
                                                        <p class="font-black text-slate-900">{{ v.day_name }}, {{ v.visit_date_formatted }}</p>
                                                        <p class="text-[11px] text-slate-400 font-bold">Jam: {{ v.visit_time }} WIB</p>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-5 py-3.5">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-700 font-black text-xs flex items-center justify-center shrink-0">
                                                        {{ v.agent_name.charAt(0).toUpperCase() }}
                                                    </div>
                                                    <div>
                                                        <p class="font-black text-slate-900">{{ v.agent_name }}</p>
                                                        <a 
                                                            v-if="v.agent_phone" 
                                                            :href="`https://wa.me/${v.agent_phone.replace(/[^0-9]/g, '')}`" 
                                                            target="_blank" 
                                                            class="text-[11px] text-emerald-600 hover:text-emerald-700 font-bold flex items-center gap-0.5">
                                                            <span>💬</span> {{ v.agent_phone }}
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="px-5 py-3.5 whitespace-nowrap">
                                                <span class="px-2.5 py-1 rounded-full text-[11px] font-black bg-slate-100 text-slate-700 border border-slate-200">
                                                    🏢 {{ v.agency_name }}
                                                </span>
                                            </td>

                                            <td class="px-5 py-3.5 whitespace-nowrap">
                                                <span :class="[v.purpose_badge_class, 'px-2.5 py-1 rounded-full text-[11px] font-black border flex items-center gap-1.5 w-fit shadow-xs']">
                                                    <span>{{ v.purpose_icon }}</span>
                                                    <span>{{ v.purpose_label }}</span>
                                                </span>
                                            </td>

                                            <td class="px-5 py-3.5 whitespace-nowrap">
                                                <span class="font-bold text-slate-700">
                                                    {{ v.visitor_count || 1 }} Orang
                                                </span>
                                            </td>

                                            <td class="px-5 py-3.5 whitespace-nowrap">
                                                <p class="font-bold text-slate-800 text-xs">
                                                    {{ v.recorded_by_name }}
                                                </p>
                                                <p class="text-[10px] text-slate-400 font-medium">Petugas In-House Jaga</p>
                                            </td>

                                            <td class="px-5 py-3.5 max-w-xs">
                                                <p v-if="v.notes" class="text-xs text-slate-700 line-clamp-2">
                                                    {{ v.notes }}
                                                </p>
                                                <a 
                                                    v-if="v.documentation_url" 
                                                    :href="v.documentation_url" 
                                                    target="_blank" 
                                                    class="mt-1 inline-flex items-center gap-1 text-[11px] text-purple-600 hover:text-purple-800 font-black underline">
                                                    <span>🎬</span> Lihat Konten / Dokumen ↗
                                                </a>
                                                <span v-if="!v.notes && !v.documentation_url" class="text-slate-400">-</span>
                                            </td>

                                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button 
                                                        @click="openEditVisitModal(v)" 
                                                        class="p-1.5 text-slate-400 hover:text-blue-600 transition-colors cursor-pointer" 
                                                        title="Edit Catatan">
                                                        ✏️
                                                    </button>
                                                    <button 
                                                        @click="deleteVisit(v)" 
                                                        class="p-1.5 text-slate-400 hover:text-rose-600 transition-colors cursor-pointer" 
                                                        title="Hapus Catatan">
                                                        🗑️
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <tr v-if="!filteredVisitsList || filteredVisitsList.length === 0">
                                            <td colspan="8" class="px-5 py-12 text-center">
                                                <div class="max-w-sm mx-auto space-y-2">
                                                    <span class="text-4xl block">📍</span>
                                                    <p class="font-black text-slate-800 text-sm">Belum Ada Kunjungan Agen</p>
                                                    <p class="text-xs text-slate-400">
                                                        Belum ada agen yang tercatat hadir untuk periode ini. Klik tombol di bawah untuk mencatat kedatangan pertama.
                                                    </p>
                                                    <button 
                                                        @click="openAddVisitModal('pk')" 
                                                        class="mt-3 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black transition-all cursor-pointer">
                                                        + Catat Kedatangan Agen Sekarang
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- QUICK MODAL TO SET/CHANGE SINGLE DATE -->
        <teleport to="body">
            <div v-if="showQuickSetModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showQuickSetModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div>
                            <h2 class="text-base font-black text-slate-900 flex items-center gap-1.5">
                                <span>📅</span> Atur Petugas Jaga
                            </h2>
                            <p class="text-xs text-slate-400 mt-0.5">Tanggal: {{ quickModalDate }}</p>
                        </div>
                        <button @click="showQuickSetModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg cursor-pointer">✕</button>
                    </div>

                    <form @submit.prevent="submitQuickSet" class="space-y-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Petugas Jaga (In-House & Master Lead) *</label>
                            <select v-model="quickForm.user_id" required class="w-full px-3 py-2.5 border border-slate-200 rounded-xl font-bold bg-slate-50 focus:bg-white text-slate-800">
                                <option value="">-- Pilih Petugas Jaga --</option>
                                <optgroup label="🏠 In-House Developer">
                                    <option v-for="a in candidateAgents.filter(a => a.category === 'inhouse')" :key="a.id" :value="a.id">
                                        🏠 {{ a.name }}
                                    </option>
                                </optgroup>
                                <optgroup label="👑 Sub-Agent Master Lead">
                                    <option v-for="a in candidateAgents.filter(a => a.category === 'master_lead_sub_agent')" :key="a.id" :value="a.id">
                                        👑 {{ a.name }} (ML: {{ a.master_lead_name }})
                                    </option>
                                </optgroup>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Shift / Jam Jaga</label>
                            <select v-model="quickForm.shift" class="w-full px-3 py-2.5 border border-slate-200 rounded-xl font-medium bg-slate-50 focus:bg-white text-slate-800">
                                <option value="full_day">Full Day (Pagi - Sore / Tutup Kantor)</option>
                                <option value="pagi">Shift 1 (Pagi - Siang)</option>
                                <option value="siang">Shift 2 (Siang - Sore / Malam)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Catatan Operasional</label>
                            <input type="text" v-model="quickForm.notes" class="w-full px-3 py-2.5 border border-slate-200 rounded-xl font-medium" />
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                            <button type="button" @click="showQuickSetModal = false" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl font-bold">Batal</button>
                            <button type="submit" :disabled="quickForm.processing" class="px-5 py-2 bg-blue-600 text-white rounded-xl font-black shadow-md disabled:opacity-50">
                                {{ quickForm.processing ? 'Menyimpan...' : 'Simpan Jadwal' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <!-- MODAL CATAT / EDIT KEDATANGAN AGEN (BUKU TAMU PK & KONTEN) -->
        <teleport to="body">
            <div v-if="isVisitModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="isVisitModalOpen = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden border border-slate-100 flex flex-col max-h-[90vh]">
                    <!-- HEADER -->
                    <div class="p-6 bg-gradient-to-r from-slate-900 via-indigo-950 to-blue-950 text-white flex items-center justify-between shrink-0">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xl">📍</span>
                                <h3 class="text-base font-black text-white">
                                    {{ editingVisit ? 'Edit Catatan Kunjungan Agen' : 'Catat Kedatangan Agen di Lokasi' }}
                                </h3>
                            </div>
                            <p class="text-xs text-slate-300 mt-0.5">
                                Proyek: <strong>{{ currentProject?.name }}</strong> • Dicatat oleh Petugas Jaga In-House
                            </p>
                        </div>
                        <button 
                            @click="isVisitModalOpen = false" 
                            class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center font-bold text-sm cursor-pointer transition-all">
                            ✕
                        </button>
                    </div>

                    <!-- FORM BODY -->
                    <form @submit.prevent="submitVisitForm" class="p-6 overflow-y-auto space-y-5 text-xs">
                        <!-- TANGGAL & WAKTU -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Tanggal Kedatangan *</label>
                                <input 
                                    type="date" 
                                    v-model="visitForm.visit_date" 
                                    required 
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-blue-500/20"
                                />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Jam Kedatangan *</label>
                                <div class="flex items-center gap-1.5">
                                    <input 
                                        type="time" 
                                        v-model="visitForm.visit_time" 
                                        required 
                                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-blue-500/20"
                                    />
                                    <button 
                                        type="button" 
                                        @click="visitForm.visit_time = getLocalTime()" 
                                        class="px-2 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-[11px] shrink-0 cursor-pointer" 
                                        title="Set ke jam saat ini">
                                        Sekarang
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- AGENT SOURCE SELECTOR -->
                        <div class="space-y-2.5">
                            <label class="block font-bold text-slate-700">Pilih Identitas Agen *</label>
                            <div class="flex items-center gap-2 bg-slate-100 p-1 rounded-2xl border border-slate-200">
                                <button 
                                    type="button" 
                                    @click="visitAgentMode = 'existing'" 
                                    :class="[
                                        visitAgentMode === 'existing' ? 'bg-white text-blue-700 shadow-xs font-black' : 'text-slate-600 font-bold',
                                        'flex-1 py-1.5 rounded-xl transition-all text-xs cursor-pointer'
                                    ]">
                                    👥 Agen Terdaftar di CRM
                                </button>
                                <button 
                                    type="button" 
                                    @click="visitAgentMode = 'manual'" 
                                    :class="[
                                        visitAgentMode === 'manual' ? 'bg-white text-blue-700 shadow-xs font-black' : 'text-slate-600 font-bold',
                                        'flex-1 py-1.5 rounded-xl transition-all text-xs cursor-pointer'
                                    ]">
                                    ✍️ Agen Baru / Tamu Luar
                                </button>
                            </div>

                            <!-- EXISTING AGENT DROPDOWN -->
                            <div v-if="visitAgentMode === 'existing'" class="space-y-2">
                                <select 
                                    :value="visitForm.agent_id" 
                                    @change="(e) => {
                                        const found = candidateAgents.find(a => a.id == e.target.value);
                                        onSelectVisitorAgent(found);
                                    }" 
                                    required 
                                    class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                    <option value="">-- Pilih Agen yang Hadir --</option>
                                    <optgroup label="🏠 Sales In-House Developer">
                                        <option v-for="a in candidateAgents.filter(a => a.category === 'inhouse')" :key="a.id" :value="a.id">
                                            🏠 {{ a.name }} ({{ a.phone || 'In-House' }})
                                        </option>
                                    </optgroup>
                                    <optgroup label="👑 Sub-Agent Master Lead">
                                        <option v-for="a in candidateAgents.filter(a => a.category === 'master_lead_sub_agent')" :key="a.id" :value="a.id">
                                            👑 {{ a.name }} (Sub-Agent ML: {{ a.master_lead_name }})
                                        </option>
                                    </optgroup>
                                    <optgroup label="🏢 Mitra Agensi Properti (ERA, Brighton, dll)">
                                        <option v-for="a in candidateAgents.filter(a => a.category === 'agency')" :key="a.id" :value="a.id">
                                            🏢 {{ a.name }} ({{ a.broker_company_name || 'Mitra Agency' }})
                                        </option>
                                    </optgroup>
                                </select>
                                
                                <div v-if="visitForm.agent_id" class="p-2.5 bg-blue-50/70 border border-blue-100 rounded-xl flex items-center justify-between text-[11px]">
                                    <span class="font-bold text-blue-900">Agensi / Asal: {{ visitForm.agency_name }}</span>
                                    <span class="text-blue-700 font-bold">Telp: {{ visitForm.agent_phone || '-' }}</span>
                                </div>
                            </div>

                            <!-- MANUAL AGENT INPUT -->
                            <div v-else class="space-y-3">
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Nama Lengkap Agen *</label>
                                    <input 
                                        type="text" 
                                        v-model="visitForm.agent_name" 
                                        placeholder="Misal: Kevin Sanjaya" 
                                        required 
                                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 focus:bg-white"
                                    />
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block font-bold text-slate-700 mb-1">No. WhatsApp</label>
                                        <input 
                                            type="text" 
                                            v-model="visitForm.agent_phone" 
                                            placeholder="08123456789" 
                                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 focus:bg-white"
                                        />
                                    </div>
                                    <div>
                                        <label class="block font-bold text-slate-700 mb-1">Kantor Agensi / Asal</label>
                                        <input 
                                            type="text" 
                                            v-model="visitForm.agency_name" 
                                            placeholder="Misal: Brighton, ERA, Freelance" 
                                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 focus:bg-white"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TUJUAN KEDATANGAN (INTERACTIVE BIG CARDS) -->
                        <div class="space-y-2">
                            <label class="block font-bold text-slate-700">Tujuan Kedatangan ke Lokasi *</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label 
                                    :class="[
                                        visitForm.purpose === 'pk' ? 'bg-blue-50 border-blue-500 text-blue-900 ring-1 ring-blue-500' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100',
                                        'p-3 rounded-2xl border flex items-center gap-2.5 cursor-pointer transition-all'
                                    ]">
                                    <input type="radio" value="pk" v-model="visitForm.purpose" class="sr-only" />
                                    <span class="text-xl">📚</span>
                                    <div>
                                        <p class="font-black text-xs">Product Knowledge</p>
                                        <p class="text-[10px] text-slate-400">Briefing & info unit</p>
                                    </div>
                                </label>

                                <label 
                                    :class="[
                                        visitForm.purpose === 'konten' ? 'bg-purple-50 border-purple-500 text-purple-900 ring-1 ring-purple-500' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100',
                                        'p-3 rounded-2xl border flex items-center gap-2.5 cursor-pointer transition-all'
                                    ]">
                                    <input type="radio" value="konten" v-model="visitForm.purpose" class="sr-only" />
                                    <span class="text-xl">🎬</span>
                                    <div>
                                        <p class="font-black text-xs">Bikin Konten Medsos</p>
                                        <p class="text-[10px] text-slate-400">Reels, TikTok, Foto</p>
                                    </div>
                                </label>

                                <label 
                                    :class="[
                                        visitForm.purpose === 'survey_klien' ? 'bg-emerald-50 border-emerald-500 text-emerald-900 ring-1 ring-emerald-500' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100',
                                        'p-3 rounded-2xl border flex items-center gap-2.5 cursor-pointer transition-all'
                                    ]">
                                    <input type="radio" value="survey_klien" v-model="visitForm.purpose" class="sr-only" />
                                    <span class="text-xl">🏡</span>
                                    <div>
                                        <p class="font-black text-xs">Survey Unit / Klien</p>
                                        <p class="text-[10px] text-slate-400">Bawa calon pembeli</p>
                                    </div>
                                </label>

                                <label 
                                    :class="[
                                        visitForm.purpose === 'koordinasi' ? 'bg-amber-50 border-amber-500 text-amber-900 ring-1 ring-amber-500' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100',
                                        'p-3 rounded-2xl border flex items-center gap-2.5 cursor-pointer transition-all'
                                    ]">
                                    <input type="radio" value="koordinasi" v-model="visitForm.purpose" class="sr-only" />
                                    <span class="text-xl">🤝</span>
                                    <div>
                                        <p class="font-black text-xs">Koordinasi & Brosur</p>
                                        <p class="text-[10px] text-slate-400">Ambil materi fisik</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- JUMLAH ROMBONGAN -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Jumlah Rombongan</label>
                                <input 
                                    type="number" 
                                    min="1" 
                                    max="50" 
                                    v-model="visitForm.visitor_count" 
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-slate-900 focus:bg-white"
                                />
                                <span class="text-[10px] text-slate-400">Misal: 1 (sendiri), 3 (bawa tim konten)</span>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Link Konten / Video (Opsional)</label>
                                <input 
                                    type="url" 
                                    v-model="visitForm.documentation_url" 
                                    placeholder="https://instagram.com/reel/..." 
                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium text-slate-900 focus:bg-white"
                                />
                            </div>
                        </div>

                        <!-- CATATAN / RINCIAN -->
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Catatan Tambahan</label>
                            <textarea 
                                v-model="visitForm.notes" 
                                rows="2" 
                                placeholder="Misal: Take video di rumah contoh tipe 36, minta materi pricelist cetak 5 lembar..." 
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium text-slate-900 focus:bg-white"
                            ></textarea>
                        </div>

                        <!-- FOOTER INFO PENCATAT -->
                        <div class="p-3 bg-slate-50 rounded-2xl border border-slate-200 flex items-center justify-between text-[11px] text-slate-600">
                            <span>Petugas Pencatat Resmi:</span>
                            <span class="font-black text-slate-900 flex items-center gap-1">
                                <span>✍️</span> {{ currentUser?.name || 'Petugas In-House' }}
                            </span>
                        </div>

                        <!-- BUTTONS -->
                        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                            <button 
                                type="button" 
                                @click="isVisitModalOpen = false" 
                                class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl font-bold transition-all cursor-pointer">
                                Batal
                            </button>
                            <button 
                                type="submit" 
                                :disabled="visitForm.processing" 
                                class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-2xl font-black shadow-lg shadow-blue-500/25 transition-all cursor-pointer disabled:opacity-50">
                                {{ visitForm.processing ? 'Menyimpan...' : (editingVisit ? 'Perbarui Catatan' : 'Simpan Kedatangan Agen') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>
    </CrmLayout>
</template>
