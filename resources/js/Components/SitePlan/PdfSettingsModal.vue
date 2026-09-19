<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    project: Object,
});

const emit = defineEmits(['close']);

const settings = props.project?.settings || {};

const form = useForm({
    valid_until: settings.pricelist_valid_until || '31 Oktober 2026',
    bank_info: settings.pricelist_bank_info || 'a.n PT. SERANGKAI RODEN DEVELOPMENT, BRI: 012001004640307',
    notes: settings.pricelist_notes || [
        'Harga diatas sudah termasuk BPHTB, PPN, AJB, SHM, Smart Door Lock, Kanopi, Sanitari, dan Taman Depan',
        'Harga diatas belum termasuk additional yang diajukan oleh Pembeli',
        'Harga dan ketersediaan unit tidak mengikat sebelum pembayaran Booking Fee',
        'Booking Fee dianggap hangus apabila terdapat pembatalan sepihak dari pembeli',
        'Pembayaran Down Payment (DP) dapat dilunasi paling lambat 14 hari semenjak Booking Fee dibayarkan',
        'Pembayaran yang diakui adalah yang memiliki BUKTI KUITANSI / TRANSFER resmi ke Developer',
        'Serah Terima Unit dilakukan maksimal 12 bulan setelah pembangunan dimulai',
    ].join('\n'),
    order_steps: settings.pricelist_order_steps || [
        'Melakukan Booking Fee terhadap unit yang dipilih',
        'Melengkapi dokumen persyaratan yang diperlukan',
        'Mengisi SPR (Surat Pemesanan Rumah) sebagai bukti pemesanan unit',
        'Pembayaran DP sesuai dengan skema yang telah disepakati',
        'Penandatanganan PPJB (Perjanjian Pengikatan Jual Beli)',
        'Pelunasan Angsuran sesuai dengan skema yang disepakati',
        'Serah Terima Unit',
    ].join('\n'),
    partner_banks: settings.pricelist_partner_banks || ['BRI', 'BSI', 'Mandiri', 'Bank BTN'],
});

const availableBanks = ['BRI', 'BSI', 'Mandiri', 'Bank BTN', 'BCA', 'Danamon', 'Maybank', 'CIMB Niaga', 'Bank DKI', 'BNC'];

function toggleBank(bName) {
    const idx = form.partner_banks.indexOf(bName);
    if (idx > -1) {
        form.partner_banks.splice(idx, 1);
    } else {
        form.partner_banks.push(bName);
    }
}

function submitSettings() {
    form.post(`/projects/${props.project.id}/price-list/settings`, {
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
        }
    });
}
</script>

<template>
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-md animate-in fade-in duration-200">
        <div class="bg-white rounded-3xl w-full max-w-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <!-- HEADER -->
            <div class="px-8 py-6 bg-slate-900 text-white flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center font-black text-lg shadow-lg">
                        ⚙️
                    </div>
                    <div>
                        <h3 class="text-base font-black uppercase tracking-tight">Pengaturan Catatan PDF & Bank Kerjasama</h3>
                        <p class="text-xs text-slate-400 font-medium">Kustomisasi teks footer catatan, tahapan, & logo bank pada PDF Price List</p>
                    </div>
                </div>
                <button @click="$emit('close')" class="w-8 h-8 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center font-bold text-lg">
                    &times;
                </button>
            </div>

            <!-- FORM BODY -->
            <form @submit.prevent="submitSettings" class="p-8 overflow-y-auto space-y-5 text-xs">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block font-black uppercase tracking-wider text-slate-500 mb-1">Masa Berlaku Price List</label>
                        <input v-model="form.valid_until" type="text" placeholder="Contoh: 31 Oktober 2026" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl font-bold text-slate-900 focus:ring-2 focus:ring-amber-500" />
                    </div>

                    <div>
                        <label class="block font-black uppercase tracking-wider text-slate-500 mb-1">Rekening Resmi Developer</label>
                        <input v-model="form.bank_info" type="text" placeholder="a.n PT. SERANGKAI RODEN DEVELOPMENT..." class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl font-bold text-slate-900 focus:ring-2 focus:ring-amber-500" />
                    </div>
                </div>

                <div>
                    <label class="block font-black uppercase tracking-wider text-slate-500 mb-1">Catatan Pemasaran (Satu Poin Per Baris)</label>
                    <textarea v-model="form.notes" rows="6" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl font-medium text-slate-900 leading-relaxed focus:ring-2 focus:ring-amber-500"></textarea>
                </div>

                <div>
                    <label class="block font-black uppercase tracking-wider text-slate-500 mb-1">Tahapan Pemesanan Unit (Satu Poin Per Baris)</label>
                    <textarea v-model="form.order_steps" rows="6" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl font-medium text-slate-900 leading-relaxed focus:ring-2 focus:ring-amber-500"></textarea>
                </div>

                <div>
                    <label class="block font-black uppercase tracking-wider text-slate-500 mb-2">Pilih Bank Kerjasama (Ditampilkan di PDF)</label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            v-for="b in availableBanks"
                            :key="b"
                            @click="toggleBank(b)"
                            :class="[
                                'px-3.5 py-2 rounded-xl font-black text-xs transition-all border',
                                form.partner_banks.includes(b) ? 'bg-amber-500 text-white border-amber-500 shadow-md' : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200'
                            ]"
                        >
                            {{ form.partner_banks.includes(b) ? '✓ ' : '+ ' }}{{ b }}
                        </button>
                    </div>
                </div>

                <!-- FOOTER ACTIONS -->
                <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="$emit('close')" class="px-5 py-3 bg-slate-100 text-slate-600 rounded-xl font-black uppercase tracking-wider hover:bg-slate-200">
                        Batal
                    </button>
                    <button type="submit" :disabled="form.processing" class="px-6 py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-black uppercase tracking-wider shadow-lg shadow-amber-500/20">
                        Simpan Pengaturan PDF
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
