<script setup>
import { ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';

const props = defineProps({
    unit: Object,
    isInternal: Boolean,
});

const emit = defineEmits(['close']);

const activeTab = ref('specs'); // 'specs' | 'price_history' | 'status_history' | 'edit'

const statusOptions = [
    { value: 'available', label: 'Available (Tersedia)' },
    { value: 'reserved', label: 'Reserved (Reservasi)' },
    { value: 'booked', label: 'Booked (Booking Fee)' },
    { value: 'sold', label: 'Sold Out (Terjual)' },
    { value: 'hold', label: 'Hold (Ditahan Management)' },
];

const editForm = useForm({
    status: props.unit?.status || 'available',
    final_price: props.unit?.final_price || 0,
    promo: props.unit?.promo || '',
    carport: props.unit?.carport || 1,
    facing_direction: props.unit?.facing_direction || '',
    net_price: props.unit?.net_price || '',
    commission_notes: props.unit?.commission_notes || '',
    management_notes: props.unit?.management_notes || '',
    notes: '',
});

function submitUpdate() {
    editForm.put(`/units/${props.unit.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            activeTab.value = 'specs';
            emit('close');
        }
    });
}

function formatRupiah(val) {
    if (!val) return 'Rp 0';
    return 'Rp ' + Number(val).toLocaleString('id-ID');
}

function formatDate(dt) {
    if (!dt) return '-';
    return new Date(dt).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-md animate-in fade-in duration-200">
        <div class="bg-white rounded-3xl w-full max-w-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <!-- MODAL HEADER -->
            <div class="px-8 py-6 bg-slate-900 text-white flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-blue-600 flex items-center justify-center font-black text-lg text-white shadow-lg">
                        {{ unit?.block }}{{ unit?.number }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-black tracking-tight text-white">{{ unit?.label }}</h3>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500 text-white shadow-sm">
                                {{ unit?.status }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 font-medium">{{ unit?.unit_type?.name }}</p>
                    </div>
                </div>
                <button @click="$emit('close')" class="w-9 h-9 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center text-lg font-bold transition-colors">
                    &times;
                </button>
            </div>

            <!-- MODAL TABS BAR -->
            <div class="px-8 bg-slate-100 border-b border-slate-200 flex items-center gap-2 shrink-0 overflow-x-auto">
                <button
                    @click="activeTab = 'specs'"
                    :class="['py-3 px-4 text-xs font-black uppercase tracking-wider border-b-2 transition-all', activeTab === 'specs' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-900']"
                >
                    📋 Detail Spesifikasi
                </button>
                <button
                    @click="activeTab = 'price_history'"
                    :class="['py-3 px-4 text-xs font-black uppercase tracking-wider border-b-2 transition-all', activeTab === 'price_history' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-900']"
                >
                    📈 Histori Harga
                </button>
                <button
                    @click="activeTab = 'status_history'"
                    :class="['py-3 px-4 text-xs font-black uppercase tracking-wider border-b-2 transition-all', activeTab === 'status_history' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-900']"
                >
                    🔄 Histori Status
                </button>
                <button
                    v-if="isInternal"
                    @click="activeTab = 'edit'"
                    :class="['py-3 px-4 text-xs font-black uppercase tracking-wider border-b-2 transition-all ml-auto', activeTab === 'edit' ? 'border-amber-500 text-amber-600' : 'border-transparent text-amber-600 hover:text-amber-700']"
                >
                    ⚙️ Edit / Update Admin
                </button>
            </div>

            <!-- MODAL BODY -->
            <div class="p-8 overflow-y-auto space-y-6">
                <!-- TAB 1: SPECS -->
                <div v-if="activeTab === 'specs'" class="space-y-6">
                    <!-- PRICE HIGHLIGHT -->
                    <div class="p-6 bg-gradient-to-br from-slate-900 to-slate-800 rounded-3xl text-white flex items-center justify-between shadow-xl">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Harga Jual Resmi (PL)</span>
                            <span class="text-2xl font-black text-emerald-400 tracking-tight">{{ formatRupiah(unit?.final_price) }}</span>
                        </div>
                        <div v-if="unit?.promo" class="text-right max-w-[200px]">
                            <span class="px-2.5 py-1 bg-amber-500/20 text-amber-300 border border-amber-500/30 rounded-lg text-[10px] font-bold block">
                                🎁 {{ unit.promo }}
                            </span>
                        </div>
                    </div>

                    <!-- GRID SPECS -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Luas Tanah (LT)</span>
                            <span class="text-base font-black text-slate-900">{{ unit?.unit_type?.land_area || '-' }} m²</span>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Luas Bangunan (LB)</span>
                            <span class="text-base font-black text-slate-900">{{ unit?.unit_type?.building_area || '-' }} m²</span>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Kamar Tidur / Mandi</span>
                            <span class="text-base font-black text-slate-900">{{ unit?.unit_type?.bedrooms || 0 }} KT / {{ unit?.unit_type?.bathrooms || 0 }} KM</span>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Jumlah Lantai</span>
                            <span class="text-base font-black text-slate-900">{{ unit?.unit_type?.floors || 1 }} Lantai</span>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Carport</span>
                            <span class="text-base font-black text-slate-900">{{ unit?.carport || 1 }} Mobil</span>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80">
                            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Arah Hadap</span>
                            <span class="text-base font-black text-slate-900">{{ unit?.facing_direction || 'Utara' }}</span>
                        </div>
                    </div>

                    <!-- INTERNAL CONFIDENTIAL DATA (Visible only for Internal Management / Master Lead) -->
                    <div v-if="isInternal && (unit?.net_price || unit?.management_notes || unit?.commission_notes)" class="p-5 bg-amber-50 rounded-2xl border border-amber-200 space-y-2">
                        <span class="text-xs font-black uppercase tracking-wider text-amber-800 block">🔒 Informasi Internal Management</span>
                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div v-if="unit?.net_price"><span class="text-amber-700 font-bold">Net Price:</span> <span class="font-black text-slate-900">{{ formatRupiah(unit.net_price) }}</span></div>
                            <div v-if="unit?.commission_notes"><span class="text-amber-700 font-bold">Komisi Note:</span> <span class="font-bold text-slate-900">{{ unit.commission_notes }}</span></div>
                        </div>
                        <p v-if="unit?.management_notes" class="text-xs text-amber-900 leading-relaxed font-medium mt-1">
                            <span class="font-bold">Catatan Management:</span> {{ unit.management_notes }}
                        </p>
                    </div>
                </div>

                <!-- TAB 2: PRICE HISTORY -->
                <div v-else-if="activeTab === 'price_history'" class="space-y-4">
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-500">Histori Perubahan Harga Unit</h4>
                    <div v-if="unit?.price_histories?.length" class="space-y-3">
                        <div v-for="h in unit.price_histories" :key="h.id" class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 flex items-center justify-between text-xs">
                            <div>
                                <p class="font-black text-slate-900 text-sm">{{ formatRupiah(h.new_price) }}</p>
                                <p class="text-[10px] text-slate-500 font-bold">Harga Lama: {{ formatRupiah(h.old_price) }}</p>
                                <p v-if="h.notes" class="text-[11px] text-slate-600 mt-1">Catatan: {{ h.notes }}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-[11px] font-bold text-slate-700 block">{{ h.user?.name || 'Admin System' }}</span>
                                <span class="text-[10px] text-slate-400 font-bold">{{ formatDate(h.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-400 font-medium text-center py-8">Belum ada riwayat perubahan harga.</p>
                </div>

                <!-- TAB 3: STATUS HISTORY -->
                <div v-else-if="activeTab === 'status_history'" class="space-y-4">
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-500">Histori Perubahan Status Unit</h4>
                    <div v-if="unit?.status_histories?.length" class="space-y-3">
                        <div v-for="s in unit.status_histories" :key="s.id" class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 flex items-center justify-between text-xs">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="px-2 py-0.5 bg-slate-200 text-slate-700 rounded text-[9px] font-bold uppercase">{{ s.old_status }}</span>
                                    <span>➔</span>
                                    <span class="px-2 py-0.5 bg-blue-600 text-white rounded text-[9px] font-bold uppercase">{{ s.new_status }}</span>
                                </div>
                                <p v-if="s.notes" class="text-[11px] text-slate-600">Catatan: {{ s.notes }}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-[11px] font-bold text-slate-700 block">{{ s.user?.name || 'Admin System' }}</span>
                                <span class="text-[10px] text-slate-400 font-bold">{{ formatDate(s.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-400 font-medium text-center py-8">Belum ada riwayat perubahan status.</p>
                </div>

                <!-- TAB 4: EDIT FORM (INTERNAL ADMIN) -->
                <form v-else-if="activeTab === 'edit' && isInternal" @submit.prevent="submitUpdate" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Status Unit</label>
                            <select v-model="editForm.status" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-amber-500">
                                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Harga Jual Official (Rp)</label>
                            <input v-model="editForm.final_price" type="number" required class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-amber-500" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Promo & Program Diskon</label>
                            <input v-model="editForm.promo" type="text" placeholder="Contoh: Diskon 50Jt + Free Canopy Rooftop" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-amber-500" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Arah Hadap</label>
                            <input v-model="editForm.facing_direction" type="text" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-amber-500" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Carport</label>
                            <input v-model="editForm.carport" type="number" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm font-bold text-slate-900 focus:ring-2 focus:ring-amber-500" />
                        </div>
                        <div class="col-span-2 pt-2 border-t border-slate-100">
                            <span class="text-[10px] font-black uppercase tracking-wider text-amber-700 block mb-2">🔒 Pengaturan Internal Management</span>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 mb-1">Net Price (Rp)</label>
                                    <input v-model="editForm.net_price" type="number" class="w-full px-4 py-2.5 bg-amber-50/50 border-none rounded-xl text-xs font-bold" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 mb-1">Komisi Note</label>
                                    <input v-model="editForm.commission_notes" type="text" class="w-full px-4 py-2.5 bg-amber-50/50 border-none rounded-xl text-xs font-bold" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3">
                        <button type="button" @click="activeTab = 'specs'" class="px-5 py-3 bg-slate-100 text-slate-600 rounded-xl text-xs font-black uppercase tracking-wider hover:bg-slate-200">
                            Batal
                        </button>
                        <button type="submit" :disabled="editForm.processing" class="px-6 py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-black uppercase tracking-wider shadow-lg shadow-amber-500/20">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>

            <!-- MODAL FOOTER ACTION BUTTONS -->
            <div class="px-8 py-5 bg-slate-50 border-t border-slate-200/80 flex items-center justify-between shrink-0">
                <span class="text-xs font-bold text-slate-500">Status: <span class="font-black uppercase text-slate-900">{{ unit?.status }}</span></span>
                
                <div class="flex items-center gap-3">
                    <Link
                        v-if="unit?.status === 'available'"
                        :href="`/reservations?unit_id=${unit.id}`"
                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-xs font-black uppercase tracking-wider shadow-lg shadow-blue-500/20 transition-all flex items-center gap-2"
                    >
                        <span>🚀 Reservasi Unit Ini</span>
                    </Link>
                    <button @click="$emit('close')" class="px-5 py-3 bg-slate-200 text-slate-700 rounded-2xl text-xs font-black uppercase tracking-wider hover:bg-slate-300 transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
