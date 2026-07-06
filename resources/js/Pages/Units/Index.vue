<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    units: Object,
    stats: Object,
    filters: Object,
    projects: Array,
    unitTypes: Array,
});

const search = ref(props.filters?.search || '');
const projectFilter = ref(props.filters?.project_id || '');
const statusFilter = ref(props.filters?.status || '');
const typeFilter = ref(props.filters?.unit_type_id || '');
const viewMode = ref('grid'); // 'grid' or 'table'

let timeout;
watch(search, (val) => { clearTimeout(timeout); timeout = setTimeout(() => applyFilters(), 400); });
watch([projectFilter, statusFilter, typeFilter], () => applyFilters());

function applyFilters() {
    router.get('/units', {
        search: search.value, project_id: projectFilter.value,
        status: statusFilter.value, unit_type_id: typeFilter.value,
    }, { preserveState: true, replace: true });
}

// Status config
const statusConfig = {
    available: { label: 'Tersedia', color: 'bg-emerald-500', textColor: 'text-emerald-700', bgLight: 'bg-emerald-50 border-emerald-200' },
    hold: { label: 'Hold', color: 'bg-amber-500', textColor: 'text-amber-700', bgLight: 'bg-amber-50 border-amber-200' },
    booked: { label: 'Booked', color: 'bg-blue-500', textColor: 'text-blue-700', bgLight: 'bg-blue-50 border-blue-200' },
    sold: { label: 'Terjual', color: 'bg-slate-500', textColor: 'text-slate-700', bgLight: 'bg-slate-100 border-slate-300' },
    cancelled: { label: 'Batal', color: 'bg-rose-500', textColor: 'text-rose-700', bgLight: 'bg-rose-50 border-rose-200' },
};

// Computed types filtered by project
const filteredTypes = computed(() => {
    if (!projectFilter.value) return props.unitTypes;
    return props.unitTypes.filter(t => t.project_id == projectFilter.value);
});

// Quick status update
function updateUnitStatus(unit, newStatus) {
    router.put(`/units/${unit.id}`, { status: newStatus }, { preserveState: true, preserveScroll: true });
}

// Add unit modal
const showAddModal = ref(false);
const addMode = ref('single'); // 'single' or 'bulk'

const addForm = useForm({
    project_id: '', unit_type_id: '', block: '', number: '', floor: '', facing_direction: '', premium_charge: '', final_price: '', notes: '',
});

const bulkForm = useForm({
    project_id: '', unit_type_id: '', block: '', start_number: 1, end_number: 10, facing_direction: '',
});

function submitUnit() {
    addForm.post('/units', { preserveScroll: true, onSuccess: () => { showAddModal.value = false; addForm.reset(); } });
}

function submitBulk() {
    bulkForm.post('/units/bulk', { preserveScroll: true, onSuccess: () => { showAddModal.value = false; bulkForm.reset(); } });
}

function deleteUnit(id) {
    if (confirm('Yakin hapus unit ini?')) {
        router.delete(`/units/${id}`, { preserveScroll: true });
    }
}

const selectedMapUnit = ref(null);
const unitsByBlock = computed(() => {
    const grouped = {};
    props.units.data.forEach(unit => {
        const b = unit.block || 'Unknown';
        if (!grouped[b]) grouped[b] = [];
        grouped[b].push(unit);
    });
    // Sort units in each block by number
    Object.keys(grouped).forEach(b => {
        grouped[b].sort((x, y) => x.number.localeCompare(y.number));
    });
    return grouped;
});

import KprCalculatorModal from '@/Components/Crm/KprCalculatorModal.vue';

const showKprModal = ref(false);
const kprPrice = ref(0);
const kprUnitCode = ref('');

function openKprSimulator(unit) {
    kprPrice.value = Number(unit.final_price);
    kprUnitCode.value = `${unit.block || ''}${unit.number}`;
    showKprModal.value = true;
}

function formatPrice(p) {
    if (!p) return '-';
    if (p >= 1000000000) return `Rp ${(p / 1000000000).toFixed(1)}M`;
    return `Rp ${(p / 1000000).toFixed(0)}jt`;
}

// Detail Unit Drawer/Modal State
const selectedUnitDetail = ref(null);
const detailTab = ref('progress'); // progress | legal | booking

const certificateLabels = {
    belum_pecah: 'Belum Pecah (Induk)',
    pecah_di_notaris: 'Proses Pecah di Notaris',
    sudah_balik_nama: 'Sudah Balik Nama (SHM/SHGB)',
    diserahkan_ke_konsumen: 'Diserahkan ke Konsumen',
    diserahkan_ke_bank: 'Diserahkan ke Bank (Jaminan KPR)',
};

const legalForm = useForm({
    certificate_status: 'belum_pecah',
    certificate_number: '',
    imb_number: '',
    pbb_number: '',
    legal_notes: '',
    status: '',
});

const progressForm = useForm({
    unit_id: '',
    progress_percentage: 0,
    description: '',
    notes: '',
    recorded_date: new Date().toISOString().split('T')[0],
});

function openUnitDetail(unit) {
    selectedUnitDetail.value = unit;
    detailTab.value = 'progress';

    legalForm.certificate_status = unit.certificate_status || 'belum_pecah';
    legalForm.certificate_number = unit.certificate_number || '';
    legalForm.imb_number = unit.imb_number || '';
    legalForm.pbb_number = unit.pbb_number || '';
    legalForm.legal_notes = unit.legal_notes || '';
    legalForm.status = unit.status || 'available';

    progressForm.reset();
    progressForm.unit_id = unit.id;
    progressForm.progress_percentage = unit.latest_progress?.progress_percentage || 0;
    progressForm.recorded_date = new Date().toISOString().split('T')[0];
}

function submitLegalUpdate() {
    legalForm.put(`/units/${selectedUnitDetail.value.id}`, {
        preserveScroll: true,
        onSuccess: (page) => {
            const updatedUnit = props.units.data.find(u => u.id === selectedUnitDetail.value.id);
            if (updatedUnit) {
                selectedUnitDetail.value = updatedUnit;
            }
        }
    });
}

function submitProgressUpdate() {
    progressForm.post('/units/progress', {
        preserveScroll: true,
        onSuccess: (page) => {
            progressForm.description = '';
            progressForm.notes = '';
            const updatedUnit = props.units.data.find(u => u.id === selectedUnitDetail.value.id);
            if (updatedUnit) {
                selectedUnitDetail.value = updatedUnit;
            }
        }
    });
}
</script>

<template>
    <Head title="Inventory" />
    <CrmLayout>
        <template #breadcrumb>Inventory</template>

        <!-- HEADER -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Inventory Unit</h1>
                <p class="text-sm text-slate-500 mt-1">Kelola stok unit properti semua proyek</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex bg-slate-100 rounded-xl p-0.5">
                    <button @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-white shadow-sm' : ''" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all">🏠 Grid</button>
                    <button @click="viewMode = 'table'" :class="viewMode === 'table' ? 'bg-white shadow-sm' : ''" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all">📊 Tabel</button>
                    <button @click="viewMode = 'map'" :class="viewMode === 'map' ? 'bg-white shadow-sm' : ''" class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all">🗺️ Denah Map</button>
                </div>
                <button @click="showAddModal = true"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    Tambah Unit
                </button>
            </div>
        </div>

        <!-- STATS -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
            <div v-for="(conf, key) in statusConfig" :key="key"
                class="bg-white rounded-xl border border-slate-100 p-3 text-center shadow-sm hover:shadow-md transition-all cursor-pointer"
                @click="statusFilter = statusFilter === key ? '' : key">
                <p class="text-xl font-black text-slate-900">{{ stats[key] || 0 }}</p>
                <div class="flex items-center justify-center gap-1.5 mt-1">
                    <span :class="conf.color" class="w-2 h-2 rounded-full"></span>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ conf.label }}</p>
                </div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="flex flex-wrap gap-3 mb-6">
            <div class="relative flex-1 min-w-[200px] max-w-sm">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input v-model="search" type="text" placeholder="Cari blok atau nomor..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
            </div>
            <select v-model="projectFilter" class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium cursor-pointer">
                <option value="">Semua Proyek</option>
                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }} ({{ p.code }})</option>
            </select>
            <select v-model="typeFilter" class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium cursor-pointer">
                <option value="">Semua Tipe</option>
                <option v-for="t in filteredTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
        </div>

        <!-- GRID VIEW -->
        <div v-if="viewMode === 'grid'" class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-8 lg:grid-cols-10 gap-2">
            <div v-for="unit in units.data" :key="unit.id"
                :class="statusConfig[unit.status]?.bgLight || 'bg-white border-slate-200'"
                class="relative border rounded-xl p-2.5 text-center hover:shadow-md hover:-translate-y-0.5 transition-all cursor-pointer group"
                :title="`${unit.block || ''}${unit.number} — ${statusConfig[unit.status]?.label}`"
                @click="openUnitDetail(unit)">
                
                <!-- Status Dot -->
                <span :class="statusConfig[unit.status]?.color" class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full"></span>
                
                <!-- Unit Label -->
                <p class="text-xs font-black text-slate-800 leading-tight">{{ unit.block }}{{ unit.number }}</p>
                <p class="text-[9px] text-slate-500 font-medium truncate mt-0.5">{{ unit.unit_type?.name || '-' }}</p>
                <!-- Progress Percentage Indicator on Grid -->
                <div v-if="unit.latest_progress?.progress_percentage" class="mt-1 flex items-center justify-center gap-0.5">
                    <span class="w-1.5 h-1.5 bg-blue-500 rounded-full inline-block"></span>
                    <span class="text-[8px] font-black text-blue-600">{{ unit.latest_progress.progress_percentage }}%</span>
                </div>
                <p class="text-[9px] font-bold mt-1" :class="statusConfig[unit.status]?.textColor">{{ formatPrice(unit.final_price) }}</p>

                <!-- Hover Actions -->
                <div class="absolute inset-0 bg-white/95 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-1.5 p-1.5" @click.stop>
                    <select @change="updateUnitStatus(unit, $event.target.value); $event.target.value = unit.status" :value="unit.status"
                        class="w-full text-[10px] font-bold border-0 bg-slate-100 rounded-md py-1 cursor-pointer focus:ring-1 focus:ring-blue-500">
                        <option v-for="(c, k) in statusConfig" :key="k" :value="k">{{ c.label }}</option>
                    </select>
                    <button @click.stop="openUnitDetail(unit)" class="w-full py-1 bg-slate-100 text-slate-700 text-[10px] font-bold rounded-md hover:bg-slate-200 transition-colors">👁️ Detail</button>
                    <button @click.stop="openKprSimulator(unit)" class="w-full py-1 bg-blue-50 text-blue-600 text-[10px] font-bold rounded-md hover:bg-blue-100 transition-colors">Hitung KPR</button>
                </div>
            </div>
        </div>

        <!-- TABLE VIEW -->
        <div v-if="viewMode === 'table'" class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="text-left px-4 py-3 text-[10px] font-black text-slate-500 uppercase">Unit</th>
                            <th class="text-left px-4 py-3 text-[10px] font-black text-slate-500 uppercase hidden md:table-cell">Proyek</th>
                            <th class="text-left px-4 py-3 text-[10px] font-black text-slate-500 uppercase hidden md:table-cell">Tipe</th>
                            <th class="text-left px-4 py-3 text-[10px] font-black text-slate-500 uppercase">Progress Fisik</th>
                            <th class="text-left px-4 py-3 text-[10px] font-black text-slate-500 uppercase">Status</th>
                            <th class="text-right px-4 py-3 text-[10px] font-black text-slate-500 uppercase">Harga</th>
                            <th class="text-right px-4 py-3 text-[10px] font-black text-slate-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="unit in units.data" :key="unit.id" class="hover:bg-blue-50/30 transition-colors cursor-pointer" @click="openUnitDetail(unit)">
                            <td class="px-4 py-3">
                                <p class="text-sm font-bold text-slate-900">{{ unit.block }}{{ unit.number }}</p>
                                <p v-if="unit.facing_direction" class="text-[10px] text-slate-400">{{ unit.facing_direction }}</p>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell text-xs text-slate-600">{{ unit.project?.name }}</td>
                            <td class="px-4 py-3 hidden md:table-cell text-xs text-slate-600">{{ unit.unit_type?.name }}</td>
                            <!-- Progress Column in Table -->
                            <td class="px-4 py-3 text-xs">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                        <div class="bg-blue-600 h-full rounded-full transition-all" :style="`width: ${unit.latest_progress?.progress_percentage || 0}%`"></div>
                                    </div>
                                    <span class="font-bold text-slate-700">{{ unit.latest_progress?.progress_percentage || 0 }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3" @click.stop>
                                <select :value="unit.status" @change="updateUnitStatus(unit, $event.target.value)"
                                    :class="statusConfig[unit.status]?.bgLight"
                                    class="text-[10px] font-bold rounded-full px-2 py-0.5 border cursor-pointer focus:ring-2 focus:ring-blue-500/20">
                                    <option v-for="(c, k) in statusConfig" :key="k" :value="k">{{ c.label }}</option>
                                </select>
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-slate-900">{{ formatPrice(unit.final_price) }}</td>
                            <td class="px-4 py-3 text-right" @click.stop>
                                <div class="flex items-center justify-end gap-1.5 ml-auto">
                                    <button @click="openUnitDetail(unit)" class="px-2.5 py-1 bg-slate-100 text-slate-700 text-[10px] font-bold rounded-lg hover:bg-slate-200 transition-colors">
                                        👁️ Detail
                                    </button>
                                    <button @click="openKprSimulator(unit)" class="px-2.5 py-1 bg-blue-50 text-blue-600 text-[10px] font-bold rounded-lg hover:bg-blue-100 transition-colors">
                                        🧮 KPR
                                    </button>
                                    <button @click="deleteUnit(unit.id)" class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-rose-50 text-slate-400 hover:text-rose-500 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="!units.data.length" class="text-center py-16">
                <div class="text-4xl mb-3">🏠</div>
                <p class="text-sm font-bold text-slate-500">Belum ada unit</p>
            </div>
        </div>

        <!-- MAP VIEW -->
        <div v-if="viewMode === 'map'" class="space-y-6">
            <!-- Legend -->
            <div class="bg-white p-5 rounded-2xl border border-slate-100 flex flex-wrap gap-4 items-center justify-between text-xs shadow-sm">
                <div class="flex items-center gap-2">
                    <span class="font-black text-slate-700 uppercase tracking-widest">Warna Kavling:</span>
                </div>
                <div class="flex flex-wrap gap-4">
                    <span v-for="(conf, key) in statusConfig" :key="key" class="flex items-center gap-2 font-bold text-slate-600">
                        <span :class="conf.color" class="w-3 h-3 rounded-md inline-block"></span>
                        {{ conf.label }}
                    </span>
                </div>
            </div>

            <!-- SVG Layout or Grid Blocks -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-for="(blockUnits, blockName) in unitsByBlock" :key="blockName" class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider mb-4 border-b border-slate-50 pb-2">Blok {{ blockName }}</h3>
                    
                    <!-- Dynamic Site Plan Map represented as visual squares grid -->
                    <div class="grid grid-cols-5 sm:grid-cols-8 md:grid-cols-6 lg:grid-cols-8 gap-2">
                        <button v-for="unit in blockUnits" :key="unit.id"
                            @click="selectedMapUnit = unit"
                            :class="[
                                statusConfig[unit.status]?.bgLight,
                                selectedMapUnit?.id === unit.id ? 'ring-2 ring-blue-600' : ''
                            ]"
                            class="aspect-square border rounded-xl flex flex-col items-center justify-center p-1.5 hover:shadow-md transition-all relative">
                            <span class="text-xs font-black text-slate-800">{{ unit.number }}</span>
                            <span class="text-[8px] font-bold mt-0.5 text-slate-400 capitalize">{{ unit.unit_type?.code }}</span>
                            <!-- Progress Percentage Indicator on Denah Map -->
                            <span v-if="unit.latest_progress?.progress_percentage" class="text-[7px] bg-blue-100 text-blue-700 px-1 py-0.5 rounded font-black mt-0.5 leading-none">
                                {{ unit.latest_progress.progress_percentage }}%
                            </span>
                            <span :class="statusConfig[unit.status]?.color" class="absolute bottom-1 right-1 w-1.5 h-1.5 rounded-full"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Detail Popover Card -->
            <div v-if="selectedMapUnit" class="bg-slate-900 text-white p-6 rounded-3xl shadow-xl flex flex-col md:flex-row justify-between items-start md:items-center gap-6 animate-in fade-in duration-200">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-white/10 rounded-2xl flex items-center justify-center text-xl shrink-0">🏠</div>
                    <div>
                        <h4 class="text-base font-black text-white">Unit {{ selectedMapUnit.block }}{{ selectedMapUnit.number }}</h4>
                        <p class="text-xs text-slate-400">{{ selectedMapUnit.project?.name }} • {{ selectedMapUnit.unit_type?.name }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs w-full md:w-auto">
                    <div>
                        <p class="text-[9px] font-bold text-slate-400 uppercase">Harga Final</p>
                        <p class="font-black text-white text-sm mt-0.5">{{ formatPrice(selectedMapUnit.final_price) }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold text-slate-400 uppercase">Arah Hadap</p>
                        <p class="font-black text-white capitalize mt-0.5">{{ selectedMapUnit.facing_direction || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold text-slate-400 uppercase">Ubah Status</p>
                        <select :value="selectedMapUnit.status" @change="updateUnitStatus(selectedMapUnit, $event.target.value); selectedMapUnit.status = $event.target.value"
                            class="bg-slate-800 border-none text-[10px] font-bold text-white rounded-lg py-1 px-2 cursor-pointer focus:ring-1 focus:ring-blue-500 mt-0.5">
                            <option v-for="(c, k) in statusConfig" :key="k" :value="k">{{ c.label }}</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="openUnitDetail(selectedMapUnit)" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-350 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all border border-slate-700">
                            👁️ Detail
                        </button>
                        <button @click="openKprSimulator(selectedMapUnit)" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all border border-slate-700">
                            🧮 KPR
                        </button>
                        <Link v-if="selectedMapUnit.status === 'available'" :href="`/bookings/create?unit_id=${selectedMapUnit.id}`"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-blue-500/20 text-center w-full whitespace-nowrap">
                            Pesan Unit
                        </Link>
                        <span v-else class="text-[10px] font-black uppercase text-slate-400 text-center w-full whitespace-nowrap">Tidak Tersedia</span>
                    </div>
                </div>
                <button @click="selectedMapUnit = null" class="text-slate-400 hover:text-white font-black text-lg leading-none shrink-0">&times;</button>
            </div>
            
            <div v-if="!units.data.length" class="text-center py-16 bg-white rounded-3xl border border-slate-100 shadow-sm">
                <div class="text-4xl mb-3">🗺️</div>
                <p class="text-sm font-bold text-slate-500">Belum ada unit untuk ditampilkan di peta.</p>
            </div>
        </div>

        <!-- PAGINATION -->
        <div v-if="units.links?.length > 3" class="flex justify-center gap-1 mt-8">
            <a v-for="link in units.links" :key="link.label" :href="link.url || '#'" @click.prevent="link.url && router.get(link.url, {}, { preserveState: true })"
                :class="[link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-slate-600 hover:bg-slate-50', !link.url ? 'opacity-40 pointer-events-none' : '']"
                class="px-3.5 py-2 text-xs font-bold rounded-lg border border-slate-200 transition-all"
                v-html="link.label" />
        </div>

        <!-- ADD UNIT MODAL -->
        <teleport to="body">
            <div v-if="showAddModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showAddModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="sticky top-0 bg-white px-6 py-4 border-b border-slate-100 rounded-t-2xl z-10">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="text-lg font-black text-slate-900">Tambah Unit</h2>
                            <button @click="showAddModal = false" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-400">&times;</button>
                        </div>
                        <div class="flex bg-slate-100 rounded-xl p-0.5">
                            <button @click="addMode = 'single'" :class="addMode === 'single' ? 'bg-white shadow-sm' : ''" class="flex-1 py-1.5 text-xs font-bold rounded-lg transition-all">Satu Unit</button>
                            <button @click="addMode = 'bulk'" :class="addMode === 'bulk' ? 'bg-white shadow-sm' : ''" class="flex-1 py-1.5 text-xs font-bold rounded-lg transition-all">Bulk Generate</button>
                        </div>
                    </div>

                    <!-- SINGLE MODE -->
                    <form v-if="addMode === 'single'" @submit.prevent="submitUnit" class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Proyek <span class="text-rose-500">*</span></label>
                                <select v-model="addForm.project_id" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm cursor-pointer">
                                    <option value="">Pilih</option>
                                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Tipe <span class="text-rose-500">*</span></label>
                                <select v-model="addForm.unit_type_id" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm cursor-pointer">
                                    <option value="">Pilih</option>
                                    <option v-for="t in unitTypes.filter(t => !addForm.project_id || t.project_id == addForm.project_id)" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Blok</label>
                                <input v-model="addForm.block" type="text" placeholder="A" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm uppercase" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Nomor <span class="text-rose-500">*</span></label>
                                <input v-model="addForm.number" type="text" placeholder="01" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Arah Hadap</label>
                                <input v-model="addForm.facing_direction" type="text" placeholder="Timur" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Harga Final</label>
                                <input v-model="addForm.final_price" type="number" placeholder="500000000" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showAddModal = false" class="px-5 py-2.5 text-sm font-bold text-slate-600 bg-slate-100 rounded-xl">Batal</button>
                            <button type="submit" :disabled="addForm.processing" class="px-6 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl shadow-lg hover:bg-blue-700 transition-all disabled:opacity-50">Simpan</button>
                        </div>
                    </form>

                    <!-- BULK MODE -->
                    <form v-if="addMode === 'bulk'" @submit.prevent="submitBulk" class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Proyek <span class="text-rose-500">*</span></label>
                                <select v-model="bulkForm.project_id" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm cursor-pointer">
                                    <option value="">Pilih</option>
                                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Tipe <span class="text-rose-500">*</span></label>
                                <select v-model="bulkForm.unit_type_id" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm cursor-pointer">
                                    <option value="">Pilih</option>
                                    <option v-for="t in unitTypes.filter(t => !bulkForm.project_id || t.project_id == bulkForm.project_id)" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Blok <span class="text-rose-500">*</span></label>
                                <input v-model="bulkForm.block" type="text" placeholder="D" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm uppercase" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Arah Hadap</label>
                                <input v-model="bulkForm.facing_direction" type="text" placeholder="Selatan" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Nomor Mulai <span class="text-rose-500">*</span></label>
                                <input v-model.number="bulkForm.start_number" type="number" min="1" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Nomor Akhir <span class="text-rose-500">*</span></label>
                                <input v-model.number="bulkForm.end_number" type="number" min="1" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm" />
                            </div>
                        </div>
                        <div class="p-3 bg-blue-50 rounded-xl text-xs text-blue-700 font-medium">
                            Akan membuat <strong>{{ Math.max(0, (bulkForm.end_number || 0) - (bulkForm.start_number || 0) + 1) }}</strong> unit:
                            {{ bulkForm.block }}{{ String(bulkForm.start_number).padStart(2, '0') }} s/d {{ bulkForm.block }}{{ String(bulkForm.end_number).padStart(2, '0') }}
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showAddModal = false" class="px-5 py-2.5 text-sm font-bold text-slate-600 bg-slate-100 rounded-xl">Batal</button>
                            <button type="submit" :disabled="bulkForm.processing" class="px-6 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl shadow-lg hover:bg-blue-700 transition-all disabled:opacity-50">Generate Unit</button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <!-- DRAWER DETAIL UNIT (Tab: Progress, Legal, Booking) -->
        <teleport to="body">
            <div v-if="selectedUnitDetail" class="fixed inset-0 z-[100] flex items-center justify-end">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm shadow-2xl" @click="selectedUnitDetail = null"></div>
                <div class="relative bg-white h-screen w-full max-w-xl shadow-2xl flex flex-col z-10 animate-in slide-in-from-right duration-350">
                    
                    <!-- Header -->
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between shrink-0 bg-[#0f172a] text-white">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center text-lg">🏠</div>
                            <div>
                                <h2 class="text-sm font-black uppercase tracking-wider">Unit {{ selectedUnitDetail.block }}{{ selectedUnitDetail.number }}</h2>
                                <p class="text-[10px] text-slate-400 font-bold mt-0.5">{{ selectedUnitDetail.project?.name }} • Tipe {{ selectedUnitDetail.unit_type?.name }}</p>
                            </div>
                        </div>
                        <button @click="selectedUnitDetail = null" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/10 text-slate-400 hover:text-white font-bold">&times;</button>
                    </div>

                    <!-- Info Ribbon -->
                    <div class="px-6 py-3 bg-slate-50 border-b border-slate-100 flex items-center justify-between shrink-0 text-xs">
                        <div class="flex items-center gap-2">
                            <span :class="statusConfig[selectedUnitDetail.status]?.color" class="w-2.5 h-2.5 rounded-full inline-block"></span>
                            <span class="font-black text-slate-700 uppercase">{{ statusConfig[selectedUnitDetail.status]?.label }}</span>
                        </div>
                        <div class="font-black text-blue-600 text-sm">
                            {{ formatPrice(selectedUnitDetail.final_price) }}
                        </div>
                    </div>

                    <!-- Tab Buttons -->
                    <div class="flex border-b border-slate-100 px-4 shrink-0 bg-slate-50">
                        <button @click="detailTab = 'progress'" :class="detailTab === 'progress' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 font-bold'"
                            class="px-4 py-3 border-b-2 text-[10px] uppercase tracking-wider transition-all flex-1 text-center">🏗️ Progress Fisik</button>
                        <button @click="detailTab = 'legal'" :class="detailTab === 'legal' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 font-bold'"
                            class="px-4 py-3 border-b-2 text-[10px] uppercase tracking-wider transition-all flex-1 text-center">🧾 Legal & Sertifikat</button>
                        <button @click="detailTab = 'booking'" :class="detailTab === 'booking' ? 'border-blue-600 text-blue-600 font-black' : 'border-transparent text-slate-500 font-bold'"
                            class="px-4 py-3 border-b-2 text-[10px] uppercase tracking-wider transition-all flex-1 text-center">👤 Transaksi</button>
                    </div>

                    <!-- Scrollable Tab Content -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">

                        <!-- ══ PROGRESS TAB ══ -->
                        <div v-if="detailTab === 'progress'" class="space-y-6">
                            <!-- Current progress circle/badge -->
                            <div class="bg-gradient-to-br from-slate-900 to-slate-800 rounded-3xl p-6 text-white shadow-xl flex items-center justify-between">
                                <div>
                                    <h3 class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Progress Pembangunan</h3>
                                    <p class="text-xs text-slate-300 font-medium mt-1">{{ selectedUnitDetail.latest_progress?.description || 'Belum ada catatan progress lapangan' }}</p>
                                </div>
                                <div class="w-16 h-16 rounded-full border-4 border-blue-500/25 flex items-center justify-center text-sm font-black text-blue-400 bg-white/5">
                                    {{ selectedUnitDetail.latest_progress?.progress_percentage || 0 }}%
                                </div>
                            </div>

                            <!-- Form Update Progress (Admin/Staff) -->
                            <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 space-y-4">
                                <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider">👷 Rekam Progress Fisik Baru</h4>
                                <form @submit.prevent="submitProgressUpdate" class="space-y-3.5">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Progress (%) *</label>
                                            <input v-model.number="progressForm.progress_percentage" type="number" min="0" max="100" required class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500" />
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Tanggal *</label>
                                            <input v-model="progressForm.recorded_date" type="date" required class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500" />
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Deskripsi Pekerjaan *</label>
                                        <input v-model="progressForm.description" type="text" placeholder="Contoh: Pengecoran tiang pancang, pasang atap..." required class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500" />
                                    </div>
                                    <div>
                                        <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Catatan Tambahan</label>
                                        <textarea v-model="progressForm.notes" placeholder="Opsional" rows="2" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500"></textarea>
                                    </div>
                                    <button type="submit" :disabled="progressForm.processing" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-md shadow-blue-500/10">
                                        {{ progressForm.processing ? 'Menyimpan...' : 'Simpan Progress Baru' }}
                                    </button>
                                </form>
                            </div>

                            <!-- History Timeline -->
                            <div class="space-y-4">
                                <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider">⏳ Riwayat Pembangunan</h4>
                                <div v-if="selectedUnitDetail.progress_history?.length" class="relative pl-6 border-l-2 border-slate-100 space-y-5">
                                    <div v-for="log in selectedUnitDetail.progress_history" :key="log.id" class="relative">
                                        <span class="absolute -left-[31px] top-0 w-3 h-3 rounded-full bg-blue-500 border-2 border-white"></span>
                                        <p class="text-xs font-black text-slate-800">{{ log.description }} ({{ log.progress_percentage }}%)</p>
                                        <p class="text-[9px] text-slate-400 font-bold mt-0.5">{{ new Date(log.recorded_date).toLocaleDateString('id-ID', { dateStyle: 'medium' }) }} • Oleh: {{ log.recorded_by?.name || 'Sistem' }}</p>
                                        <p v-if="log.notes" class="text-[10px] text-slate-500 italic mt-1 bg-slate-50 p-2 rounded-lg border border-slate-100">{{ log.notes }}</p>
                                    </div>
                                </div>
                                <div v-else class="text-center py-6 text-slate-400 italic text-xs font-bold bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                                    Belum ada histori pembangunan fisik.
                                </div>
                            </div>
                        </div>

                        <!-- ══ LEGAL TAB ══ -->
                        <div v-else-if="detailTab === 'legal'" class="space-y-5">
                            <form @submit.prevent="submitLegalUpdate" class="space-y-4">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Status Sertifikat</label>
                                    <select v-model="legalForm.certificate_status" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500">
                                        <option v-for="(label, key) in certificateLabels" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Nomor Sertifikat (SHM/SHGB)</label>
                                    <input v-model="legalForm.certificate_number" type="text" placeholder="Masukkan nomor sertifikat" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Nomor PBG / IMB</label>
                                    <input v-model="legalForm.imb_number" type="text" placeholder="Masukkan nomor IMB / PBG" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Nomor PBB (Pajak Bumi Bangunan)</label>
                                    <input v-model="legalForm.pbb_number" type="text" placeholder="Masukkan nomor PBB unit" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Catatan Hukum / Notaris</label>
                                    <textarea v-model="legalForm.legal_notes" placeholder="Catatan tambahan mengenai berkas sertifikat, PBB, atau IMB..." rows="3" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500"></textarea>
                                </div>
                                <button type="submit" :disabled="legalForm.processing" class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-md">
                                    {{ legalForm.processing ? 'Menyimpan...' : 'Simpan Pembaruan Dokumen Legal' }}
                                </button>
                            </form>
                        </div>

                        <!-- ══ TRANSACTION TAB ══ -->
                        <div v-else-if="detailTab === 'booking'" class="space-y-6">
                            <div v-if="selectedUnitDetail.booking" class="space-y-6">
                                <!-- Buyer profile summary -->
                                <div class="bg-blue-50 rounded-2xl p-5 border border-blue-100 space-y-3">
                                    <h4 class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Informasi Pembeli</h4>
                                    <div>
                                        <p class="text-sm font-black text-slate-900">{{ selectedUnitDetail.booking.lead?.name }}</p>
                                        <p class="text-xs text-slate-500 mt-0.5">{{ selectedUnitDetail.booking.lead?.phone || '-' }} • {{ selectedUnitDetail.booking.lead?.email || '-' }}</p>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 text-xs pt-2 border-t border-blue-100">
                                        <div>
                                            <p class="text-[9px] font-bold text-slate-400 uppercase">Skema Bayar</p>
                                            <p class="font-black text-slate-800 capitalize mt-0.5">{{ selectedUnitDetail.booking.payment_scheme }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[9px] font-bold text-slate-400 uppercase">Nilai Transaksi</p>
                                            <p class="font-black text-blue-600 mt-0.5">{{ formatCurrency(selectedUnitDetail.booking.deal_price) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <Link :href="`/bookings/${selectedUnitDetail.booking.id}`" class="flex-1 py-2 bg-slate-950 hover:bg-slate-800 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all text-center">
                                        👁️ Detail Booking
                                    </Link>
                                    <button @click="openKprSimulator(selectedUnitDetail)" class="flex-1 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all text-center">
                                        🧮 KPR Simulator
                                    </button>
                                </div>
                            </div>
                            <div v-else class="text-center py-16 bg-slate-50 rounded-3xl border border-dashed border-slate-200 space-y-4 p-6">
                                <div class="text-4xl">🔑</div>
                                <div>
                                    <p class="text-xs font-black text-slate-500 uppercase tracking-wider">Unit Belum Terjual / Booking</p>
                                    <p class="text-[10px] text-slate-400 mt-1">Unit ini saat ini berstatus bebas dan siap dipesan konsumen baru.</p>
                                </div>
                                <div class="pt-2">
                                    <Link :href="`/bookings/create?unit_id=${selectedUnitDetail.id}`" class="inline-block px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-blue-500/10">
                                        Pesan / Booking Unit Ini
                                    </Link>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </teleport>

        <KprCalculatorModal :show="showKprModal" :initialPrice="kprPrice" :unitCode="kprUnitCode" @close="showKprModal = false" />
    </CrmLayout>
</template>
