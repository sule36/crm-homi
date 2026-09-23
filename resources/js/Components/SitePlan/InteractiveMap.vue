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
const activeFilterBlock = ref('ALL');
const mapMode = ref('graphic'); // 'graphic' | 'grid'
const hoveredUnit = ref(null);

// Status dot colors — matching the reference siteplan image style
const statusDots = {
    available: { label: 'Available', dot: 'bg-transparent', dotBorder: 'border-transparent', ring: '', textColor: 'text-emerald-500', show: false },
    reserved:  { label: 'Reserved',  dot: 'bg-amber-400',   dotBorder: 'border-white',       ring: 'ring-2 ring-amber-400/40',  textColor: 'text-amber-500',   show: true },
    booked:    { label: 'Booked',    dot: 'bg-orange-500',   dotBorder: 'border-white',       ring: 'ring-2 ring-orange-500/40', textColor: 'text-orange-500',  show: true },
    sold:      { label: 'Sold',      dot: 'bg-emerald-500',  dotBorder: 'border-white',       ring: 'ring-2 ring-emerald-500/40', textColor: 'text-emerald-500', show: true },
    hold:      { label: 'Hold',      dot: 'bg-rose-500',     dotBorder: 'border-white',       ring: 'ring-2 ring-rose-500/40',   textColor: 'text-rose-500',    show: true },
};

// Grid mode colors (richer for card display)
const statusCards = {
    available: { label: 'Available', bg: 'bg-blue-50 border-blue-200 hover:bg-blue-100',        badge: 'bg-blue-500 text-white',    text: 'text-blue-700' },
    reserved:  { label: 'Reserved',  bg: 'bg-amber-50 border-amber-200 hover:bg-amber-100',     badge: 'bg-amber-500 text-white',   text: 'text-amber-700' },
    booked:    { label: 'Booked',    bg: 'bg-orange-50 border-orange-200 hover:bg-orange-100',   badge: 'bg-orange-500 text-white',  text: 'text-orange-700' },
    sold:      { label: 'Sold Out',  bg: 'bg-emerald-50 border-emerald-200 hover:bg-emerald-100', badge: 'bg-emerald-600 text-white', text: 'text-emerald-700' },
    hold:      { label: 'Hold',      bg: 'bg-rose-50 border-rose-200 hover:bg-rose-100',        badge: 'bg-rose-500 text-white',    text: 'text-rose-700' },
};

const blockNames = computed(() => {
    if (!props.unitsByBlock) return [];
    return ['ALL', ...Object.keys(props.unitsByBlock).sort()];
});

const filteredBlockUnits = computed(() => {
    if (activeFilterBlock.value === 'ALL') return props.unitsByBlock;
    return { [activeFilterBlock.value]: props.unitsByBlock[activeFilterBlock.value] || [] };
});

// Filter units for graphic mode based on block filter
const filteredUnits = computed(() => {
    if (activeFilterBlock.value === 'ALL') return props.units;
    return props.units.filter(u => u.block === activeFilterBlock.value);
});

const siteplanImg = computed(() => {
    if (props.project?.siteplan_image) return props.project.siteplan_image;
    if (props.project?.master_plan_image) return props.project.master_plan_image;
    return 'projects/masterplans/alonica_siteplan.jpg';
});

const imageSrc = computed(() => {
    if (!siteplanImg.value) return '';
    if (siteplanImg.value.startsWith('http') || siteplanImg.value.startsWith('/')) {
        return siteplanImg.value;
    }
    return `/storage/${siteplanImg.value}`;
});

function formatPrice(p) {
    if (!p) return '-';
    if (p >= 1000000000) return `Rp ${(p / 1000000000).toFixed(2)}M`;
    return `Rp ${(p / 1000000).toFixed(0)}Jt`;
}

function handleZoomIn() { if (zoom.value < 3) zoom.value += 0.15; }
function handleZoomOut() { if (zoom.value > 0.5) zoom.value -= 0.15; }
function handleResetZoom() { zoom.value = 1; }

function getUnitDotPosition(unit) {
    // Place the dot at the top-right corner of the unit kavling area
    const coords = unit.siteplan_coordinates;
    if (!coords) return null;
    return {
        left: `${coords.x + (coords.w || 5) * 0.35}%`,
        top: `${coords.y - (coords.h || 4) * 0.1}%`,
    };
}

function getUnitAreaPosition(unit) {
    // Create a clickable transparent area over the unit's kavling position
    const coords = unit.siteplan_coordinates;
    if (!coords) return null;
    return {
        left: `${coords.x}%`,
        top: `${coords.y}%`,
        width: `${coords.w || 5}%`,
        height: `${coords.h || 4}%`,
    };
}
</script>

<template>
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
        <!-- MAP TOOLBAR -->
        <div class="px-5 py-3.5 bg-slate-900 text-white flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></div>
                <div>
                    <h3 class="text-sm font-black uppercase tracking-wider">Interactive Site Plan</h3>
                    <p class="text-[11px] text-slate-400">Klik kavling untuk detail & status real-time</p>
                </div>
            </div>

            <!-- DISPLAY MODE SWITCHER -->
            <div class="flex items-center gap-1 bg-slate-800 p-1 rounded-xl border border-slate-700/60">
                <button
                    @click="mapMode = 'graphic'"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-black uppercase transition-all flex items-center gap-1.5',
                        mapMode === 'graphic' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white'
                    ]"
                >
                    🗺️ Peta Gambar
                </button>
                <button
                    @click="mapMode = 'grid'"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-black uppercase transition-all flex items-center gap-1.5',
                        mapMode === 'grid' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white'
                    ]"
                >
                    🔲 Grid Blok
                </button>
            </div>

            <!-- BLOCK FILTER TABS -->
            <div class="flex items-center gap-1 bg-slate-800/90 p-1 rounded-xl border border-slate-700/50 flex-wrap">
                <button
                    v-for="b in blockNames"
                    :key="b"
                    @click="activeFilterBlock = b"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-[11px] font-extrabold uppercase transition-all',
                        activeFilterBlock === b ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white'
                    ]"
                >
                    {{ b === 'ALL' ? 'Semua' : `Blok ${b}` }}
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
                <button v-if="isInternal" @click="$emit('open-mapper')" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-[11px] font-black uppercase tracking-wider transition-all shadow-md flex items-center gap-1.5">
                    ⚙️ Edit Layout
                </button>
            </div>
        </div>

        <!-- ==================== -->
        <!-- 🗺️ GRAPHIC MAP MODE  -->
        <!-- ==================== -->
        <div v-if="mapMode === 'graphic'" class="bg-[#0c1b3a] overflow-auto" style="max-height: 75vh;">
            <div
                class="relative mx-auto transition-transform duration-200 origin-top-left inline-block"
                :style="{ transform: `scale(${zoom})`, transformOrigin: 'top left' }"
            >
                <!-- SITE PLAN IMAGE (Fixed Background) -->
                <img
                    v-if="imageSrc"
                    :src="imageSrc"
                    class="block max-w-none select-none pointer-events-none"
                    style="min-width: 900px; width: 100%;"
                    draggable="false"
                    alt="Site Plan"
                />

                <!-- CLICKABLE UNIT AREAS (Invisible, just for interaction) -->
                <div
                    v-for="u in filteredUnits"
                    :key="'area-' + u.id"
                    @click="$emit('select-unit', u)"
                    @mouseenter="hoveredUnit = u"
                    @mouseleave="hoveredUnit = null"
                    :style="{
                        position: 'absolute',
                        ...getUnitAreaPosition(u),
                    }"
                    class="cursor-pointer z-10 rounded-sm transition-all duration-150"
                    :class="hoveredUnit?.id === u.id ? 'bg-white/15 ring-1 ring-white/30' : 'hover:bg-white/10'"
                >
                    <!-- TOOLTIP ON HOVER -->
                    <div
                        v-if="hoveredUnit?.id === u.id"
                        class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 z-[100] w-56 bg-slate-900/95 backdrop-blur-sm text-white rounded-2xl p-3.5 text-left shadow-2xl pointer-events-none ring-1 ring-white/20 border border-slate-700"
                    >
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="font-black text-sm text-blue-400">{{ u.block }}{{ u.number }}</span>
                            <span
                                :class="[
                                    'px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider',
                                    u.status === 'sold' ? 'bg-emerald-500 text-white' :
                                    u.status === 'booked' ? 'bg-orange-500 text-white' :
                                    u.status === 'reserved' ? 'bg-amber-400 text-white' :
                                    u.status === 'hold' ? 'bg-rose-500 text-white' :
                                    'bg-blue-500 text-white'
                                ]"
                            >
                                {{ statusDots[u.status]?.label || u.status }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-300 font-medium">{{ u.unit_type?.name || 'Standard' }}</p>
                        <p class="text-[10px] text-slate-400">LT {{ u.unit_type?.land_area || 0 }}m² • LB {{ u.unit_type?.building_area || 0 }}m²</p>
                        <p class="text-xs font-black text-emerald-400 mt-1.5">{{ formatPrice(u.final_price) }}</p>
                        <p v-if="u.promo" class="text-[10px] text-amber-300 font-bold mt-1 truncate">🎁 {{ u.promo }}</p>
                        <div class="mt-2 pt-1.5 border-t border-slate-700 text-[10px] text-blue-400 font-bold">
                            👆 Klik untuk detail lengkap
                        </div>
                    </div>
                </div>

                <!-- STATUS DOTS (Small colored circles on unit positions) -->
                <div
                    v-for="u in filteredUnits"
                    :key="'dot-' + u.id"
                    v-show="statusDots[u.status]?.show"
                    @click="$emit('select-unit', u)"
                    :style="{
                        position: 'absolute',
                        ...getUnitDotPosition(u),
                    }"
                    :class="[
                        'w-[14px] h-[14px] rounded-full border-2 cursor-pointer z-20 transition-all duration-200 hover:scale-150',
                        statusDots[u.status]?.dot,
                        statusDots[u.status]?.dotBorder,
                        statusDots[u.status]?.ring,
                    ]"
                    :title="`${u.block}${u.number} — ${statusDots[u.status]?.label}`"
                >
                </div>

                <!-- NO IMAGE FALLBACK -->
                <div v-if="!imageSrc" class="flex items-center justify-center min-h-[500px] text-slate-400">
                    <div class="text-center">
                        <p class="text-4xl mb-3">🗺️</p>
                        <p class="text-sm font-black uppercase tracking-wider">Belum ada gambar Site Plan</p>
                        <p class="text-xs mt-1">Upload gambar melalui tombol "Edit Layout" di atas</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== -->
        <!-- 🔲 GRID BLOCK MODE   -->
        <!-- ==================== -->
        <div v-else class="p-6 bg-slate-50 overflow-auto" style="max-height: 75vh;">
            <div
                class="space-y-8 transition-transform duration-200 origin-top-left"
                :style="{ transform: `scale(${zoom})` }"
            >
                <div v-for="(bUnits, bName) in filteredBlockUnits" :key="bName" class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm">
                    <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center font-black text-sm shadow-md">
                                {{ bName }}
                            </div>
                            <div>
                                <h4 class="text-base font-black text-slate-900 uppercase tracking-tight">Kavling Blok {{ bName }}</h4>
                                <p class="text-xs text-slate-500 font-medium">Total {{ bUnits.length }} Kavling</p>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-blue-50 text-blue-600 rounded-full text-xs font-extrabold">
                            Available: {{ bUnits.filter(u => u.status === 'available').length }}
                        </span>
                    </div>

                    <!-- GRID OF UNIT CARDS -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-6 gap-3">
                        <div
                            v-for="u in bUnits"
                            :key="u.id"
                            @click="$emit('select-unit', u)"
                            :class="[
                                'group relative p-3.5 rounded-2xl border-2 cursor-pointer transition-all duration-200 hover:-translate-y-1 hover:shadow-xl',
                                statusCards[u.status]?.bg || 'bg-white border-slate-200',
                            ]"
                        >
                            <!-- HEADER: LOT ID & STATUS BADGE -->
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-black tracking-tight text-slate-900">
                                    {{ u.block }}{{ u.number }}
                                </span>
                                <span :class="['px-2 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-wider shadow-sm', statusCards[u.status]?.badge]">
                                    {{ statusCards[u.status]?.label || u.status }}
                                </span>
                            </div>

                            <!-- SPECS -->
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

                            <!-- HOVER TOOLTIP -->
                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-50 w-52 bg-slate-900 text-white rounded-2xl p-3 text-left shadow-2xl pointer-events-none ring-1 ring-white/10">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-black text-sm">{{ u.block }}{{ u.number }}</span>
                                    <span :class="['px-2 py-0.5 rounded text-[9px] font-bold uppercase', statusCards[u.status]?.badge]">
                                        {{ u.status }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-300">{{ u.unit_type?.name }}</p>
                                <p class="text-[10px] text-slate-400">LT {{ u.unit_type?.land_area }}m² | LB {{ u.unit_type?.building_area }}m² | {{ u.facing_direction || '-' }}</p>
                                <p class="text-xs font-black text-emerald-400 mt-1">{{ formatPrice(u.final_price) }}</p>
                                <p v-if="u.promo" class="text-[10px] text-amber-300 font-bold mt-0.5 truncate">🎁 {{ u.promo }}</p>
                                <div class="mt-2 pt-1 border-t border-slate-800 text-[10px] text-blue-400 font-bold">
                                    👆 Klik untuk detail
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LEGEND BAR -->
        <div class="px-6 py-3 bg-slate-50 border-t border-slate-200/80 flex flex-wrap items-center gap-5">
            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Keterangan:</span>
            <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-emerald-500 border-2 border-white shadow-sm"></span>
                <span class="text-[11px] font-bold text-slate-600">Sold</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-orange-500 border-2 border-white shadow-sm"></span>
                <span class="text-[11px] font-bold text-slate-600">Booked</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-amber-400 border-2 border-white shadow-sm"></span>
                <span class="text-[11px] font-bold text-slate-600">Reserved</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-rose-500 border-2 border-white shadow-sm"></span>
                <span class="text-[11px] font-bold text-slate-600">Hold</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="w-3 h-3 rounded-full bg-transparent border-2 border-dashed border-slate-300"></span>
                <span class="text-[11px] font-bold text-slate-600">Available</span>
            </div>
        </div>
    </div>
</template>
