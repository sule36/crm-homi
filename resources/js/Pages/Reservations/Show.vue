<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const props = defineProps({
    reservation: Object,
    settings: Object,
});

const showRefundModal = ref(false);
const showEditModal = ref(false);
const activeTab = ref('signatures');

const refundForm = useForm({
    refund_bank_name: '',
    refund_account_number: '',
    refund_account_name: props.reservation.client_name || '',
    refund_reason: 'Pengajuan tidak dapat disetujui / Client mengajukan pembatalan reservasi (100% Refundable).',
    refund_proof: null,
});

const editForm = useForm({
    client_name: props.reservation.client_name || '',
    client_phone: props.reservation.client_phone || '',
    client_email: props.reservation.client_email || '',
    client_nik: props.reservation.client_nik || '',
    amount: props.reservation.amount || 10000000,
    payment_method: props.reservation.payment_method || 'transfer',
    company_name: props.reservation.company_name || props.settings?.company_name || 'PT Serangkai Roden Development',
    receipt_title: props.reservation.receipt_title || 'KWITANSI TANDA TERIMA RESERVASI UNIT',
    city: props.reservation.city || props.settings?.spr_signatures?.city || 'Jakarta Selatan',
    terms_text: props.reservation.terms_text || '',
    policy_title: props.reservation.policy_title || 'GARANSI KLAUSA 100% REFUNDABLE (PENGEMBALIAN DANA UTUH)',
    policy_text: props.reservation.policy_text || 'Apabila pengajuan penawaran harga/skema pembayaran tidak disetujui oleh Developer atau Calon Pembeli memutuskan untuk membatalkan pengajuan sebelum penandatanganan Surat Pemesanan Rumah (SPR), dana reservasi ini DIJAMIN DIKEMBALIKAN 100% UTUH (TANPA POTONGAN BIAYA APAPUN).',
    custom_overrides: props.reservation.custom_overrides || {
        project_name: props.reservation.project?.name || '',
        unit_code: props.reservation.unit?.code || props.reservation.unit?.number || '',
        unit_type_name: props.reservation.unit?.unit_type?.name || '',
        spec_text: `LB ${props.reservation.unit?.building_area || props.reservation.unit?.unit_type?.building_area || ''} m² / LT ${props.reservation.unit?.surface_area || props.reservation.unit?.unit_type?.surface_area || ''} m²`,
    },
    agent_coordinator_name: props.reservation.agent_coordinator_name || props.reservation.agent_coordinator?.name || '',
    agent_coordinator_title: props.reservation.agent_coordinator_title || 'Master Lead / Agent Coordinator',
    notes: props.reservation.notes || '',
});

const submitEdit = () => {
    editForm.put(`/reservations/${props.reservation.id}`, {
        onSuccess: () => {
            showEditModal.value = false;
        }
    });
};

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
                    <div class="flex items-center gap-2 flex-wrap">
                        <button @click="showEditModal = true" class="px-4 py-2.5 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5">
                            <span>✏️</span> Edit PT & Penandatangan
                        </button>
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

        <!-- MODAL EDIT OTENTIKASI & TEMPLATE KWITANSI (DINAMIS SEPERTI SPR) -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-xl space-y-5 relative animate-in fade-in zoom-in duration-200 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">✏️</span>
                        <div>
                            <h3 class="text-base font-black text-slate-900">Customization Kwitansi Reservasi</h3>
                            <p class="text-[10px] text-slate-500 font-semibold">Ubah PT, Ttd, Klausa, Ketentuan, Teks & Detail Unit Bebas (Seperti SPR)</p>
                        </div>
                    </div>
                    <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">✕</button>
                </div>

                <!-- TAB HEADERS -->
                <div class="flex items-center gap-2 border-b border-slate-200 pb-2 text-xs font-bold">
                    <button type="button" @click="activeTab = 'signatures'" :class="activeTab === 'signatures' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3.5 py-1.5 rounded-xl transition-all">
                        ✍️ PT & Penandatangan
                    </button>
                    <button type="button" @click="activeTab = 'client'" :class="activeTab === 'client' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3.5 py-1.5 rounded-xl transition-all">
                        👤 Pemohon & Detail Unit
                    </button>
                    <button type="button" @click="activeTab = 'terms'" :class="activeTab === 'terms' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3.5 py-1.5 rounded-xl transition-all">
                        📜 Ketentuan & Klausa Garansi
                    </button>
                </div>

                <form @submit.prevent="submitEdit" class="space-y-4 text-xs">

                    <!-- TAB 1: PT & PENANDATANGAN -->
                    <div v-if="activeTab === 'signatures'" class="space-y-4">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nama PT / Perusahaan Developer (Kwitansi) <span class="text-rose-500">*</span></label>
                            <input v-model="editForm.company_name" type="text" placeholder="Contoh: PT Serangkai Roden Development" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-blue-500" />
                            <p class="text-[10px] text-slate-400 mt-1">Nama PT yang tercetak pada Kop Surat & Tanda Tangan Kwitansi.</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Nama Penandatangan (Koordinator) <span class="text-rose-500">*</span></label>
                                <input v-model="editForm.agent_coordinator_name" type="text" placeholder="Masukkan nama penandatangan..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Jabatan Penandatangan <span class="text-rose-500">*</span></label>
                                <input v-model="editForm.agent_coordinator_title" type="text" placeholder="Contoh: Master Lead / Agent Coordinator" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-blue-500" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Kota Penerbitan (Kota TTD)</label>
                                <input v-model="editForm.city" type="text" placeholder="Contoh: Jakarta Selatan" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Judul Dokumen Kwitansi</label>
                                <input v-model="editForm.receipt_title" type="text" placeholder="KWITANSI TANDA TERIMA RESERVASI UNIT" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-blue-500" />
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: PEMOHON & DETAIL UNIT OVERRIDES -->
                    <div v-if="activeTab === 'client'" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Nama Lengkap Pemohon</label>
                                <input v-model="editForm.client_name" type="text" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">No. WhatsApp / HP</label>
                                <input v-model="editForm.client_phone" type="text" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-blue-500" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Email Pemohon</label>
                                <input v-model="editForm.client_email" type="email" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">NIK KTP Pemohon</label>
                                <input v-model="editForm.client_nik" type="text" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-blue-500" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Nominal Reservasi (Rp)</label>
                                <input v-model="editForm.amount" type="number" step="500000" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold text-emerald-700 font-mono focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Metode Pembayaran</label>
                                <select v-model="editForm.payment_method" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-blue-500">
                                    <option value="transfer">Bank Transfer (Rekening Resmi Developer)</option>
                                    <option value="cash">Cash / Tunai di Office</option>
                                    <option value="qris">QRIS / EDC Merchant</option>
                                </select>
                            </div>
                        </div>

                        <div class="p-3.5 bg-blue-50/70 rounded-2xl border border-blue-200 space-y-3">
                            <p class="font-black text-blue-900 text-xs">🏠 Custom Override Teks Unit Properti (Kwitansi)</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Nama Proyek</label>
                                    <input v-model="editForm.custom_overrides.project_name" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-bold" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Kode / Nomor Unit</label>
                                    <input v-model="editForm.custom_overrides.unit_code" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-bold" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Tipe Properti</label>
                                    <input v-model="editForm.custom_overrides.unit_type_name" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-bold" />
                                </div>
                                <div>
                                    <label class="block font-bold text-slate-700 mb-1">Spesifikasi Luas (LB / LT)</label>
                                    <input v-model="editForm.custom_overrides.spec_text" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-bold" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: KETENTUAN & KLAUSA GARANSI -->
                    <div v-if="activeTab === 'terms'" class="space-y-4">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Ketentuan Kredit Pemotongan Uang Tanda Jadi (Seksi 2)</label>
                            <textarea v-model="editForm.terms_text" rows="4" placeholder="Ketikan poin ketentuan pemotongan UTJ custom (kosongkan untuk menggunakan standar otomatis)..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:ring-2 focus:ring-blue-500"></textarea>
                            <p class="text-[10px] text-slate-400 mt-1">Kosongkan jika ingin menggunakan formula standar pemotongan otomatis.</p>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Judul Klausa Garansi (Seksi 3)</label>
                            <input v-model="editForm.policy_title" type="text" placeholder="GARANSI KLAUSA 100% REFUNDABLE (PENGEMBALIAN DANA UTUH)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Isi Teks Garansi Refund 100% (Seksi 3)</label>
                            <textarea v-model="editForm.policy_text" rows="3" placeholder="Teks klausa garansi pengembalian dana 100%..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Catatan Reservasi</label>
                            <input v-model="editForm.notes" type="text" placeholder="Catatan reservasi..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:ring-2 focus:ring-blue-500" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                        <span class="text-[10px] text-slate-400 font-bold">💡 Perubahan akan langsung memperbarui kwitansi PDF.</span>
                        <div class="flex items-center gap-3">
                            <button type="button" @click="showEditModal = false" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold">Batal</button>
                            <button type="submit" :disabled="editForm.processing" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold shadow-md shadow-blue-600/20">
                                {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan Kwitansi' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </CrmLayout>
</template>
