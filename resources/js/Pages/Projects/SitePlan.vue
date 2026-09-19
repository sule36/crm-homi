<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InteractiveMap from '@/Components/SitePlan/InteractiveMap.vue';
import PriceListTable from '@/Components/SitePlan/PriceListTable.vue';
import UnitDetailModal from '@/Components/SitePlan/UnitDetailModal.vue';
import BulkUpdateModal from '@/Components/SitePlan/BulkUpdateModal.vue';

const props = defineProps({
    project: Object,
    projects: Array,
    stats: Object,
    units: Array,
    unitsByBlock: Object,
    filters: Object,
    isInternal: Boolean,
    unitTypes: Array,
});

const currentViewMode = ref('siteplan'); // 'siteplan' | 'pricelist'

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const typeFilter = ref(props.filters?.unit_type_id || '');
const blockFilter = ref(props.filters?.block || '');
const minPrice = ref(props.filters?.min_price || '');
const maxPrice = ref(props.filters?.max_price || '');

let filterTimeout;
watch([search, statusFilter, typeFilter, blockFilter, minPrice, maxPrice], () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => applyFilters(), 400);
});

function applyFilters() {
    router.get(`/projects/${props.project.id}/site-plan`, {
        search: search.value,
        status: statusFilter.value,
        unit_type_id: typeFilter.value,
        block: blockFilter.value,
        min_price: minPrice.value,
        max_price: maxPrice.value,
    }, { preserveState: true, replace: true });
}

function resetFilters() {
    search.value = '';
    statusFilter.value = '';
    typeFilter.value = '';
    blockFilter.value = '';
    minPrice.value = '';
    maxPrice.value = '';
    applyFilters();
}

function handleProjectChange(e) {
    const pId = e.target.value;
    if (pId) {
        router.get(`/projects/${pId}/site-plan`);
    }
}

// Modal States
const selectedUnit = ref(null);
const showDetailModal = ref(false);
const showBulkModal = ref(false);
const bulkUnitIds = ref([]);

function openUnitDetail(unit) {
    selectedUnit.value = unit;
    showDetailModal.value = true;
}

function openBulkUpdate(unitIds) {
    bulkUnitIds.value = unitIds;
    showBulkModal.value = true;
}
</script>

<template>
    <Head :title="`Site Plan & Price List - ${project.name}`" />
    <CrmLayout>
        <template #breadcrumb>
            <span class="text-slate-400">Proyek</span> / {{ project.name }} / Interactive Site Plan
        </template>

        <!-- TOP BAR: PROJECT HEADER & SWITCHER -->
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 mb-8">
            <div>
                <div class="flex items-center gap-3 mb-1.5">
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ project.name }}</h1>
                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-extrabold uppercase tracking-wider">
                        Master Plan CRM
                    </span>
                </div>
                <p class="text-xs text-slate-500 font-medium flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                    {{ project.location }} — {{ project.address }}
                </p>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <!-- PROJECT SWITCHER DROPDOWN -->
                <div class="flex items-center gap-2 bg-white p-1.5 pl-3 rounded-2xl border border-slate-200/80 shadow-sm">
                    <span class="text-xs font-bold text-slate-400 uppercase">Ganti Proyek:</span>
                    <select :value="project.id" @change="handleProjectChange" class="bg-slate-50 border-none rounded-xl text-xs font-black text-slate-900 focus:ring-2 focus:ring-blue-600 pr-8">
                        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }} ({{ p.code }})</option>
                    </select>
                </div>

                <Link :href="`/projects/${project.id}`" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-2xl text-xs font-black uppercase tracking-wider transition-colors">
                    Kembali ke Detail
                </Link>
            </div>
        </div>

        <!-- 📊 ALONICA HILLS SUMMARY DASHBOARD BAR -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-3xl border border-slate-200/80 p-5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Unit</span>
                    <div class="w-2.5 h-2.5 rounded-full bg-slate-400"></div>
                </div>
                <p class="text-3xl font-black text-slate-900 tracking-tight">{{ stats.total }}</p>
                <p class="text-[11px] text-slate-400 font-bold mt-1">100% Total Stok</p>
            </div>

            <div class="bg-white rounded-3xl border border-emerald-100 p-5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-black uppercase tracking-widest text-emerald-600">Available</span>
                    <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></div>
                </div>
                <p class="text-3xl font-black text-emerald-600 tracking-tight">{{ stats.available }}</p>
                <p class="text-[11px] text-emerald-700 font-bold mt-1">Siap Dipasarkan</p>
            </div>

            <div class="bg-white rounded-3xl border border-amber-100 p-5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-black uppercase tracking-widest text-amber-600">Reserved</span>
                    <div class="w-2.5 h-2.5 rounded-full bg-amber-500"></div>
                </div>
                <p class="text-3xl font-black text-amber-600 tracking-tight">{{ stats.reserved }}</p>
                <p class="text-[11px] text-amber-700 font-bold mt-1">Proses Reservasi</p>
            </div>

            <div class="bg-white rounded-3xl border border-indigo-100 p-5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-black uppercase tracking-widest text-indigo-600">Booked</span>
                    <div class="w-2.5 h-2.5 rounded-full bg-indigo-600"></div>
                </div>
                <p class="text-3xl font-black text-indigo-600 tracking-tight">{{ stats.booked }}</p>
                <p class="text-[11px] text-indigo-700 font-bold mt-1">Booking Fee Masuk</p>
            </div>

            <div class="bg-white rounded-3xl border border-slate-200/80 p-5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Sold Out</span>
                    <div class="w-2.5 h-2.5 rounded-full bg-slate-700"></div>
                </div>
                <p class="text-3xl font-black text-slate-800 tracking-tight">{{ stats.sold }}</p>
                <p class="text-[11px] text-slate-500 font-bold mt-1">Terjual Final</p>
            </div>

            <div class="bg-white rounded-3xl border border-rose-100 p-5 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-black uppercase tracking-widest text-rose-600">Hold</span>
                    <div class="w-2.5 h-2.5 rounded-full bg-rose-500"></div>
                </div>
                <p class="text-3xl font-black text-rose-600 tracking-tight">{{ stats.hold }}</p>
                <p class="text-[11px] text-rose-700 font-bold mt-1">Ditahan Management</p>
            </div>
        </div>

        <!-- FILTER TOOLBAR & VIEW MODE SWITCHER -->
        <div class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-sm mb-8 space-y-4">
            <div class="flex flex-col lg:flex-row items-center justify-between gap-4">
                <!-- VIEW SWITCHER TABS -->
                <div class="flex items-center gap-1.5 bg-slate-100 p-1.5 rounded-2xl border border-slate-200 w-full lg:w-auto">
                    <button
                        @click="currentViewMode = 'siteplan'"
                        :class="[
                            'flex-1 lg:flex-none px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-2',
                            currentViewMode === 'siteplan' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-600 hover:text-slate-900'
                        ]"
                    >
                        🗺️ Interactive Site Plan
                    </button>
                    <button
                        @click="currentViewMode = 'pricelist'"
                        :class="[
                            'flex-1 lg:flex-none px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-2',
                            currentViewMode === 'pricelist' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-600 hover:text-slate-900'
                        ]"
                    >
                        📋 Dynamic Price List
                    </button>
                </div>

                <!-- SEARCH INPUT -->
                <div class="relative w-full lg:w-80">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Cari Blok, No Unit, atau Promo..."
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-600"
                    />
                    <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            <!-- FILTERS ROW -->
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3 pt-3 border-t border-slate-100">
                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Filter Status</label>
                    <select v-model="statusFilter" class="w-full px-3 py-2 bg-slate-50 border-none rounded-xl text-xs font-bold text-slate-900 focus:ring-2 focus:ring-blue-600">
                        <option value="">Semua Status</option>
                        <option value="available">Available</option>
                        <option value="reserved">Reserved</option>
                        <option value="booked">Booked</option>
                        <option value="sold">Sold Out</option>
                        <option value="hold">Hold</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Filter Tipe Unit</label>
                    <select v-model="typeFilter" class="w-full px-3 py-2 bg-slate-50 border-none rounded-xl text-xs font-bold text-slate-900 focus:ring-2 focus:ring-blue-600">
                        <option value="">Semua Tipe</option>
                        <option v-for="t in unitTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Filter Blok</label>
                    <select v-model="blockFilter" class="w-full px-3 py-2 bg-slate-50 border-none rounded-xl text-xs font-bold text-slate-900 focus:ring-2 focus:ring-blue-600">
                        <option value="">Semua Blok</option>
                        <option v-for="b in Object.keys(unitsByBlock || {}).sort()" :key="b" :value="b">Blok {{ b }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Min Harga (Rp)</label>
                    <input v-model="minPrice" type="number" placeholder="Min" class="w-full px-3 py-2 bg-slate-50 border-none rounded-xl text-xs font-bold text-slate-900 focus:ring-2 focus:ring-blue-600" />
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Max Harga (Rp)</label>
                    <input v-model="maxPrice" type="number" placeholder="Max" class="w-full px-3 py-2 bg-slate-50 border-none rounded-xl text-xs font-bold text-slate-900 focus:ring-2 focus:ring-blue-600" />
                </div>

                <div class="flex items-end">
                    <button @click="resetFilters" class="w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-black uppercase tracking-wider transition-colors">
                        Reset Filter
                    </button>
                </div>
            </div>
        </div>

        <!-- MAIN VIEW COMPONENT CONTENT -->
        <div>
            <!-- INTERACTIVE MAP VIEW -->
            <InteractiveMap
                v-if="currentViewMode === 'siteplan'"
                :project="project"
                :units="units"
                :unitsByBlock="unitsByBlock"
                :isInternal="isInternal"
                @select-unit="openUnitDetail"
            />

            <!-- DYNAMIC PRICE LIST VIEW -->
            <PriceListTable
                v-else
                :project="project"
                :units="units"
                :unitTypes="unitTypes"
                :isInternal="isInternal"
                :filters="filters"
                @select-unit="openUnitDetail"
                @open-bulk-update="openBulkUpdate"
            />
        </div>

        <!-- MODALS -->
        <UnitDetailModal
            v-if="showDetailModal"
            :unit="selectedUnit"
            :isInternal="isInternal"
            @close="showDetailModal = false"
        />

        <BulkUpdateModal
            v-if="showBulkModal"
            :unitIds="bulkUnitIds"
            @close="showBulkModal = false"
        />
    </CrmLayout>
</template>
