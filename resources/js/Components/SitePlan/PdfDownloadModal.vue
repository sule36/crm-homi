<script setup>
import { ref } from 'vue';

const props = defineProps({
    project: Object,
    isInternal: Boolean,
    filters: Object,
});

const emit = defineEmits(['close']);

const pdfMode = ref('combined'); // 'combined' | 'siteplan' | 'pricelist'
const pdfVersion = ref('customer'); // 'customer' | 'agent' | 'internal'

function triggerDownload() {
    const params = new URLSearchParams({
        mode: pdfMode.value,
        version: pdfVersion.value,
        status: props.filters?.status || '',
        unit_type_id: props.filters?.unit_type_id || '',
        block: props.filters?.block || '',
        min_price: props.filters?.min_price || '',
        max_price: props.filters?.max_price || '',
    });

    const url = `/projects/${props.project.id}/price-list/export-pdf?${params.toString()}`;
    window.open(url, '_blank');
    emit('close');
}
</script>

<template>
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-md animate-in fade-in duration-200">
        <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden">
            <!-- HEADER -->
            <div class="px-8 py-6 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-600 flex items-center justify-center text-white text-lg font-black shadow-lg">
                        📄
                    </div>
                    <div>
                        <h3 class="text-base font-black uppercase tracking-tight">Export PDF Official Terupdate</h3>
                        <p class="text-xs text-slate-400 font-medium">Dokumen real-time otomatis dari database CRM</p>
                    </div>
                </div>
                <button @click="$emit('close')" class="w-8 h-8 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center font-bold text-lg">
                    &times;
                </button>
            </div>

            <!-- FORM BODY -->
            <div class="p-8 space-y-6 text-xs">
                <!-- 1. PILIH DOKUMEN -->
                <div>
                    <label class="block font-black uppercase tracking-wider text-slate-500 mb-2">1. Pilih Format / Tampilan Dokumen</label>
                    <div class="grid grid-cols-1 gap-2.5">
                        <label
                            :class="['p-3.5 rounded-2xl border-2 cursor-pointer transition-all flex items-center justify-between', pdfMode === 'combined' ? 'border-blue-600 bg-blue-50/50' : 'border-slate-200 hover:border-slate-300']"
                        >
                            <div class="flex items-center gap-3">
                                <input type="radio" value="combined" v-model="pdfMode" class="text-blue-600 focus:ring-blue-500" />
                                <div>
                                    <span class="font-black text-slate-900 text-sm block">Site Plan + Price List PDF (Rekomendasi)</span>
                                    <span class="text-[11px] text-slate-500">Dokumen lengkap 2 bagian: Peta visual site plan & tabel harga resmi</span>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 bg-blue-100 text-blue-700 font-bold rounded text-[9px] uppercase">Lengkap</span>
                        </label>

                        <label
                            :class="['p-3.5 rounded-2xl border-2 cursor-pointer transition-all flex items-center justify-between', pdfMode === 'siteplan' ? 'border-blue-600 bg-blue-50/50' : 'border-slate-200 hover:border-slate-300']"
                        >
                            <div class="flex items-center gap-3">
                                <input type="radio" value="siteplan" v-model="pdfMode" class="text-blue-600 focus:ring-blue-500" />
                                <div>
                                    <span class="font-black text-slate-900 text-sm block">Download Site Plan PDF Saja</span>
                                    <span class="text-[11px] text-slate-500">Ringkasan visual status kavling & harga per lot</span>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 font-bold rounded text-[9px] uppercase">Visual</span>
                        </label>

                        <label
                            :class="['p-3.5 rounded-2xl border-2 cursor-pointer transition-all flex items-center justify-between', pdfMode === 'pricelist' ? 'border-blue-600 bg-blue-50/50' : 'border-slate-200 hover:border-slate-300']"
                        >
                            <div class="flex items-center gap-3">
                                <input type="radio" value="pricelist" v-model="pdfMode" class="text-blue-600 focus:ring-blue-500" />
                                <div>
                                    <span class="font-black text-slate-900 text-sm block">Download Price List PDF Saja</span>
                                    <span class="text-[11px] text-slate-500">Tabel rinci spesifikasi, luas tanah/bangunan, & promo</span>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 font-bold rounded text-[9px] uppercase">Tabel</span>
                        </label>
                    </div>
                </div>

                <!-- 2. PILIH VERSI TARGET AUDIENS -->
                <div>
                    <label class="block font-black uppercase tracking-wider text-slate-500 mb-2">2. Pilih Versi Target Audiens PDF</label>
                    <div class="grid grid-cols-3 gap-2.5">
                        <label
                            :class="['p-3 rounded-2xl border-2 cursor-pointer text-center transition-all', pdfVersion === 'customer' ? 'border-blue-600 bg-blue-50 text-blue-900' : 'border-slate-200 text-slate-600 hover:border-slate-300']"
                        >
                            <input type="radio" value="customer" v-model="pdfVersion" class="sr-only" />
                            <span class="text-base block mb-1">👤</span>
                            <span class="font-black text-xs block">Public / Customer</span>
                            <span class="text-[9px] opacity-75 block mt-0.5">Informasi Publik</span>
                        </label>

                        <label
                            :class="['p-3 rounded-2xl border-2 cursor-pointer text-center transition-all', pdfVersion === 'agent' ? 'border-blue-600 bg-blue-50 text-blue-900' : 'border-slate-200 text-slate-600 hover:border-slate-300']"
                        >
                            <input type="radio" value="agent" v-model="pdfVersion" class="sr-only" />
                            <span class="text-base block mb-1">🤝</span>
                            <span class="font-black text-xs block">Agent Partner</span>
                            <span class="text-[9px] opacity-75 block mt-0.5">Plus Catatan Agent</span>
                        </label>

                        <label
                            v-if="isInternal"
                            :class="['p-3 rounded-2xl border-2 cursor-pointer text-center transition-all', pdfVersion === 'internal' ? 'border-amber-500 bg-amber-50 text-amber-900' : 'border-slate-200 text-slate-600 hover:border-slate-300']"
                        >
                            <input type="radio" value="internal" v-model="pdfVersion" class="sr-only" />
                            <span class="text-base block mb-1">🔒</span>
                            <span class="font-black text-xs block">Internal Mgmt</span>
                            <span class="text-[9px] opacity-75 block mt-0.5">Net Price & Notes</span>
                        </label>
                    </div>
                </div>

                <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 text-[11px] text-slate-600 flex items-center gap-2">
                    <span>💡</span>
                    <span>PDF akan mencantumkan timestamp <strong>"Price & availability subject to latest update"</strong> secara resmi.</span>
                </div>

                <!-- FOOTER ACTIONS -->
                <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="$emit('close')" class="px-5 py-3 bg-slate-100 text-slate-600 rounded-xl font-black uppercase tracking-wider hover:bg-slate-200">
                        Batal
                    </button>
                    <button @click="triggerDownload" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-black uppercase tracking-wider shadow-lg shadow-emerald-600/20 flex items-center gap-2">
                        <span>📥 Download PDF Terupdate</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
