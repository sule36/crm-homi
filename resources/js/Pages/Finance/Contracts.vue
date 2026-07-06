<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    contracts: Array,
    projects: Array,
    rab_items: Array,
    bank_accounts: Array,
    filters: Object,
});

const formatCurrency = (v) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v || 0);

const selectedProject = ref(props.filters?.project_id || '');

function changeProject() {
    if (selectedProject.value) {
        router.get('/finance/contracts', { project_id: selectedProject.value }, { preserveState: true, preserveScroll: true });
    }
}

// Contract Form Modal
const showModal = ref(false);
const editMode = ref(false);
const selectedContract = ref(null);

const form = useForm({
    project_id: props.filters?.project_id || '',
    contractor_name: '',
    contract_number: '',
    description: '',
    total_amount: 0,
    termins: [
        { label: 'Termin 1 / DP', percentage: 20, amount: 0, due_date: '', rab_item_id: '' }
    ]
});

// Auto calculate termin amounts based on total contract amount & percentage
function syncTerminAmount() {
    form.termins.forEach(t => {
        t.amount = Math.round((Number(t.percentage) / 100) * Number(form.total_amount));
    });
}

function addTerminRow() {
    const totalPercentage = form.termins.reduce((sum, t) => sum + Number(t.percentage), 0);
    const leftPercentage = Math.max(0, 100 - totalPercentage);
    
    form.termins.push({
        label: `Termin ${form.termins.length + 1}`,
        percentage: leftPercentage,
        amount: Math.round((leftPercentage / 100) * Number(form.total_amount)),
        due_date: '',
        rab_item_id: ''
    });
}

function removeTerminRow(index) {
    if (form.termins.length > 1) {
        form.termins.splice(index, 1);
    }
}

function openCreate() {
    editMode.value = false;
    selectedContract.value = null;
    form.reset();
    form.project_id = selectedProject.value;
    form.termins = [{ label: 'Termin 1 / DP', percentage: 20, amount: 0, due_date: '', rab_item_id: '' }];
    showModal.value = true;
}

// Edit Mode only updates Contractor Contract details (status & desc)
const editForm = useForm({
    contractor_name: '',
    description: '',
    status: 'active'
});

function openEdit(contract) {
    editMode.value = true;
    selectedContract.value = contract;
    editForm.contractor_name = contract.contractor_name;
    editForm.description = contract.description;
    editForm.status = contract.status;
    showModal.value = true;
}

function submitForm() {
    if (editMode.value && selectedContract.value) {
        editForm.put(`/finance/contracts/${selectedContract.value.id}`, {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; }
        });
    } else {
        form.post('/finance/contracts', {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; }
        });
    }
}

function deleteContract(contract) {
    if (confirm(`Hapus kontrak kerja "${contract.contract_number}"? Ini juga akan menghapus termin yang belum dibayarkan.`)) {
        router.delete(`/finance/contracts/${contract.id}`, { preserveScroll: true });
    }
}

// Pay Termin Modal
const showPayModal = ref(false);
const payTarget = ref(null);
const payForm = useForm({
    bank_account_id: '',
    paid_date: new Date().toISOString().split('T')[0],
    notes: '',
});

function openPayTermin(termin) {
    payTarget.value = termin;
    payForm.reset();
    payForm.paid_date = new Date().toISOString().split('T')[0];
    if (props.bank_accounts?.length > 0) {
        payForm.bank_account_id = props.bank_accounts[0].id;
    }
    showPayModal.value = true;
}

function submitPay() {
    payForm.post(`/finance/contracts/termins/${payTarget.value.id}/pay`, {
        preserveScroll: true,
        onSuccess: () => { showPayModal.value = false; }
    });
}
</script>

<template>
    <Head title="Kontrak Subkontraktor" />
    <CrmLayout>
        <template #breadcrumb>Keuangan / Kontrak Subkon</template>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Kontrak & Termin Subkontraktor</h1>
                <p class="text-sm text-slate-500 mt-1">Kelola kontrak kerja mitra kontraktor luar dan pembayaran termin konstruksi.</p>
            </div>
            <Link href="/finance" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">← Kas Utama</Link>
        </div>

        <!-- Project Selector -->
        <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm mb-8">
            <div class="flex flex-col sm:flex-row items-end gap-4">
                <div class="flex-1">
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Pilih Proyek</label>
                    <select v-model="selectedProject" @change="changeProject" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 focus:ring-1 focus:ring-blue-500">
                        <option value="">-- Pilih Proyek --</option>
                        <option v-for="proj in projects" :key="proj.id" :value="proj.id">🏗️ {{ proj.name }}</option>
                    </select>
                </div>
                <button v-if="selectedProject" @click="openCreate" class="px-5 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-500/25 hover:-translate-y-0.5 transition-all whitespace-nowrap">
                    + Buat Kontrak Baru
                </button>
            </div>
        </div>

        <!-- Contracts List -->
        <div v-if="selectedProject && contracts.length" class="space-y-6">
            <div v-for="contract in contracts" :key="contract.id" class="bg-white rounded-3xl border border-slate-100 overflow-hidden shadow-sm">
                <!-- Contract Header -->
                <div class="px-8 py-5 bg-slate-50 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-black text-slate-900">{{ contract.contractor_name }}</h3>
                            <span :class="contract.status === 'completed' ? 'bg-emerald-50 text-emerald-600' : 'bg-blue-50 text-blue-600'"
                                class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-wider">
                                {{ contract.status === 'completed' ? 'Selesai' : 'Aktif' }}
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-400 font-bold mt-1">No: {{ contract.contract_number }} • {{ contract.description }}</p>
                    </div>
                    <div class="flex items-center gap-6">
                        <div class="text-right">
                            <p class="text-[9px] font-black text-slate-400 uppercase">Nilai Kontrak</p>
                            <p class="text-sm font-black text-slate-900">{{ formatCurrency(contract.total_amount) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[9px] font-black text-slate-400 uppercase text-emerald-500">Terbayar</p>
                            <p class="text-sm font-black text-emerald-600">{{ formatCurrency(contract.paid_amount) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[9px] font-black text-slate-400 uppercase text-amber-500">Sisa</p>
                            <p class="text-sm font-black text-amber-600">{{ formatCurrency(contract.remaining_amount) }}</p>
                        </div>
                        <div class="flex items-center gap-1.5 ml-2">
                            <button @click="openEdit(contract)" class="p-1.5 text-slate-400 hover:text-blue-600 transition-colors" title="Edit">✏️</button>
                            <button @click="deleteContract(contract)" class="p-1.5 text-slate-400 hover:text-rose-600 transition-colors" title="Hapus">🗑️</button>
                        </div>
                    </div>
                </div>

                <!-- Termins Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-[9px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                <th class="px-8 py-3">Termin Pembayaran</th>
                                <th class="px-4 py-3 text-center">Persentase</th>
                                <th class="px-4 py-3 text-right">Nominal</th>
                                <th class="px-4 py-3">Pos Anggaran RAB</th>
                                <th class="px-4 py-3">Jatuh Tempo</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-8 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="termin in contract.termins" :key="termin.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-8 py-3">
                                    <p class="font-bold text-slate-900">{{ termin.label }}</p>
                                </td>
                                <td class="px-4 py-3 text-center font-bold text-slate-600">{{ termin.percentage }}%</td>
                                <td class="px-4 py-3 text-right font-black text-slate-800">{{ formatCurrency(termin.amount) }}</td>
                                <td class="px-4 py-3 text-slate-500 font-bold">
                                    <span v-if="termin.rab_item" class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-[9px] font-black">
                                        {{ termin.rab_item?.description }}
                                    </span>
                                    <span v-else class="text-slate-400 italic text-[10px]">Belum dihubungkan</span>
                                </td>
                                <td class="px-4 py-3 text-slate-600 font-medium">
                                    {{ termin.due_date ? new Date(termin.due_date).toLocaleDateString('id-ID') : '-' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="termin.status === 'paid' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'"
                                        class="px-2 py-0.5 rounded text-[9px] font-black uppercase">
                                        {{ termin.status === 'paid' ? '✅ Lunas' : '⏳ Pending' }}
                                    </span>
                                </td>
                                <td class="px-8 py-3 text-right">
                                    <button v-if="termin.status === 'pending'" @click="openPayTermin(termin)"
                                        class="px-3 py-1.5 bg-emerald-600 text-white text-[10px] font-black rounded-lg hover:bg-emerald-700 transition-all">
                                        Bayar Termin
                                    </button>
                                    <span v-else class="text-[10px] text-slate-400 font-bold italic">
                                        Lunas ({{ new Date(termin.paid_date).toLocaleDateString('id-ID') }})
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="selectedProject && !contracts.length" class="bg-white rounded-3xl border border-slate-100 p-16 text-center shadow-sm">
            <p class="text-4xl mb-4">🤝</p>
            <p class="text-sm font-black text-slate-400 uppercase tracking-widest">Belum Ada Kontrak Kerja</p>
            <p class="text-xs text-slate-500 mt-2">Mulai buat kontrak kerja subkontraktor untuk proyek ini.</p>
        </div>

        <div v-else class="bg-white rounded-3xl border border-slate-100 p-16 text-center shadow-sm">
            <p class="text-4xl mb-4">📋</p>
            <p class="text-sm font-black text-slate-400 uppercase tracking-widest">Pilih Proyek</p>
            <p class="text-xs text-slate-500 mt-2">Pilih proyek di atas untuk melihat kontrak subkontraktor.</p>
        </div>

        <!-- Create/Edit Modal -->
        <teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
                    <div class="sticky top-0 bg-white/95 backdrop-blur-sm px-8 py-6 border-b border-slate-100 z-10">
                        <h2 class="text-lg font-black text-slate-900">{{ editMode ? '✏️ Edit Detail Kontrak' : '🤝 Buat Kontrak Kerja Baru' }}</h2>
                    </div>

                    <!-- Create Form -->
                    <form v-if="!editMode" @submit.prevent="submitForm" class="p-8 space-y-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Nama Kontraktor / Subkon *</label>
                                <input v-model="form.contractor_name" type="text" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700" placeholder="e.g. CV Prima Mandiri" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Nomor Kontrak *</label>
                                <input v-model="form.contract_number" type="text" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700" placeholder="e.g. 021/SPK/HRT/VI/2026" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Deskripsi / Uraian Pekerjaan *</label>
                                <input v-model="form.description" type="text" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700" placeholder="e.g. Borongan pembuatan pondasi & dinding tipe 36" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Total Nilai Kontrak (Rp) *</label>
                                <input v-model="form.total_amount" type="number" required min="1" @input="syncTerminAmount" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700" />
                            </div>
                        </div>

                        <!-- Termins Planner Section -->
                        <div class="border-t border-slate-100 pt-5">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Tahapan / Termin Pembayaran</h3>
                                <button type="button" @click="addTerminRow" class="px-3 py-1.5 bg-slate-900 text-white text-[10px] font-black rounded-lg uppercase tracking-wider">
                                    + Tambah Tahap
                                </button>
                            </div>

                            <div class="space-y-3">
                                <div v-for="(t, idx) in form.termins" :key="idx" class="grid grid-cols-12 gap-3 items-end bg-slate-50 p-4 rounded-xl border border-slate-200/60">
                                    <div class="col-span-3">
                                        <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Label Termin</label>
                                        <input v-model="t.label" type="text" required class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700" />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Persen (%)</label>
                                        <input v-model="t.percentage" type="number" required min="0.01" max="100" step="0.01" @input="syncTerminAmount" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700" />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Nominal (Rp)</label>
                                        <span class="block px-3 py-2 bg-slate-100 text-xs font-black text-slate-600 rounded-lg border border-slate-200 truncate">{{ formatCurrency(t.amount) }}</span>
                                    </div>
                                    <div class="col-span-3">
                                        <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Pos Anggaran RAB</label>
                                        <select v-model="t.rab_item_id" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-[10px] font-bold text-slate-700">
                                            <option value="">-- Hubungkan RAB --</option>
                                            <option v-for="item in rab_items" :key="item.id" :value="item.id">🧱 {{ item.description }}</option>
                                        </select>
                                    </div>
                                    <div class="col-span-2 flex items-center gap-1">
                                        <div class="flex-1">
                                            <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Due Date</label>
                                            <input v-model="t.due_date" type="date" class="w-full px-2 py-2 bg-white border border-slate-200 rounded-lg text-[10px] font-bold text-slate-700" />
                                        </div>
                                        <button type="button" v-if="form.termins.length > 1" @click="removeTerminRow(idx)" class="p-2 text-rose-500 hover:bg-rose-50 rounded-lg transition-colors">🗑️</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-5 border-t border-slate-100">
                            <button type="button" @click="showModal = false" class="px-6 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                            <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-xs font-bold rounded-xl shadow-lg">
                                Simpan Kontrak
                            </button>
                        </div>
                    </form>

                    <!-- Edit Form -->
                    <form v-else @submit.prevent="submitForm" class="p-8 space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Nama Kontraktor *</label>
                            <input v-model="editForm.contractor_name" type="text" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Deskripsi *</label>
                            <input v-model="editForm.description" type="text" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Status Kontrak</label>
                            <select v-model="editForm.status" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">
                                <option value="active">Aktif / Berjalan</option>
                                <option value="completed">Selesai (Pekerjaan Selesai)</option>
                            </select>
                        </div>
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showModal = false" class="px-6 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                            <button type="submit" :disabled="editForm.processing" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-xs font-bold rounded-xl shadow-lg">
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <!-- Pay Termin Modal -->
        <teleport to="body">
            <div v-if="showPayModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showPayModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md">
                    <div class="px-8 py-6 border-b border-slate-100">
                        <h2 class="text-lg font-black text-slate-900">💳 Bayar Termin Subkon</h2>
                        <p class="text-xs text-slate-500 mt-0.5">{{ payTarget?.contract?.contractor_name }} - {{ payTarget?.label }}</p>
                        <p class="text-sm font-black text-blue-600 mt-1">Nominal: {{ formatCurrency(payTarget?.amount) }}</p>
                    </div>
                    <form @submit.prevent="submitPay" class="p-8 space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Bayar Dari Rekening / Kas *</label>
                            <select v-model="payForm.bank_account_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">
                                <option v-for="bank in bank_accounts" :key="bank.id" :value="bank.id">
                                    {{ bank.name }} (Saldo: {{ formatCurrency(bank.current_balance) }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tanggal Pembayaran *</label>
                            <input v-model="payForm.paid_date" type="date" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Catatan Pembayaran</label>
                            <textarea v-model="payForm.notes" rows="2" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700" placeholder="Opsional..."></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showPayModal = false" class="px-6 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                            <button type="submit" :disabled="payForm.processing" class="px-6 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white text-xs font-bold rounded-xl shadow-lg disabled:opacity-50">
                                {{ payForm.processing ? 'Menyimpan...' : 'Bayar Lunas' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>
    </CrmLayout>
</template>
