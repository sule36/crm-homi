<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const props = defineProps({
    brokers: Object,
    agents: Object,
    brokerList: Array,
    defaultRates: Object,
    filters: Object,
});

const activeTab = ref('agencies'); // 'agencies' | 'agents'

// Modal Detail Kantor Agency & Agen
const showDetailModal = ref(false);
const selectedBroker = ref(null);

function openDetailModal(broker) {
    selectedBroker.value = broker;
    showDetailModal.value = true;
}

function formatCurrency(val) {
    if (!val) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
}

// Modal Agency
const showAgencyModal = ref(false);
const editingAgency = ref(null);

const agencyForm = useForm({
    name: '',
    code: '',
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

// Modal Agent Custom Fee & Bonus
const showAgentModal = ref(false);
const editingAgent = ref(null);

const agentForm = useForm({
    name: '',
    agent_type: 'inhouse',
    broker_company_id: null,
    commission_rate: '',
    custom_bonus: 0,
    bank_name: '',
    bank_account_number: '',
    bank_account_name: '',
});

function openAgentModal(agent) {
    editingAgent.value = agent;
    agentForm.name = agent.name;
    agentForm.agent_type = agent.agent_type || (agent.broker_company_id ? 'agency_agent' : 'inhouse');
    agentForm.broker_company_id = agent.broker_company_id || null;
    agentForm.commission_rate = agent.commission_rate || '';
    agentForm.custom_bonus = agent.custom_bonus || 0;
    agentForm.bank_name = agent.bank_name || '';
    agentForm.bank_account_number = agent.bank_account_number || '';
    agentForm.bank_account_name = agent.bank_account_name || '';
    showAgentModal.value = true;
}

function submitAgent() {
    agentForm.put(route('users.update', editingAgent.value.id), {
        onSuccess: () => { showAgentModal.value = false; }
    });
}
</script>

<template>
    <Head title="Manajemen Agen & Kantor Agency" />

    <CrmLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/60 backdrop-blur-xl border border-slate-800 p-6 rounded-2xl">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                        <span>🏢</span>
                        <span>Manajemen Agen & Kantor Agency</span>
                    </h1>
                    <p class="text-sm text-slate-400 mt-1">
                        Kelola Kantor Broker Agency, Agen Inhouse, Agency Agent, dan Freelance Independen beserta parameter fee & insentif promo.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <button 
                        @click="openAgencyModal()" 
                        class="px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-semibold rounded-xl text-sm transition-all shadow-lg shadow-amber-500/20 flex items-center gap-2"
                    >
                        <span>➕</span>
                        <span>Daftarkan Kantor Agency</span>
                    </button>
                </div>
            </div>

            <!-- Default Parameter Summary Bar -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-slate-900/40 border border-slate-800 p-4 rounded-xl flex items-center gap-4">
                    <div class="w-10 h-10 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center font-bold text-lg">🏠</div>
                    <div>
                        <div class="text-xs text-slate-400">Default Rate Inhouse</div>
                        <div class="text-lg font-bold text-white">{{ defaultRates.inhouse }}%</div>
                    </div>
                </div>
                <div class="bg-slate-900/40 border border-slate-800 p-4 rounded-xl flex items-center gap-4">
                    <div class="w-10 h-10 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center font-bold text-lg">🏢</div>
                    <div>
                        <div class="text-xs text-slate-400">Default Rate Agency</div>
                        <div class="text-lg font-bold text-white">{{ defaultRates.agency }}%</div>
                    </div>
                </div>
                <div class="bg-slate-900/40 border border-slate-800 p-4 rounded-xl flex items-center gap-4">
                    <div class="w-10 h-10 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold text-lg">💼</div>
                    <div>
                        <div class="text-xs text-slate-400">Default Rate Independen</div>
                        <div class="text-lg font-bold text-white">{{ defaultRates.independent }}%</div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="border-b border-slate-800 flex gap-4">
                <button 
                    @click="activeTab = 'agencies'"
                    :class="activeTab === 'agencies' ? 'border-amber-500 text-amber-400' : 'border-transparent text-slate-400 hover:text-slate-200'"
                    class="py-3 px-1 border-b-2 font-medium text-sm transition-all flex items-center gap-2"
                >
                    <span>🏢 Kantor Agency (Broker Companies)</span>
                    <span class="px-2 py-0.5 rounded-full text-xs bg-slate-800 text-slate-300">{{ brokers.total }}</span>
                </button>
                <button 
                    @click="activeTab = 'agents'"
                    :class="activeTab === 'agents' ? 'border-amber-500 text-amber-400' : 'border-transparent text-slate-400 hover:text-slate-200'"
                    class="py-3 px-1 border-b-2 font-medium text-sm transition-all flex items-center gap-2"
                >
                    <span>👥 Direktori Agen (Inhouse & Eksternal)</span>
                    <span class="px-2 py-0.5 rounded-full text-xs bg-slate-800 text-slate-300">{{ agents.total }}</span>
                </button>
            </div>

            <!-- TAB 1: KANTOR AGENCY (BROKER COMPANIES) -->
            <div v-if="activeTab === 'agencies'" class="space-y-4">
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="bg-slate-800/80 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-700">
                            <tr>
                                <th class="p-4">Kode & Nama Kantor Agency</th>
                                <th class="p-4">PIC / Kontak</th>
                                <th class="p-4">Tim Agen & Sales</th>
                                <th class="p-4">Akumulasi Komisi Kantor</th>
                                <th class="p-4">Rate Fee (%)</th>
                                <th class="p-4">Rekening Pencairan</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="b in brokers.data" :key="b.id" class="hover:bg-slate-800/40 transition-colors">
                                <td class="p-4">
                                    <div class="font-bold text-white">{{ b.name }}</div>
                                    <div class="text-xs text-amber-400 font-mono">{{ b.code || 'NO-CODE' }}</div>
                                    <div class="text-xs text-slate-500 truncate max-w-xs">{{ b.address || '-' }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="text-slate-200 font-medium">{{ b.contact_person || '-' }}</div>
                                    <div class="text-xs text-slate-400">{{ b.phone || '-' }}</div>
                                    <div class="text-xs text-slate-500">{{ b.email || '-' }}</div>
                                </td>
                                <td class="p-4">
                                    <button @click="openDetailModal(b)" class="px-2.5 py-1 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded-lg text-xs font-semibold hover:bg-blue-500/20 transition-all flex items-center gap-1.5">
                                        <span>👥 {{ b.agents_count }} Agen</span>
                                        <span class="text-[10px] text-slate-400">({{ b.commissions_count }} Closing)</span>
                                    </button>
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-emerald-400 text-sm">
                                        {{ formatCurrency(b.commissions_sum_amount) }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 flex gap-2 mt-0.5">
                                        <span class="text-emerald-400">Cair: {{ formatCurrency(b.paid_commissions_sum) }}</span>
                                        <span class="text-amber-400">Pending: {{ formatCurrency(b.pending_commissions_sum) }}</span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span class="text-amber-400 font-bold text-base">{{ b.commission_rate ? b.commission_rate + '%' : 'Default' }}</span>
                                </td>
                                <td class="p-4">
                                    <div v-if="b.bank_name" class="text-xs text-slate-300">
                                        <div class="font-semibold text-slate-200">{{ b.bank_name }} - {{ b.bank_account_number }}</div>
                                        <div class="text-slate-400">a.n {{ b.bank_account_name }}</div>
                                    </div>
                                    <div v-else class="text-xs text-slate-500 italic">Belum diatur</div>
                                </td>
                                <td class="p-4">
                                    <span :class="b.status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border-rose-500/20'" class="px-2.5 py-1 border rounded-full text-xs font-medium capitalize">
                                        {{ b.status }}
                                    </span>
                                </td>
                                <td class="p-4 text-right space-x-1.5">
                                    <button @click="openDetailModal(b)" class="px-2.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold" title="Lihat Tim Agen & Akumulasi Komisi">👁️ Detail</button>
                                    <button @click="openAgencyModal(b)" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-amber-400 rounded-lg text-xs font-semibold">Edit</button>
                                    <button @click="deleteAgency(b)" class="px-2.5 py-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-lg text-xs font-semibold">Hapus</button>
                                </td>
                            </tr>
                            <tr v-if="brokers.data.length === 0">
                                <td colspan="8" class="p-8 text-center text-slate-500">Belum ada kantor agency yang terdaftar. Klik "Daftarkan Kantor Agency" di atas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: DIREKTORI AGEN -->
            <div v-if="activeTab === 'agents'" class="space-y-4">
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="bg-slate-800/80 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-700">
                            <tr>
                                <th class="p-4">Nama Agen</th>
                                <th class="p-4">Tipe Agen</th>
                                <th class="p-4">Kantor Agency Naungan</th>
                                <th class="p-4">Rate Komisi Efektif</th>
                                <th class="p-4">Promo Bonus (Rp)</th>
                                <th class="p-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="a in agents.data" :key="a.id" class="hover:bg-slate-800/40 transition-colors">
                                <td class="p-4">
                                    <div class="font-bold text-white">{{ a.name }}</div>
                                    <div class="text-xs text-slate-400">{{ a.email }} • {{ a.phone || '-' }}</div>
                                </td>
                                <td class="p-4">
                                    <span v-if="a.agent_type === 'inhouse'" class="px-2.5 py-1 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded-full text-xs font-semibold">🏠 In-House Agent</span>
                                    <span v-else-if="a.agent_type === 'agency_agent'" class="px-2.5 py-1 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-full text-xs font-semibold">🏢 Agency Agent</span>
                                    <span v-else class="px-2.5 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-full text-xs font-semibold">💼 Freelance Independen</span>
                                </td>
                                <td class="p-4">
                                    <div v-if="a.broker_company" class="font-medium text-slate-200">{{ a.broker_company.name }}</div>
                                    <div v-else class="text-xs text-slate-500 italic">Tanpa Kantor (Direct / Inhouse)</div>
                                </td>
                                <td class="p-4">
                                    <span class="text-amber-400 font-bold">
                                        {{ a.commission_rate ? a.commission_rate + '%' : (a.broker_company ? a.broker_company.commission_rate + '% (Office)' : 'System Default') }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span v-if="a.custom_bonus > 0" class="text-emerald-400 font-semibold">+ Rp {{ Number(a.custom_bonus).toLocaleString('id-ID') }}</span>
                                    <span v-else class="text-xs text-slate-500">-</span>
                                </td>
                                <td class="p-4 text-right">
                                    <button @click="openAgentModal(a)" class="px-3 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/20 rounded-lg text-xs font-semibold">Set Fee & Promo</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL AGENCY -->
        <div v-if="showAgencyModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 space-y-4">
                <h3 class="text-lg font-bold text-white">{{ editingAgency ? 'Edit Kantor Agency' : 'Daftarkan Kantor Agency Baru' }}</h3>
                <form @submit.prevent="submitAgency" class="space-y-4 text-sm">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Kantor Agency *</label>
                            <input v-model="agencyForm.name" required type="text" placeholder="misal: Ray White Cilandak" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Kode Agency</label>
                            <input v-model="agencyForm.code" type="text" placeholder="misal: RW-CLD" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white uppercase font-mono" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">PIC / Contact Person</label>
                            <input v-model="agencyForm.contact_person" type="text" placeholder="Nama Manager/PIC" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">No. HP / WhatsApp</label>
                            <input v-model="agencyForm.phone" type="text" placeholder="0812..." class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Rate Fee Kantor (%)</label>
                            <input v-model="agencyForm.commission_rate" type="number" step="0.1" placeholder="3.0" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-bold text-amber-400" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Status</label>
                            <select v-model="agencyForm.status" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="border-t border-slate-800 pt-3">
                        <label class="block text-xs font-semibold text-amber-400 mb-2">Rekening Pencairan Komisi Kantor</label>
                        <div class="grid grid-cols-3 gap-2">
                            <input v-model="agencyForm.bank_name" type="text" placeholder="Bank (BCA/BSI)" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white text-xs" />
                            <input v-model="agencyForm.bank_account_number" type="text" placeholder="No. Rekening" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white text-xs" />
                            <input v-model="agencyForm.bank_account_name" type="text" placeholder="Atas Nama" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white text-xs" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-3">
                        <button type="button" @click="showAgencyModal = false" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-amber-500 text-slate-950 font-bold rounded-xl">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL AGENT SET FEE & PROMO -->
        <div v-if="showAgentModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-4">
                <h3 class="text-lg font-bold text-white">Atur Parameter Fee & Promo: {{ agentForm.name }}</h3>
                <form @submit.prevent="submitAgent" class="space-y-4 text-sm">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Tipe Agen</label>
                        <select v-model="agentForm.agent_type" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                            <option value="inhouse">🏠 In-House Agent (Internal)</option>
                            <option value="agency_agent">🏢 Agency Agent (Bernaung di Kantor Agency)</option>
                            <option value="independent">💼 Freelance / Agen Independen</option>
                        </select>
                    </div>
                    <div v-if="agentForm.agent_type === 'agency_agent'">
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Pilih Kantor Agency</label>
                        <select v-model="agentForm.broker_company_id" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                            <option :value="null">-- Pilih Kantor Agency --</option>
                            <option v-for="b in brokerList" :key="b.id" :value="b.id">{{ b.name }} ({{ b.commission_rate }}%)</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Custom Fee Rate (%)</label>
                            <input v-model="agentForm.commission_rate" type="number" step="0.1" placeholder="Kosongkan jika ikuti rate kantor/default" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-bold text-amber-400" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Promo Cash Bonus (Rp)</label>
                            <input v-model="agentForm.custom_bonus" type="number" step="100000" placeholder="0" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-bold text-emerald-400" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-3">
                        <button type="button" @click="showAgentModal = false" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-amber-500 text-slate-950 font-bold rounded-xl">Simpan Parameter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL DETAIL KANTOR AGENCY & TIM AGEN -->
        <div v-if="showDetailModal && selectedBroker" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-3xl w-full max-h-[90vh] overflow-y-auto p-6 md:p-8 space-y-6">
                <div class="flex justify-between items-center pb-4 border-b border-slate-800">
                    <div>
                        <h3 class="text-xl font-bold text-white flex items-center gap-2">
                            <span>🏢</span> <span>{{ selectedBroker.name }}</span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-1">Kode: <span class="font-mono text-amber-400">{{ selectedBroker.code || 'NO-CODE' }}</span> • Rate Komisi Kantor: <span class="text-amber-400 font-bold">{{ selectedBroker.commission_rate || 3.0 }}%</span></p>
                    </div>
                    <button @click="showDetailModal = false" class="text-slate-400 hover:text-white font-bold text-lg">&times;</button>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800">
                        <div class="text-xs text-slate-400">Total Akumulasi Komisi</div>
                        <div class="text-lg font-black text-emerald-400 mt-1">{{ formatCurrency(selectedBroker.commissions_sum_amount) }}</div>
                    </div>
                    <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800">
                        <div class="text-xs text-slate-400">Komisi Sudah Dicairkan</div>
                        <div class="text-lg font-black text-blue-400 mt-1">{{ formatCurrency(selectedBroker.paid_commissions_sum) }}</div>
                    </div>
                    <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800">
                        <div class="text-xs text-slate-400">Komisi Belum Dicairkan</div>
                        <div class="text-lg font-black text-amber-400 mt-1">{{ formatCurrency(selectedBroker.pending_commissions_sum) }}</div>
                    </div>
                </div>

                <!-- Rekening Pencairan -->
                <div class="p-4 bg-slate-950/60 rounded-2xl border border-slate-800 flex justify-between items-center text-xs">
                    <div>
                        <span class="text-slate-400 font-medium">Rekening Pencairan Kantor:</span>
                        <span v-if="selectedBroker.bank_name" class="ml-2 text-white font-bold">{{ selectedBroker.bank_name }} - {{ selectedBroker.bank_account_number }} (a.n {{ selectedBroker.bank_account_name }})</span>
                        <span v-else class="ml-2 text-slate-500 italic">Belum diatur</span>
                    </div>
                    <button @click="openAgencyModal(selectedBroker); showDetailModal = false" class="text-amber-400 hover:underline font-bold">Edit Rekening</button>
                </div>

                <!-- Daftar Agen Terdaftar Under This Agency -->
                <div>
                    <h4 class="text-sm font-bold text-white mb-3 flex items-center justify-between">
                        <span>👥 Daftar Agen Terdaftar di Kantor Ini ({{ selectedBroker.agents?.length || 0 }} Agen)</span>
                    </h4>
                    <div class="bg-slate-950 border border-slate-800 rounded-2xl overflow-hidden">
                        <table class="w-full text-left text-xs text-slate-300">
                            <thead class="bg-slate-800/80 uppercase tracking-wider text-slate-400 border-b border-slate-800">
                                <tr>
                                    <th class="p-3">Nama Agen</th>
                                    <th class="p-3">Kontak & Email</th>
                                    <th class="p-3">Rate Agen</th>
                                    <th class="p-3 text-right">Akumulasi Komisi Agen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60">
                                <tr v-for="agent in selectedBroker.agents" :key="agent.id" class="hover:bg-slate-900 transition-colors">
                                    <td class="p-3 font-bold text-white">{{ agent.name }}</td>
                                    <td class="p-3 text-slate-400">{{ agent.email }} • {{ agent.phone || '-' }}</td>
                                    <td class="p-3 text-amber-400 font-bold">{{ agent.commission_rate ? agent.commission_rate + '%' : 'Ikuti Kantor (' + (selectedBroker.commission_rate || 3) + '%)' }}</td>
                                    <td class="p-3 text-right font-bold text-emerald-400">{{ formatCurrency(agent.commissions_sum_amount) }}</td>
                                </tr>
                                <tr v-if="!selectedBroker.agents || selectedBroker.agents.length === 0">
                                    <td colspan="4" class="p-6 text-center text-slate-500">Belum ada agen yang terdaftar di kantor agency ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button @click="showDetailModal = false" class="px-6 py-2.5 bg-slate-800 text-slate-200 font-bold rounded-xl text-xs hover:bg-slate-700">Tutup</button>
                </div>
            </div>
        </div>
    </CrmLayout>
</template>
