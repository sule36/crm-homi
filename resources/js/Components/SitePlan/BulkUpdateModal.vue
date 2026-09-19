<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    unitIds: Array,
});

const emit = defineEmits(['close']);

const bulkForm = useForm({
    unit_ids: props.unitIds,
    status: '',
    final_price: '',
    promo: '',
    notes: '',
});

function submitBulk() {
    bulkForm.unit_ids = props.unitIds;
    bulkForm.post('/units/bulk-update', {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        }
    });
}
</script>

<template>
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-md animate-in fade-in duration-200">
        <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden">
            <div class="px-8 py-6 bg-amber-500 text-white flex items-center justify-between">
                <div>
                    <h3 class="text-base font-black uppercase tracking-tight">⚡ Bulk Update Unit Inventory</h3>
                    <p class="text-xs text-amber-100 font-medium">Memperbarui {{ unitIds.length }} unit sekaligus</p>
                </div>
                <button @click="$emit('close')" class="text-amber-100 hover:text-white text-xl font-bold">&times;</button>
            </div>

            <form @submit.prevent="submitBulk" class="p-8 space-y-4 text-xs">
                <div>
                    <label class="block font-black uppercase text-slate-500 mb-1">Status Baru (Kosongkan jika tidak diubah)</label>
                    <select v-model="bulkForm.status" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl font-bold text-slate-900 focus:ring-2 focus:ring-amber-500">
                        <option value="">-- Tidak Ada Perubahan Status --</option>
                        <option value="available">Available (Tersedia)</option>
                        <option value="reserved">Reserved (Reservasi)</option>
                        <option value="booked">Booked (Booking Fee)</option>
                        <option value="sold">Sold Out (Terjual)</option>
                        <option value="hold">Hold (Ditahan Management)</option>
                    </select>
                </div>

                <div>
                    <label class="block font-black uppercase text-slate-500 mb-1">Harga Jual Baru (Rp) (Kosongkan jika tidak diubah)</label>
                    <input v-model="bulkForm.final_price" type="number" placeholder="Contoh: 3840921600" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl font-bold text-slate-900 focus:ring-2 focus:ring-amber-500" />
                </div>

                <div>
                    <label class="block font-black uppercase text-slate-500 mb-1">Promo Baru (Kosongkan jika tidak diubah)</label>
                    <input v-model="bulkForm.promo" type="text" placeholder="Contoh: Diskon Khusus Akhir Tahun" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl font-bold text-slate-900 focus:ring-2 focus:ring-amber-500" />
                </div>

                <div>
                    <label class="block font-black uppercase text-slate-500 mb-1">Alasan / Catatan Perubahan Massal</label>
                    <textarea v-model="bulkForm.notes" rows="2" placeholder="Contoh: Penyesuaian price list Q3 2026" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl font-medium text-slate-900 focus:ring-2 focus:ring-amber-500"></textarea>
                </div>

                <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="$emit('close')" class="px-5 py-3 bg-slate-100 text-slate-600 rounded-xl font-black uppercase tracking-wider hover:bg-slate-200">
                        Batal
                    </button>
                    <button type="submit" :disabled="bulkForm.processing" class="px-6 py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-black uppercase tracking-wider shadow-lg shadow-amber-500/20">
                        Proses Update {{ unitIds.length }} Unit
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
