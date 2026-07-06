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
                    👁️ Tinjau SPK
                </button>
                <a :href="`/bookings/${booking.id}/spk`" target="_blank" class="px-6 py-2.5 bg-slate-900 text-white text-sm font-bold rounded-xl shadow-lg hover:-translate-y-0.5 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Download SPK (PDF)
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
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Data Konsumen</p>
                            <div class="space-y-3">
                                <div>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase">Nama Lengkap</p>
                                    <p class="text-sm font-bold text-slate-900">{{ booking.lead?.name }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase">Telepon / WhatsApp</p>
                                    <p class="text-sm font-bold text-slate-900">{{ booking.lead?.phone }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase">Skema Pembayaran</p>
                                    <span class="inline-block px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-black uppercase mt-1">{{ booking.payment_scheme }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PAYMENT SCHEDULE -->
                <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Jadwal Pembayaran</h2>
                        <span class="text-[10px] bg-blue-50 text-blue-600 px-2 py-1 rounded font-bold uppercase">{{ booking.payment_scheme }}</span>
                    </div>
                    
                    <div v-if="booking.payment_schedules?.length" class="overflow-hidden">
                        <table class="w-full text-left">
                            <thead class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50">
                                <tr>
                                    <th class="py-3">Tagihan</th>
                                    <th class="py-3">Jatuh Tempo</th>
                                    <th class="py-3 text-right">Jumlah</th>
                                    <th class="py-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="schedule in booking.payment_schedules" :key="schedule.id" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <p class="font-black text-slate-900">{{ schedule.label }}</p>
                                        <p class="text-[10px] text-slate-400">#{{ schedule.installment_number }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-slate-500 font-medium">
                                        {{ new Date(schedule.due_date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}
                                    </td>
                                    <td class="px-6 py-4 font-black text-slate-900">
                                        Rp {{ Number(schedule.amount).toLocaleString('id-ID') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span :class="statusColors[schedule.status]" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                            {{ schedule.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button v-if="schedule.status !== 'paid'" @click="openPaymentModal(schedule)" class="px-3 py-1 bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-blue-700">Bayar</button>
                                        <span v-else class="text-[10px] text-emerald-600 font-black uppercase tracking-widest">Lunas ✅</span>
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
                        <div v-for="tx in booking.transactions" :key="tx.id" class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-lg shadow-sm">💰</div>
                                <div>
                                    <p class="text-xs font-black text-slate-900 uppercase tracking-tight">{{ tx.payment_method }} - {{ tx.bank_name || 'Cash' }}</p>
                                    <p class="text-[10px] text-slate-400">{{ tx.notes || 'No notes' }} • {{ new Date(tx.created_at).toLocaleDateString('id-ID') }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-black text-slate-900">Rp {{ Number(tx.amount).toLocaleString('id-ID') }}</p>
                                <button @click="deleteTransaction(tx.id)" class="text-[10px] font-black text-rose-500 uppercase tracking-tighter hover:underline">Hapus</button>
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

    <!-- SPK PREVIEW MODAL -->
    <teleport to="body">
        <div v-if="showSpkPreview" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showSpkPreview = false"></div>
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto p-8 md:p-12">
                <div class="flex justify-between items-center mb-6 pb-4 border-b border-slate-100">
                    <span class="text-xs font-black uppercase tracking-widest text-slate-400">Pratinjau Surat Pemesanan Konsumen (SPK)</span>
                    <button @click="showSpkPreview = false" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-400">&times;</button>
                </div>

                <!-- Printable Document Style Wrapper -->
                <div class="border border-slate-200 p-8 rounded-2xl bg-white text-slate-800 text-xs space-y-6 shadow-inner font-serif leading-relaxed">
                    <!-- SPK Header -->
                    <div class="flex justify-between items-start border-b-2 border-slate-800 pb-4">
                        <div>
                            <h2 class="text-base font-black uppercase tracking-wide text-slate-900">HOMI DEVELOPER CRM</h2>
                            <p class="text-[10px] text-slate-500 font-sans mt-1">Sistem Pemesanan Unit Properti Terintegrasi</p>
                        </div>
                        <div class="text-right">
                            <h3 class="text-sm font-black text-slate-900">{{ booking.spk_number }}</h3>
                            <p class="text-[10px] text-slate-500 font-sans">Tanggal: {{ new Date(booking.booking_date).toLocaleDateString('id-ID', { dateStyle: 'long' }) }}</p>
                        </div>
                    </div>

                    <!-- Section: Buyer Details -->
                    <div class="space-y-2">
                        <h4 class="font-sans font-black uppercase tracking-wider text-slate-900 text-[10px]">I. DATA PEMESAN (KONSUMEN)</h4>
                        <table class="w-full text-left font-sans">
                            <tr class="border-b border-slate-100"><td class="py-1.5 w-1/3 text-slate-500">Nama Lengkap</td><td class="py-1.5 font-bold text-slate-800">{{ booking.lead?.name }}</td></tr>
                            <tr class="border-b border-slate-100"><td class="py-1.5 text-slate-500">No. Telepon / WA</td><td class="py-1.5 font-bold text-slate-800">{{ booking.lead?.phone }}</td></tr>
                            <tr class="border-b border-slate-100"><td class="py-1.5 text-slate-500">Email</td><td class="py-1.5 font-bold text-slate-800">{{ booking.lead?.email || '-' }}</td></tr>
                            <tr class="border-b border-slate-100"><td class="py-1.5 text-slate-500">No. Identitas KTP</td><td class="py-1.5 font-bold text-slate-800">{{ booking.lead?.identity_number || '-' }}</td></tr>
                        </table>
                    </div>

                    <!-- Section: Property Details -->
                    <div class="space-y-2">
                        <h4 class="font-sans font-black uppercase tracking-wider text-slate-900 text-[10px]">II. DATA UNIT PROPERTI</h4>
                        <table class="w-full text-left font-sans font-medium text-slate-700">
                            <tr class="border-b border-slate-100"><td class="py-1.5 w-1/3 text-slate-500">Proyek Perumahan</td><td class="py-1.5 font-bold text-slate-800">{{ booking.unit?.project?.name }}</td></tr>
                            <tr class="border-b border-slate-100"><td class="py-1.5 text-slate-500">Kavling Unit</td><td class="py-1.5 font-bold text-slate-800">Blok {{ booking.unit?.block }} No. {{ booking.unit?.number }}</td></tr>
                            <tr class="border-b border-slate-100"><td class="py-1.5 text-slate-500">Tipe Rumah</td><td class="py-1.5 font-bold text-slate-800">{{ booking.unit?.unit_type?.name }}</td></tr>
                            <tr class="border-b border-slate-100"><td class="py-1.5 text-slate-500">Arah Hadap</td><td class="py-1.5 font-bold text-slate-800 capitalize">{{ booking.unit?.facing_direction || '-' }}</td></tr>
                        </table>
                    </div>

                    <!-- Section: Financial Details -->
                    <div class="space-y-2">
                        <h4 class="font-sans font-black uppercase tracking-wider text-slate-900 text-[10px]">III. RINCIAN HARGA & PEMBAYARAN</h4>
                        <table class="w-full text-left font-sans">
                            <tr class="border-b border-slate-100"><td class="py-1.5 w-1/3 text-slate-500">Harga Jual Unit</td><td class="py-1.5 font-bold text-slate-800 text-right">{{ formatCurrency(booking.base_price) }}</td></tr>
                            <tr class="border-b border-slate-100"><td class="py-1.5 text-slate-500">PPN (11%)</td><td class="py-1.5 font-bold text-slate-800 text-right">{{ formatCurrency(booking.ppn_amount) }}</td></tr>
                            <tr class="border-b border-slate-100"><td class="py-1.5 text-slate-500">BPHTB + AJB & BBN</td><td class="py-1.5 font-bold text-slate-800 text-right">{{ formatCurrency(Number(booking.bphtb_amount) + Number(booking.ajb_bbn_amount)) }}</td></tr>
                            <tr class="border-b border-slate-100"><td class="py-1.5 text-slate-500">Biaya Legalitas Lainnya</td><td class="py-1.5 font-bold text-slate-800 text-right">{{ formatCurrency(booking.other_legal_fees) }}</td></tr>
                            <tr class="border-b border-slate-100 bg-slate-50 font-bold"><td class="py-2 text-slate-900">Total Harga Kesepakatan (All-in)</td><td class="py-2 text-blue-600 text-right text-sm">{{ formatCurrency(booking.final_price) }}</td></tr>
                            <tr class="border-b border-slate-100"><td class="py-1.5 text-slate-500">Booking Fee (Tanda Jadi)</td><td class="py-1.5 font-bold text-emerald-600 text-right">{{ formatCurrency(booking.booking_fee) }}</td></tr>
                            <tr class="border-b border-slate-100"><td class="py-1.5 text-slate-500">Skema Pembayaran</td><td class="py-1.5 font-bold text-slate-800 text-right uppercase">{{ booking.payment_scheme }}</td></tr>
                        </table>
                    </div>

                    <!-- Section: Terms & Conditions -->
                    <div class="space-y-2">
                        <h4 class="font-sans font-black uppercase tracking-wider text-slate-900 text-[10px]">IV. SYARAT & KETENTUAN PEMESANAN</h4>
                        <div class="text-[9px] text-slate-500 font-sans space-y-1.5 pl-2">
                            <p>1. Uang Tanda Jadi (UTJ) atau Booking Fee yang telah dibayarkan bersifat non-refundable (tidak dapat ditarik kembali) apabila pemesan membatalkan transaksi secara sepihak.</p>
                            <p>2. Pemesan wajib melengkapi berkas administrasi dan persyaratan KPR paling lambat 14 hari kerja setelah penandatanganan SPK ini.</p>
                            <p>3. Apabila terjadi penolakan fasilitas KPR oleh pihak Bank, pemesan setuju untuk beralih ke skema pembayaran Cash Installment atau pengembalian dana disesuaikan dengan regulasi developer.</p>
                            <p>4. Harga all-in yang tertera sudah termasuk biaya sertifikat, IMB/PBG, instalasi listrik, dan air bersih standar proyek.</p>
                        </div>
                    </div>

                    <!-- Section: Signatures -->
                    <div class="pt-6 grid grid-cols-2 text-center font-sans text-[10px] gap-8">
                        <div class="space-y-12">
                            <p class="text-slate-500">Pemesan (Konsumen),</p>
                            <div>
                                <p class="font-bold text-slate-900 underline">{{ booking.lead?.name }}</p>
                                <p class="text-[8px] text-slate-400">Tanda Tangan Konsumen</p>
                            </div>
                        </div>
                        <div class="space-y-12">
                            <p class="text-slate-500">Sales Agent Developer,</p>
                            <div>
                                <p class="font-bold text-slate-900 underline">{{ booking.booked_by?.name || 'Staff' }}</p>
                                <p class="text-[8px] text-slate-400">Tanda Tangan Sales</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3">
                    <button @click="showSpkPreview = false" class="px-6 py-3 text-xs font-bold text-slate-500 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all">Tutup</button>
                    <a :href="`/bookings/${booking.id}/spk`" target="_blank" class="px-6 py-3 bg-slate-900 text-white text-xs font-bold rounded-xl shadow-lg hover:shadow-slate-800 transition-all flex items-center gap-2">
                        📥 Download PDF
                    </a>
                </div>
            </div>
        </div>
    </teleport>
</template>
