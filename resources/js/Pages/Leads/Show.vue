<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ lead: Object, agents: Array, units: { type: Array, default: () => [] } });

const showNegoModal = ref(false);
const negoForm = useForm({
    unit_id: '',
    lead_id: props.lead?.id,
    client_name: props.lead?.name || '',
    client_phone: props.lead?.phone || '',
    client_email: props.lead?.email || '',
});

const showShareNegoModal = ref(false);
const createdNegoLink = ref('');

function submitNegoForm() {
    negoForm.post('/negotiations', {
        preserveScroll: true,
        onSuccess: (page) => {
            showNegoModal.value = false;
            const flashLink = page.props.flash?.negotiation_link;
            const flashToken = page.props.flash?.negotiation_token;
            if (flashLink) {
                createdNegoLink.value = flashLink;
            } else if (flashToken) {
                createdNegoLink.value = window.location.origin + '/nego/' + flashToken;
            }
            showShareNegoModal.value = true;
        }
    });
}

function shareNegoWa() {
    const msg = `Halo Bapak/Ibu *${props.lead.name}*,\n\nSilakan isi Form Pengajuan Negosiasi penawaran harga & opsi pembayaran melalui link berikut:\n\n🔗 ${createdNegoLink.value}\n\nForm akan langsung terkirim untuk ditinjau oleh pihak Developer.\n\nTerima kasih!`;
    const phone = (props.lead.phone || '').replace(/[^0-9]/g, '');
    const waPhone = phone.startsWith('0') ? '62' + phone.substring(1) : phone;
    window.open(`https://wa.me/${waPhone}?text=${encodeURIComponent(msg)}`, '_blank');
}

function copyNegoLink(token) {
    const url = typeof token === 'string' && token.startsWith('http') ? token : `${window.location.origin}/nego/${token}`;
    navigator.clipboard.writeText(url);
    alert('Link Form Negosiasi berhasil disalin!\n' + url);
}

function negoStatusBadge(status) {
    const badges = {
        draft: 'bg-slate-100 text-slate-700',
        pending: 'bg-amber-100 text-amber-800 border border-amber-300',
        counter_offer: 'bg-blue-100 text-blue-800 border border-blue-300',
        approved: 'bg-emerald-100 text-emerald-800 border border-emerald-300',
        rejected: 'bg-rose-100 text-rose-800 border border-rose-300',
        expired: 'bg-slate-100 text-slate-500 line-through'
    };
    return badges[status] || 'bg-slate-100 text-slate-700';
}

const statusSteps = [
    { key: 'new', label: 'Baru', color: 'blue' },
    { key: 'contacted', label: 'Dihubungi', color: 'cyan' },
    { key: 'visited', label: 'Kunjungan', color: 'purple' },
    { key: 'negotiation', label: 'Negosiasi', color: 'amber' },
    { key: 'booking', label: 'Booking', color: 'emerald' },
    { key: 'won', label: 'Won', color: 'green' },
];

const currentStepIndex = computed(() => statusSteps.findIndex(s => s.key === props.lead.status));

// Activity Form
const activityForm = useForm({ type: 'note', description: '' });
function submitActivity() {
    activityForm.post(`/leads/${props.lead.id}/activity`, { preserveScroll: true, onSuccess: () => activityForm.reset('description') });
}

// Reminder Form
import KprCalculatorModal from '@/Components/Crm/KprCalculatorModal.vue';

const reminderForm = useForm({ remind_at: '', message: '' });
const showReminderModal = ref(false);
const showKprModal = ref(false);

function submitReminder() {
    reminderForm.post(`/leads/${props.lead.id}/reminder`, { preserveScroll: true, onSuccess: () => { showReminderModal.value = false; reminderForm.reset(); } });
}

const generatedMessage = ref('');
function generateWaMessage(type) {
    const agentName = props.lead.assigned_to_user?.name || 'Konsultan Homi';
    const projectName = props.lead.project?.name || 'Proyek Homi';
    
    if (type === 'perkenalan') {
        generatedMessage.value = `Halo Bapak/Ibu *${props.lead.name}*,\n\nPerkenalkan saya *${agentName}* dari Homi Developer. Terima kasih telah menyatakan minat pada proyek *${projectName}*.\n\nApakah ada waktu luang untuk berdiskusi sebentar mengenai tipe rumah atau denah unit yang sedang dicari?\n\nSalam,\n*${agentName}* - Homi Developer`;
    } else if (type === 'visit') {
        generatedMessage.value = `Halo Bapak/Ibu *${props.lead.name}*,\n\nMenyambung percakapan kita, kami mengundang Bapak/Ibu untuk berkunjung langsung (*Site Visit*) melihat rumah contoh dan lokasi proyek *${projectName}* pada akhir pekan ini.\n\nApakah hari Sabtu atau Minggu besok ada waktu luang?\n\nTerima kasih,\n*${agentName}*`;
    } else if (type === 'booking_reminder') {
        generatedMessage.value = `Halo Bapak/Ibu *${props.lead.name}*,\n\nKami mengonfirmasi ketertarikan Anda pada unit di *${projectName}*. Untuk mengamankan nomor kavling unit pilihan Anda agar tidak terjual ke konsumen lain, Anda dapat melakukan pembayaran Booking Fee (UTJ).\n\nJika ingin melakukan pembayaran atau konsultasi KPR terlebih dahulu, silakan hubungi saya kembali.\n\nTerima kasih,\n*${agentName}*`;
    } else {
        generatedMessage.value = `Halo Bapak/Ibu *${props.lead.name}*,\n\nBagaimana kabar rencana kepemilikan hunian impian Anda di *${projectName}*? Jika ada pertanyaan mengenai promo diskon, suku bunga KPR bank partner, atau ingin berkunjung kembali ke lokasi, saya siap membantu.\n\nSalam,\n*${agentName}*`;
    }
}

// Quick update & Profile Edit
const showEditLeadModal = ref(false);
const editForm = useForm({
    name: props.lead.name || '',
    phone: props.lead.phone || '',
    email: props.lead.email || '',
    identity_number: props.lead.identity_number || '',
    npwp: props.lead.npwp || '',
    address: props.lead.address || '',
    job: props.lead.job || '',
    source: props.lead.source || 'walk_in',
    status: props.lead.status || 'new',
    project_id: props.lead.project_id || '',
    broker_company_id: props.lead.broker_company_id || '',
    assigned_to: typeof props.lead.assigned_to === 'object' && props.lead.assigned_to !== null 
        ? props.lead.assigned_to.id 
        : (props.lead.assigned_to_user?.id || props.lead.assigned_to || null),
    notes: props.lead.notes || '',
});

function updateLead() {
    editForm.put(`/leads/${props.lead.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditLeadModal.value = false;
        }
    });
}

const agentSearchQuery = ref('');

const matchingLeadBrokerAgents = computed(() => {
    if (!props.lead.broker_company_id) return [];
    return (props.agents || []).filter(a => a.broker_company_id === props.lead.broker_company_id);
});

const filteredAgentsList = computed(() => {
    let list = props.agents || [];
    if (agentSearchQuery.value) {
        const q = agentSearchQuery.value.toLowerCase().trim();
        list = list.filter(a => 
            a.name.toLowerCase().includes(q) || 
            (a.broker_company && a.broker_company.name.toLowerCase().includes(q))
        );
    }
    return list;
});

const currentAssignedAgent = computed(() => {
    return (props.agents || []).find(a => a.id === editForm.assigned_to) || props.lead.assigned_to_user;
});

const activityIcons = {
    call: '📞', whatsapp: '💬', email: '📧', visit: '🏠', meeting: '🤝', note: '📝', status_change: '🔄',
};

function timeAgo(date) {
    if (!date) return '-';
    const time = new Date(date).getTime();
    if (isNaN(time)) return '-';
    const diff = Date.now() - time;
    if (diff < 0) return 'baru saja';
    const mins = Math.floor(diff / 60000);
    if (mins < 60) return `${mins}m lalu`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}j lalu`;
    const days = Math.floor(hrs / 24);
    return `${days}h lalu`;
}

function scoreColor(s) {
    if (s >= 8) return 'from-emerald-500 to-green-500';
    if (s >= 5) return 'from-amber-500 to-orange-500';
    return 'from-rose-500 to-red-500';
}
</script>

<template>
    <Head :title="`Lead: ${lead.name}`" />
    <CrmLayout>
        <template #breadcrumb>
            <span class="text-gray-400">Leads</span> / {{ lead.name }}
        </template>

        <!-- HEADER -->
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-4">
                <!-- Score -->
                <div :class="`bg-gradient-to-br ${scoreColor(lead.score)}`" class="w-14 h-14 rounded-2xl flex items-center justify-center text-white font-black text-xl shadow-lg">
                    {{ lead.score }}
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ lead.name }}</h1>
                        <button @click="showEditLeadModal = true" class="px-2 py-0.5 bg-blue-50 text-blue-600 hover:bg-blue-100 text-xs font-bold rounded-lg transition-colors flex items-center gap-1 border border-blue-200 cursor-pointer">
                            ✏️ Edit Profil
                        </button>
                    </div>
                    <p class="text-sm text-slate-500">{{ lead.phone }} {{ lead.email ? `• ${lead.email}` : '' }}</p>
                </div>
            </div>
            <div class="w-full lg:w-auto overflow-x-auto pb-2 sm:pb-0">
                <div class="flex items-center gap-2 min-w-max sm:min-w-0 sm:flex-wrap">
                    <button @click="showNegoModal = true" class="px-4 py-2 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-xs font-black rounded-xl shadow-lg shadow-amber-500/20 hover:scale-105 transition-all flex items-center gap-1 shrink-0">
                        <span>🤝</span> <span>Kirim Form Negosiasi</span>
                    </button>
                    <Link :href="`/bookings/create?lead_id=${lead.id}`" class="px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 text-white text-xs font-black rounded-xl shadow-lg shadow-emerald-500/20 hover:scale-105 transition-all shrink-0">💳 Buat Booking</Link>
                    <Link href="/kpr-scoring" class="px-4 py-2 bg-indigo-50 text-indigo-700 text-xs font-bold rounded-xl hover:bg-indigo-100 transition-colors flex items-center gap-1.5 shrink-0">
                        <span>📊</span> <span>Analisis Neraca Client</span>
                    </Link>
                    <button @click="showKprModal = true" class="px-4 py-2 bg-blue-50 text-blue-700 text-xs font-bold rounded-xl hover:bg-blue-100 transition-colors shrink-0">🧮 Kalkulator KPR</button>
                    <button @click="showReminderModal = true" class="px-4 py-2 bg-amber-50 text-amber-700 text-xs font-bold rounded-xl hover:bg-amber-100 transition-colors shrink-0">⏰ Set Reminder</button>
                    <a :href="`https://wa.me/${lead.phone?.replace(/^0/, '62')}`" target="_blank" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition-colors shrink-0">💬 WhatsApp</a>
                </div>
            </div>
        </div>

        <!-- STATUS PIPELINE -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 mb-6 shadow-sm overflow-hidden">
            <div class="overflow-x-auto pb-2">
                <div class="min-w-[550px]">
                    <div class="flex items-center justify-between">
                        <div v-for="(step, i) in statusSteps" :key="step.key" class="flex items-center flex-1 min-w-0">
                            <button @click="editForm.status = step.key; updateLead()"
                                :class="i <= currentStepIndex ? `bg-${step.color}-500 text-white shadow-lg` : 'bg-slate-100 text-slate-400'"
                                class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-black shrink-0 transition-all hover:scale-110">
                                {{ i + 1 }}
                            </button>
                            <div v-if="i < statusSteps.length - 1"
                                :class="i < currentStepIndex ? `bg-${step.color}-300` : 'bg-slate-200'"
                                class="h-0.5 flex-1 mx-1 transition-colors"></div>
                        </div>
                    </div>
                    <div class="flex justify-between mt-2 text-[9px] font-bold uppercase tracking-wider text-slate-500">
                        <span v-for="step in statusSteps" :key="step.key" class="text-center flex-1">{{ step.label }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- LEFT: Activity Timeline -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Add Activity -->
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-4">Tambah Aktivitas</h2>
                    <form @submit.prevent="submitActivity">
                        <div class="flex flex-wrap gap-2 mb-3">
                            <button v-for="t in ['call','whatsapp','email','visit','meeting','note']" :key="t" type="button"
                                @click="activityForm.type = t"
                                :class="activityForm.type === t ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all capitalize">
                                {{ activityIcons[t] }} {{ t }}
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <input v-model="activityForm.description" type="text" placeholder="Deskripsi aktivitas..." class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                            <button type="submit" :disabled="activityForm.processing" class="px-5 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition-colors disabled:opacity-50">Kirim</button>
                        </div>
                    </form>
                </div>

                <!-- Timeline -->
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-4">Riwayat Aktivitas</h2>
                    <div v-if="lead.activities?.length" class="space-y-4">
                        <div v-for="act in lead.activities" :key="act.id" class="flex gap-3">
                            <div class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center text-sm shrink-0">{{ activityIcons[act.type] || '📝' }}</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-slate-900">{{ act.description }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] text-slate-400 font-medium">{{ act.user?.name }}</span>
                                    <span class="text-[10px] text-slate-300">•</span>
                                    <span class="text-[10px] text-slate-400">{{ timeAgo(act.created_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-400">Belum ada aktivitas tercatat.</p>
                </div>
            </div>

            <!-- RIGHT: Details -->
            <div class="space-y-6">
                <!-- Negotiation List Card -->
                <div class="bg-white rounded-2xl border border-amber-200/80 p-5 shadow-sm space-y-3 bg-gradient-to-br from-amber-50/30 to-white">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                            <span>🤝</span> Riwayat Form Negosiasi
                        </h2>
                        <button @click="showNegoModal = true" class="px-2.5 py-1 bg-amber-500 text-white text-[10px] font-black rounded-lg hover:bg-amber-600 transition-all shadow-sm shrink-0">
                            + Kirim Link
                        </button>
                    </div>
                    <div v-if="lead.negotiations?.length" class="space-y-3">
                        <div v-for="nego in lead.negotiations" :key="nego.id" class="p-3 bg-white rounded-xl border border-slate-200 text-xs space-y-2 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-black text-slate-800">Unit: {{ nego.unit?.unit_number || 'Semua Unit' }}</span>
                                <span :class="['px-2 py-0.5 rounded-full font-bold uppercase text-[9px]', negoStatusBadge(nego.status)]">
                                    {{ nego.status }}
                                </span>
                            </div>
                            <div v-if="nego.offered_price" class="text-[11px] text-slate-600 space-y-0.5">
                                <div>Penawaran: <strong class="text-emerald-700">Rp {{ Number(nego.offered_price).toLocaleString('id-ID') }}</strong></div>
                                <div>Listing: <span class="line-through text-slate-400">Rp {{ Number(nego.unit_listed_price).toLocaleString('id-ID') }}</span></div>
                            </div>
                            <div v-else class="text-[11px] text-slate-400 italic">
                                Belum diisi client (menunggu submit)
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                                <Link :href="`/negotiations/${nego.id}`" class="text-[10px] font-bold text-blue-600 hover:underline">Detail CRM →</Link>
                                <button @click="copyNegoLink(nego.token)" class="text-[10px] font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 px-2 py-1 rounded-lg border border-emerald-200">
                                    📋 Salin Link WA
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="p-3 bg-slate-50/80 rounded-xl text-center border border-slate-100">
                        <p class="text-[11px] text-slate-500 mb-2 font-medium">Belum ada form negosiasi untuk lead ini.</p>
                        <button @click="showNegoModal = true" class="px-3 py-1.5 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-xs font-bold rounded-xl hover:scale-105 transition-all shadow-sm">
                            🤝 Generate Form Negosiasi Pertama
                        </button>
                    </div>
                </div>

                <!-- Lead Info -->
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Detail Lead</h2>
                        <button @click="showEditLeadModal = true" class="text-xs font-bold text-blue-600 hover:underline flex items-center gap-1 cursor-pointer">
                            ✏️ Edit Data
                        </button>
                    </div>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between"><span class="text-slate-500">Nama</span><span class="font-bold text-slate-900">{{ lead.name }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">No. HP</span><span class="font-bold text-slate-900">{{ lead.phone }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Email</span><span class="font-bold text-slate-900">{{ lead.email || '-' }}</span></div>
                        <div v-if="lead.identity_number" class="flex justify-between"><span class="text-slate-500">NIK (KTP)</span><span class="font-bold text-slate-900">{{ lead.identity_number }}</span></div>
                        <div v-if="lead.job" class="flex justify-between"><span class="text-slate-500">Pekerjaan</span><span class="font-bold text-slate-900">{{ lead.job }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Proyek</span><span class="font-bold text-slate-900">{{ lead.project?.name || '-' }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Sumber</span><span class="font-bold capitalize text-slate-900">{{ lead.source?.replace('_', ' ') }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Broker</span><span class="font-bold text-slate-900">{{ lead.broker_company?.name || '-' }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Terakhir Kontak</span><span class="font-bold text-slate-900">{{ lead.last_contacted_at ? timeAgo(lead.last_contacted_at) : '-' }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Masuk</span><span class="font-bold text-slate-900">{{ new Date(lead.created_at).toLocaleDateString('id-ID') }}</span></div>
                    </div>
                </div>

                <!-- Assign Agent Card -->
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-1.5">
                            <span>👤</span>
                            <span>Sales PIC / Agen</span>
                        </h2>
                        <span v-if="lead.broker_company" class="px-2 py-0.5 bg-amber-100 text-amber-800 text-[10px] font-bold rounded-full">
                            🏢 {{ lead.broker_company.code || lead.broker_company.name }}
                        </span>
                    </div>

                    <!-- Search Input -->
                    <div class="relative">
                        <input 
                            v-model="agentSearchQuery" 
                            type="text" 
                            placeholder="🔍 Cari nama agen / kantor..." 
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-blue-500/20" 
                        />
                    </div>

                    <!-- Categorized Select Dropdown -->
                    <select v-model="editForm.assigned_to" @change="updateLead()" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                        <option :value="null">-- Belum di-assign --</option>

                        <!-- Priority Group: Sub-agents from Lead's Broker Company -->
                        <optgroup v-if="matchingLeadBrokerAgents.length" :label="`⭐ SUB-AGENT KANTOR LEAD (${lead.broker_company?.name})`">
                            <option v-for="a in matchingLeadBrokerAgents" :key="'match-' + a.id" :value="a.id">
                                ⭐ {{ a.name }} ({{ a.broker_company ? a.broker_company.name : 'Agency' }})
                            </option>
                        </optgroup>

                        <!-- Group: All Agency Sub-Agents -->
                        <optgroup label="🏢 SUB-AGENT KANTOR AGENCY (BROKER)">
                            <option v-for="a in filteredAgentsList.filter(x => x.broker_company_id || x.agent_type === 'agency_agent')" :key="'agency-' + a.id" :value="a.id">
                                🏢 {{ a.name }} ({{ a.broker_company ? a.broker_company.name : 'Agency' }})
                            </option>
                        </optgroup>

                        <!-- Group: Independent Freelance Agents -->
                        <optgroup label="💼 AGEN FREELANCE INDEPENDEN">
                            <option v-for="a in filteredAgentsList.filter(x => x.agent_type === 'independent')" :key="'indep-' + a.id" :value="a.id">
                                💼 {{ a.name }} (Freelance)
                            </option>
                        </optgroup>

                        <!-- Group: In-House Sales Team -->
                        <optgroup label="🏠 TIM SALES IN-HOUSE">
                            <option v-for="a in filteredAgentsList.filter(x => !x.broker_company_id && x.agent_type === 'inhouse')" :key="'inhouse-' + a.id" :value="a.id">
                                🏠 {{ a.name }} (In-House)
                            </option>
                        </optgroup>
                    </select>

                    <!-- Assigned Agent Card Detail Summary -->
                    <div v-if="currentAssignedAgent" class="p-3 rounded-xl border text-xs space-y-1.5 transition-all"
                        :class="currentAssignedAgent.broker_company_id ? 'bg-amber-50/70 border-amber-200' : 'bg-blue-50/70 border-blue-200'">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-900">{{ currentAssignedAgent.name }}</span>
                            <span v-if="currentAssignedAgent.broker_company_id" class="px-2 py-0.5 bg-amber-200 text-amber-900 text-[9px] font-extrabold rounded-md">
                                Sub-Agent
                            </span>
                            <span v-else-if="currentAssignedAgent.agent_type === 'independent'" class="px-2 py-0.5 bg-emerald-200 text-emerald-900 text-[9px] font-extrabold rounded-md">
                                Independen
                            </span>
                            <span v-else class="px-2 py-0.5 bg-blue-200 text-blue-900 text-[9px] font-extrabold rounded-md">
                                Sales In-House
                            </span>
                        </div>
                        <div v-if="currentAssignedAgent.broker_company || lead.broker_company" class="text-[11px] text-amber-800 font-semibold flex items-center gap-1">
                            <span>🏢 Kantor:</span>
                            <span>{{ currentAssignedAgent.broker_company?.name || lead.broker_company?.name }}</span>
                        </div>
                        <p v-if="currentAssignedAgent.broker_company_id || lead.broker_company_id" class="text-[9.5px] text-amber-900 italic font-medium pt-0.5 border-t border-amber-200/60">
                            🔒 Komisi & pencairan mengacu ke Rekening Kantor Agency.
                        </p>
                    </div>
                </div>

                <!-- Notes -->
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3">Catatan</h2>
                    <textarea v-model="editForm.notes" @blur="updateLead()" rows="4" placeholder="Catatan internal..." class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 resize-none"></textarea>
                </div>

                <!-- Smart WA Follow-up -->
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                        <span>💬</span> Smart WA Follow-up
                    </h2>
                    <div class="space-y-3">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase">Pilih Template Pesan</label>
                        <div class="grid grid-cols-2 gap-2 text-[10px]">
                            <button type="button" @click="generateWaMessage('perkenalan')" class="p-2 border border-slate-200 rounded-xl font-bold bg-slate-50 hover:bg-slate-100 text-slate-700 text-left">👋 Perkenalan</button>
                            <button type="button" @click="generateWaMessage('visit')" class="p-2 border border-slate-200 rounded-xl font-bold bg-slate-50 hover:bg-slate-100 text-slate-700 text-left">🏠 Site Visit</button>
                            <button type="button" @click="generateWaMessage('booking_reminder')" class="p-2 border border-slate-200 rounded-xl font-bold bg-slate-50 hover:bg-slate-100 text-slate-700 text-left">💳 Tagihan UTJ</button>
                            <button type="button" @click="generateWaMessage('custom')" class="p-2 border border-slate-200 rounded-xl font-bold bg-slate-50 hover:bg-slate-100 text-slate-700 text-left">✏️ Custom Follow-up</button>
                        </div>
                        <div v-if="generatedMessage" class="mt-4 pt-4 border-t border-slate-100 space-y-3">
                            <textarea v-model="generatedMessage" rows="5" class="w-full p-3 bg-slate-50 border-none rounded-xl text-xs leading-relaxed focus:ring-1 focus:ring-blue-500 font-sans"></textarea>
                            <a :href="`https://wa.me/${(lead.phone || '').replace(/^0/, '62').replace(/[^0-9]/g, '')}?text=${encodeURIComponent(generatedMessage)}`" target="_blank"
                                class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black rounded-xl transition-all shadow-lg shadow-emerald-500/20 text-center block uppercase tracking-widest">
                                Kirim WhatsApp →
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Reminders -->
                <div v-if="lead.reminders?.length" class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3">Reminders</h2>
                    <div class="space-y-2">
                        <div v-for="r in lead.reminders" :key="r.id" class="flex items-center gap-3 p-2.5 rounded-xl"
                            :class="r.status === 'pending' ? 'bg-amber-50' : 'bg-slate-50'">
                            <span class="text-sm">{{ r.status === 'pending' ? '⏰' : '✅' }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-slate-700 truncate">{{ r.message || 'Follow up' }}</p>
                                <p class="text-[10px] text-slate-400">{{ new Date(r.remind_at).toLocaleString('id-ID') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- REMINDER MODAL -->
        <teleport to="body">
            <div v-if="showReminderModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showReminderModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h2 class="text-lg font-black text-slate-900 mb-4">Set Follow-Up Reminder</h2>
                    <form @submit.prevent="submitReminder" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Waktu Reminder <span class="text-rose-500">*</span></label>
                            <input v-model="reminderForm.remind_at" type="datetime-local" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Pesan</label>
                            <textarea v-model="reminderForm.message" rows="3" placeholder="Follow up soal tipe unit..." class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 resize-none"></textarea>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="showReminderModal = false" class="px-5 py-2.5 text-sm font-bold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200">Batal</button>
                            <button type="submit" :disabled="reminderForm.processing" class="px-6 py-2.5 bg-amber-500 text-white text-sm font-bold rounded-xl hover:bg-amber-600 shadow-lg transition-all">Set Reminder</button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <!-- NEGOTIATION MODAL -->
        <teleport to="body">
            <div v-if="showNegoModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showNegoModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 overflow-y-auto max-h-[90vh]">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-lg font-black text-slate-900 flex items-center gap-2">
                            <span>🤝</span> Kirim Form Negosiasi
                        </h2>
                        <button @click="showNegoModal = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                    </div>
                    <p class="text-xs text-slate-500 mb-5 leading-relaxed">
                        Generate link form pengajuan harga & fasilitas khusus untuk dikirimkan ke calon pembeli via WhatsApp.
                    </p>
                    
                    <form @submit.prevent="submitNegoForm" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Pilih Unit Rumah <span class="text-rose-500">*</span></label>
                            <select v-model="negoForm.unit_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                                <option value="">-- Pilih Unit Proyek --</option>
                                <option v-for="u in units" :key="u.id" :value="u.id">
                                    Unit {{ u.unit_number }} - {{ u.unit_type?.name || 'Standard' }} (Listing: Rp {{ Number(u.final_price || u.price).toLocaleString('id-ID') }})
                                </option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Client <span class="text-rose-500">*</span></label>
                                <input v-model="negoForm.client_name" type="text" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-amber-500/20" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">No. WhatsApp <span class="text-rose-500">*</span></label>
                                <input v-model="negoForm.client_phone" type="text" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-amber-500/20" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Email Client (Opsional)</label>
                            <input v-model="negoForm.client_email" type="email" placeholder="client@email.com" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-amber-500/20" />
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                            <button type="button" @click="showNegoModal = false" class="px-5 py-2.5 text-xs font-bold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Batal</button>
                            <button type="submit" :disabled="negoForm.processing" class="px-6 py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-xs font-black rounded-xl hover:scale-105 shadow-lg shadow-amber-500/25 transition-all disabled:opacity-50">
                                🚀 Generate Link & Buka Form
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <!-- SHARE NEGO LINK MODAL -->
        <teleport to="body">
            <div v-if="showShareNegoModal && createdNegoLink" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showShareNegoModal = false"></div>
                <div class="relative bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 space-y-5 shadow-2xl animate-in zoom-in duration-150">
                    <div class="text-center">
                        <div class="w-14 h-14 bg-emerald-100 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3 shadow-inner">🤝</div>
                        <h3 class="text-base font-black text-slate-900">Form Negosiasi Siap Dikirim!</h3>
                        <p class="text-xs text-slate-500 mt-1">Kirimkan link form ini ke <strong>{{ lead.name }}</strong> agar client mengisi penawaran harga secara mandiri.</p>
                    </div>

                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2">
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-wider">Link Form Negosiasi</p>
                        <div class="flex items-center gap-2">
                            <input :value="createdNegoLink" readonly class="flex-1 px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-mono text-blue-600 truncate" />
                            <button @click="copyNegoLink(createdNegoLink)" class="px-3 py-2 bg-slate-900 text-white rounded-xl text-[10px] font-bold hover:bg-slate-800 transition-all shrink-0">
                                📋 Salin
                            </button>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button @click="shareNegoWa" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-xl shadow-lg shadow-emerald-500/20 transition-all flex items-center justify-center gap-2">
                            💬 Kirim via WhatsApp
                        </button>
                        <button @click="showShareNegoModal = false" class="px-5 py-3 bg-slate-100 text-slate-600 font-bold text-xs rounded-xl hover:bg-slate-200 transition-all">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </teleport>

        <!-- EDIT LEAD PROFILE MODAL -->
        <teleport to="body">
            <div v-if="showEditLeadModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showEditLeadModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg p-6 overflow-y-auto max-h-[90vh]">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                        <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                            <span>✏️</span> Edit Profil Lead
                        </h2>
                        <button @click="showEditLeadModal = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                    </div>

                    <form @submit.prevent="updateLead" class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                                <input v-model="editForm.name" type="text" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500/20" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">No. WhatsApp / HP <span class="text-rose-500">*</span></label>
                                <input v-model="editForm.phone" type="text" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500/20" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Email</label>
                                <input v-model="editForm.email" type="email" placeholder="email@domain.com" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500/20" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Pekerjaan</label>
                                <input v-model="editForm.job" type="text" placeholder="PNS, Swasta, Wiraswasta..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500/20" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">NIK (No. KTP)</label>
                                <input v-model="editForm.identity_number" type="text" placeholder="320xxxxxxxxxxxxx" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500/20" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">NPWP</label>
                                <input v-model="editForm.npwp" type="text" placeholder="NPWP..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500/20" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Alamat Lengkap</label>
                            <textarea v-model="editForm.address" rows="2" placeholder="Alamat tinggal client..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500/20 resize-none"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Sumber Lead</label>
                                <select v-model="editForm.source" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                                    <option value="facebook">Facebook Ads</option>
                                    <option value="instagram">Instagram Ads</option>
                                    <option value="google">Google Ads</option>
                                    <option value="tiktok">TikTok Ads</option>
                                    <option value="walk_in">Walk-in (Datang Langsung)</option>
                                    <option value="referral">Referral</option>
                                    <option value="broker">Broker / Agency</option>
                                    <option value="website">Website</option>
                                    <option value="other">Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Status Pipeline</label>
                                <select v-model="editForm.status" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                                    <option v-for="step in statusSteps" :key="step.key" :value="step.key">
                                        {{ step.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 mt-6">
                            <button type="button" @click="showEditLeadModal = false" class="px-5 py-2.5 text-xs font-bold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Batal</button>
                            <button type="submit" :disabled="editForm.processing" class="px-6 py-2.5 bg-blue-600 text-white text-xs font-black rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-500/20 transition-all disabled:opacity-50">
                                💾 Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <KprCalculatorModal :show="showKprModal" @close="showKprModal = false" />
    </CrmLayout>
</template>
