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
});

// Active project filter
const activeProjectId = ref(props.selectedProjectId || (props.projects?.[0]?.id || ''));
const activeMonth = ref(props.selectedMonth || new Date().getMonth() + 1);
const activeYear = ref(props.selectedYear || new Date().getFullYear());

// Active Tab: 'daily' | 'weekly' | 'monthly' | 'roster'
const activeTab = ref('daily');

// Watch project or month change to reload data
watch([activeProjectId, activeMonth, activeYear], ([pId, m, y]) => {
    router.get('/duty-schedules', {
        project_id: pId,
        month: m,
        year: y,
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
                            'px-5 py-3 text-xs font-black rounded-t-xl transition-all flex items-center gap-2 cursor-pointer'
                        ]">
                        <span>📋</span> Daftar Roster & Riwayat
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
    </CrmLayout>
</template>
