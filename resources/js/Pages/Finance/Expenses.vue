<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    expenses: Object,
    categories: Array,
    projects: Array,
    bank_accounts: Array,
    stats: Object,
    filters: Object,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value || 0);
};

// Modal state
const showModal = ref(false);
const editMode = ref(false);
const selectedExpense = ref(null);

const form = useForm({
    project_id: '',
    expense_category_id: '',
    description: '',
    amount: '',
    expense_date: new Date().toISOString().split('T')[0],
    payment_method: 'transfer',
    vendor_name: '',
    receipt_number: '',
    notes: '',
    bank_account_id: '',
    ppn_amount: 0,
    pph_amount: 0,
});

function openCreateModal() {
    editMode.value = false;
    selectedExpense.value = null;
    form.reset();
    form.expense_date = new Date().toISOString().split('T')[0];
    if (props.bank_accounts?.length > 0) {
        form.bank_account_id = props.bank_accounts[0].id;
    }
    showModal.value = true;
}

function openEditModal(expense) {
    editMode.value = true;
    selectedExpense.value = expense;
    form.project_id = expense.project_id || '';
    form.expense_category_id = expense.expense_category_id;
    form.description = expense.description;
    form.amount = expense.amount;
    form.expense_date = expense.expense_date?.split('T')[0] || '';
    form.payment_method = expense.payment_method;
    form.vendor_name = expense.vendor_name || '';
    form.receipt_number = expense.receipt_number || '';
    form.notes = expense.notes || '';
    form.bank_account_id = expense.bank_account_id || '';
    form.ppn_amount = expense.ppn_amount || 0;
    form.pph_amount = expense.pph_amount || 0;
    showModal.value = true;
}

function submitForm() {
    if (editMode.value && selectedExpense.value) {
        form.put(`/finance/expenses/${selectedExpense.value.id}`, {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    } else {
        form.post('/finance/expenses', {
            preserveScroll: true,
            onSuccess: () => { showModal.value = false; form.reset(); },
        });
    }
}

function deleteExpense(expense) {
    if (confirm(`Hapus pengeluaran "${expense.description}"?`)) {
        router.delete(`/finance/expenses/${expense.id}`, { preserveScroll: true });
    }
}

// Filter
const filterCategory = ref(props.filters?.category_id || '');
const filterProject = ref(props.filters?.project_id || '');
const searchQuery = ref(props.filters?.search || '');

function applyFilters() {
    router.get('/finance/expenses', {
        search: searchQuery.value || undefined,
        category_id: filterCategory.value || undefined,
        project_id: filterProject.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}

function resetFilters() {
    searchQuery.value = '';
    filterCategory.value = '';
    filterProject.value = '';
    router.get('/finance/expenses', {}, { preserveState: true });
}

// Max bar for trend chart
const maxTrend = computed(() => {
    if (!props.stats?.monthly_trend?.length) return 1;
    return Math.max(...props.stats.monthly_trend.map(t => t.total), 1);
});
</script>

<template>
    <Head title="Pengeluaran Operasional" />
    <CrmLayout>
        <template #breadcrumb>Keuangan / Pengeluaran</template>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Pengeluaran Operasional</h1>
                <p class="text-sm text-slate-500 mt-1">Catat dan kelola semua biaya keluar perusahaan.</p>
            </div>
            <div class="flex items-center gap-3">
                <Link href="/finance" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">
                    ← Kas Utama
                </Link>
                <button @click="openCreateModal" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-rose-600 to-pink-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-rose-500/25 hover:shadow-rose-500/40 hover:-translate-y-0.5 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    Catat Pengeluaran
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Bulan Ini</p>
                <h3 class="text-2xl font-black text-rose-600">{{ formatCurrency(stats.total_this_month) }}</h3>
                <p class="text-[10px] text-slate-400 font-bold mt-2">{{ new Date().toLocaleDateString('id-ID', { month: 'long', year: 'numeric' }) }}</p>
            </div>
            <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Tahun Ini</p>
                <h3 class="text-2xl font-black text-slate-900">{{ formatCurrency(stats.total_this_year) }}</h3>
                <p class="text-[10px] text-slate-400 font-bold mt-2">Akumulasi {{ new Date().getFullYear() }}</p>
            </div>
            <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-6 shadow-xl">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Tren 6 Bulan</p>
                <div class="flex items-end gap-1.5 h-16">
                    <div v-for="trend in stats.monthly_trend" :key="trend.month"
                        class="flex-1 bg-rose-500/80 rounded-t-sm transition-all hover:bg-rose-400 relative group cursor-default"
                        :style="`height: ${Math.max(4, (trend.total / maxTrend) * 100)}%`">
                        <div class="absolute -top-10 left-1/2 -translate-x-1/2 bg-white text-slate-900 text-[8px] font-bold px-2 py-1 rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
                            {{ trend.month }}<br/>{{ formatCurrency(trend.total) }}
                        </div>
                    </div>
                </div>
                <div class="flex justify-between mt-2">
                    <span v-for="trend in stats.monthly_trend" :key="'l'+trend.month" class="text-[7px] text-slate-500 font-bold flex-1 text-center truncate">{{ trend.month.split(' ')[0] }}</span>
                </div>
            </div>
        </div>

        <!-- Category Breakdown -->
        <div v-if="stats.category_breakdown?.length" class="bg-white rounded-2xl border border-slate-100 p-6 mb-8 shadow-sm">
            <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider mb-4">Breakdown Kategori Bulan Ini</h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div v-for="cat in stats.category_breakdown" :key="cat.category" class="rounded-xl p-4 border border-slate-100 hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-lg">{{ cat.icon }}</span>
                        <span class="text-[10px] font-black text-slate-500 uppercase tracking-wide truncate">{{ cat.category }}</span>
                    </div>
                    <p class="text-sm font-black" :style="`color: ${cat.color}`">{{ formatCurrency(cat.total) }}</p>
                </div>
            </div>
        </div>

        <!-- Filter & Table -->
        <div class="bg-white rounded-3xl border border-slate-100 overflow-hidden shadow-sm">
            <!-- Filters -->
            <div class="p-6 bg-slate-50 border-b border-slate-100 flex flex-col sm:flex-row gap-3 items-end">
                <div class="flex-1">
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Cari</label>
                    <input v-model="searchQuery" type="text" placeholder="Deskripsi, vendor..."
                        class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-rose-500"
                        @keyup.enter="applyFilters" />
                </div>
                <div class="w-full sm:w-44">
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Kategori</label>
                    <select v-model="filterCategory" @change="applyFilters" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 cursor-pointer focus:ring-1 focus:ring-rose-500">
                        <option value="">Semua</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.icon }} {{ cat.name }}</option>
                    </select>
                </div>
                <div class="w-full sm:w-44">
                    <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Proyek</label>
                    <select v-model="filterProject" @change="applyFilters" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 cursor-pointer focus:ring-1 focus:ring-rose-500">
                        <option value="">Semua</option>
                        <option v-for="proj in projects" :key="proj.id" :value="proj.id">{{ proj.name }}</option>
                    </select>
                </div>
                <button @click="resetFilters" class="px-4 py-2 bg-slate-200 text-slate-600 text-[10px] font-black uppercase rounded-xl hover:bg-slate-300 transition-all whitespace-nowrap">Reset</button>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">
                            <th class="px-6 py-4">Tanggal</th>
                            <th class="px-6 py-4">Kategori</th>
                            <th class="px-6 py-4">Deskripsi</th>
                            <th class="px-6 py-4">Kas / Rekening</th>
                            <th class="px-6 py-4">Proyek</th>
                            <th class="px-6 py-4 text-right">Pajak (PPN/PPh)</th>
                            <th class="px-6 py-4 text-right">Jumlah</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="exp in expenses.data" :key="exp.id" class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <span class="font-bold text-slate-700">{{ new Date(exp.expense_date).toLocaleDateString('id-ID') }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black" :style="`background: ${exp.category?.color}15; color: ${exp.category?.color}`">
                                    {{ exp.category?.icon }} {{ exp.category?.name }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-bold text-slate-900 max-w-[200px] truncate">{{ exp.description }}</p>
                                <p v-if="exp.receipt_number" class="text-[10px] text-slate-400 font-bold">No. {{ exp.receipt_number }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="exp.bank_account" class="px-2 py-1 bg-slate-100 text-slate-700 rounded text-[9px] font-black font-mono">
                                    {{ exp.bank_account.name }}
                                </span>
                                <span v-else class="text-slate-400 text-[10px] font-bold italic">Cash</span>
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="exp.project" class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-[9px] font-black">{{ exp.project.name }}</span>
                                <span v-else class="text-slate-400 text-[10px] font-bold italic">Umum</span>
                            </td>
                            <td class="px-6 py-4 text-right font-medium text-slate-600">
                                <p v-if="exp.ppn_amount > 0" class="text-[9px] text-rose-500 font-bold">PPN: {{ formatCurrency(exp.ppn_amount) }}</p>
                                <p v-if="exp.pph_amount > 0" class="text-[9px] text-amber-600 font-bold">PPh: {{ formatCurrency(exp.pph_amount) }}</p>
                                <span v-if="!exp.ppn_amount && !exp.pph_amount">-</span>
                            </td>
                            <td class="px-6 py-4 text-right font-black text-rose-600">{{ formatCurrency(exp.amount) }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button @click="openEditModal(exp)" class="p-1.5 text-slate-400 hover:text-blue-600 transition-colors" title="Edit">✏️</button>
                                    <button @click="deleteExpense(exp)" class="p-1.5 text-slate-400 hover:text-rose-600 transition-colors" title="Hapus">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!expenses.data?.length">
                            <td colspan="8" class="px-6 py-16 text-center">
                                <p class="text-3xl mb-3">💸</p>
                                <p class="text-xs font-black text-slate-400 uppercase tracking-widest">Belum Ada Pengeluaran</p>
                                <p class="text-xs text-slate-500 mt-1">Mulai catat pengeluaran operasional perusahaan.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="expenses.links?.length > 3" class="px-6 py-4 border-t border-slate-100 flex items-center justify-between">
                <p class="text-[10px] text-slate-400 font-bold">Menampilkan {{ expenses.from }}-{{ expenses.to }} dari {{ expenses.total }}</p>
                <div class="flex gap-1">
                    <template v-for="link in expenses.links" :key="link.label">
                        <Link v-if="link.url" :href="link.url" v-html="link.label"
                            :class="[link.active ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-100']"
                            class="px-3 py-1.5 rounded-lg text-[10px] font-bold border border-slate-200 transition-all" />
                        <span v-else v-html="link.label" class="px-3 py-1.5 rounded-lg text-[10px] font-bold text-slate-300" />
                    </template>
                </div>
            </div>
        </div>

        <!-- MODAL -->
        <teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                    <!-- Header -->
                    <div class="sticky top-0 bg-white/95 backdrop-blur-sm px-8 py-6 border-b border-slate-100 z-10">
                        <h2 class="text-lg font-black text-slate-900">{{ editMode ? 'Edit Pengeluaran' : '💸 Catat Pengeluaran Baru' }}</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Isi detail pengeluaran operasional di bawah.</p>
                    </div>

                    <form @submit.prevent="submitForm" class="p-8 space-y-5">
                        <!-- Category & Date -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Kategori *</label>
                                <select v-model="form.expense_category_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-rose-500">
                                    <option value="">Pilih Kategori</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.icon }} {{ cat.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tanggal *</label>
                                <input v-model="form.expense_date" type="date" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-rose-500" />
                            </div>
                        </div>

                        <!-- Description -->
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Deskripsi *</label>
                            <input v-model="form.description" type="text" required placeholder="Contoh: Pembelian semen 50 sak" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-rose-500" />
                        </div>

                        <!-- Amount & Method -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Jumlah (Rp) *</label>
                                <input v-model="form.amount" type="number" required min="1" placeholder="0" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-rose-500" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Metode Pembayaran *</label>
                                <select v-model="form.payment_method" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-rose-500">
                                    <option value="transfer">Transfer Bank</option>
                                    <option value="cash">Cash / Tunai</option>
                                    <option value="cheque">Cek / Giro</option>
                                </select>
                            </div>
                        </div>

                        <!-- Source Bank Account (Kas) -->
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Bayar Dari Rekening / Kas *</label>
                            <select v-model="form.bank_account_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-rose-500">
                                <option value="">-- Pilih Kas / Rekening --</option>
                                <option v-for="bank in bank_accounts" :key="bank.id" :value="bank.id">
                                    {{ bank.name }} (Saldo: {{ formatCurrency(bank.current_balance) }})
                                </option>
                            </select>
                        </div>

                        <!-- Taxes breakdown (PPN & PPh) -->
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Pajak PPN Masukan (Rp)</label>
                                <input v-model="form.ppn_amount" type="number" min="0" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-rose-500" />
                                <p class="text-[9px] text-slate-400 mt-1">PPN 11% dari vendor (jika ada)</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Potongan Pajak PPh (Rp)</label>
                                <input v-model="form.pph_amount" type="number" min="0" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-rose-500" />
                                <p class="text-[9px] text-slate-400 mt-1">Potongan PPh 21/23/final jasa (jika ada)</p>
                            </div>
                        </div>

                        <!-- Vendor & Receipt -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Nama Vendor / Supplier</label>
                                <input v-model="form.vendor_name" type="text" placeholder="Opsional" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-rose-500" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">No. Kwitansi / Invoice</label>
                                <input v-model="form.receipt_number" type="text" placeholder="Opsional" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-rose-500" />
                            </div>
                        </div>

                        <!-- Project -->
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Proyek Terkait</label>
                            <select v-model="form.project_id" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-rose-500">
                                <option value="">Umum (Tidak Terkait Proyek)</option>
                                <option v-for="proj in projects" :key="proj.id" :value="proj.id">🏗️ {{ proj.name }}</option>
                            </select>
                            <p class="text-[10px] text-slate-400 mt-1 font-medium italic">Kosongkan jika pengeluaran tidak terkait proyek spesifik.</p>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Catatan</label>
                            <textarea v-model="form.notes" rows="2" placeholder="Catatan tambahan..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-rose-500"></textarea>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showModal = false" class="px-6 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-200 transition-all">Batal</button>
                            <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-gradient-to-r from-rose-600 to-pink-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-rose-500/25 hover:shadow-rose-500/40 transition-all disabled:opacity-50">
                                {{ form.processing ? 'Menyimpan...' : (editMode ? 'Simpan Perubahan' : 'Catat Pengeluaran') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>
    </CrmLayout>
</template>
