<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const props = defineProps({
    reservation: Object,
    settings: Object,
});

const showRefundModal = ref(false);

const refundForm = useForm({
    refund_bank_name: '',
    refund_account_number: '',
    refund_account_name: props.reservation.client_name || '',
    refund_reason: 'Pengajuan tidak dapat disetujui / Client mengajukan pembatalan reservasi (100% Refundable).',
    refund_proof: null,
});

const handleRefundProofChange = (e) => {
    refundForm.refund_proof = e.target.files[0];
};

const submitRefund = () => {
    refundForm.post(`/reservations/${props.reservation.id}/refund`, {
        onSuccess: () => {
            showRefundModal.value = false;
        }
    });
};

const formatCurrency = (val) => {
    if (!val) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const statusConfig = {
    active: { label: 'Reservasi Aktif (Hold Unit)', color: 'bg-emerald-100 text-emerald-800 border-emerald-300', icon: '🟢' },
    converted: { label: 'Dikonversi ke Booking (SPR)', color: 'bg-blue-100 text-blue-800 border-blue-300', icon: '📝' },
    refunded: { label: 'Dibatalkan & Di-Refund 100%', color: 'bg-amber-100 text-amber-800 border-amber-300', icon: '🔄' },
    cancelled: { label: 'Dibatalkan', color: 'bg-slate-100 text-slate-700 border-slate-300', icon: '❌' },
};
</script>

<template>
    <CrmLayout title="Detail Reservasi Unit">
        <Head :title="`Reservasi Unit ${reservation.reservation_number}`" />

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- BREADCRUMB & HEADER -->
            <div>
                <Link href="/reservations" class="text-xs font-bold text-blue-600 hover:underline flex items-center gap-1 mb-2">
                    ← Kembali ke Daftar Reservasi
                </Link>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Reservasi #{{ reservation.reservation_number }}</h1>
                            <span :class="(statusConfig[reservation.status] || statusConfig.active).color" class="px-3 py-1 text-xs font-black rounded-lg border flex items-center gap-1">
                                {{ (statusConfig[reservation.status] || statusConfig.active).icon }}
                                {{ (statusConfig[reservation.status] || statusConfig.active).label }}
                            </span>
                            <span class="px-2.5 py-1 text-xs font-black bg-emerald-50 text-emerald-700 border border-emerald-300 rounded-lg">🛡️ Garansi 100% Refundable</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">Dibuat pada {{ formatDate(reservation.created_at) }} oleh {{ reservation.creator?.name || '-' }}</p>
                    </div>

                    <!-- ACTION BUTTONS -->
                    <div class="flex items-center gap-2">
                        <a :href="`/reservations/${reservation.id}/receipt`" target="_blank" class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-1.5">
                            <span>📄</span> Cetak Kwitansi PDF
                        </a>
                        <Link v-if="reservation.status === 'active'" :href="`/reservations/${reservation.id}/convert`" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-emerald-600/20 flex items-center gap-1.5">
                            <span>➡️</span> Konversi ke Booking (SPR)
                        </Link>
                        <button v-if="reservation.status === 'active'" @click="showRefundModal = true" class="px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold transition-all shadow-sm flex items-center gap-1.5">
                            <span>🔄</span> Batal & Refund 100%
                        </button>
                    </div>
                </div>
            </div>

            <!-- AMOUNT HIGHLIGHT CARD -->
            <div class="bg-gradient-to-r from-emerald-900 via-teal-900 to-slate-900 text-white rounded-3xl p-6 sm:p-8 shadow-lg relative overflow-hidden">
                <div class="absolute -right-8 -bottom-8 w-40 h-40 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 relative z-10">
                    <div>
                        <p class="text-xs font-bold text-emerald-300 uppercase tracking-widest">Biaya Reservasi Unit (Hold Booking)</p>
                        <h2 class="text-3xl sm:text-4xl font-black font-mono text-emerald-400 mt-1">{{ formatCurrency(reservation.amount) }}</h2>
                        <p class="text-xs text-emerald-100/80 mt-1">Metode Pembayaran: <strong class="uppercase text-white">{{ reservation.payment_method }}</strong></p>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md border border-white/20 px-4 py-3 rounded-2xl text-right">
                        <p class="text-[10px] font-bold text-emerald-200 uppercase">Status Kebijakan</p>
                        <p class="text-sm font-black text-white">100% Refundable</p>
                        <p class="text-[10px] text-emerald-200 mt-0.5">Garansi pengembalian dana utuh</p>
                    </div>
                </div>
            </div>

            <!-- DETAIL GRID -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- CARD 1: IDENTITAS PEMOHON -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-4">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3 flex items-center gap-2">
                        <span>👤</span> Identitas Pemohon / Client
                    </h3>
                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500 font-medium">Nama Pemohon:</span>
                            <span class="font-bold text-slate-900">{{ reservation.client_name }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500 font-medium">No. WhatsApp / HP:</span>
                            <span class="font-bold text-slate-900">{{ reservation.client_phone }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500 font-medium">Email:</span>
                            <span class="font-bold text-slate-900">{{ reservation.client_email || '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500 font-medium">NIK KTP:</span>
                            <span class="font-mono font-bold text-slate-900">{{ reservation.client_nik || '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- CARD 2: DETAIL UNIT & PROYEK -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-4">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3 flex items-center gap-2">
                        <span>🏠</span> Unit Properti & Proyek
                    </h3>
                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500 font-medium">Nama Proyek:</span>
                            <span class="font-bold text-slate-900">{{ reservation.project?.name || '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500 font-medium">Kode Unit:</span>
                            <span class="font-bold text-blue-700">Unit {{ reservation.unit?.code || reservation.unit?.number || '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500 font-medium">Tipe Properti:</span>
                            <span class="font-bold text-slate-900">Tipe {{ reservation.unit?.unit_type?.name || '-' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500 font-medium">Spesifikasi Luas:</span>
                            <span class="font-bold text-slate-900">LB {{ reservation.unit?.building_area || reservation.unit?.unit_type?.building_area }} m² / LT {{ reservation.unit?.surface_area || reservation.unit?.unit_type?.surface_area }} m²</span>
                        </div>
                    </div>
                </div>

                <!-- CARD 3: SIMULASI PEMOTONGAN SISA UTJ -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-3 md:col-span-2">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3 flex items-center gap-2">
                        <span>🧮</span> Ketentuan Pemotongan Sisa UTJ Saat Booking
                    </h3>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 text-xs text-slate-700 space-y-2">
                        <p class="font-bold text-slate-900">• Kredit Biaya Reservasi: <span class="font-mono text-emerald-700 font-black text-sm">{{ formatCurrency(reservation.amount) }}</span></p>
                        <p>• Nominal reservasi di atas akan memotong total Booking Fee (UTJ) secara otomatis saat dikonversi menjadi Surat Pemesanan Rumah (SPR).</p>
                        <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 font-medium text-[11px]">
                            <strong>Contoh:</strong> Jika total UTJ standar Rp 17.000.000, maka sisa yang harus dibayar saat booking = <strong>Rp 17.000.000 - {{ formatCurrency(reservation.amount) }} = Sisa UTJ yang perlu ditagihkan.</strong>
                        </div>
                    </div>
                </div>

                <!-- CARD 4: PENANDATANGAN AGENT KOORDINATOR -->
                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-4 md:col-span-2">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-3 flex items-center gap-2">
                        <span>✍️</span> Agent Koordinator Penandatangan Kwitansi
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Nama Penandatangan</p>
                            <p class="text-sm font-black text-slate-900 mt-0.5">{{ reservation.agent_coordinator_name || reservation.agent_coordinator?.name || '-' }}</p>
                            <p class="text-[10px] text-slate-500 font-semibold">{{ reservation.agent_coordinator_title || 'Agent Coordinator' }}</p>
                        </div>
                        <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Bukti Pembayaran Reservasi</p>
                            <a v-if="reservation.payment_proof" :href="`/storage/${reservation.payment_proof}`" target="_blank" class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:underline mt-1">
                                📄 Lihat Bukti Pembayaran
                            </a>
                            <p v-else class="text-xs text-slate-400 italic mt-1">Belum ada lampiran bukti transfer</p>
                        </div>
                    </div>
                </div>

                <!-- RINCIAN REFUND JIKA DI-REFUND -->
                <div v-if="reservation.status === 'refunded'" class="bg-amber-50 border border-amber-200 rounded-3xl p-6 shadow-sm space-y-4 md:col-span-2">
                    <h3 class="text-xs font-black text-amber-900 uppercase tracking-wider border-b border-amber-200 pb-3 flex items-center gap-2">
                        <span>🔄</span> Rincian Pengembalian Dana 100% (Refund)
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-amber-950">
                        <div class="p-3 bg-white/70 rounded-xl border border-amber-200">
                            <p class="text-[10px] text-amber-700 font-bold">Nominal Di-Refund:</p>
                            <p class="font-black text-emerald-700 text-sm font-mono">{{ formatCurrency(reservation.refund_amount) }}</p>
                        </div>
                        <div class="p-3 bg-white/70 rounded-xl border border-amber-200">
                            <p class="text-[10px] text-amber-700 font-bold">Tanggal Refund:</p>
                            <p class="font-bold">{{ formatDate(reservation.refund_date) }}</p>
                        </div>
                        <div class="p-3 bg-white/70 rounded-xl border border-amber-200">
                            <p class="text-[10px] text-amber-700 font-bold">Rekening Tujuan:</p>
                            <p class="font-bold">{{ reservation.refund_bank_name }} - {{ reservation.refund_account_number }} a/n {{ reservation.refund_account_name }}</p>
                        </div>
                    </div>
                    <div class="text-xs text-amber-800">
                        <strong>Alasan Refund:</strong> {{ reservation.refund_reason }}
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL PROCESS REFUND 100% -->
        <div v-if="showRefundModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-xl space-y-5 relative animate-in fade-in zoom-in duration-200">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🔄</span>
                        <h3 class="text-base font-black text-slate-900">Proses Refund 100% Reservasi</h3>
                    </div>
                    <button @click="showRefundModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">✕</button>
                </div>

                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-3 text-xs text-amber-900">
                    <p class="font-bold">🛡️ Garansi 100% Refundable</p>
                    <p class="mt-0.5">Dana sebesar <strong>{{ formatCurrency(reservation.amount) }}</strong> akan dikembalikan utuh 100% ke rekening pembeli, dan status unit <strong>{{ reservation.unit?.code || reservation.unit?.number }}</strong> akan dikembalikan menjadi <strong>Available</strong>.</p>
                </div>

                <form @submit.prevent="submitRefund" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Bank Pengembalian <span class="text-rose-500">*</span></label>
                        <input v-model="refundForm.refund_bank_name" type="text" placeholder="BCA / Mandiri / BNI / BRI" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-amber-500" />
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">No. Rekening Tujuan <span class="text-rose-500">*</span></label>
                        <input v-model="refundForm.refund_account_number" type="text" placeholder="Nomor rekening tujuan..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-amber-500" />
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Pemilik Rekening <span class="text-rose-500">*</span></label>
                        <input v-model="refundForm.refund_account_name" type="text" placeholder="Nama sesuai rekening..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-amber-500" />
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Alasan Refund <span class="text-rose-500">*</span></label>
                        <textarea v-model="refundForm.refund_reason" rows="3" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:ring-2 focus:ring-amber-500"></textarea>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Upload Bukti Transfer Refund (Opsional)</label>
                        <input @change="handleRefundProofChange" type="file" accept="image/*,.pdf" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-medium" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" @click="showRefundModal = false" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold">Batal</button>
                        <button type="submit" :disabled="refundForm.processing" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl font-bold shadow-md shadow-amber-600/20">
                            {{ refundForm.processing ? 'Memproses...' : 'Konfirmasi Refund 100%' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </CrmLayout>
</template>
