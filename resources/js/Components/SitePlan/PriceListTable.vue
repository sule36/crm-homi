<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    project: Object,
    units: Array,
    unitTypes: Array,
    isInternal: Boolean,
    filters: Object,
});

const emit = defineEmits(['select-unit', 'open-bulk-update', 'apply-filters']);

const selectedUnitIds = ref([]);
const selectAll = ref(false);

const statusBadges = {
    available: { label: 'Available', color: 'bg-emerald-100 text-emerald-800 border-emerald-300' },
    reserved: { label: 'Reserved', color: 'bg-amber-100 text-amber-800 border-amber-300' },
    booked: { label: 'Booked', color: 'bg-indigo-100 text-indigo-800 border-indigo-300' },
    sold: { label: 'Sold Out', color: 'bg-slate-200 text-slate-800 border-slate-400' },
    hold: { label: 'Hold', color: 'bg-rose-100 text-rose-800 border-rose-300' },
};

function toggleSelectAll() {
    if (selectAll.value) {
        selectedUnitIds.value = props.units.map(u => u.id);
    } else {
        selectedUnitIds.value = [];
    }
}

function handleBulkAction() {
    if (!selectedUnitIds.value.length) return;
    emit('open-bulk-update', selectedUnitIds.value);
}

function formatRupiah(val) {
    if (!val) return 'Rp 0';
    return 'Rp ' + Number(val).toLocaleString('id-ID');
}

function triggerExcelExport() {
    window.location.href = `/projects/${props.project.id}/price-list/export-excel`;
}

function triggerPdfModal() {
    emit('open-pdf-modal');
}
</script>

<template>
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
        <!-- HEADER & ACTIONS BAR -->
        <div class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-black text-slate-900 uppercase tracking-tight">Dynamic Price List Inventory</h3>
                <p class="text-xs text-slate-500">Tabel harga resmi real-time terkoneksi langsung dengan database Site Plan</p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <!-- BULK UPDATE BUTTON -->
                <button
                    v-if="isInternal && selectedUnitIds.length"
                    @click="handleBulkAction"
                    class="px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all shadow-md flex items-center gap-1.5"
                >
                    <span>⚡ Edit {{ selectedUnitIds.length }} Unit</span>
                </button>

                <!-- DOWNLOAD PDF TERUPDATE -->
                <button
                    @click="triggerPdfModal"
                    class="px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all shadow-md flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Download PDF Terupdate
                </button>

                <!-- EXPORT EXCEL -->
                <button
                    @click="triggerExcelExport"
                    class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all shadow-md flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Export Excel / CSV
                </button>
            </div>
        </div>

        <!-- TABLE DATA -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-900 text-white uppercase text-[10px] tracking-wider font-extrabold select-none">
                    <tr>
                        <th v-if="isInternal" class="py-4 px-4 text-center w-10">
                            <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="rounded border-slate-700 bg-slate-800 text-blue-600 focus:ring-blue-500 cursor-pointer" />
                        </th>
                        <th class="py-4 px-4">Kavling / Unit</th>
                        <th class="py-4 px-4">Tipe Unit</th>
                        <th class="py-4 px-4 text-right">LT (m²)</th>
                        <th class="py-4 px-4 text-right">LB (m²)</th>
                        <th class="py-4 px-4 text-center">Spesifikasi</th>
                        <th class="py-4 px-4">Arah Hadap</th>
                        <th class="py-4 px-4 text-right">Harga Jual (Rp)</th>
                        <th class="py-4 px-4">Promo & Program</th>
                        <th class="py-4 px-4 text-center">Status</th>
                        <th class="py-4 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    <tr
                        v-for="u in units"
                        :key="u.id"
                        class="hover:bg-blue-50/50 transition-colors group"
                    >
                        <td v-if="isInternal" class="py-3.5 px-4 text-center">
                            <input type="checkbox" :value="u.id" v-model="selectedUnitIds" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer" />
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="font-black text-slate-900 text-sm block">{{ u.label }}</span>
                            <span class="text-[10px] text-slate-400 font-bold uppercase">Blok {{ u.block }} No {{ u.number }}</span>
                        </td>
                        <td class="py-3.5 px-4 font-bold text-slate-900">
                            {{ u.unit_type?.name || 'Tipe Standard' }}
                        </td>
                        <td class="py-3.5 px-4 text-right font-extrabold text-slate-900">
                            {{ u.unit_type?.land_area || '-' }} m²
                        </td>
                        <td class="py-3.5 px-4 text-right font-extrabold text-slate-900">
                            {{ u.unit_type?.building_area || '-' }} m²
                        </td>
                        <td class="py-3.5 px-4 text-center text-[11px]">
                            <span>{{ u.unit_type?.bedrooms || 0 }} KT / {{ u.unit_type?.bathrooms || 0 }} KM</span>
                            <span class="block text-[10px] text-slate-500 font-bold">{{ u.carport }} Carport</span>
                        </td>
                        <td class="py-3.5 px-4 text-slate-600 font-bold">
                            {{ u.facing_direction || 'Utara' }}
                        </td>
                        <td class="py-3.5 px-4 text-right font-black text-emerald-600 text-sm">
                            {{ formatRupiah(u.final_price) }}
                        </td>
                        <td class="py-3.5 px-4">
                            <span v-if="u.promo" class="px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded text-[10px] font-bold block">
                                🎁 {{ u.promo }}
                            </span>
                            <span v-else class="text-slate-400 text-[11px]">-</span>
                        </td>
                        <td class="py-3.5 px-4 text-center">
                            <span :class="['px-2.5 py-1 rounded-full text-[10px] font-black uppercase border tracking-wider', statusBadges[u.status]?.color]">
                                {{ statusBadges[u.status]?.label || u.status }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 text-center">
                            <button
                                @click="$emit('select-unit', u)"
                                class="px-3 py-1.5 bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white rounded-xl text-[11px] font-black uppercase tracking-wider transition-all"
                            >
                                Detail
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!units.length">
                        <td :colspan="isInternal ? 11 : 10" class="py-12 text-center text-slate-400 font-bold">
                            Tidak ada unit yang sesuai dengan filter pencarian.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
