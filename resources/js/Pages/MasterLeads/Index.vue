<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const props = defineProps({
    masterLeads: Object,
    stats: Object,
    filters: Object,
});

const page = usePage();
const flashCredentials = computed(() => page.props.flash?.new_credentials || null);

const searchQuery = ref(props.filters?.search || '');

function search() {
    router.get(route('master-leads.index'), { search: searchQuery.value }, { preserveState: true, replace: true });
}

function formatCurrency(val) {
    if (!val) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
}

// Modal Create & Edit Master Lead
const showModal = ref(false);
const editingUser = ref(null);

const form = useForm({
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
    form.password = pass;
}

function openModal(user = null) {
    editingUser.value = user;
    if (user) {
        form.name = user.name;
        form.email = user.email;
        form.phone = user.phone || '';
        form.password = '';
        form.commission_rate = user.commission_rate || 4.5;
        form.bank_name = user.bank_name || '';
        form.bank_account_number = user.bank_account_number || '';
        form.bank_account_name = user.bank_account_name || '';
        form.status = user.status || 'active';
    } else {
        form.reset();
        form.commission_rate = props.stats.default_overriding_rate || 4.5;
        form.status = 'active';
        generateRandomPassword();
    }
    showModal.value = true;
}

// Credential Sharing Modal
const showCredentialModal = ref(false);
const selectedCredential = ref(null);
const copiedSuccess = ref(false);

function openCredentialModal(ml) {
    selectedCredential.value = {
        name: ml.name,
        email: ml.email,
        phone: ml.phone,
        password: '(Sesuai password saat dibuat / di-reset)',
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
    const text = `Halo ${selectedCredential.value.name},\nBerikut adalah akses login Portal CRM Master Lead Developer Anda:\n\n🌐 URL Login: ${selectedCredential.value.login_url}\n📧 Email: ${selectedCredential.value.email}\n🔑 Password: ${selectedCredential.value.password}\n\nSilakan login dan daftarkan tim sub-agent Anda.`;
    navigator.clipboard.writeText(text);
    copiedSuccess.value = true;
    setTimeout(() => { copiedSuccess.value = false; }, 3000);
}

function shareViaWhatsApp() {
    if (!selectedCredential.value) return;
    const text = `Halo ${selectedCredential.value.name},\nBerikut adalah akses login Portal CRM Master Lead Developer Anda:\n\n🌐 URL Login: ${selectedCredential.value.login_url}\n📧 Email: ${selectedCredential.value.email}\n🔑 Password: ${selectedCredential.value.password}\n\nSilakan login dan daftarkan tim sub-agent Anda.`;
    const phone = (selectedCredential.value.phone || '').replace(/[^0-9]/g, '');
    const waPhone = phone.startsWith('0') ? '62' + phone.slice(1) : phone;
    const url = waPhone ? `https://wa.me/${waPhone}?text=${encodeURIComponent(text)}` : `https://wa.me/?text=${encodeURIComponent(text)}`;
    window.open(url, '_blank');
}

function submitForm() {
    if (editingUser.value) {
        form.put(route('master-leads.update', editingUser.value.id), {
            onSuccess: () => { showModal.value = false; }
        });
    } else {
        const createdPass = form.password;
        form.post(route('master-leads.store'), {
            onSuccess: () => {
                showModal.value = false;
                if (flashCredentials.value) {
                    selectedCredential.value = flashCredentials.value;
                } else {
                    selectedCredential.value = {
                        name: form.name,
                        email: form.email,
                        phone: form.phone,
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
    if (confirm(`Apakah Anda yakin ingin menghapus Master Lead ${ml.name}? Seluruh agen di bawahnya akan menjadi independen.`)) {
        router.delete(route('master-leads.destroy', ml.id));
    }
}

// Modal View Sub-Agents
const showSubAgentsModal = ref(false);
const selectedMasterLeadForSubAgents = ref(null);

function openSubAgentsModal(ml) {
    selectedMasterLeadForSubAgents.value = ml;
    showSubAgentsModal.value = true;
}
</script>

<template>
    <CrmLayout>
        <Head title="Manajemen Master Lead" />

        <div class="p-4 sm:p-6 space-y-6 max-w-7xl mx-auto">
            <!-- Header Banner -->
            <div class="bg-gradient-to-r from-purple-900/40 via-slate-900 to-amber-900/30 border border-purple-500/30 rounded-3xl p-6 sm:p-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 relative overflow-hidden shadow-2xl">
                <div class="space-y-2 relative z-10">
                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-purple-500/20 border border-purple-500/40 rounded-full text-purple-300 text-xs font-semibold">
                        <span>👑 Konsep Master Lead Perusahaan</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-white">Kelola Partner Master Lead</h1>
                    <p class="text-xs sm:text-sm text-slate-300 max-w-2xl leading-relaxed">
                        Developer cukup mendaftarkan akun **Master Lead**. Sistem akan otomatis men-generate **URL Login, Email & Password**. Master Lead tersebut yang akan mendaftarkan dan mengelola tim sub-agent-nya sendiri.
                    </p>
                </div>
                <button 
                    @click="openModal()" 
                    class="px-5 py-3 bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700 text-white font-bold rounded-2xl shadow-lg shadow-purple-500/25 flex items-center gap-2 text-sm shrink-0 transition-all transform hover:scale-[1.02]"
                >
                    <span>➕</span>
                    <span>Buat Akun Master Lead Baru</span>
                </button>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-2xl flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-purple-500/20 text-purple-400 flex items-center justify-center text-xl font-bold">👑</div>
                    <div>
                        <div class="text-xs text-slate-400">Total Master Lead</div>
                        <div class="text-xl font-extrabold text-white">{{ stats.total_master_leads }} Partner</div>
                    </div>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-2xl flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-blue-500/20 text-blue-400 flex items-center justify-center text-xl font-bold">👥</div>
                    <div>
                        <div class="text-xs text-slate-400">Total Sub-Agent Naungan</div>
                        <div class="text-xl font-extrabold text-white">{{ stats.total_sub_agents }} Sales Agen</div>
                    </div>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-2xl flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl font-bold">📈</div>
                    <div>
                        <div class="text-xs text-slate-400">Total Omset Tim</div>
                        <div class="text-lg font-extrabold text-emerald-400">{{ formatCurrency(stats.total_revenue) }}</div>
                    </div>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-2xl flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-xl font-bold">⚙️</div>
                    <div>
                        <div class="text-xs text-slate-400">Default Overriding Rate</div>
                        <div class="text-xl font-extrabold text-amber-400">{{ stats.default_overriding_rate }}%</div>
                    </div>
                </div>
            </div>

            <!-- Table Container -->
            <div class="bg-slate-900/60 border border-slate-800 rounded-3xl p-6 space-y-4 shadow-xl">
                <!-- Search & Filters -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="relative w-full sm:w-80">
                        <input 
                            v-model="searchQuery" 
                            @keyup.enter="search"
                            type="text" 
                            placeholder="🔍 Cari Master Lead nama/email/wa..."
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-purple-500"
                        />
                    </div>
                    <div class="text-xs text-slate-400">
                        Menampilkan <span class="font-bold text-white">{{ masterLeads.data.length }}</span> Master Lead
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950 text-slate-400 uppercase font-semibold border-b border-slate-800">
                            <tr>
                                <th class="p-4">Master Lead Partner</th>
                                <th class="p-4">Kontak & Email</th>
                                <th class="p-4">Overriding Rate</th>
                                <th class="p-4">Rekening Bank</th>
                                <th class="p-4">Sub-Agent Tim</th>
                                <th class="p-4">Omset Penjualan</th>
                                <th class="p-4 text-right">Aksi & Akses</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="ml in masterLeads.data" :key="ml.id" class="hover:bg-slate-800/40 transition-all">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-purple-500/20 text-purple-400 flex items-center justify-center font-bold text-sm border border-purple-500/30">
                                            👑
                                        </div>
                                        <div>
                                            <div class="font-bold text-white text-sm">{{ ml.name }}</div>
                                            <div class="text-[10px] text-slate-500">Terdaftar: {{ ml.created_at }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="font-medium text-white">{{ ml.email }}</div>
                                    <div class="text-slate-400 font-mono text-[11px]">{{ ml.phone || 'No WA' }}</div>
                                </td>
                                <td class="p-4">
                                    <span class="px-2.5 py-1 bg-purple-500/10 text-purple-400 border border-purple-500/30 rounded-lg font-bold">
                                        {{ ml.commission_rate }}%
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div v-if="ml.bank_name" class="font-semibold text-amber-400">
                                        {{ ml.bank_name }} - {{ ml.bank_account_number }}
                                        <div class="text-[10px] text-slate-400">a.n. {{ ml.bank_account_name }}</div>
                                    </div>
                                    <div v-else class="text-slate-500 italic text-[11px]">Belum diatur</div>
                                </td>
                                <td class="p-4">
                                    <button @click="openSubAgentsModal(ml)" class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/30 rounded-xl text-blue-400 font-bold transition-all">
                                        <span>👥 {{ ml.sub_agents_count }} Sub-Agent</span>
                                        <span>➔</span>
                                    </button>
                                </td>
                                <td class="p-4">
                                    <div class="font-extrabold text-emerald-400">{{ formatCurrency(ml.total_revenue) }}</div>
                                    <div class="text-[10px] text-slate-400">{{ ml.total_bookings }} Unit Terjual</div>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button 
                                            @click="openCredentialModal(ml)" 
                                            title="Salin & Kirim Akses Login"
                                            class="px-3 py-1.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-xl font-bold flex items-center gap-1 text-xs"
                                        >
                                            <span>🔑</span>
                                            <span>Akses Login</span>
                                        </button>
                                        <button 
                                            @click="openModal(ml)" 
                                            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl font-semibold text-xs"
                                        >
                                            Edit
                                        </button>
                                        <button 
                                            @click="deleteMasterLead(ml)" 
                                            class="px-3 py-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-xl font-semibold text-xs"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="masterLeads.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-slate-500">
                                    Belum ada akun Master Lead. Klik "Buat Akun Master Lead Baru" di atas.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL 1: CREATE / EDIT MASTER LEAD -->
        <div v-if="showModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-lg w-full p-6 space-y-5">
                <div class="flex justify-between items-center pb-3 border-b border-slate-800">
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <span>👑</span>
                        <span>{{ editingUser ? 'Edit Data Master Lead' : 'Buat Akun Master Lead Baru' }}</span>
                    </h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-white font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4 text-sm">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Partner / Perusahaan Master Lead *</label>
                        <input v-model="form.name" required type="text" placeholder="misal: Ray White Central / PT Master Properti" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-white" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Email Login *</label>
                            <input v-model="form.email" required type="email" placeholder="master@agency.com" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">No. WhatsApp / HP</label>
                            <input v-model="form.phone" type="text" placeholder="0812..." class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white" />
                        </div>
                    </div>

                    <div v-if="!editingUser">
                        <label class="block text-xs font-semibold text-amber-400 mb-1">Password Akses CRM Master Lead *</label>
                        <div class="flex gap-2">
                            <input v-model="form.password" required type="text" placeholder="Password login..." class="w-full bg-slate-950 border border-amber-500/40 rounded-xl px-3 py-2 text-amber-300 font-mono font-bold" />
                            <button type="button" @click="generateRandomPassword()" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs rounded-xl font-bold shrink-0">
                                🎲 Generate
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Overriding Fee Rate (%)</label>
                            <input v-model="form.commission_rate" type="number" step="0.1" placeholder="4.5" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-purple-400 font-bold text-base" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Status Partner</label>
                            <select v-model="form.status" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-bold">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="border-t border-slate-800 pt-3 space-y-2">
                        <label class="block text-xs font-semibold text-amber-400">Rekening Pencairan Overriding Fee Master Lead</label>
                        <div class="grid grid-cols-3 gap-2">
                            <input v-model="form.bank_name" type="text" placeholder="Bank (BCA/BSI)" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white text-xs" />
                            <input v-model="form.bank_account_number" type="text" placeholder="No. Rekening" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white text-xs" />
                            <input v-model="form.bank_account_name" type="text" placeholder="Atas Nama" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white text-xs" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-3">
                        <button type="button" @click="showModal = false" class="px-4 py-2.5 bg-slate-800 text-slate-300 rounded-xl">Batal</button>
                        <button type="submit" class="px-5 py-2.5 bg-gradient-to-r from-purple-500 to-indigo-600 text-white font-bold rounded-xl shadow-lg shadow-purple-500/20">
                            {{ editingUser ? 'Simpan Perubahan' : '🚀 Buat Akun & Generate Akses' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL 2: SHARE CREDENTIALS -->
        <div v-if="showCredentialModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-purple-500/40 rounded-3xl max-w-md w-full p-6 space-y-5 shadow-2xl">
                <div class="text-center space-y-2">
                    <div class="w-14 h-14 rounded-2xl bg-purple-500/20 text-purple-400 border border-purple-500/40 flex items-center justify-center text-2xl mx-auto">
                        🔑
                    </div>
                    <h3 class="text-lg font-bold text-white">Akses Login Master Lead</h3>
                    <p class="text-xs text-slate-400">Salin dan bagikan kredensial di bawah ini kepada partner Master Lead Anda.</p>
                </div>

                <div v-if="selectedCredential" class="bg-slate-950 p-4 rounded-2xl border border-slate-800 space-y-3 text-xs font-mono">
                    <div>
                        <div class="text-[10px] text-slate-500 uppercase font-sans">Nama Partner:</div>
                        <div class="text-white font-bold font-sans text-sm">{{ selectedCredential.name }}</div>
                    </div>
                    <div class="border-t border-slate-900 pt-2">
                        <div class="text-[10px] text-slate-500 uppercase font-sans">URL Login CRM:</div>
                        <div class="text-purple-400 font-bold select-all">{{ selectedCredential.login_url }}</div>
                    </div>
                    <div class="border-t border-slate-900 pt-2">
                        <div class="text-[10px] text-slate-500 uppercase font-sans">Email Login:</div>
                        <div class="text-emerald-400 font-bold select-all">{{ selectedCredential.email }}</div>
                    </div>
                    <div class="border-t border-slate-900 pt-2">
                        <div class="text-[10px] text-slate-500 uppercase font-sans">Password:</div>
                        <div class="text-amber-400 font-bold select-all">{{ selectedCredential.password }}</div>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <button 
                        @click="shareViaWhatsApp()" 
                        class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-2xl flex items-center justify-center gap-2 text-sm shadow-lg shadow-emerald-600/20"
                    >
                        <span>💬</span>
                        <span>Kirim Akses via WhatsApp</span>
                    </button>

                    <button 
                        @click="copyCredentialsText()" 
                        class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold rounded-2xl flex items-center justify-center gap-2 text-xs"
                    >
                        <span>📋</span>
                        <span>{{ copiedSuccess ? '✓ Berhasil Disalin!' : 'Salin Text Kredensial' }}</span>
                    </button>

                    <button 
                        @click="showCredentialModal = false" 
                        class="w-full py-2 text-slate-400 hover:text-white text-xs font-semibold"
                    >
                        Tutup Window
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL 3: VIEW SUB-AGENTS UNDER MASTER LEAD -->
        <div v-if="showSubAgentsModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-2xl w-full p-6 space-y-4">
                <div class="flex justify-between items-center pb-3 border-b border-slate-800">
                    <div>
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            <span>👥 Sub-Agent Naungan:</span>
                            <span class="text-purple-400">{{ selectedMasterLeadForSubAgents?.name }}</span>
                        </h3>
                        <p class="text-xs text-slate-400">Daftar agen sales yang berada di bawah naungan Master Lead ini.</p>
                    </div>
                    <button @click="showSubAgentsModal = false" class="text-slate-400 hover:text-white font-bold">&times;</button>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950 text-slate-400 uppercase font-semibold">
                            <tr>
                                <th class="p-3">Nama Agen</th>
                                <th class="p-3">Kontak & Email</th>
                                <th class="p-3">Tipe Agen</th>
                                <th class="p-3">Kantor Agency</th>
                                <th class="p-3">Fee Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <tr v-for="sa in selectedMasterLeadForSubAgents?.sub_agents" :key="sa.id" class="hover:bg-slate-800/40">
                                <td class="p-3 font-bold text-white">{{ sa.name }}</td>
                                <td class="p-3">
                                    <div class="text-slate-200">{{ sa.email }}</div>
                                    <div class="text-slate-400 text-[10px]">{{ sa.phone || '-' }}</div>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                        :class="{
                                            'bg-amber-500/20 text-amber-400': sa.agent_type === 'agency_agent',
                                            'bg-emerald-500/20 text-emerald-400': sa.agent_type === 'independent',
                                            'bg-blue-500/20 text-blue-400': sa.agent_type === 'inhouse_master_lead',
                                        }"
                                    >
                                        {{ sa.agent_type === 'agency_agent' ? 'Berbendera' : (sa.agent_type === 'independent' ? 'Independen' : 'Inhouse ML') }}
                                    </span>
                                </td>
                                <td class="p-3 text-slate-400">{{ sa.broker_company_name || '-' }}</td>
                                <td class="p-3 font-bold text-amber-400">{{ sa.commission_rate ? sa.commission_rate + '%' : 'Default' }}</td>
                            </tr>
                            <tr v-if="!selectedMasterLeadForSubAgents?.sub_agents?.length">
                                <td colspan="5" class="p-6 text-center text-slate-500">Belum ada sub-agent yang terdaftar di bawah Master Lead ini.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-between items-center pt-3 border-t border-slate-800">
                    <span class="text-xs text-slate-400">Master Lead dapat mendaftarkan sub-agent langsung melalui akun login miliknya.</span>
                    <button @click="showSubAgentsModal = false" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-semibold">Tutup</button>
                </div>
            </div>
        </div>
    </CrmLayout>
</template>
