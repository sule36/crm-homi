<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import KprProgress from '@/Components/Crm/KprProgress.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    booking: Object
});

const showPaymentModal = ref(false);
const selectedSchedule = ref(null);
const showSpkPreview = ref(false);

const paymentForm = useForm({
    booking_id: props.booking.id,
    payment_schedule_id: null,
    amount: 0,
    payment_method: 'transfer',
    bank_name: '',
    reference_number: '',
    notes: '',
});

function openPaymentModal(schedule) {
    selectedSchedule.value = schedule;
    paymentForm.payment_schedule_id = schedule.id;
    paymentForm.amount = schedule.amount;
    showPaymentModal.value = true;
}

function submitPayment() {
    paymentForm.post('/transactions', {
        onSuccess: () => {
            showPaymentModal.value = false;
            paymentForm.reset();
        }
    });
}

function deleteTransaction(id) {
    if (confirm('Hapus catatan transaksi ini? Status cicilan akan disesuaikan kembali.')) {
        router.delete(`/transactions/${id}`);
    }
}

// Flexible Payment Schedule Management
const showScheduleModal = ref(false);
const showAddRowModal = ref(false);
const editingScheduleRow = ref(null);

const regenForm = useForm({
    payment_scheme: props.booking.payment_scheme || 'cash_installment',
    installment_months: props.booking.installment_months || 60,
    dp_amount: props.booking.dp_amount || 0,
    dp_installment_months: props.booking.dp_installment_months || 0,
});

function submitRegenSchedule() {
    regenForm.post(`/bookings/${props.booking.id}/regenerate-schedule`, {
        preserveScroll: true,
        onSuccess: () => { showScheduleModal.value = false; }
    });
}

const addRowForm = useForm({
    label: '',
    amount: '',
    due_date: '',
});

function submitAddRow() {
    addRowForm.post(`/bookings/${props.booking.id}/schedules`, {
        preserveScroll: true,
        onSuccess: () => { showAddRowModal.value = false; addRowForm.reset(); }
    });
}

const editRowForm = useForm({
    label: '',
    amount: '',
    due_date: '',
    status: 'upcoming',
});

function openEditRow(schedule) {
    editingScheduleRow.value = schedule;
    editRowForm.label = schedule.label;
    editRowForm.amount = schedule.amount;
    editRowForm.due_date = schedule.due_date;
    editRowForm.status = schedule.status;
}

function submitEditRow() {
    if (!editingScheduleRow.value) return;
    editRowForm.put(`/payment-schedules/${editingScheduleRow.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { editingScheduleRow.value = null; }
    });
}

function deleteRow(schedule) {
    if (confirm(`Hapus baris tagihan "${schedule.label}"?`)) {
        router.delete(`/payment-schedules/${schedule.id}`, { preserveScroll: true });
    }
}

function sendEmailRow(schedule) {
    if (!props.booking.lead?.email) {
        return alert('Konsumen (Lead) ini belum memiliki alamat email yang terdaftar.');
    }
    if (confirm(`Kirimkan invoice tagihan "${schedule.label}" ke email ${props.booking.lead.email}?`)) {
        router.post(`/payment-schedules/${schedule.id}/send-email`, {}, { preserveScroll: true });
    }
}

const showReasonModal = ref(false);
const actionType = ref(''); // 'reject' or 'cancel'
const reason = ref('');

const approve = () => {
    if (confirm('Apakah Anda yakin ingin menyetujui booking ini? Status unit akan berubah menjadi Booked.')) {
        router.post(`/bookings/${props.booking.id}/approve`);
    }
};

const openReasonModal = (type) => {
    actionType.value = type;
    showReasonModal.value = true;
};

const submitAction = () => {
    if (!reason.value) return alert('Alasan harus diisi.');
    router.post(`/bookings/${props.booking.id}/${actionType.value}`, { reason: reason.value }, {
        onSuccess: () => {
            showReasonModal.value = false;
            reason.value = '';
        }
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};

const statusColors = {
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-rose-100 text-rose-700',
    completed: 'bg-blue-100 text-blue-700',
};

const copyTrackingLink = () => {
    const url = `${window.location.origin}/track/${props.booking.tracking_token}`;
    navigator.clipboard.writeText(url);
    alert('Link pelacakan berhasil disalin!');
};

const sendWaTxReceipt = (tx) => {
    const name = props.booking.lead?.name || 'Konsumen';
    const amountStr = formatCurrency(tx.amount);
    const unitStr = props.booking.unit ? `Blok ${props.booking.unit.block} No. ${props.booking.unit.number}` : 'Unit';
    const projStr = props.booking.unit?.project?.name || 'Homi Developer';
    const rawPhone = props.booking.lead?.phone || '';
    const phone = rawPhone.replace(/\D/g, '').replace(/^0/, '62');
    const receiptUrl = `${window.location.origin}/finance/transactions/${tx.id}/receipt`;

    const message = `Halo Bapak/Ibu *${name}*,\n\nBerikut adalah Kwitansi Pembayaran Resmi (TTD & Cap Stempel) untuk unit *${unitStr}* (*${projStr}*) sebesar *${amountStr}*:\n${receiptUrl}\n\nTerima kasih,\n*Keuangan Homi Developer*`;

    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank');
};

const whatsappReminderLink = computed(() => {
    const url = `${window.location.origin}/track/${props.booking.tracking_token}`;
    const message = `Halo Bapak/Ibu ${props.booking.lead.name}, ini dari tim sales ${props.booking.project.name}. Berikut adalah link untuk memantau progres pesanan dan riwayat pembayaran unit Anda: ${url}. Terima kasih.`;
    return `https://wa.me/${props.booking.lead.phone.replace(/\D/g, '')}?text=${encodeURIComponent(message)}`;
});

// Document Upload Logic
const docForm = useForm({
    type: 'ktp',
    file: null,
});

const handleFileChange = (e) => {
    docForm.file = e.target.files[0];
};

const submitDocument = () => {
    if (!docForm.file) return alert('Silakan pilih berkas terlebih dahulu.');
    docForm.post(`/bookings/${props.booking.id}/documents`, {
        preserveScroll: true,
        onSuccess: () => {
            docForm.reset();
            const fileInput = document.getElementById('doc-file-input');
            if (fileInput) fileInput.value = '';
        }
    });
};

const deleteDocument = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus dokumen ini?')) {
        router.delete(`/booking-documents/${id}`, {
            preserveScroll: true
        });
    }
};

const docTypeLabels = {
    ktp: '🪪 KTP Pemesan',
    kk: '👨‍👩‍👧‍👦 Kartu Keluarga (KK)',
    npwp: '💳 NPWP Pemesan',
    payment_proof: '🧾 Bukti Bayar UTJ/DP',
    other: '📁 Dokumen Tambahan'
};
</script>

<template>
    <Head :title="`Booking: ${booking.spk_number}`" />
    <CrmLayout>
        <template #breadcrumb>
            <Link href="/bookings" class="text-slate-400 hover:text-blue-600">Booking</Link>
            <span class="mx-2 text-slate-300">/</span>
            <span class="text-slate-900 font-bold">{{ booking.spk_number }}</span>
        </template>

        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 mb-8">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-white rounded-2xl border border-slate-100 flex items-center justify-center text-xl shadow-sm">
                    📋
                </div>
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ booking.spk_number }}</h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span :class="statusColors[booking.status]" class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider">
                            {{ booking.status }}
                        </span>
                        <span class="text-xs text-slate-400">Dibuat oleh {{ booking.booked_by?.name || 'Staff' }} pada {{ new Date(booking.booking_date).toLocaleString('id-ID') }}</span>
                    </div>
                </div>
            </div>
            
            <div v-if="booking.status === 'pending'" class="flex gap-2">
                <button @click="approve" class="px-6 py-2.5 bg-emerald-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-emerald-500/20 hover:-translate-y-0.5 transition-all">
                    Approve
                </button>
                <button @click="openReasonModal('reject')" class="px-6 py-2.5 bg-rose-50 text-rose-600 text-sm font-bold rounded-xl border border-rose-100 hover:bg-rose-100 transition-all">
                    Reject
                </button>
            </div>
            <div v-else-if="booking.status === 'approved'" class="flex gap-2">
                <button @click="showSpkPreview = true" class="px-6 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl shadow-lg hover:-translate-y-0.5 transition-all flex items-center gap-2">
                    👁️ Tinjau SPR
                </button>
                <a :href="`/bookings/${booking.id}/spk`" target="_blank" class="px-6 py-2.5 bg-slate-900 text-white text-sm font-bold rounded-xl shadow-lg hover:-translate-y-0.5 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Download SPR (PDF)
                </a>
                <button @click="openReasonModal('cancel')" class="px-4 py-2.5 text-slate-400 text-xs font-bold hover:text-rose-600 transition-all">
                    Batalkan Pesanan
                </button>
            </div>
        </div>

        <!-- QUICK ACTIONS BAR -->
        <div class="bg-white rounded-2xl border border-blue-100 p-4 mb-8 flex flex-wrap items-center justify-between gap-4 shadow-sm shadow-blue-500/5">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.826L10.172 13.828a4 4 0 005.656 0l4-4a4 4 0 10-5.656-5.656l-1.102 1.101m-.758 4.826L12 12"/></svg>
                </div>
                <div>
                    <p class="text-xs font-black text-slate-900 uppercase tracking-tight">Customer Tracking Link</p>
                    <p class="text-[10px] text-slate-400 font-bold">Bagikan link ini agar konsumen bisa pantau progres & pembayaran.</p>
                </div>
            </div>
            <div class="flex gap-2">
                <button @click="copyTrackingLink" class="px-4 py-2 bg-slate-100 text-slate-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-slate-200 transition-all">Salin Link</button>
                <a :href="whatsappReminderLink" target="_blank" class="px-4 py-2 bg-emerald-500 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-emerald-600 transition-all shadow-lg shadow-emerald-500/20 flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.335-1.662c1.72.937 3.659 1.432 5.631 1.433h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Kirim WA
                </a>
            </div>
        </div>

        <!-- KPR PROGRESS (ONLY FOR KPR SCHEME) -->
        <KprProgress v-if="booking.payment_scheme === 'kpr' && booking.status !== 'pending'" :booking="booking" />

        <!-- REASON MODAL -->
        <div v-if="showReasonModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showReasonModal = false"></div>
            <div class="relative bg-white rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl p-8">
                <h3 class="text-xl font-black text-slate-900 mb-2 uppercase tracking-tight">{{ actionType === 'reject' ? 'Tolak' : 'Batalkan' }} Booking</h3>
                <p class="text-xs text-slate-500 mb-6">Berikan alasan mengapa pesanan ini {{ actionType === 'reject' ? 'ditolak' : 'dibatalkan' }}.</p>
                
                <div class="space-y-4">
                    <textarea v-model="reason" rows="3" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-rose-500/20" placeholder="Contoh: Dokumen tidak lengkap..."></textarea>
                    
                    <div class="flex gap-3">
                        <button @click="submitAction" class="flex-1 py-4 bg-rose-600 text-white font-black rounded-2xl shadow-xl hover:-translate-y-1 transition-all uppercase text-xs tracking-widest">
                            KONFIRMASI {{ actionType }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- DETAIL KONSUMEN & UNIT -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-6">Informasi Transaksi</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Unit Info -->
                        <div class="space-y-4">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Data Unit</p>
                            <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                                <h3 class="text-sm font-black text-slate-900">{{ booking.unit?.project?.name }}</h3>
                                <p class="text-xs text-slate-500 mt-1">Blok {{ booking.unit?.block }}{{ booking.unit?.number }} • Tipe {{ booking.unit?.unit_type?.name }}</p>
                                <div class="mt-4 flex items-center justify-between pt-4 border-t border-slate-200">
                                    <span class="text-xs text-slate-400">Harga Unit</span>
                                    <span class="text-sm font-black text-slate-900">{{ formatCurrency(booking.final_price) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Customer Info -->
                        <div class="space-y-4">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Data Konsumen Utama</p>
                            <div class="space-y-2 text-xs">
                                <div>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase">Nama Pemesan</p>
                                    <p class="text-sm font-bold text-slate-900">{{ booking.lead?.name }}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase">NIK KTP</p>
                                        <p class="font-bold text-slate-800">{{ booking.buyer_nik || booking.lead?.identity_number || '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase">NPWP</p>
                                        <p class="font-bold text-slate-800">{{ booking.buyer_npwp || booking.lead?.npwp || '-' }}</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase">Pekerjaan</p>
                                        <p class="font-bold text-slate-800">{{ booking.buyer_job || booking.lead?.job || '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase">No. HP / WA</p>
                                        <p class="font-bold text-slate-800">{{ booking.lead?.phone }}</p>
                                    </div>
                                </div>

                                <div v-if="booking.secondary_name" class="mt-3 p-3 bg-amber-50 rounded-xl border border-amber-200/70 space-y-1">
                                    <p class="text-[9px] font-black text-amber-700 uppercase tracking-wider">Pemesan 2 / Penanggung Jawab Pembayaran</p>
                                    <p class="text-xs font-bold text-slate-900">{{ booking.secondary_name }} ({{ booking.secondary_relationship || 'Penanggung Jawab' }})</p>
                                    <p class="text-[10px] text-slate-600">NIK: {{ booking.secondary_nik || '-' }} • Telp: {{ booking.secondary_phone || '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PAYMENT SCHEDULE -->
                <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-slate-100">
                        <div>
                            <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                                <span>📅</span> <span>Jadwal Pembayaran</span>
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Skema: <span class="font-bold text-amber-600 uppercase">{{ booking.payment_scheme }}</span> 
                                <span v-if="booking.installment_months"> ({{ booking.installment_months }} Bulan / {{ (booking.installment_months / 12).toFixed(1) }} Thn)</span>
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button @click="showScheduleModal = true" class="px-3 py-1.5 bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold rounded-xl hover:bg-amber-100 transition-colors flex items-center gap-1">
                                <span>⚙️</span> <span>Atur Skema / Tenor</span>
                            </button>
                            <button @click="showAddRowModal = true" class="px-3 py-1.5 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition-colors flex items-center gap-1">
                                <span>➕</span> <span>Tambah Tagihan</span>
                            </button>
                        </div>
                    </div>
                    
                    <div v-if="booking.payment_schedules?.length" class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50">
                                <tr>
                                    <th class="py-3 px-4">Tagihan</th>
                                    <th class="py-3 px-4">Jatuh Tempo</th>
                                    <th class="py-3 px-4 text-right">Jumlah</th>
                                    <th class="py-3 px-4 text-center">Status</th>
                                    <th class="py-3 px-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="schedule in booking.payment_schedules" :key="schedule.id" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-4 py-3">
                                        <p class="font-black text-slate-900 text-xs">{{ schedule.label }}</p>
                                        <p class="text-[10px] text-slate-400">#{{ schedule.installment_number }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 font-medium text-xs">
                                        {{ new Date(schedule.due_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}
                                    </td>
                                    <td class="px-4 py-3 font-black text-slate-900 text-xs text-right">
                                        Rp {{ Number(schedule.amount).toLocaleString('id-ID') }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span :class="statusColors[schedule.status]" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                            {{ schedule.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-1.5">
                                        <button v-if="schedule.status !== 'paid'" @click="openPaymentModal(schedule)" class="px-2.5 py-1 bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-blue-700">Bayar</button>
                                        <span v-else class="text-[10px] text-emerald-600 font-black uppercase tracking-widest mr-2">Lunas ✅</span>

                                        <button @click="sendEmailRow(schedule)" class="p-1 text-slate-400 hover:text-blue-600 transition-colors" title="Kirim Invoice Email Tagihan Ke Konsumen">✉️</button>
                                        <button @click="openEditRow(schedule)" class="p-1 text-slate-400 hover:text-amber-600 transition-colors" title="Edit Baris">✏️</button>
                                        <button @click="deleteRow(schedule)" class="p-1 text-slate-400 hover:text-rose-600 transition-colors" title="Hapus Baris Tagihan Ini">🗑️</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-10">
                        <p class="text-xs text-slate-400">Jadwal pembayaran akan otomatis digenerate setelah booking disetujui.</p>
                    </div>
                </div>

                <!-- TRANSACTION HISTORY -->
                <div class="bg-white rounded-3xl border border-slate-100 p-8 shadow-sm mb-8">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-900 mb-6">Transaction History</h3>
                    <div v-if="booking.transactions?.length" class="space-y-4">
                        <div v-for="tx in booking.transactions" :key="tx.id" class="flex flex-col sm:flex-row sm:items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100 gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-lg shadow-sm">💰</div>
                                <div>
                                    <p class="text-xs font-black text-slate-900 uppercase tracking-tight">{{ tx.payment_method }} - {{ tx.bank_name || 'Cash' }}</p>
                                    <p class="text-[10px] text-slate-400">{{ tx.notes || 'Pembayaran' }} • {{ new Date(tx.created_at).toLocaleDateString('id-ID') }}</p>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap items-center gap-2 justify-end">
                                <span class="text-sm font-black text-slate-900 mr-2">Rp {{ Number(tx.amount).toLocaleString('id-ID') }}</span>
                                
                                <a :href="`/finance/transactions/${tx.id}/receipt`" target="_blank" class="px-2.5 py-1 bg-white border border-slate-200 text-slate-700 text-[10px] font-black rounded-lg hover:bg-slate-100 transition-all flex items-center gap-1 shadow-sm">
                                    <span>📄</span> <span>Kwitansi TTD & Cap</span>
                                </a>

                                <button @click="sendWaTxReceipt(tx)" class="px-2.5 py-1 bg-emerald-600 text-white text-[10px] font-black rounded-lg hover:bg-emerald-700 transition-all shadow-sm flex items-center gap-1">
                                    <span>💬</span> <span>Kirim WA</span>
                                </button>

                                <a v-if="tx.wet_receipt_file" :href="`/storage/${tx.wet_receipt_file}`" target="_blank" class="px-2.5 py-1 bg-amber-500 text-white text-[10px] font-black rounded-lg hover:bg-amber-600 transition-all shadow-sm flex items-center gap-1">
                                    <span>📎</span> <span>Kwitansi Basah</span>
                                </a>

                                <button @click="deleteTransaction(tx.id)" class="text-[10px] font-black text-rose-500 uppercase tracking-tighter hover:underline ml-1">Hapus</button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-10 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-100">
                        <p class="text-[11px] text-slate-400 font-bold uppercase tracking-widest">Belum ada transaksi tercatat.</p>
                    </div>
                </div>
            </div>

            <!-- SUMMARY SIDEBAR -->
            <div class="space-y-6">
                <div class="bg-slate-900 rounded-2xl p-6 text-white shadow-xl">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-blue-400 mb-6">Finansial</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-slate-400">Harga Dasar</span>
                            <span class="text-xs font-black">{{ formatCurrency(booking.base_price) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-slate-400">Pajak PPN (11%)</span>
                            <span class="text-xs font-black">{{ formatCurrency(booking.ppn_amount) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-slate-400">BPHTB + AJB/BBN</span>
                            <span class="text-xs font-black">{{ formatCurrency(Number(booking.bphtb_amount) + Number(booking.ajb_bbn_amount)) }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-white/10">
                            <span class="text-xs text-white font-black">Total All-in</span>
                            <span class="text-lg font-black text-blue-400">{{ formatCurrency(booking.final_price) }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-4 border-t border-white/10">
                            <span class="text-xs text-slate-400">Booking Fee (UTJ)</span>
                            <span class="text-sm font-black text-emerald-400">{{ formatCurrency(booking.booking_fee) }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-4 border-t border-white/10">
                            <span class="text-xs text-slate-400">Total Harga</span>
                            <span class="text-lg font-black">{{ formatCurrency(booking.final_price) }}</span>
                        </div>
                    </div>
                </div>

                <!-- COMMISSION BOX -->
                <div v-if="booking.commission_amount > 0" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 mb-4">Komisi Sales</h3>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-lg font-black text-blue-600">{{ formatCurrency(booking.commission_amount) }}</p>
                            <p class="text-[10px] text-slate-400 font-bold uppercase">{{ booking.commission_status }}</p>
                        </div>
                        <span v-if="booking.commission_status === 'paid'" class="w-8 h-8 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center">✓</span>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 mb-4">Catatan Internal</h3>
                    <p class="text-xs text-slate-500 italic leading-relaxed">
                        {{ booking.notes || 'Tidak ada catatan khusus.' }}
                    </p>
                </div>

                <!-- DOKUMEN PERSYARATAN KONSUMEN -->
                <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm space-y-5">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center justify-between">
                        <span>📁 Berkas Persyaratan</span>
                        <span class="text-[9px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded font-black">{{ booking.documents?.length || 0 }} File</span>
                    </h3>

                    <!-- Upload Form -->
                    <form @submit.prevent="submitDocument" class="bg-slate-50 p-4 rounded-xl border border-slate-100 space-y-3">
                        <span class="text-[9px] font-black text-slate-400 uppercase">Unggah Berkas Baru</span>
                        <div class="grid grid-cols-2 gap-2">
                            <select v-model="docForm.type" class="px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-[10px] font-black text-slate-700 focus:ring-1 focus:ring-blue-500 cursor-pointer">
                                <option value="ktp">🪪 KTP Pemesan</option>
                                <option value="kk">👨‍👩‍👧‍👦 Kartu Keluarga</option>
                                <option value="npwp">💳 NPWP</option>
                                <option value="payment_proof">🧾 Bukti Bayar</option>
                                <option value="other">📁 Dokumen Lain</option>
                            </select>
                            <input id="doc-file-input" type="file" @change="handleFileChange" class="hidden" accept=".jpg,.jpeg,.png,.pdf" />
                            <button type="button" @click="document.getElementById('doc-file-input').click()" class="px-2.5 py-1.5 bg-white border border-slate-200 rounded-lg text-[10px] font-black text-slate-600 hover:bg-slate-100 text-center truncate">
                                {{ docForm.file ? docForm.file.name : '📎 Pilih File' }}
                            </button>
                        </div>
                        <button type="submit" :disabled="docForm.processing || !docForm.file" class="w-full py-2 bg-blue-600 hover:bg-blue-700 text-white text-[9px] font-black uppercase tracking-widest rounded-lg transition-all shadow-md shadow-blue-500/10 disabled:opacity-40">
                            {{ docForm.processing ? 'Mengunggah...' : 'Unggah Dokumen' }}
                        </button>
                    </form>

                    <!-- Document List -->
                    <div class="space-y-2">
                        <div v-for="doc in booking.documents" :key="doc.id" class="flex items-center justify-between p-3 bg-slate-50 rounded-xl border border-slate-100 text-xs">
                            <div class="min-w-0 flex-1 pr-2">
                                <p class="text-[9px] font-black text-slate-400 uppercase">{{ docTypeLabels[doc.type] }}</p>
                                <a :href="`/storage/${doc.file_path}`" target="_blank" class="font-bold text-blue-600 hover:underline block truncate mt-0.5">{{ doc.name }}</a>
                            </div>
                            <button @click="deleteDocument(doc.id)" class="text-rose-500 hover:bg-rose-50 p-1.5 rounded-lg transition-colors shrink-0">
                                🗑️
                            </button>
                        </div>
                        <div v-if="!booking.documents?.length" class="text-center py-6 text-slate-400 italic text-[10px] font-bold bg-slate-50 rounded-xl border border-dashed border-slate-200">
                            Belum ada dokumen persyaratan diunggah.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </CrmLayout>

    <!-- PAYMENT MODAL -->
    <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showPaymentModal = false"></div>
        <div class="relative bg-white rounded-[2.5rem] w-full max-w-md overflow-hidden shadow-2xl animate-in zoom-in duration-200 p-10">
            <h3 class="text-xl font-black text-slate-900 mb-2 uppercase tracking-tight">Catat Pembayaran</h3>
            <p class="text-xs text-slate-500 mb-8">{{ selectedSchedule?.label }}</p>

            <form @submit.prevent="submitPayment" class="space-y-5">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Jumlah Pembayaran (IDR)</label>
                    <input v-model="paymentForm.amount" type="number" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-black focus:ring-2 focus:ring-blue-600/20" />
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Metode</label>
                    <select v-model="paymentForm.payment_method" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-black focus:ring-2 focus:ring-blue-600/20">
                        <option value="transfer">Transfer Bank</option>
                        <option value="cash">Tunai / Cash</option>
                        <option value="cheque">Cek / Giro</option>
                    </select>
                </div>
                <div v-if="paymentForm.payment_method === 'transfer'">
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Nama Bank & Ref</label>
                    <div class="grid grid-cols-2 gap-3">
                        <input v-model="paymentForm.bank_name" type="text" placeholder="Bank" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-black focus:ring-2 focus:ring-blue-600/20" />
                        <input v-model="paymentForm.reference_number" type="text" placeholder="No. Ref" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-black focus:ring-2 focus:ring-blue-600/20" />
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Catatan</label>
                    <textarea v-model="paymentForm.notes" class="w-full px-5 py-4 bg-slate-50 border-none rounded-2xl text-sm font-black focus:ring-2 focus:ring-blue-600/20" rows="2"></textarea>
                </div>
                <div class="pt-4">
                    <button type="submit" :disabled="paymentForm.processing"
                        class="w-full py-5 bg-blue-600 text-white font-black rounded-[1.5rem] shadow-xl shadow-blue-600/20 hover:-translate-y-1 transition-all active:scale-95 disabled:opacity-50">
                        SIMPAN PEMBAYARAN
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- SPK / SPR PREVIEW MODAL -->
    <teleport to="body">
        <div v-if="showSpkPreview" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showSpkPreview = false"></div>
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[92vh] flex flex-col p-6 md:p-8">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100 shrink-0">
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-widest text-slate-900">Pratinjau Surat Pemesanan Rumah (SPR)</h3>
                        <p class="text-[10px] text-slate-400">Pratinjau dokumen sesuai template DNA Umala/Andara & Pengaturan Control Room.</p>
                    </div>
                    <button @click="showSpkPreview = false" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-400 font-bold">&times;</button>
                </div>

                <!-- Live Document Stream Iframe -->
                <div class="flex-1 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-inner">
                    <iframe :src="`/bookings/${booking.id}/spk/view?html=1`" class="w-full h-full min-h-[500px] border-0 rounded-2xl"></iframe>
                </div>

                <div class="mt-4 flex justify-end gap-3 shrink-0 pt-2">
                    <button @click="showSpkPreview = false" class="px-6 py-3 text-xs font-bold text-slate-500 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all">Tutup</button>
                    <a :href="`/bookings/${booking.id}/spk`" target="_blank" class="px-6 py-3 bg-slate-900 text-white text-xs font-bold rounded-xl shadow-lg hover:shadow-slate-800 transition-all flex items-center gap-2">
                        📥 Download PDF Resmi
                    </a>
                </div>
            </div>
        </div>

        <!-- REGENERATE SCHEDULE MODAL -->
        <div v-if="showScheduleModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showScheduleModal = false"></div>
            <div class="relative bg-white rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl p-8 animate-in zoom-in duration-150">
                <div class="flex justify-between items-center mb-6 pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <span>⚙️</span> <span>Atur Skema & Tenor Cash Bertahap</span>
                    </h3>
                    <button @click="showScheduleModal = false" class="text-slate-400 hover:text-slate-600 font-bold">&times;</button>
                </div>

                <form @submit.prevent="submitRegenSchedule" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Skema Pembayaran</label>
                        <select v-model="regenForm.payment_scheme" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-amber-500/20">
                            <option value="cash_installment">Cash Bertahap / In-House</option>
                            <option value="cash">Cash Keras</option>
                            <option value="kpr">KPR Bank</option>
                        </select>
                    </div>

                    <div v-if="regenForm.payment_scheme === 'cash_installment'">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tenor Cicilan (Bulan / Tahun)</label>
                        <div class="grid grid-cols-2 gap-3">
                            <select v-model.number="regenForm.installment_months" class="px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-amber-500/20">
                                <option :value="6">6 Bulan (0.5 Tahun)</option>
                                <option :value="12">12 Bulan (1 Tahun)</option>
                                <option :value="24">24 Bulan (2 Tahun)</option>
                                <option :value="36">36 Bulan (3 Tahun)</option>
                                <option :value="48">48 Bulan (4 Tahun)</option>
                                <option :value="60">60 Bulan (5 Tahun)</option>
                                <option :value="72">72 Bulan (6 Tahun)</option>
                            </select>
                            <input v-model.number="regenForm.installment_months" type="number" min="1" max="360" placeholder="Custom (Bulan)" class="px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-amber-500/20" />
                        </div>
                        <p class="text-[10px] text-amber-600 mt-1 font-bold">Total Durasi: {{ regenForm.installment_months }} Bulan ({{ (regenForm.installment_months / 12).toFixed(1) }} Tahun)</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Uang Muka / DP (Rp)</label>
                            <input v-model.number="regenForm.dp_amount" type="number" placeholder="0" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tenor DP (Bulan)</label>
                            <input v-model.number="regenForm.dp_installment_months" type="number" placeholder="0" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" />
                        </div>
                    </div>

                    <div class="p-3 bg-amber-50 rounded-xl border border-amber-100 text-[10px] text-amber-800 leading-relaxed font-medium">
                        ⚠️ **Perhatian**: Meng-generate ulang jadwal akan memperbarui seluruh baris tagihan yang belum lunas disesuaikan dengan tenor & skema pembayaran baru.
                    </div>

                    <div class="pt-3 flex justify-end gap-2">
                        <button type="button" @click="showScheduleModal = false" class="px-4 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                        <button type="submit" :disabled="regenForm.processing" class="px-6 py-2.5 bg-amber-600 text-white text-xs font-black rounded-xl shadow-lg hover:bg-amber-700 uppercase">
                            GENERATE ULANG JADWAL
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ADD SCHEDULE ROW MODAL -->
        <div v-if="showAddRowModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showAddRowModal = false"></div>
            <div class="relative bg-white rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-8">
                <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-4">Tambah Baris Tagihan Baru</h3>
                <form @submit.prevent="submitAddRow" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Nama / Label Tagihan</label>
                        <input v-model="addRowForm.label" type="text" placeholder="misal: Balloon Payment Th-1" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" required />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Jumlah Nominal (Rp)</label>
                        <input v-model.number="addRowForm.amount" type="number" placeholder="50000000" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" required />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Tanggal Jatuh Tempo</label>
                        <input v-model="addRowForm.due_date" type="date" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" required />
                    </div>
                    <div class="pt-3 flex justify-end gap-2">
                        <button type="button" @click="showAddRowModal = false" class="px-4 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                        <button type="submit" :disabled="addRowForm.processing" class="px-6 py-2.5 bg-slate-900 text-white text-xs font-black rounded-xl shadow-lg uppercase">
                            SIMPAN BARIS
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- EDIT SCHEDULE ROW MODAL -->
        <div v-if="editingScheduleRow" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="editingScheduleRow = null"></div>
            <div class="relative bg-white rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-8">
                <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-4">Edit Baris Tagihan</h3>
                <form @submit.prevent="submitEditRow" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Nama / Label Tagihan</label>
                        <input v-model="editRowForm.label" type="text" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" required />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Jumlah Nominal (Rp)</label>
                        <input v-model.number="editRowForm.amount" type="number" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" required />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Tanggal Jatuh Tempo</label>
                        <input v-model="editRowForm.due_date" type="date" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" required />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Status Tagihan</label>
                        <select v-model="editRowForm.status" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold">
                            <option value="upcoming">Upcoming (Belum Bayar)</option>
                            <option value="paid">Paid (Lunas)</option>
                            <option value="overdue">Overdue (Jatuh Tempo)</option>
                            <option value="partial">Partial (Sebagian)</option>
                        </select>
                    </div>
                    <div class="pt-3 flex justify-end gap-2">
                        <button type="button" @click="editingScheduleRow = null" class="px-4 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                        <button type="submit" :disabled="editRowForm.processing" class="px-6 py-2.5 bg-blue-600 text-white text-xs font-black rounded-xl shadow-lg uppercase">
                            SIMPAN PERUBAHAN
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </teleport>
</template>
