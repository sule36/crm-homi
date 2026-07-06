<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    items: Array,
    projects: Array,
    summary: Object,
    category_labels: Object,
    filters: Object,
});

const formatCurrency = (v) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v || 0);
const formatNumber = (v) => new Intl.NumberFormat('id-ID').format(v || 0);

const selectedProject = ref(props.filters?.project_id || '');

function changeProject() {
    if (selectedProject.value) {
        router.get('/finance/rab', { project_id: selectedProject.value }, { preserveState: true, preserveScroll: true });
    }
}

// Group items by category
const groupedItems = computed(() => {
    if (!props.items?.length) return [];
    const groups = {};
    props.items.forEach(item => {
        if (!groups[item.category]) {
            groups[item.category] = {
                category: item.category,
                label: props.category_labels?.[item.category] || item.category,
                items: [],
                total_budget: 0,
                total_realization: 0,
            };
        }
        groups[item.category].items.push(item);
        groups[item.category].total_budget += item.total_price;
        groups[item.category].total_realization += item.realization_total || 0;
    });
    return Object.values(groups);
});

// Create item modal
const showModal = ref(false);
const editMode = ref(false);
const selectedItem = ref(null);

const form = useForm({
    project_id: props.filters?.project_id || '',
    category: '',
    sub_category: '',
    description: '',
    unit: 'ls',
    volume: 1,
    unit_price: 0,
    notes: '',
});

const computedTotal = computed(() => (Number(form.volume) || 0) * (Number(form.unit_price) || 0));

function openCreate() {
    editMode.value = false;
    selectedItem.value = null;
    form.reset();
    form.project_id = selectedProject.value;
    showModal.value = true;
}

function openEdit(item) {
    editMode.value = true;
    selectedItem.value = item;
    form.project_id = item.project_id;
    form.category = item.category;
    form.sub_category = item.sub_category || '';
    form.description = item.description;
    form.unit = item.unit;
    form.volume = item.volume;
    form.unit_price = item.unit_price;
    form.notes = item.notes || '';
    showModal.value = true;
}

function submitForm() {
    if (editMode.value && selectedItem.value) {
        form.put(`/finance/rab/${selectedItem.value.id}`, { preserveScroll: true, onSuccess: () => { showModal.value = false; } });
    } else {
        form.post('/finance/rab', { preserveScroll: true, onSuccess: () => { showModal.value = false; } });
    }
}

function deleteItem(item) {
    if (confirm(`Hapus item RAB "${item.description}"?`)) {
        router.delete(`/finance/rab/${item.id}`, { preserveScroll: true });
    }
}

// Realization modal
const showRealizeModal = ref(false);
const realizeTarget = ref(null);

const realizeForm = useForm({
    amount: '',
    realization_date: new Date().toISOString().split('T')[0],
    vendor_name: '',
    notes: '',
    create_expense: true,
});

function openRealize(item) {
    realizeTarget.value = item;
    realizeForm.reset();
    realizeForm.realization_date = new Date().toISOString().split('T')[0];
    realizeForm.create_expense = true;
    showRealizeModal.value = true;
}

function submitRealize() {
    realizeForm.post(`/finance/rab/${realizeTarget.value.id}/realize`, {
        preserveScroll: true,
        onSuccess: () => { showRealizeModal.value = false; },
    });
}

// Duplicate modal
const showDuplicateModal = ref(false);
const duplicateForm = useForm({
    source_project_id: '',
    target_project_id: '',
});

function openDuplicate() {
    duplicateForm.reset();
    duplicateForm.target_project_id = selectedProject.value;
    showDuplicateModal.value = true;
}

function submitDuplicate() {
    duplicateForm.post('/finance/rab/duplicate', {
        preserveScroll: true,
        onSuccess: () => { showDuplicateModal.value = false; },
    });
}

const unitOptions = ['ls', 'm²', 'm³', 'm\'', 'kg', 'sak', 'btg', 'bh', 'unit', 'set', 'titik', 'lot'];
</script>

<template>
    <Head title="RAB Proyek" />
    <CrmLayout>
        <template #breadcrumb>Keuangan / RAB</template>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Rencana Anggaran Biaya (RAB)</h1>
                <p class="text-sm text-slate-500 mt-1">Budget dan tracking realisasi biaya pembangunan per proyek.</p>
            </div>
            <div class="flex items-center gap-3">
                <Link href="/finance" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">← Kas Utama</Link>
                <a v-if="selectedProject" :href="`/finance/rab/export?project_id=${selectedProject}`" target="_blank"
                    class="px-4 py-2.5 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition-all">📥 Export CSV</a>
            </div>
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
                    + Tambah Item RAB
                </button>
                <button v-if="selectedProject" @click="openDuplicate" class="px-5 py-3 bg-slate-200 text-slate-700 text-sm font-bold rounded-xl hover:bg-slate-300 transition-all whitespace-nowrap">
                    📋 Duplikasi RAB
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div v-if="summary" class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Anggaran</p>
                <h3 class="text-xl font-black text-slate-900">{{ formatCurrency(summary.total_budget) }}</h3>
            </div>
            <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Realisasi</p>
                <h3 class="text-xl font-black text-blue-600">{{ formatCurrency(summary.total_realization) }}</h3>
            </div>
            <div class="rounded-2xl p-6 border shadow-sm" :class="summary.deviation >= 0 ? 'bg-emerald-50 border-emerald-100' : 'bg-rose-50 border-rose-100'">
                <p class="text-[10px] font-black uppercase tracking-widest mb-1" :class="summary.deviation >= 0 ? 'text-emerald-500' : 'text-rose-500'">Sisa Anggaran</p>
                <h3 class="text-xl font-black" :class="summary.deviation >= 0 ? 'text-emerald-700' : 'text-rose-700'">{{ formatCurrency(summary.deviation) }}</h3>
            </div>
            <div class="bg-slate-900 rounded-2xl p-6 shadow-xl">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Progress</p>
                <h3 class="text-xl font-black text-white">{{ summary.progress_percent }}%</h3>
                <div class="w-full h-2 bg-slate-700 rounded-full mt-3 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500"
                        :class="summary.progress_percent > 100 ? 'bg-rose-500' : 'bg-blue-500'"
                        :style="`width: ${Math.min(100, summary.progress_percent)}%`"></div>
                </div>
            </div>
        </div>

        <!-- RAB Table Grouped by Category -->
        <div v-if="selectedProject && groupedItems.length" class="space-y-6">
            <div v-for="group in groupedItems" :key="group.category" class="bg-white rounded-3xl border border-slate-100 overflow-hidden shadow-sm">
                <!-- Category Header -->
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900">{{ group.label }}</h3>
                        <p class="text-[10px] text-slate-400 font-bold mt-0.5">{{ group.items.length }} item • Anggaran: {{ formatCurrency(group.total_budget) }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <p class="text-[9px] font-black text-slate-400 uppercase">Realisasi</p>
                            <p class="text-sm font-black text-blue-600">{{ formatCurrency(group.total_realization) }}</p>
                        </div>
                        <div class="w-20 h-2 bg-slate-200 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full transition-all"
                                :style="`width: ${group.total_budget > 0 ? Math.min(100, (group.total_realization / group.total_budget) * 100) : 0}%`"></div>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-[9px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                <th class="px-6 py-3">Uraian Pekerjaan</th>
                                <th class="px-4 py-3 text-center">Satuan</th>
                                <th class="px-4 py-3 text-right">Volume</th>
                                <th class="px-4 py-3 text-right">Harga Satuan</th>
                                <th class="px-4 py-3 text-right">Total Anggaran</th>
                                <th class="px-4 py-3 text-right">Realisasi</th>
                                <th class="px-4 py-3 text-right">Selisih</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="item in group.items" :key="item.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-3">
                                    <p class="font-bold text-slate-900">{{ item.description }}</p>
                                    <p v-if="item.sub_category" class="text-[10px] text-slate-400">{{ item.sub_category }}</p>
                                </td>
                                <td class="px-4 py-3 text-center text-slate-600 font-medium">{{ item.unit }}</td>
                                <td class="px-4 py-3 text-right font-bold text-slate-700">{{ formatNumber(item.volume) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-slate-700">{{ formatCurrency(item.unit_price) }}</td>
                                <td class="px-4 py-3 text-right font-black text-slate-900">{{ formatCurrency(item.total_price) }}</td>
                                <td class="px-4 py-3 text-right font-black text-blue-600">{{ formatCurrency(item.realization_total) }}</td>
                                <td class="px-4 py-3 text-right font-black" :class="item.deviation >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                    {{ formatCurrency(item.deviation) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button @click="openRealize(item)" class="px-2 py-1 bg-emerald-50 text-emerald-600 text-[9px] font-black rounded-md hover:bg-emerald-100 transition-all" title="Catat Realisasi">💰</button>
                                        <button @click="openEdit(item)" class="px-2 py-1 bg-slate-100 text-slate-600 text-[9px] font-black rounded-md hover:bg-slate-200 transition-all" title="Edit">✏️</button>
                                        <button @click="deleteItem(item)" class="px-2 py-1 bg-rose-50 text-rose-500 text-[9px] font-black rounded-md hover:bg-rose-100 transition-all" title="Hapus">🗑️</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="selectedProject && !groupedItems.length" class="bg-white rounded-3xl border border-slate-100 p-16 text-center shadow-sm">
            <p class="text-4xl mb-4">🏗️</p>
            <p class="text-sm font-black text-slate-400 uppercase tracking-widest">Belum Ada Item RAB</p>
            <p class="text-xs text-slate-500 mt-2">Mulai tambah item RAB atau duplikasi dari proyek lain.</p>
        </div>

        <div v-else class="bg-white rounded-3xl border border-slate-100 p-16 text-center shadow-sm">
            <p class="text-4xl mb-4">📋</p>
            <p class="text-sm font-black text-slate-400 uppercase tracking-widest">Pilih Proyek</p>
            <p class="text-xs text-slate-500 mt-2">Pilih proyek di atas untuk melihat dan mengelola RAB.</p>
        </div>

        <!-- Create/Edit Item Modal -->
        <teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
                    <div class="sticky top-0 bg-white/95 backdrop-blur-sm px-8 py-6 border-b border-slate-100 z-10">
                        <h2 class="text-lg font-black text-slate-900">{{ editMode ? '✏️ Edit Item RAB' : '🏗️ Tambah Item RAB' }}</h2>
                    </div>
                    <form @submit.prevent="submitForm" class="p-8 space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Kategori *</label>
                            <select v-model="form.category" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">
                                <option value="">Pilih Kategori</option>
                                <option v-for="(label, key) in category_labels" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Uraian Pekerjaan *</label>
                            <input v-model="form.description" type="text" required placeholder="Contoh: Galian tanah pondasi" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700" />
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Satuan</label>
                                <select v-model="form.unit" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">
                                    <option v-for="u in unitOptions" :key="u" :value="u">{{ u }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Volume</label>
                                <input v-model="form.volume" type="number" step="0.01" min="0" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Harga Satuan (Rp)</label>
                                <input v-model="form.unit_price" type="number" min="0" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700" />
                            </div>
                        </div>
                        <div class="bg-blue-50 rounded-xl p-4 border border-blue-100">
                            <p class="text-[10px] font-black text-blue-500 uppercase">Total Anggaran</p>
                            <p class="text-xl font-black text-blue-700 mt-1">{{ formatCurrency(computedTotal) }}</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Catatan</label>
                            <textarea v-model="form.notes" rows="2" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showModal = false" class="px-6 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                            <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-xs font-bold rounded-xl shadow-lg disabled:opacity-50">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <!-- Realize Modal -->
        <teleport to="body">
            <div v-if="showRealizeModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showRealizeModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md">
                    <div class="px-8 py-6 border-b border-slate-100">
                        <h2 class="text-lg font-black text-slate-900">💰 Catat Realisasi</h2>
                        <p class="text-xs text-slate-500 mt-0.5">{{ realizeTarget?.description }}</p>
                        <p class="text-[10px] text-slate-400 mt-1">Anggaran: {{ formatCurrency(realizeTarget?.total_price) }} • Sudah: {{ formatCurrency(realizeTarget?.realization_total) }}</p>
                    </div>
                    <form @submit.prevent="submitRealize" class="p-8 space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Jumlah Realisasi (Rp) *</label>
                            <input v-model="realizeForm.amount" type="number" required min="1" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tanggal *</label>
                            <input v-model="realizeForm.realization_date" type="date" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Vendor</label>
                            <input v-model="realizeForm.vendor_name" type="text" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700" />
                        </div>
                        <div class="flex items-center gap-2">
                            <input v-model="realizeForm.create_expense" type="checkbox" id="createExpense" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                            <label for="createExpense" class="text-xs font-bold text-slate-700">Otomatis catat sebagai Pengeluaran Operasional</label>
                        </div>
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showRealizeModal = false" class="px-6 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                            <button type="submit" :disabled="realizeForm.processing" class="px-6 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white text-xs font-bold rounded-xl shadow-lg disabled:opacity-50">Catat Realisasi</button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <!-- Duplicate Modal -->
        <teleport to="body">
            <div v-if="showDuplicateModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showDuplicateModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md">
                    <div class="px-8 py-6 border-b border-slate-100">
                        <h2 class="text-lg font-black text-slate-900">📋 Duplikasi RAB</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Salin semua item RAB dari proyek lain.</p>
                    </div>
                    <form @submit.prevent="submitDuplicate" class="p-8 space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Proyek Sumber *</label>
                            <select v-model="duplicateForm.source_project_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">
                                <option value="">Pilih Proyek</option>
                                <option v-for="proj in projects.filter(p => p.id != selectedProject)" :key="proj.id" :value="proj.id">{{ proj.name }}</option>
                            </select>
                        </div>
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showDuplicateModal = false" class="px-6 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                            <button type="submit" :disabled="duplicateForm.processing" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-xs font-bold rounded-xl shadow-lg disabled:opacity-50">Duplikasi</button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>
    </CrmLayout>
</template>
