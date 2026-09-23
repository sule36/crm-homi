<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    project: Object,
    units: Array,
});

const emit = defineEmits(['close']);

const imagePreview = ref(props.project?.siteplan_image ? `/storage/${props.project.siteplan_image}` : null);
const selectedFile = ref(null);

const form = useForm({
    siteplan_image: null,
    coordinates: props.units.map(u => ({
        unit_id: u.id,
        block: u.block,
        number: u.number,
        label: u.label,
        x: u.siteplan_coordinates?.x || 10,
        y: u.siteplan_coordinates?.y || 10,
        w: u.siteplan_coordinates?.w || 40,
        h: u.siteplan_coordinates?.h || 40,
    })),
});

const selectedUnitId = ref(props.units[0]?.id || null);

function handleFileChange(e) {
    const file = e.target.files[0];
    if (file) {
        selectedFile.value = file;
        form.siteplan_image = file;
        imagePreview.value = URL.createObjectURL(file);
    }
}

function selectUnitForPlacement(unitId) {
    selectedUnitId.value = unitId;
}

function handleImageClick(e) {
    if (!selectedUnitId.value) return;
    const rect = e.currentTarget.getBoundingClientRect();
    const clickX = ((e.clientX - rect.left) / rect.width) * 100;
    const clickY = ((e.clientY - rect.top) / rect.height) * 100;

    const coord = form.coordinates.find(c => c.unit_id === selectedUnitId.value);
    if (coord) {
        coord.x = parseFloat(clickX.toFixed(2));
        coord.y = parseFloat(clickY.toFixed(2));
    }
}

function submit() {
    form.post(`/projects/${props.project.id}/site-plan/coordinates`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        }
    });
}
</script>

<template>
    <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/80 backdrop-blur-md flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-5xl w-full shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
            <!-- HEADER -->
            <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-blue-600 flex items-center justify-center text-sm font-black">⚙️</div>
                    <div>
                        <h3 class="text-base font-black uppercase tracking-wider">Upload & Atur Posisi Lot Site Plan</h3>
                        <p class="text-xs text-slate-400">Upload gambar masterplan & klik pada gambar untuk menyesuaikan posisi pin unit</p>
                    </div>
                </div>
                <button @click="$emit('close')" class="p-2 text-slate-400 hover:text-white rounded-xl bg-slate-800 hover:bg-slate-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- BODY CONTENT -->
            <div class="p-6 overflow-y-auto space-y-6 flex-1">
                <!-- FILE UPLOAD CONTAINER -->
                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200">
                    <label class="block text-xs font-black uppercase text-slate-700 mb-2">Upload File Gambar Site Plan Baru (.JPG / .PNG)</label>
                    <input
                        type="file"
                        accept="image/*"
                        @change="handleFileChange"
                        class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer"
                    />
                    <p class="text-[11px] text-slate-400 mt-1">Disarankan gambar resolusi tinggi (Landscape 16:9 atau 4:3).</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <!-- UNIT SELECTOR LIST -->
                    <div class="lg:col-span-1 bg-slate-50 p-4 rounded-2xl border border-slate-200 max-h-[450px] overflow-y-auto space-y-2">
                        <span class="block text-[10px] font-black uppercase text-slate-400 mb-2">Pilih Unit Untuk Di-Pin:</span>
                        <div
                            v-for="c in form.coordinates"
                            :key="c.unit_id"
                            @click="selectUnitForPlacement(c.unit_id)"
                            :class="[
                                'p-2.5 rounded-xl border text-xs font-black cursor-pointer transition-all flex items-center justify-between',
                                selectedUnitId === c.unit_id ? 'bg-blue-600 text-white border-blue-600 shadow-md' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'
                            ]"
                        >
                            <span>{{ c.label }}</span>
                            <span class="text-[10px] opacity-75">({{ c.x }}%, {{ c.y }}%)</span>
                        </div>
                    </div>

                    <!-- IMAGE CANVAS PREVIEW -->
                    <div class="lg:col-span-3 bg-slate-900 rounded-2xl p-4 overflow-auto min-h-[450px] relative flex items-center justify-center">
                        <div v-if="imagePreview" class="relative inline-block cursor-crosshair" @click="handleImageClick">
                            <img :src="imagePreview" class="rounded-xl max-w-full block" />

                            <!-- PIN PREVIEWS -->
                            <div
                                v-for="c in form.coordinates"
                                :key="c.unit_id"
                                :style="{
                                    position: 'absolute',
                                    left: `${c.x}%`,
                                    top: `${c.y}%`,
                                }"
                                :class="[
                                    'w-7 h-7 -ml-3.5 -mt-3.5 rounded-lg border-2 flex items-center justify-center text-[10px] font-black shadow-lg transition-transform',
                                    selectedUnitId === c.unit_id ? 'bg-amber-400 text-slate-900 border-white ring-4 ring-amber-400/40 scale-125 z-30' : 'bg-blue-600 text-white border-white z-10'
                                ]"
                                :title="`${c.label} (${c.x}%, ${c.y}%)`"
                            >
                                {{ c.block }}{{ c.number }}
                            </div>
                        </div>

                        <div v-else class="text-center p-8 text-slate-500">
                            <p class="text-sm font-bold">Belum ada gambar site plan.</p>
                            <p class="text-xs mt-1">Silakan upload gambar di atas terlebih dahulu.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                <button @click="$emit('close')" type="button" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-black uppercase">
                    Batal
                </button>
                <button @click="submit" :disabled="form.processing" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black uppercase tracking-wider shadow-lg flex items-center gap-2">
                    <span v-if="form.processing">Menyimpan...</span>
                    <span v-else>💾 Simpan Layout & Gambar</span>
                </button>
            </div>
        </div>
    </div>
</template>
