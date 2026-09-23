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
const mapMode = ref('graphic'); // 'graphic' | 'grid'

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

const siteplanImg = computed(() => {
    if (props.project?.siteplan_image) return props.project.siteplan_image;
    if (props.project?.master_plan_image) return props.project.master_plan_image;
    // Automatic fallback for Alonica Hills or any default siteplan image
    if (props.project?.name?.toLowerCase().includes('alonica') || true) {
        return 'projects/masterplans/alonica_siteplan.jpg';
    }
    return null;
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
                    <p class="text-[11px] text-slate-400">Klik pada lot unit di gambar/grid untuk detail & status real-time</p>
                </div>
            </div>

            <!-- DISPLAY MODE SWITCHER: GRAPHIC VS GRID -->
            <div class="flex items-center gap-1 bg-slate-800 p-1 rounded-xl border border-slate-700/60">
                <button
                    @click="mapMode = 'graphic'"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-black uppercase transition-all flex items-center gap-1.5',
                        mapMode === 'graphic' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white'
                    ]"
                >
                    <span>🗺️ Peta Gambar</span>
                </button>
                <button
                    @click="mapMode = 'grid'"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-xs font-black uppercase transition-all flex items-center gap-1.5',
                        mapMode === 'grid' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-white'
                    ]"
                >
                    <span>🔲 Grid Blok</span>
                </button>
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

            <!-- ZOOM & TOOL CONTROLS -->
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
                    <span>⚙️ Upload / Edit Layout</span>
                </button>
            </div>
        </div>

        <!-- SITE PLAN VIEW CONTAINER -->
        <div class="p-6 bg-slate-950 min-h-[550px] overflow-auto relative select-none flex items-center justify-center">
            <!-- 🗺️ GRAPHIC SITE PLAN IMAGE MODE -->
            <div v-if="mapMode === 'graphic' && imageSrc" class="relative transition-transform duration-200 origin-top-left inline-block my-auto" :style="{ transform: `scale(${zoom})` }">
                <img :src="imageSrc" class="rounded-2xl shadow-2xl max-w-none w-full min-w-[850px] block border border-slate-800" />
                
                <!-- PINNED UNIT MARKERS -->
                <div
                    v-for="u in units"
                    :key="u.id"
                    @click="$emit('select-unit', u)"
                    :style="{
                        position: 'absolute',
                        left: `${u.siteplan_coordinates?.x || 50}%`,
                        top: `${u.siteplan_coordinates?.y || 50}%`,
                        width: `${u.siteplan_coordinates?.w || 36}px`,
                        height: `${u.siteplan_coordinates?.h || 36}px`,
                    }"
                    :class="[
                        'group cursor-pointer -ml-4.5 -mt-4.5 rounded-xl border-2 flex items-center justify-center font-black text-xs transition-all shadow-2xl hover:scale-125 z-20 hover:z-50 ring-2 ring-black/40',
                        statusBadges[u.status]?.color || 'bg-slate-600 text-white',
                        statusBadges[u.status]?.border || 'border-white'
                    ]"
                >
                    <span>{{ u.block }}{{ u.number }}</span>

                    <!-- TOOLTIP ON HOVER -->
                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-50 w-52 bg-slate-900 text-white rounded-2xl p-3.5 text-left shadow-2xl pointer-events-none ring-1 ring-white/20 border border-slate-700">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="font-black text-sm text-blue-400">Unit {{ u.label }}</span>
                            <span :class="['px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider', statusBadges[u.status]?.color]">
                                {{ u.status }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-300 font-medium">{{ u.unit_type?.name }}</p>
                        <p class="text-[10px] text-slate-400">LT {{ u.unit_type?.land_area || 0 }}m² | LB {{ u.unit_type?.building_area || 0 }}m²</p>
                        <p class="text-xs font-black text-emerald-400 mt-1.5">{{ formatPrice(u.final_price) }}</p>
                        <p v-if="u.promo" class="text-[10px] text-amber-300 font-bold mt-1 truncate">🎁 {{ u.promo }}</p>
                        <div class="mt-2 pt-1.5 border-t border-slate-800 text-[10px] text-blue-400 font-bold flex items-center gap-1">
                            <span>👉 Klik untuk detail lengkap</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 🔲 VISUAL LOT GRID SITE PLAN (Fallback & Clean Grid Mode) -->
            <div v-else class="w-full space-y-8 transition-transform duration-200 origin-top-left bg-slate-50 p-6 rounded-2xl" :style="{ transform: `scale(${zoom})` }">
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
