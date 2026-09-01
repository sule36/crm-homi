<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const props = defineProps({
    masterLeads: Object,
    brokers: Object,
    allAgents: Object,
    subAgentCommissions: Object,
    mlOverridingCommissions: Array,
    brokerList: Array,
    masterLeadList: Array,
    stats: Object,
    filters: Object,
});

const page = usePage();
const flashCredentials = computed(() => page.props.flash?.new_credentials || null);

// Tabs: 'hierarchy' | 'agencies' | 'agents' | 'ledger'
const activeTab = ref('hierarchy');
const ledgerSubTab = ref('sub_agent'); // 'sub_agent' | 'master_lead'

// Search & Filter State
const searchMl = ref(props.filters?.search || '');
const searchAgency = ref(props.filters?.search_agency || '');
const searchAgent = ref(props.filters?.search_agent || '');
const searchLedger = ref(props.filters?.search_ledger || '');

let searchTimeout = null;
function searchMasterLeads() {
    router.get(route('master-leads.index'), { 
        search: searchMl.value, 
        search_agency: searchAgency.value,
        search_agent: searchAgent.value,
        search_ledger: searchLedger.value,
    }, { preserveState: true, replace: true });
}
function debouncedSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => searchMasterLeads(), 400);
}

function formatCurrency(val) {
    if (!val) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
}

// Accordion open/close state for Master Leads
const expandedMasterLeadIds = ref([]);
function toggleExpand(id) {
    if (expandedMasterLeadIds.value.includes(id)) {
        expandedMasterLeadIds.value = expandedMasterLeadIds.value.filter(i => i !== id);
    } else {
        expandedMasterLeadIds.value.push(id);
    }
}

// -------------------------------------------------------------
// MODAL 1: CREATE / EDIT MASTER LEAD
// -------------------------------------------------------------
const showMlModal = ref(false);
const editingMl = ref(null);

const mlForm = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    commission_rate: 4.5,
    bank_name: '',
    bank_account_number: '',
    bank_account_name: '',
    status: 'active',
});

function generateRandomPassword() {
    const chars = 'abcdefghjkmnpqrstuvwxyz23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    let pass = '';
    for (let i = 0; i < 8; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    mlForm.password = pass;
}

function openMlModal(ml = null) {
    editingMl.value = ml;
    if (ml) {
        mlForm.name = ml.name;
        mlForm.email = ml.email;
        mlForm.phone = ml.phone || '';
        mlForm.password = '';
        mlForm.commission_rate = ml.commission_rate || 4.5;
        mlForm.bank_name = ml.bank_name || '';
        mlForm.bank_account_number = ml.bank_account_number || '';
        mlForm.bank_account_name = ml.bank_account_name || '';
        mlForm.status = ml.status || 'active';
    } else {
        mlForm.reset();
        mlForm.commission_rate = props.stats.default_overriding_rate || 4.5;
        mlForm.status = 'active';
        generateRandomPassword();
    }
    showMlModal.value = true;
}

function submitMlForm() {
    if (editingMl.value) {
        mlForm.put(route('master-leads.update', editingMl.value.id), {
            onSuccess: () => { showMlModal.value = false; }
        });
    } else {
        const createdPass = mlForm.password;
        mlForm.post(route('master-leads.store'), {
            onSuccess: () => {
                showMlModal.value = false;
                if (flashCredentials.value) {
                    selectedCredential.value = flashCredentials.value;
                } else {
                    selectedCredential.value = {
                        name: mlForm.name,
                        email: mlForm.email,
                        phone: mlForm.phone,
                        password: createdPass,
                        login_url: window.location.origin + '/login',
                    };
                }
                showCredentialModal.value = true;
            }
        });
    }
}

function deleteMasterLead(ml) {
    if (confirm(`Apakah Anda yakin ingin menghapus Master Lead ${ml.name}? Seluruh sub-agent di bawahnya akan berdiri sendiri.`)) {
        router.delete(route('master-leads.destroy', ml.id));
    }
}

// -------------------------------------------------------------
// MODAL 2: SALIN / KIRIM AKSES LOGIN MASTER LEAD
// -------------------------------------------------------------
const showCredentialModal = ref(false);
const selectedCredential = ref(null);
const copiedSuccess = ref(false);

function openCredentialModal(ml) {
    selectedCredential.value = {
        name: ml.name,
        email: ml.email,
        phone: ml.phone,
        password: '(Password saat didaftarkan)',
        login_url: window.location.origin + '/login',
    };
    copiedSuccess.value = false;
    showCredentialModal.value = true;
}

if (flashCredentials.value) {
    selectedCredential.value = flashCredentials.value;
    showCredentialModal.value = true;
}

function copyCredentialsText() {
    if (!selectedCredential.value) return;
    const text = `Halo ${selectedCredential.value.name},\nBerikut adalah akses login Portal CRM Master Lead Perusahaan:\n\n🌐 URL Login: ${selectedCredential.value.login_url}\n📧 Email: ${selectedCredential.value.email}\n🔑 Password: ${selectedCredential.value.password}\n\nSilakan login dan daftarkan tim sub-agent Anda.`;
    navigator.clipboard.writeText(text);
    copiedSuccess.value = true;
    setTimeout(() => { copiedSuccess.value = false; }, 3000);
}

function shareViaWhatsApp() {
    if (!selectedCredential.value) return;
    const text = `Halo ${selectedCredential.value.name},\nBerikut adalah akses login Portal CRM Master Lead Perusahaan:\n\n🌐 URL Login: ${selectedCredential.value.login_url}\n📧 Email: ${selectedCredential.value.email}\n🔑 Password: ${selectedCredential.value.password}\n\nSilakan login dan daftarkan tim sub-agent Anda.`;
    const phone = (selectedCredential.value.phone || '').replace(/[^0-9]/g, '');
    const waPhone = phone.startsWith('0') ? '62' + phone.slice(1) : phone;
    const url = waPhone ? `https://wa.me/${waPhone}?text=${encodeURIComponent(text)}` : `https://wa.me/?text=${encodeURIComponent(text)}`;
    window.open(url, '_blank');
}

// -------------------------------------------------------------
// MODAL 3: CREATE / EDIT SUB-AGENT (LEVEL 3)
// -------------------------------------------------------------
const showSubAgentModal = ref(false);
const editingAgent = ref(null);

const agentForm = useForm({
    name: '',
    phone: '',
    email: '',
    role: 'sales_agent',
    agent_type: 'agency_agent',
    broker_company_id: null,
    master_lead_id: null,
    commission_rate: '',
    custom_bonus: 0,
    bank_name: '',
    bank_account_number: '',
    bank_account_name: '',
});

function openSubAgentModal(agent = null, presetMasterLeadId = null, presetBrokerCompanyId = null) {
    editingAgent.value = agent;
    if (agent) {
        agentForm.name = agent.name;
        agentForm.phone = agent.phone || '';
        agentForm.email = agent.email || '';
        agentForm.agent_type = agent.agent_type || 'agency_agent';
        agentForm.broker_company_id = agent.broker_company_id || null;
        agentForm.master_lead_id = agent.master_lead_id || presetMasterLeadId;
        agentForm.commission_rate = agent.commission_rate || '';
        agentForm.custom_bonus = agent.custom_bonus || 0;
        agentForm.bank_name = agent.bank_name || '';
        agentForm.bank_account_number = agent.bank_account_number || '';
        agentForm.bank_account_name = agent.bank_account_name || '';
    } else {
        agentForm.reset();
        agentForm.agent_type = presetBrokerCompanyId ? 'agency_agent' : 'agency_agent';
        agentForm.broker_company_id = presetBrokerCompanyId || (props.brokerList?.[0]?.id || null);
        agentForm.master_lead_id = presetMasterLeadId || (props.masterLeadList?.[0]?.id || null);
    }
    showSubAgentModal.value = true;
}

function submitSubAgent() {
    const payload = {
        name: agentForm.name,
        phone: agentForm.phone || null,
        email: agentForm.email || null,
        role: 'sales_agent',
        agent_type: agentForm.agent_type,
        broker_company_id: agentForm.agent_type === 'agency_agent' ? (agentForm.broker_company_id || null) : null,
        master_lead_id: agentForm.master_lead_id || null,
        commission_rate: (agentForm.commission_rate !== '' && agentForm.commission_rate !== null) ? Number(agentForm.commission_rate) : null,
        custom_bonus: (agentForm.custom_bonus !== '' && agentForm.custom_bonus !== null) ? Number(agentForm.custom_bonus) : 0,
        bank_name: agentForm.bank_name || null,
        bank_account_number: agentForm.bank_account_number || null,
        bank_account_name: agentForm.bank_account_name || null,
    };

    if (editingAgent.value) {
        router.put(route('users.update', editingAgent.value.id), payload, {
            onSuccess: () => { showSubAgentModal.value = false; }
        });
    } else {
        router.post(route('users.store'), payload, {
            onSuccess: () => { showSubAgentModal.value = false; }
        });
    }
}

// -------------------------------------------------------------
// MODAL 4: CREATE / EDIT KANTOR AGENCY (BROKER COMPANY)
// -------------------------------------------------------------
const showAgencyModal = ref(false);
const editingAgency = ref(null);

const agencyForm = useForm({
    name: '',
    code: '',
    master_lead_id: null,
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    commission_rate: 3.0,
    status: 'active',
    bank_name: '',
    bank_account_number: '',
    bank_account_name: '',
    notes: '',
});

function openAgencyModal(broker = null) {
    editingAgency.value = broker;
    if (broker) {
        agencyForm.name = broker.name;
        agencyForm.code = broker.code || '';
        agencyForm.master_lead_id = broker.master_lead_id || null;
        agencyForm.contact_person = broker.contact_person || '';
        agencyForm.phone = broker.phone || '';
        agencyForm.email = broker.email || '';
        agencyForm.address = broker.address || '';
        agencyForm.commission_rate = broker.commission_rate || 3.0;
        agencyForm.status = broker.status || 'active';
        agencyForm.bank_name = broker.bank_name || '';
        agencyForm.bank_account_number = broker.bank_account_number || '';
        agencyForm.bank_account_name = broker.bank_account_name || '';
        agencyForm.notes = broker.notes || '';
    } else {
        agencyForm.reset();
        agencyForm.commission_rate = 3.0;
        agencyForm.status = 'active';
    }
    showAgencyModal.value = true;
}

function submitAgency() {
    if (editingAgency.value) {
        agencyForm.put(route('settings.brokers.update', editingAgency.value.id), {
            onSuccess: () => { showAgencyModal.value = false; }
        });
    } else {
        agencyForm.post(route('settings.brokers.store'), {
            onSuccess: () => { showAgencyModal.value = false; }
        });
    }
}

function deleteAgency(broker) {
    if (confirm(`Apakah Anda yakin ingin menghapus kantor agency ${broker.name}?`)) {
        router.delete(route('settings.brokers.destroy', broker.id));
    }
}

// -------------------------------------------------------------
// MODAL 5: CATAT TRANSFER KOMISI SUB-AGENT (MASTER LEAD -> SUB AGENT)
// -------------------------------------------------------------
const showPaySubAgentModal = ref(false);
const selectedSubAgentComm = ref(null);
const paySubAgentForm = useForm({
    receipt_number: '',
    notes: '',
});

function openPaySubAgentModal(comm) {
    selectedSubAgentComm.value = comm;
    paySubAgentForm.receipt_number = 'TRF-ML-' + Math.floor(100000 + Math.random() * 900000);
    paySubAgentForm.notes = '';
    showPaySubAgentModal.value = true;
}

function submitPaySubAgent() {
    if (!selectedSubAgentComm.value) return;
    paySubAgentForm.post(route('master-leads.pay-sub-agent', selectedSubAgentComm.value.id), {
        onSuccess: () => { showPaySubAgentModal.value = false; }
    });
}
</script>

<template>
    <CrmLayout>
        <Head title="Jaringan Master Lead & Sales Agent" />

        <div class="p-4 sm:p-6 space-y-6 max-w-7xl mx-auto">
            <!-- PAGE HEADER (ERP STYLE) -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                        <span>Jaringan Keagenan</span>
                        <span>/</span>
                        <span class="text-blue-600 font-semibold">Master Lead & Sub-Agent</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                        <span>Jaringan Keagenan Sales</span>
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Hirarki Penjualan: <span class="font-bold text-slate-700 dark:text-slate-300">Developer ➔ Master Lead ➔ Sub-Agent</span>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button 
                        @click="openMlModal()" 
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-blue-500/20 hover:shadow-blue-500/35 hover:-translate-y-0.5 transition-all"
                    >
                        <span>👑</span>
                        <span>Buat Master Lead Baru</span>
                    </button>
                    <button 
                        @click="openSubAgentModal()" 
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 text-white text-xs font-bold rounded-xl transition-all border border-slate-700"
                    >
                        <span>➕</span>
                        <span>Tambah Sub-Agent</span>
                    </button>
                    <button 
                        @click="openAgencyModal()" 
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 text-xs font-bold rounded-xl transition-all border border-slate-300 dark:border-slate-700"
                    >
                        <span>🏢</span>
                        <span>Daftar Kantor Agency</span>
                    </button>
                </div>
            </div>

            <!-- METRIC CARDS (ERP STYLE) -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl flex items-center gap-3.5 shadow-sm">
                    <div class="w-11 h-11 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold text-lg shrink-0">👑</div>
                    <div>
                        <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total Master Lead</div>
                        <div class="text-xl font-black text-slate-900 dark:text-white">{{ stats.total_master_leads }} Partner</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl flex items-center gap-3.5 shadow-sm">
                    <div class="w-11 h-11 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-lg shrink-0">👥</div>
                    <div>
                        <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total Sub-Agent</div>
                        <div class="text-xl font-black text-slate-900 dark:text-white">{{ stats.total_sub_agents }} Agen Sales</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl flex items-center gap-3.5 shadow-sm">
                    <div class="w-11 h-11 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-lg shrink-0">🏢</div>
                    <div>
                        <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Kantor Agency</div>
                        <div class="text-xl font-black text-slate-900 dark:text-white">{{ stats.total_brokers }} Broker</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl flex items-center gap-3.5 shadow-sm">
                    <div class="w-11 h-11 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-lg shrink-0">📈</div>
                    <div>
                        <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total Omset Tim</div>
                        <div class="text-lg font-black text-emerald-600 dark:text-emerald-400">{{ formatCurrency(stats.total_revenue) }}</div>
                    </div>
                </div>
            </div>

            <!-- TABS ERP NAVIGATION -->
            <div class="border-b border-slate-200 dark:border-slate-800 flex gap-6">
                <button 
                    @click="activeTab = 'hierarchy'"
                    :class="activeTab === 'hierarchy' ? 'border-blue-600 text-blue-600 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300 font-medium'"
                    class="py-3 px-1 border-b-2 text-xs transition-all flex items-center gap-2"
                >
                    <span>👑 Structure: Developer ➔ Master Lead ➔ Sub-Agent</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 font-bold">{{ masterLeads.data.length }} Master</span>
                </button>

                <button 
                    @click="activeTab = 'agencies'"
                    :class="activeTab === 'agencies' ? 'border-blue-600 text-blue-600 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300 font-medium'"
                    class="py-3 px-1 border-b-2 text-xs transition-all flex items-center gap-2"
                >
                    <span>🏢 Kantor Agency (Broker Companies)</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 font-bold">{{ brokers.total }}</span>
                </button>

                <button 
                    @click="activeTab = 'agents'"
                    :class="activeTab === 'agents' ? 'border-blue-600 text-blue-600 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300 font-medium'"
                    class="py-3 px-1 border-b-2 text-xs transition-all flex items-center gap-2"
                >
                    <span>👤 Semua Direktori Agen Sales</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-bold">{{ allAgents.total }}</span>
                </button>

                <button 
                    @click="activeTab = 'ledger'"
                    :class="activeTab === 'ledger' ? 'border-blue-600 text-blue-600 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300 font-medium'"
                    class="py-3 px-1 border-b-2 text-xs transition-all flex items-center gap-2"
                >
                    <span>💵 Neraca & Arus Kas Master Lead</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 font-bold">{{ subAgentCommissions?.total || 0 }} Trx</span>
                </button>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 1: HIERARCHICAL VIEW (DEVELOPER -> MASTER LEAD -> SUB-AGENT) -->
            <!-- ========================================================================= -->
            <div v-if="activeTab === 'hierarchy'" class="space-y-4">
                <!-- Search bar -->
                <div class="flex items-center justify-between gap-4 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="relative w-full sm:w-96">
                        <input 
                            v-model="searchMl"
                            @keyup.enter="searchMasterLeads"
                            @input="debouncedSearch"
                            type="text" 
                            placeholder="🔍 Cari Master Lead nama/email/wa..."
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-blue-500"
                        />
                    </div>
                    <div class="text-xs text-slate-500">
                        Pilih Master Lead untuk melihat/menambah **Sub-Agent** di bawah naungannya.
                    </div>
                </div>

                <!-- Master Leads Cards Loop -->
                <div v-for="ml in masterLeads.data" :key="ml.id" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm transition-all">
                    <!-- Master Lead Header (Level 2) -->
                    <div class="p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50/50 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-800">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xl font-bold shrink-0">
                                👑
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ ml.name }}</h3>
                                    <span class="px-2.5 py-0.5 bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 border border-purple-300 dark:border-purple-800 rounded-full text-[10px] font-bold">
                                        Overriding Rate: {{ ml.commission_rate }}%
                                    </span>
                                </div>
                                <div class="text-xs text-slate-500 flex items-center gap-3 mt-1">
                                    <span>📧 {{ ml.email }}</span>
                                    <span>•</span>
                                    <span>📱 WA: {{ ml.phone || '-' }}</span>
                                    <span v-if="ml.bank_name">• 💳 {{ ml.bank_name }} ({{ ml.bank_account_number }})</span>
                                </div>
                            </div>
                        </div>

                        <!-- Stats & Action Buttons -->
                        <div class="flex items-center gap-4">
                            <div class="text-right hidden lg:block">
                                <div class="text-[11px] text-slate-400">Total Omset Tim:</div>
                                <div class="text-sm font-black text-emerald-600 dark:text-emerald-400">{{ formatCurrency(ml.total_revenue) }}</div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button 
                                    @click="openCredentialModal(ml)"
                                    title="Kirim Kredensial Login" 
                                    class="px-3 py-1.5 bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 rounded-xl text-xs font-bold hover:bg-emerald-100 transition-all flex items-center gap-1"
                                >
                                    <span>🔑</span>
                                    <span>Akses Login</span>
                                </button>

                                <button 
                                    @click="openSubAgentModal(null, ml.id)" 
                                    class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1 shadow-md shadow-blue-600/20"
                                >
                                    <span>➕</span>
                                    <span>Tambah Sub-Agent</span>
                                </button>

                                <button 
                                    @click="openMlModal(ml)" 
                                    class="px-3 py-1.5 bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 text-slate-700 dark:text-slate-300 rounded-xl text-xs font-semibold"
                                >
                                    Edit
                                </button>

                                <button 
                                    @click="toggleExpand(ml.id)" 
                                    class="px-3 py-1.5 bg-purple-50 dark:bg-purple-950/60 hover:bg-purple-100 text-purple-700 dark:text-purple-300 border border-purple-300 dark:border-purple-800 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                                >
                                    <span>Sub-Agent ({{ ml.sub_agents.length }})</span>
                                    <span class="transition-transform duration-200" :class="expandedMasterLeadIds.includes(ml.id) ? 'rotate-180' : ''">▼</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Level 3: Nested Sub-Agents Table -->
                    <div v-if="expandedMasterLeadIds.includes(ml.id)" class="p-4 bg-white dark:bg-slate-950/60 border-t border-slate-200 dark:border-slate-800">
                        <div class="flex items-center justify-between mb-3 px-1">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                <span>↳ Sub-Agent yang bernaung di bawah</span>
                                <span class="text-purple-600 dark:text-purple-400 font-extrabold">{{ ml.name }}</span>
                            </span>
                            <span class="text-[11px] text-slate-400">{{ ml.sub_agents.length }} Sales Agent Terdaftar</span>
                        </div>

                        <div v-if="ml.sub_agents.length > 0" class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                            <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                                <thead class="bg-slate-100 dark:bg-slate-900 text-slate-500 font-semibold uppercase text-[10px] border-b border-slate-200 dark:border-slate-800">
                                    <tr>
                                        <th class="p-3">Nama Sub-Agent</th>
                                        <th class="p-3">Tipe Sales</th>
                                        <th class="p-3">Kantor Agency Naungan</th>
                                        <th class="p-3">Fee Rate Agen (%)</th>
                                        <th class="p-3">Rekening Pencairan</th>
                                        <th class="p-3">Total Penjualan</th>
                                        <th class="p-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="sa in ml.sub_agents" :key="sa.id" class="hover:bg-slate-50 dark:hover:bg-slate-900/50 transition-colors">
                                        <td class="p-3">
                                            <div class="font-bold text-slate-900 dark:text-white">{{ sa.name }}</div>
                                            <div class="text-[11px] text-slate-400">{{ sa.email }} • {{ sa.phone || '-' }}</div>
                                        </td>
                                        <td class="p-3">
                                            <span v-if="sa.agent_type === 'agency_agent'" class="px-2 py-0.5 bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-800 rounded-full font-semibold text-[10px]">
                                                🏢 Berbendera (Agency)
                                            </span>
                                            <span v-else-if="sa.agent_type === 'independent'" class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 rounded-full font-semibold text-[10px]">
                                                💼 Freelance Independen
                                            </span>
                                            <span v-else class="px-2 py-0.5 bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 border border-blue-300 dark:border-blue-800 rounded-full font-semibold text-[10px]">
                                                🤝 In-House Master Lead
                                            </span>
                                        </td>
                                        <td class="p-3">
                                            <div v-if="sa.broker_company_name" class="font-semibold text-slate-800 dark:text-slate-200">
                                                🏢 {{ sa.broker_company_name }}
                                            </div>
                                            <div v-else class="text-slate-400 italic text-[11px]">Direct Master Lead</div>
                                        </td>
                                        <td class="p-3 font-bold text-amber-600 dark:text-amber-400">
                                            {{ sa.commission_rate ? sa.commission_rate + '%' : 'Default' }}
                                        </td>
                                        <td class="p-3 text-[11px]">
                                            <div v-if="sa.effective_bank_account" class="font-semibold text-slate-700 dark:text-slate-300">
                                                <span v-if="sa.effective_bank_account.is_office" class="text-amber-600 dark:text-amber-400">🏢 Office Bank: {{ sa.effective_bank_account.bank_name }}</span>
                                                <span v-else>💼 {{ sa.effective_bank_account.bank_name }} - {{ sa.effective_bank_account.bank_account_number }}</span>
                                            </div>
                                            <div v-else class="text-slate-400 italic">Belum diatur</div>
                                        </td>
                                        <td class="p-3">
                                            <div class="font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(sa.total_revenue) }}</div>
                                            <div class="text-[10px] text-slate-400">{{ sa.total_bookings }} Unit Booked</div>
                                        </td>
                                        <td class="p-3 text-right">
                                            <button @click="openSubAgentModal(sa, ml.id)" class="px-2.5 py-1 bg-amber-50 dark:bg-amber-950/60 hover:bg-amber-100 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-800 rounded-lg font-bold text-[11px]">
                                                Edit Parameter
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="p-6 text-center text-xs text-slate-400 bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-dashed border-slate-200 dark:border-slate-800">
                            Belum ada sub-agent di bawah Master Lead ini. Klik "Tambah Sub-Agent" di kanan atas untuk mengaitkan agen.
                        </div>
                    </div>
                </div>

                <div v-if="masterLeads.data.length === 0" class="p-12 text-center text-slate-400 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
                    Belum ada Master Lead yang terdaftar. Klik "Buat Master Lead Baru" untuk mendaftarkan partner pertama Anda.
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 2: KANTOR AGENCY (BROKER COMPANIES) -->
            <!-- ========================================================================= -->
            <div v-if="activeTab === 'agencies'" class="space-y-4">
                <!-- Search bar for agencies -->
                <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="relative w-full sm:w-96">
                        <input 
                            v-model="searchAgency"
                            @keyup.enter="searchMasterLeads"
                            @input="debouncedSearch"
                            type="text" 
                            placeholder="🔍 Cari kantor agency nama/kode..."
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-blue-500"
                        />
                    </div>
                </div>

                <div v-if="brokers.data && brokers.data.length > 0" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                    <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                        <thead class="bg-slate-100 dark:bg-slate-900 text-slate-500 font-semibold uppercase text-[10px] border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="p-4">Kode & Nama Kantor Agency</th>
                                <th class="p-4">PIC / Kontak</th>
                                <th class="p-4">Jumlah Sub-Agent</th>
                                <th class="p-4">Akumulasi Komisi Kantor</th>
                                <th class="p-4">Rate Fee (%)</th>
                                <th class="p-4">Rekening Pencairan Kantor</th>
                                <th class="p-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="b in brokers.data" :key="b.id" class="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                <td class="p-4">
                                    <div class="font-bold text-slate-900 dark:text-white text-sm">{{ b.name }}</div>
                                    <div class="text-xs text-amber-600 font-mono font-bold">{{ b.code || 'NO-CODE' }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="font-medium text-slate-900 dark:text-slate-200">{{ b.contact_person || '-' }}</div>
                                    <div class="text-[11px] text-slate-400">{{ b.phone || '-' }}</div>
                                </td>
                                <td class="p-4">
                                    <span class="px-2.5 py-1 bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 border border-blue-300 dark:border-blue-800 rounded-full font-bold">
                                        🏢 {{ b.agents_count }} Sub-Agent
                                    </span>
                                </td>
                                <td class="p-4 font-bold text-emerald-600 dark:text-emerald-400">
                                    {{ formatCurrency(b.commissions_sum_amount) }}
                                </td>
                                <td class="p-4 font-bold text-amber-600 dark:text-amber-400 text-sm">
                                    {{ b.commission_rate ? b.commission_rate + '%' : 'Default' }}
                                </td>
                                <td class="p-4">
                                    <div v-if="b.bank_name" class="font-semibold text-slate-800 dark:text-slate-200">
                                        {{ b.bank_name }} - {{ b.bank_account_number }}
                                        <div class="text-[10px] text-slate-400">a.n {{ b.bank_account_name }}</div>
                                    </div>
                                    <div v-else class="text-slate-400 italic">Belum set</div>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="openAgencyModal(b)" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 rounded-xl font-semibold">
                                            Edit
                                        </button>
                                        <button @click="deleteAgency(b)" class="px-3 py-1.5 bg-red-50 dark:bg-red-950/40 hover:bg-red-100 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800 rounded-xl font-semibold text-[11px]">
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="brokers.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900">
                        <div class="text-[11px] text-slate-500">Menampilkan {{ brokers.from }}-{{ brokers.to }} dari {{ brokers.total }} kantor agency</div>
                        <div class="flex items-center gap-1">
                            <button v-for="link in brokers.links" :key="link.label" @click="link.url && router.get(link.url, {}, { preserveState: true })" :disabled="!link.url" :class="[link.active ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100', !link.url ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer']" class="px-3 py-1.5 rounded-lg text-[11px] font-semibold border border-slate-200 dark:border-slate-700" v-html="link.label"></button>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else class="p-12 text-center text-slate-400 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
                    Belum ada Kantor Agency yang terdaftar. Klik "Daftar Kantor Agency" untuk mendaftarkan kantor pertama.
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 3: FLAT ALL AGENTS DIRECTORY -->
            <!-- ========================================================================= -->
            <div v-if="activeTab === 'agents'" class="space-y-4">
                <!-- Search bar for agents -->
                <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="relative w-full sm:w-96">
                        <input 
                            v-model="searchAgent"
                            @keyup.enter="searchMasterLeads"
                            @input="debouncedSearch"
                            type="text" 
                            placeholder="🔍 Cari agen sales nama/email..."
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-blue-500"
                        />
                    </div>
                </div>

                <div v-if="allAgents.data && allAgents.data.length > 0" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                    <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                        <thead class="bg-slate-100 dark:bg-slate-900 text-slate-500 font-semibold uppercase text-[10px] border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="p-4">Nama Agen Sales</th>
                                <th class="p-4">Tipe Agen</th>
                                <th class="p-4">Master Lead Naungan</th>
                                <th class="p-4">Kantor Agency</th>
                                <th class="p-4">Rate Fee</th>
                                <th class="p-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="a in allAgents.data" :key="a.id" class="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                <td class="p-4">
                                    <div class="font-bold text-slate-900 dark:text-white text-sm">{{ a.name }}</div>
                                    <div class="text-[11px] text-slate-400">{{ a.email }} • {{ a.phone || '-' }}</div>
                                </td>
                                <td class="p-4">
                                    <span v-if="a.agent_type === 'master_lead'" class="px-2.5 py-1 bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 border border-purple-300 dark:border-purple-800 rounded-full font-bold">
                                        👑 Master Lead
                                    </span>
                                    <span v-else-if="a.agent_type === 'agency_agent'" class="px-2.5 py-1 bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-800 rounded-full font-semibold">
                                        🏢 Agency Sub-Agent
                                    </span>
                                    <span v-else-if="a.agent_type === 'independent'" class="px-2.5 py-1 bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 rounded-full font-semibold">
                                        💼 Freelance Independen
                                    </span>
                                    <span v-else class="px-2.5 py-1 bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 border border-blue-300 dark:border-blue-800 rounded-full font-semibold">
                                        🏠 In-House Developer
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div v-if="a.master_lead" class="font-bold text-purple-600 dark:text-purple-400">
                                        👑 {{ a.master_lead.name }}
                                    </div>
                                    <div v-else class="text-slate-400 italic">Developer Direct</div>
                                </td>
                                <td class="p-4">
                                    <div v-if="a.broker_company" class="font-bold text-slate-800 dark:text-slate-200">
                                        🏢 {{ a.broker_company.name }}
                                    </div>
                                    <div v-else class="text-slate-400 italic">Tanpa Kantor</div>
                                </td>
                                <td class="p-4 font-bold text-amber-600 dark:text-amber-400">
                                    {{ a.commission_rate ? a.commission_rate + '%' : 'Default' }}
                                </td>
                                <td class="p-4 text-right">
                                    <button @click="openSubAgentModal(a)" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-700 dark:text-slate-300 rounded-xl font-semibold">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="allAgents.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900">
                        <div class="text-[11px] text-slate-500">Menampilkan {{ allAgents.from }}-{{ allAgents.to }} dari {{ allAgents.total }} agen sales</div>
                        <div class="flex items-center gap-1">
                            <button v-for="link in allAgents.links" :key="link.label" @click="link.url && router.get(link.url, {}, { preserveState: true })" :disabled="!link.url" :class="[link.active ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100', !link.url ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer']" class="px-3 py-1.5 rounded-lg text-[11px] font-semibold border border-slate-200 dark:border-slate-700" v-html="link.label"></button>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else class="p-12 text-center text-slate-400 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
                    Belum ada agen sales yang terdaftar. Klik "Tambah Sub-Agent" untuk mendaftarkan agen pertama.
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 4: NERACA KEUANGAN & ARUS KAS MASTER LEAD (TRANSFER SUB-AGENT) -->
            <!-- ========================================================================= -->
            <div v-if="activeTab === 'ledger'" class="space-y-6">
                <!-- Summary Metrics Bar for Master Lead Cashflow -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl relative group">
                        <div class="text-[11px] font-semibold text-slate-500 uppercase flex items-center justify-between">
                            <span>👑 Net Profit ML (Overriding)</span>
                            <span class="cursor-help text-purple-500 font-bold" title="Rumus: Gross Fee ML (4.5%) - Komisi Sub-Agent (3.0%) = Net ML (1.5%)">ℹ️</span>
                        </div>
                        <div class="text-lg font-black text-purple-600 dark:text-purple-400 mt-1">{{ formatCurrency(stats.net_overriding_ml) }}</div>
                        <div class="text-[10px] text-slate-400 mt-0.5">Pendapatan Bersih (1.5% Net Overriding)</div>
                        <!-- Formula Explainer Tooltip -->
                        <div class="mt-2 p-2 bg-purple-50 dark:bg-purple-950/50 border border-purple-200 dark:border-purple-800/50 rounded-xl text-[9px] text-purple-900 dark:text-purple-300 font-medium leading-tight">
                            💡 Gross 4.5% (Rp 153Jt) - Sub-Agent 3.0% (Rp 102Jt) = <b>Net ML 1.5% (Rp 51Jt/unit)</b>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl">
                        <div class="text-[11px] font-semibold text-slate-500 uppercase">📥 Tot. Komisi Sub-Agent</div>
                        <div class="text-lg font-black text-slate-900 dark:text-white mt-1">{{ formatCurrency(stats.sub_agent_total_potency) }}</div>
                        <div class="text-[10px] text-slate-400 mt-0.5">Alokasi Komisi Tim Sub-Agent (3.0%)</div>
                    </div>
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl">
                        <div class="text-[11px] font-semibold text-slate-500 uppercase">✅ Dana Salur Selesai</div>
                        <div class="text-lg font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ formatCurrency(stats.sub_agent_paid_outflow) }}</div>
                        <div class="text-[10px] text-slate-400 mt-0.5">Sudah Ditransfer ke Sub-Agent</div>
                    </div>
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 rounded-2xl">
                        <div class="text-[11px] font-semibold text-slate-500 uppercase">⏳ Kewajiban Pending</div>
                        <div class="text-lg font-black text-amber-600 dark:text-amber-400 mt-1">{{ formatCurrency(stats.sub_agent_pending_outflow) }}</div>
                        <div class="text-[10px] text-slate-400 mt-0.5">Belum Ditransfer ke Sub-Agent</div>
                    </div>
                </div>

                <!-- Sub-Tab Toggle & Search bar for ledger -->
                <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <button 
                            @click="ledgerSubTab = 'sub_agent'"
                            :class="ledgerSubTab === 'sub_agent' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition-all"
                        >
                            📥 Kewajiban Salur Sub-Agent (Mawardi - 3.0%)
                        </button>
                        <button 
                            @click="ledgerSubTab = 'master_lead'"
                            :class="ledgerSubTab === 'master_lead' ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-700 dark:bg-purple-950 dark:text-purple-300'"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                        >
                            <span>👑</span> <span>Pendapatan Net Overriding ML (KANAHOMI - 1.5%)</span>
                        </button>
                    </div>

                    <div class="relative w-full sm:w-80">
                        <input 
                            v-model="searchLedger"
                            @keyup.enter="searchMasterLeads"
                            @input="debouncedSearch"
                            type="text" 
                            placeholder="🔍 Cari transaksi/sub-agent/konsumen..."
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-blue-500"
                        />
                    </div>
                </div>

                <!-- SUB-AGENT PAYOUT TABLE -->
                <div v-if="ledgerSubTab === 'sub_agent'">
                    <div v-if="subAgentCommissions && subAgentCommissions.data && subAgentCommissions.data.length > 0" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                    <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                        <thead class="bg-slate-100 dark:bg-slate-900 text-slate-500 font-semibold uppercase text-[10px] border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th class="p-4">Sub-Agent Penerima</th>
                                <th class="p-4">Unit Booking & Proyek</th>
                                <th class="p-4">Harga Jual Net Properti</th>
                                <th class="p-4">Hak Komisi Sub-Agent</th>
                                <th class="p-4">Pencairan Dev ➔ ML</th>
                                <th class="p-4">Transfer ML ➔ Sub-Agent</th>
                                <th class="p-4">No. Ref Transfer ML</th>
                                <th class="p-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="c in subAgentCommissions.data" :key="c.id" class="hover:bg-slate-50 dark:hover:bg-slate-900/50">
                                <td class="p-4">
                                    <div class="font-bold text-slate-900 dark:text-white text-sm">{{ c.user?.name }}</div>
                                    <div class="text-[11px] text-purple-600 dark:text-purple-400 font-semibold">👑 ML: {{ c.user?.master_lead?.name || 'Master Lead' }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="font-semibold text-slate-800 dark:text-slate-200">
                                        {{ c.booking?.unit ? ('Blok ' + c.booking.unit.block + ' No. ' + c.booking.unit.number) : 'Unit Booking' }}
                                    </div>
                                    <div class="text-[11px] text-slate-400">{{ c.booking?.lead?.name || '-' }} • {{ c.booking?.unit?.project?.name || '' }}</div>
                                </td>
                                <td class="p-4 font-mono font-bold text-slate-800 dark:text-slate-200">
                                    {{ formatCurrency(c.booking?.final_price || c.booking?.unit_price || 0) }}
                                </td>
                                <td class="p-4 font-bold text-emerald-600 dark:text-emerald-400 text-sm">
                                    {{ formatCurrency(c.amount) }}
                                </td>
                                <td class="p-4">
                                    <span v-if="c.status === 'paid'" class="px-2.5 py-1 bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 rounded-full font-bold text-[10px]">
                                        🟢 Dev Paid
                                    </span>
                                    <span v-else class="px-2.5 py-1 bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-800 rounded-full font-bold text-[10px]">
                                        ⏳ Dev Pending
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span v-if="c.ml_payout_status === 'paid'" class="px-2.5 py-1 bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 rounded-full font-bold text-[10px]">
                                        ✅ Sudah Salur
                                    </span>
                                    <span v-else class="px-2.5 py-1 bg-red-100 dark:bg-red-950 text-red-700 dark:text-red-300 border border-red-300 dark:border-red-800 rounded-full font-bold text-[10px]">
                                        🔴 Belum Ditransfer
                                    </span>
                                </td>
                                <td class="p-4 font-mono text-[11px]">
                                    <div v-if="c.ml_receipt_number" class="text-blue-600 dark:text-blue-400 font-bold">{{ c.ml_receipt_number }}</div>
                                    <div v-else class="text-slate-400 italic">Belum disalurkan</div>
                                </td>
                                <td class="p-4 text-right">
                                    <button 
                                        v-if="c.ml_payout_status !== 'paid'"
                                        @click="openPaySubAgentModal(c)" 
                                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold shadow-md shadow-emerald-600/20 text-[11px] flex items-center gap-1.5 ml-auto"
                                    >
                                        <span>💳</span>
                                        <span>Transfer ke Sub-Agent</span>
                                    </button>
                                    <span v-else class="text-xs text-slate-400 font-semibold italic">✓ Selesai</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="subAgentCommissions.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900">
                        <div class="text-[11px] text-slate-500">Menampilkan {{ subAgentCommissions.from }}-{{ subAgentCommissions.to }} dari {{ subAgentCommissions.total }} komisi sub-agent</div>
                        <div class="flex items-center gap-1">
                            <button v-for="link in subAgentCommissions.links" :key="link.label" @click="link.url && router.get(link.url, {}, { preserveState: true })" :disabled="!link.url" :class="[link.active ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100', !link.url ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer']" class="px-3 py-1.5 rounded-lg text-[11px] font-semibold border border-slate-200 dark:border-slate-700" v-html="link.label"></button>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="p-12 text-center text-slate-400 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
                    Belum ada transaksi pencairan komisi untuk Sub-Agent.
                </div>
            </div>

            <!-- MASTER LEAD OVERRIDING INCOME TABLE -->
            <div v-if="ledgerSubTab === 'master_lead'">
                <div v-if="mlOverridingCommissions && mlOverridingCommissions.length > 0" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                    <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                        <thead class="bg-purple-50/60 dark:bg-purple-950/40 text-purple-900 dark:text-purple-300 font-semibold uppercase text-[10px] border-b border-purple-200 dark:border-purple-800">
                            <tr>
                                <th class="p-4">Master Lead Penerima</th>
                                <th class="p-4">Unit Booking & Proyek</th>
                                <th class="p-4">Harga Jual Net Properti</th>
                                <th class="p-4">Skema Fee Overriding</th>
                                <th class="p-4">Gross ML Fee (4.5%)</th>
                                <th class="p-4">Sub-Agent Share (3.0%)</th>
                                <th class="p-4 text-right">Net Profit ML (1.5%)</th>
                                <th class="p-4 text-center">Status Dev</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="mc in mlOverridingCommissions" :key="mc.id" class="hover:bg-purple-50/30 transition-colors">
                                <td class="p-4">
                                    <div class="font-bold text-slate-900 dark:text-white text-sm">👑 {{ mc.user?.name || 'KANAHOMI' }}</div>
                                    <div class="text-[10px] text-purple-600 dark:text-purple-400 font-medium">Master Lead Partner</div>
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-slate-800 dark:text-slate-200">
                                        {{ mc.booking?.unit ? ('Blok ' + mc.booking.unit.block + ' No. ' + mc.booking.unit.number) : 'Unit Booking' }}
                                    </div>
                                    <div class="text-[11px] text-slate-400">{{ mc.booking?.lead?.name || '-' }} • {{ mc.booking?.unit?.project?.name || '' }}</div>
                                </td>
                                <td class="p-4 font-mono font-bold text-slate-800 dark:text-slate-200">
                                    {{ formatCurrency(mc.booking?.final_price || mc.booking?.unit_price || 0) }}
                                </td>
                                <td class="p-4">
                                    <div class="text-[10px] font-mono space-y-0.5">
                                        <span class="px-2 py-0.5 bg-purple-100 dark:bg-purple-950 text-purple-800 dark:text-purple-300 rounded font-bold">Rate: {{ mc.rate_used || 4.5 }}% Overriding</span>
                                    </div>
                                </td>
                                <td class="p-4 font-mono text-slate-500">
                                    {{ formatCurrency(mc.base_commission || (mc.amount * 3)) }}
                                </td>
                                <td class="p-4 font-mono text-amber-600 dark:text-amber-400">
                                    - {{ formatCurrency((mc.base_commission || (mc.amount * 3)) - mc.amount) }}
                                </td>
                                <td class="p-4 text-right font-black text-purple-600 dark:text-purple-400 text-sm">
                                    {{ formatCurrency(mc.amount) }}
                                </td>
                                <td class="p-4 text-center">
                                    <span v-if="mc.status === 'paid'" class="px-2.5 py-1 bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 rounded-full font-bold text-[10px]">
                                        🟢 Dev Paid
                                    </span>
                                    <span v-else class="px-2.5 py-1 bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-800 rounded-full font-bold text-[10px]">
                                        ⏳ Dev Pending
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="p-12 text-center text-slate-400 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
                    Belum ada riwayat pendapatan overriding Master Lead.
                </div>
            </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- MODALS (CLEAN ERP STYLE) -->
        <!-- ========================================================================= -->

        <!-- MODAL MASTER LEAD (CREATE & EDIT) -->
        <div v-if="showMlModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-5 shadow-2xl">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>👑</span>
                        <span>{{ editingMl ? 'Edit Data Master Lead' : 'Buat Akun Master Lead Baru' }}</span>
                    </h3>
                    <button @click="showMlModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitMlForm" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Nama Partner / Perusahaan Master Lead *</label>
                        <input v-model="mlForm.name" required type="text" placeholder="misal: Ray White Central / PT Master Properti" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Email Login *</label>
                            <input v-model="mlForm.email" required type="email" placeholder="master@agency.com" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">No. WhatsApp / HP</label>
                            <input v-model="mlForm.phone" type="text" placeholder="0812..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white" />
                        </div>
                    </div>

                    <div v-if="!editingMl">
                        <label class="block font-semibold text-blue-600 dark:text-blue-400 mb-1">Password Akses CRM Master Lead *</label>
                        <div class="flex gap-2">
                            <input v-model="mlForm.password" required type="text" placeholder="Password login..." class="w-full bg-slate-50 dark:bg-slate-950 border border-blue-400 rounded-xl px-3 py-2 text-blue-700 dark:text-blue-300 font-mono font-bold" />
                            <button type="button" @click="generateRandomPassword()" class="px-3 py-2 bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 text-slate-700 dark:text-slate-300 text-xs rounded-xl font-bold shrink-0">
                                🎲 Generate
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Overriding Rate (%)</label>
                            <input v-model="mlForm.commission_rate" type="number" step="0.1" placeholder="4.5" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-purple-600 dark:text-purple-400 font-bold text-sm" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Status Partner</label>
                            <select v-model="mlForm.status" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-bold">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 dark:border-slate-800 pt-3 space-y-2">
                        <label class="block font-semibold text-amber-600 dark:text-amber-400">Rekening Pencairan Overriding Fee Master Lead</label>
                        <div class="grid grid-cols-3 gap-2">
                            <input v-model="mlForm.bank_name" type="text" placeholder="Bank (BCA/BSI)" class="bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white text-xs" />
                            <input v-model="mlForm.bank_account_number" type="text" placeholder="No. Rekening" class="bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white text-xs" />
                            <input v-model="mlForm.bank_account_name" type="text" placeholder="Atas Nama" class="bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white text-xs" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showMlModal = false" class="px-4 py-2 bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl">Batal</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-600/20">
                            {{ editingMl ? 'Simpan Perubahan' : '🚀 Buat Akun & Generate Akses' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL CREDENTIAL SHARE -->
        <div v-if="showCredentialModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <div class="text-center space-y-1.5">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800 flex items-center justify-center text-xl mx-auto">
                        🔑
                    </div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Akses Login Master Lead</h3>
                    <p class="text-xs text-slate-500">Salin dan bagikan kredensial di bawah ini kepada Master Lead Anda.</p>
                </div>

                <div v-if="selectedCredential" class="bg-slate-50 dark:bg-slate-950 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-2 text-xs font-mono">
                    <div>
                        <div class="text-[10px] text-slate-500 uppercase font-sans">Nama Partner:</div>
                        <div class="text-slate-900 dark:text-white font-bold font-sans text-sm">{{ selectedCredential.name }}</div>
                    </div>
                    <div class="border-t border-slate-200 dark:border-slate-900 pt-2">
                        <div class="text-[10px] text-slate-500 uppercase font-sans">URL Login CRM:</div>
                        <div class="text-blue-600 dark:text-blue-400 font-bold select-all">{{ selectedCredential.login_url }}</div>
                    </div>
                    <div class="border-t border-slate-200 dark:border-slate-900 pt-2">
                        <div class="text-[10px] text-slate-500 uppercase font-sans">Email Login:</div>
                        <div class="text-emerald-600 dark:text-emerald-400 font-bold select-all">{{ selectedCredential.email }}</div>
                    </div>
                    <div class="border-t border-slate-200 dark:border-slate-900 pt-2">
                        <div class="text-[10px] text-slate-500 uppercase font-sans">Password:</div>
                        <div class="text-amber-600 dark:text-amber-400 font-bold select-all">{{ selectedCredential.password }}</div>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <button 
                        @click="shareViaWhatsApp()" 
                        class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl flex items-center justify-center gap-2 text-xs shadow-lg shadow-emerald-600/20"
                    >
                        <span>💬</span>
                        <span>Kirim Akses via WhatsApp</span>
                    </button>

                    <button 
                        @click="copyCredentialsText()" 
                        class="w-full py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 font-bold rounded-xl flex items-center justify-center gap-2 text-xs border border-slate-300 dark:border-slate-700"
                    >
                        <span>📋</span>
                        <span>{{ copiedSuccess ? '✓ Berhasil Disalin!' : 'Salin Text Kredensial' }}</span>
                    </button>

                    <button 
                        @click="showCredentialModal = false" 
                        class="w-full py-1 text-slate-400 hover:text-slate-600 text-xs font-semibold"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL SUB-AGENT (CREATE & EDIT LEVEL 3) -->
        <div v-if="showSubAgentModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>👤</span>
                        <span>{{ editingAgent ? 'Edit Parameter Sub-Agent' : 'Tambah Sub-Agent Baru' }}</span>
                    </h3>
                    <button @click="showSubAgentModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitSubAgent" class="space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Nama Sub-Agent *</label>
                            <input v-model="agentForm.name" required type="text" placeholder="Nama sales..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">No. WhatsApp / HP</label>
                            <input v-model="agentForm.phone" type="text" placeholder="0812..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white" />
                        </div>
                    </div>

                    <div v-if="!editingAgent">
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Email Agen (Opsional)</label>
                        <input v-model="agentForm.email" type="email" placeholder="Kosongkan jika auto-generate" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white" />
                    </div>

                    <div>
                        <label class="block font-semibold text-purple-600 dark:text-purple-400 mb-1">👑 Master Lead Naungan *</label>
                        <select v-model="agentForm.master_lead_id" class="w-full bg-slate-50 dark:bg-slate-950 border border-purple-300 dark:border-purple-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-bold cursor-pointer">
                            <option :value="null">-- Directly Under Developer (Tanpa Master Lead) --</option>
                            <option v-for="ml in props.masterLeadList" :key="ml.id" :value="ml.id">
                                👑 {{ ml.name }} ({{ ml.phone || 'No WA' }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Tipe Sales Agent *</label>
                        <select v-model="agentForm.agent_type" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-bold">
                            <option value="agency_agent">🏢 Agency Sub-Agent (Bernaung di Kantor Agency)</option>
                            <option value="independent">💼 Freelance Independen (Agen Bebas)</option>
                            <option value="inhouse_master_lead">🤝 In-House Master Lead (Tim Internal ML)</option>
                        </select>
                    </div>

                    <!-- Kantor Agency LOV Dropdown jika Berbendera -->
                    <div v-if="agentForm.agent_type === 'agency_agent'" class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl space-y-1.5">
                        <label class="block font-bold text-amber-700 dark:text-amber-400">Pilih Kantor Agency Naungan *</label>
                        <select v-model="agentForm.broker_company_id" required class="w-full bg-white dark:bg-slate-950 border border-amber-300 dark:border-amber-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-bold cursor-pointer">
                            <option :value="null" disabled>-- Pilih Kantor Agency --</option>
                            <option v-for="b in props.brokerList" :key="b.id" :value="b.id">
                                🏢 [{{ b.code || 'NO-CODE' }}] {{ b.name }} (Rate Fee: {{ b.commission_rate || 3.0 }}%)
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Fee Rate Sub-Agent (%)</label>
                            <input v-model="agentForm.commission_rate" type="number" step="0.1" placeholder="Ikuti rate kantor" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-amber-600 dark:text-amber-400 font-bold" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Promo Bonus Cash (Rp)</label>
                            <input v-model="agentForm.custom_bonus" type="number" step="100000" placeholder="0" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-emerald-600 dark:text-emerald-400 font-bold" />
                        </div>
                    </div>

                    <div class="border-t border-slate-200 dark:border-slate-800 pt-3 space-y-2">
                        <label class="block font-semibold text-slate-700 dark:text-slate-300">Rekening Bank Personal Sub-Agent</label>
                        <div class="grid grid-cols-3 gap-2">
                            <input v-model="agentForm.bank_name" type="text" placeholder="Bank" class="bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white text-xs" />
                            <input v-model="agentForm.bank_account_number" type="text" placeholder="No. Rek" class="bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white text-xs" />
                            <input v-model="agentForm.bank_account_name" type="text" placeholder="Atas Nama" class="bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white text-xs" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showSubAgentModal = false" class="px-4 py-2 bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl">Batal</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-600/20">
                            Simpan Sub-Agent
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL KANTOR AGENCY (BROKER COMPANY) -->
        <div v-if="showAgencyModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🏢</span>
                        <span>{{ editingAgency ? 'Edit Kantor Agency' : 'Daftarkan Kantor Agency Baru' }}</span>
                    </h3>
                    <button @click="showAgencyModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitAgency" class="space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Nama Kantor Agency *</label>
                            <input v-model="agencyForm.name" required type="text" placeholder="misal: Ray White Cilandak" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Kode Agency</label>
                            <input v-model="agencyForm.code" type="text" placeholder="misal: RW-CLD" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white uppercase font-mono" />
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-purple-600 dark:text-purple-400 mb-1">👑 Master Lead Penaungan (Opsional)</label>
                        <select v-model="agencyForm.master_lead_id" class="w-full bg-slate-50 dark:bg-slate-950 border border-purple-300 dark:border-purple-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-bold cursor-pointer">
                            <option :value="null">-- Langsung di Bawah Developer --</option>
                            <option v-for="ml in props.masterLeadList" :key="ml.id" :value="ml.id">
                                👑 {{ ml.name }} ({{ ml.phone || 'No WA' }})
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">PIC / Contact Person</label>
                            <input v-model="agencyForm.contact_person" type="text" placeholder="Nama Manager" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">No. HP / WA</label>
                            <input v-model="agencyForm.phone" type="text" placeholder="0812..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Rate Fee Kantor (%)</label>
                            <input v-model="agencyForm.commission_rate" type="number" step="0.1" placeholder="3.0" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-amber-600 dark:text-amber-400 font-bold" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Status</label>
                            <select v-model="agencyForm.status" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 dark:border-slate-800 pt-3 space-y-2">
                        <label class="block font-semibold text-amber-600 dark:text-amber-400">Rekening Pencairan Komisi Kantor Agency</label>
                        <div class="grid grid-cols-3 gap-2">
                            <input v-model="agencyForm.bank_name" type="text" placeholder="Bank" class="bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white text-xs" />
                            <input v-model="agencyForm.bank_account_number" type="text" placeholder="No. Rekening" class="bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white text-xs" />
                            <input v-model="agencyForm.bank_account_name" type="text" placeholder="Atas Nama" class="bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-900 dark:text-white text-xs" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showAgencyModal = false" class="px-4 py-2 bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl">Batal</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-600/20">
                            Simpan Kantor Agency
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 5: CATAT TRANSFER KE SUB-AGENT -->
        <div v-if="showPaySubAgentModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-4 shadow-2xl">
                <div class="flex justify-between items-center pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>💳</span>
                        <span>Catat Transfer ke Sub-Agent</span>
                    </h3>
                    <button @click="showPaySubAgentModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white font-bold">&times;</button>
                </div>

                <div v-if="selectedSubAgentComm" class="bg-slate-50 dark:bg-slate-950 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
                    <div>
                        <span class="text-slate-400">Penerima Komisi:</span>
                        <div class="font-bold text-slate-900 dark:text-white text-sm">{{ selectedSubAgentComm.user?.name }}</div>
                    </div>
                    <div class="flex justify-between border-t border-slate-200 dark:border-slate-900 pt-2">
                        <span class="text-slate-400">Jumlah Komisi Sub-Agent:</span>
                        <span class="font-black text-emerald-600 dark:text-emerald-400 text-sm">{{ formatCurrency(selectedSubAgentComm.amount) }}</span>
                    </div>
                    <div class="border-t border-slate-200 dark:border-slate-900 pt-2 text-[11px]">
                        <span class="text-slate-400 block mb-0.5">Rekening Tujuan Sub-Agent:</span>
                        <div class="font-semibold text-slate-700 dark:text-slate-300">
                            {{ selectedSubAgentComm.user?.effective_bank_account?.bank_name || 'Bank' }} - {{ selectedSubAgentComm.user?.effective_bank_account?.bank_account_number || '-' }}
                            (a.n {{ selectedSubAgentComm.user?.effective_bank_account?.bank_account_name || selectedSubAgentComm.user?.name }})
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submitPaySubAgent" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">No. Referensi Transfer Bank *</label>
                        <input v-model="paySubAgentForm.receipt_number" required type="text" placeholder="misal: TRF-BCA-98721" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white font-mono font-bold" />
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">Catatan Tambahan (Opsional)</label>
                        <textarea v-model="paySubAgentForm.notes" rows="2" placeholder="Catatan bukti transfer..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3.5 py-2 text-slate-900 dark:text-white"></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showPaySubAgentModal = false" class="px-4 py-2 bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl">Batal</button>
                        <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-lg shadow-emerald-600/20">
                            Konfirmasi Transfer Selesai
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </CrmLayout>
</template>
