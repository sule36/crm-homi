<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    project: Object,
    units: Array,
    unitsByBlock: Object,
    isInternal: Boolean,
});

const emit = defineEmits(['select-unit', 'open-mapper']);

const zoom = ref(1);
const panX = ref(0);
const panY = ref(0);
const activeFilterBlock = ref('ALL');

const statusBadges = {
    available: { label: 'Available', color: 'bg-emerald-500 text-white', ring: 'ring-emerald-500/30', border: 'border-emerald-500', light: 'bg-emerald-50 text-emerald-800 border-emerald-200' },
    reserved: { label: 'Reserved', color: 'bg-amber-500 text-white', ring: 'ring-amber-500/30', border: 'border-amber-500', light: 'bg-amber-50 text-amber-800 border-amber-200' },
    booked: { label: 'Booked', color: 'bg-indigo-600 text-white', ring: 'ring-indigo-600/30', border: 'border-indigo-600', light: 'bg-indigo-50 text-indigo-800 border-indigo-200' },
    sold: { label: 'Sold Out', color: 'bg-slate-700 text-white', ring: 'ring-slate-700/30', border: 'border-slate-700', light: 'bg-slate-100 text-slate-800 border-slate-300' },
    hold: { label: 'Hold', color: 'bg-rose-500 text-white', ring: 'ring-rose-500/30', border: 'border-rose-500', light: 'bg-rose-50 text-rose-800 border-rose-200' },
};

const blockNames = computed(() => {
    if (!props.unitsByBlock) return [];
    return ['ALL', ...Object.keys(props.unitsByBlock).sort()];
});

const filteredBlockUnits = computed(() => {
    if (activeFilterBlock.value === 'ALL') return props.unitsByBlock;
    return { [activeFilterBlock.value]: props.unitsByBlock[activeFilterBlock.value] || [] };
});

function formatPrice(p) {
    if (!p) return '-';
    if (p >= 1000000000) return `Rp ${(p / 1000000000).toFixed(2)}M`;
    return `Rp ${(p / 1000000).toFixed(0)}Jt`;
}

function handleZoomIn() { if (zoom.value < 2.5) zoom.value += 0.2; }
function handleZoomOut() { if (zoom.value > 0.6) zoom.value -= 0.2; }
function handleResetZoom() { zoom.value = 1; panX.value = 0; panY.value = 0; }
</script>

<template>
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
        <!-- MAP TOOLBAR -->
        <div class="px-6 py-4 bg-slate-900 text-white flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></div>
                <div>
                    <h3 class="text-sm font-black uppercase tracking-wider">Interactive Site Plan</h3>
                    <p class="text-[11px] text-slate-400">Klik pada lot unit untuk melihat detail & status real-time</p>
                </div>
            </div>

            <!-- BLOCK FILTER TABS -->
            <div class="flex items-center gap-1.5 bg-slate-800/90 p-1 rounded-xl border border-slate-700/50">
                <button
                    v-for="b in blockNames"
                    :key="b"
                    @click="activeFilterBlock = b"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-[11px] font-extrabold uppercase transition-all',
                        activeFilterBlock === b ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white'
                    ]"
                >
                    {{ b === 'ALL' ? 'Semua Blok' : `Blok ${b}` }}
                </button>
            </div>

            <!-- ZOOM CONTROLS -->
            <div class="flex items-center gap-2">
                <button @click="handleZoomOut" class="p-2 bg-slate-800 hover:bg-slate-700 rounded-xl text-slate-300 transition-colors" title="Zoom Out">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                </button>
                <span class="text-xs font-mono font-bold text-slate-400 w-12 text-center">{{ Math.round(zoom * 100) }}%</span>
                <button @click="handleZoomIn" class="p-2 bg-slate-800 hover:bg-slate-700 rounded-xl text-slate-300 transition-colors" title="Zoom In">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </button>
                <button @click="handleResetZoom" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 rounded-xl text-[11px] font-bold text-slate-300 transition-colors">
                    Reset
                </button>
                <button v-if="isInternal" @click="$emit('open-mapper')" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-[11px] font-black uppercase tracking-wider transition-all shadow-md">
                    ⚙️ Upload/Edit Map
                </button>
            </div>
        </div>

        <!-- SITE PLAN VIEW CONTAINER -->
        <div class="p-6 bg-slate-50 min-h-[520px] overflow-auto relative select-none">
            <!-- IMAGE SITE PLAN OVERLAY (If image uploaded) -->
            <div v-if="project?.siteplan_image" class="relative transition-transform duration-200 origin-top-left inline-block" :style="{ transform: `scale(${zoom})` }">
                <img :src="`/storage/${project.siteplan_image}`" class="rounded-2xl shadow-xl max-w-full" />
                <!-- PINNED UNIT MARKERS -->
                <div
                    v-for="u in units"
                    :key="u.id"
                    @click="$emit('select-unit', u)"
                    :style="{
                        position: 'absolute',
                        left: `${u.siteplan_coordinates?.x || 10}%`,
                        top: `${u.siteplan_coordinates?.y || 10}%`,
                        width: `${u.siteplan_coordinates?.w || 40}px`,
                        height: `${u.siteplan_coordinates?.h || 40}px`,
                    }"
                    :class="[
                        'group cursor-pointer rounded-xl border-2 flex items-center justify-center font-black text-xs transition-all shadow-lg hover:scale-110 z-10',
                        statusBadges[u.status]?.color || 'bg-slate-600 text-white',
                        statusBadges[u.status]?.border || 'border-white'
                    ]"
                >
                    <span>{{ u.block }}{{ u.number }}</span>
                    <!-- TOOLTIP ON HOVER -->
                    <div class="absolute bottom-full mb-2 hidden group-hover:block z-50 w-48 bg-slate-900 text-white rounded-2xl p-3 text-left shadow-2xl pointer-events-none ring-1 ring-white/10">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-black text-sm">{{ u.label }}</span>
                            <span :class="['px-2 py-0.5 rounded text-[9px] font-bold uppercase', statusBadges[u.status]?.color]">
                                {{ u.status }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-300">LT {{ u.unit_type?.land_area }}m² | LB {{ u.unit_type?.building_area }}m²</p>
                        <p class="text-xs font-black text-emerald-400 mt-1">{{ formatPrice(u.final_price) }}</p>
                        <p v-if="u.promo" class="text-[10px] text-amber-300 font-bold mt-0.5 truncate">🎁 {{ u.promo }}</p>
                    </div>
                </div>
            </div>

            <!-- VISUAL LOT GRID SITE PLAN (Clean responsive layout) -->
            <div v-else class="space-y-8 transition-transform duration-200 origin-top-left" :style="{ transform: `scale(${zoom})` }">
                <div v-for="(bUnits, bName) in filteredBlockUnits" :key="bName" class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm">
                    <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center font-black text-sm shadow-md">
                                {{ bName }}
                            </div>
                            <div>
                                <h4 class="text-base font-black text-slate-900 uppercase tracking-tight">Kavling Cluster Blok {{ bName }}</h4>
                                <p class="text-xs text-slate-500 font-medium">Total {{ bUnits.length }} Kavling di Blok {{ bName }}</p>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-extrabold">
                            Available: {{ bUnits.filter(u => u.status === 'available').length }} Unit
                        </span>
                    </div>

                    <!-- GRID OF LOT CARDS -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-6 gap-3.5">
                        <div
                            v-for="u in bUnits"
                            :key="u.id"
                            @click="$emit('select-unit', u)"
                            :class="[
                                'group relative p-3.5 rounded-2xl border-2 cursor-pointer transition-all duration-200 hover:-translate-y-1 hover:shadow-xl',
                                statusBadges[u.status]?.light || 'bg-white border-slate-200',
                            ]"
                        >
                            <!-- HEADER LOT NUMBER & STATUS -->
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-black tracking-tight text-slate-900">
                                    {{ u.block }}{{ u.number }}
                                </span>
                                <span :class="['px-2 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-wider shadow-sm', statusBadges[u.status]?.color]">
                                    {{ statusBadges[u.status]?.label || u.status }}
                                </span>
                            </div>

                            <!-- SPECS MINI -->
                            <div class="space-y-0.5 text-[11px] text-slate-500 font-medium mb-2.5">
                                <p class="truncate">{{ u.unit_type?.name || 'Tipe Standard' }}</p>
                                <p class="text-[10px] font-bold text-slate-600">LT {{ u.unit_type?.land_area || 0 }}m² • LB {{ u.unit_type?.building_area || 0 }}m²</p>
                            </div>

                            <!-- PRICE -->
                            <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between">
                                <span class="text-xs font-black text-slate-900">
                                    {{ formatPrice(u.final_price) }}
                                </span>
                                <span v-if="u.promo" class="w-2 h-2 rounded-full bg-amber-500 animate-ping" title="Ada Promo"></span>
                            </div>

                            <!-- HOVER TOOLTIP FLOATING -->
                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-50 w-52 bg-slate-900 text-white rounded-2xl p-3 text-left shadow-2xl pointer-events-none ring-1 ring-white/10">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-black text-sm">{{ u.label }}</span>
                                    <span :class="['px-2 py-0.5 rounded text-[9px] font-bold uppercase', statusBadges[u.status]?.color]">
                                        {{ u.status }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-300">{{ u.unit_type?.name }}</p>
                                <p class="text-[10px] text-slate-400">LT {{ u.unit_type?.land_area }}m² | LB {{ u.unit_type?.building_area }}m² | {{ u.facing_direction || 'Arah Hadap Standard' }}</p>
                                <p class="text-xs font-black text-emerald-400 mt-1">{{ formatPrice(u.final_price) }}</p>
                                <p v-if="u.promo" class="text-[10px] text-amber-300 font-bold mt-0.5 truncate">🎁 {{ u.promo }}</p>
                                <div class="mt-2 pt-1 border-t border-slate-800 text-[10px] text-blue-400 font-bold flex items-center gap-1">
                                    <span>👉 Klik untuk detail unit</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
