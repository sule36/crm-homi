<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import TransactionTabs from '@/Components/Transactions/TransactionTabs.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    bookings: Object,
    stats: {
        type: Object,
        default: () => ({ total: 0, approved: 0, pending: 0, cancelled: 0, total_value: 0, total_collected: 0 }),
    },
    readyNegotiations: {
        type: Array,
        default: () => [],
    },
    readyReservations: {
        type: Array,
        default: () => [],
    },
    projects: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const projectFilter = ref(props.filters?.project_id || '');
const schemeFilter = ref(props.filters?.payment_scheme || '');

function applyFilters() {
    router.get('/bookings', {
        search: search.value,
        status: statusFilter.value,
        project_id: projectFilter.value,
        payment_scheme: schemeFilter.value,
    }, { preserveState: true, replace: true });
}

let timeout;
watch(search, () => {
    clearTimeout(timeout);
    timeout = setTimeout(applyFilters, 400);
});

watch([statusFilter, projectFilter, schemeFilter], () => {
    applyFilters();
});

const statusColors = {
    pending: 'bg-amber-100 text-amber-700 border-amber-200',
    approved: 'bg-emerald-100 text-emerald-700 border-emerald-200',
    cancelled: 'bg-rose-100 text-rose-700 border-rose-200',
    completed: 'bg-blue-100 text-blue-700 border-blue-200',
};

const statusLabels = {
    pending: '⏳ Pending Approval',
    approved: '✅ Approved (Booked)',
    cancelled: '❌ Dibatalkan',
    completed: '🏆 Lunas / Selesai',
};

const schemeLabels = {
    cash: '💰 Cash Keras',
    cash_installment: '📅 Cash Bertahap',
    kpr: '🏦 KPR Bank',
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(value || 0));
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

function getBookingPaymentProgress(booking) {
    const finalPrice = Math.max(0, Number(booking.final_price || 0));
    const txSum = (booking.transactions || []).reduce((acc, tx) => acc + Number(tx.amount || 0), 0);
    const schedules = booking.payment_schedules || [];
    const paidSchedules = schedules.filter(s => s.status === 'paid');
    const paidSchedSum = paidSchedules.reduce((acc, s) => acc + Number(s.amount || 0), 0);
    const paidAmount = Math.max(txSum, paidSchedSum);
    const pct = finalPrice > 0 ? Math.min(100, Math.round((paidAmount / finalPrice) * 100)) : 0;
    return {
        paidAmount,
        pct,
        paidCount: paidSchedules.length,
        totalCount: schedules.length,
    };
}

const hasReadyQueue = computed(() => {
    return (props.readyNegotiations?.length || 0) > 0 || (props.readyReservations?.length || 0) > 0;
});

function convertNegotiation(negoId) {
    router.post(`/negotiations/${negoId}/convert`);
}

const deleteBooking = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus transaksi booking ini? Unit properti terkait akan otomatis dikembalikan statusnya menjadi Available.')) {
        router.delete(`/bookings/${id}`);
    }
};
</script>

<template>
    <Head title="Booking & SPR" />
    <CrmLayout>
        <template #breadcrumb>Booking & SPR</template>

        <TransactionTabs activeTab="bookings" />

        <!-- HEADER -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">Daftar Booking & SPR</h1>
                    <span class="px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider bg-blue-100 text-blue-700 border border-blue-200 rounded-md">
                        Tahap 3 · Closing & Jadwal Tagihan
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                    Kelola transaksi pemesanan unit, penerbitan Surat Pesanan Rumah (SPR), dan pantau progres pembayaran terhubung dari Reservasi & Negosiasi.
                </p>
            </div>
            <Link href="/bookings/create" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-blue-500/20 hover:-translate-y-0.5 transition-all flex items-center gap-2 shrink-0">
                <span>+</span> Tambah Booking & SPR Baru
            </Link>
        </div>

        <!-- KPI SUMMARY CARDS -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="bg-white border border-slate-200 p-4 rounded-2xl shadow-2xs">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Booking</div>
                <div class="text-2xl font-black text-slate-900 mt-1">{{ stats.total || bookings.total || 0 }}</div>
                <p class="text-[10px] text-slate-400 font-semibold mt-0.5">Seluruh dokumen pemesanan</p>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 p-4 rounded-2xl shadow-2xs">
                <div class="text-[10px] font-black text-emerald-700 uppercase tracking-widest">✅ Approved (Booked)</div>
                <div class="text-2xl font-black text-emerald-800 mt-1">{{ stats.approved || 0 }}</div>
                <p class="text-[10px] text-emerald-700 font-bold mt-0.5 font-mono">{{ formatCurrency(stats.total_value) }}</p>
            </div>
            <div class="bg-amber-50 border border-amber-200 p-4 rounded-2xl shadow-2xs">
                <div class="text-[10px] font-black text-amber-700 uppercase tracking-widest">⏳ Menunggu Approval</div>
                <div class="text-2xl font-black text-amber-800 mt-1">{{ stats.pending || 0 }}</div>
                <p class="text-[10px] text-amber-700 font-semibold mt-0.5">Unit status Hold sementara</p>
            </div>
            <div class="bg-blue-50 border border-blue-200 p-4 rounded-2xl shadow-2xs">
                <div class="text-[10px] font-black text-blue-700 uppercase tracking-widest">💰 Realisasi Kas Masuk</div>
                <div class="text-lg sm:text-xl font-black text-blue-800 mt-1 font-mono truncate">{{ formatCurrency(stats.total_collected) }}</div>
                <p class="text-[10px] text-blue-600 font-semibold mt-0.5">Total UTJ, DP & cicilan terbayar</p>
            </div>
        </div>

        <!-- READY TO BOOK QUEUE (FROM STAGE 1 RESERVATION & STAGE 2 NEGOTIATION) -->
        <div v-if="hasReadyQueue" class="mb-6 p-4 bg-gradient-to-r from-indigo-50/90 via-blue-50/80 to-emerald-50/80 border border-indigo-200 rounded-2xl shadow-xs space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-sm shadow-xs shrink-0">⚡</span>
                    <div>
                        <h4 class="font-black text-xs sm:text-sm text-slate-900">
                            Siap Diterbitkan Booking & SPR ({{ (readyNegotiations?.length || 0) + (readyReservations?.length || 0) }} Transaksi)
                        </h4>
                        <p class="text-[11px] text-slate-600">
                            Daftar Negosiasi yang telah <strong>Disetujui</strong> atau Reservasi <strong>Aktif</strong> yang siap naik ke tahap Booking & penerbitan SPR resmi dengan 1 klik.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2.5">
                <!-- Ready from Approved Negotiations -->
                <div
                    v-for="nego in readyNegotiations"
                    :key="'rn-' + nego.id"
                    class="p-3 bg-white rounded-xl border border-purple-200 flex items-center justify-between gap-3 shadow-2xs"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="px-1.5 py-0.5 bg-purple-100 text-purple-800 rounded text-[9px] font-black uppercase">🤝 Deal Nego</span>
                            <span class="text-[10px] font-mono font-bold text-slate-500 truncate">{{ nego.negotiation_number }}</span>
                        </div>
                        <p class="text-xs font-black text-slate-900 truncate mt-1">{{ nego.client_name }}</p>
                        <p class="text-[10px] text-slate-500 font-semibold truncate">
                            {{ nego.unit?.label || '-' }} · <span class="text-emerald-700 font-mono font-bold">{{ formatCurrency(nego.counter_price || nego.offered_price || nego.unit_listed_price) }}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="convertNegotiation(nego.id)"
                        class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-[10px] font-black uppercase tracking-wider shrink-0 transition-all shadow-xs cursor-pointer flex items-center gap-1"
                    >
                        <span>📝</span> <span>Terbitkan SPR</span>
                    </button>
                </div>

                <!-- Ready from Active Reservations -->
                <div
                    v-for="res in readyReservations"
                    :key="'rr-' + res.id"
                    class="p-3 bg-white rounded-xl border border-emerald-200 flex items-center justify-between gap-3 shadow-2xs"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="px-1.5 py-0.5 bg-emerald-100 text-emerald-800 rounded text-[9px] font-black uppercase">🔖 Reservasi Aktif</span>
                            <span class="text-[10px] font-mono font-bold text-slate-500 truncate">{{ res.reservation_number }}</span>
                        </div>
                        <p class="text-xs font-black text-slate-900 truncate mt-1">{{ res.client_name }}</p>
                        <p class="text-[10px] text-slate-500 font-semibold truncate">
                            {{ res.unit?.label || '-' }} · UTJ <span class="text-emerald-700 font-mono font-bold">{{ formatCurrency(res.amount) }}</span>
                        </p>
                    </div>
                    <Link
                        :href="`/reservations/${res.id}/convert`"
                        class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-[10px] font-black uppercase tracking-wider shrink-0 transition-all shadow-xs flex items-center gap-1"
                    >
                        <span>➡️</span> <span>Naik Booking</span>
                    </Link>
                </div>
            </div>
        </div>

        <!-- SEARCH & MULTI-FILTER -->
        <div class="bg-white rounded-2xl border border-slate-200 p-4 mb-6 flex flex-wrap gap-3 items-center shadow-2xs">
            <div class="relative flex-1 min-w-[220px]">
                <input v-model="search" type="text" placeholder="Cari No. SPR / Nama Pembeli / HP / Unit..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-2 focus:ring-blue-500/20" />
                <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <select v-model="projectFilter" class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                <option value="">Semua Proyek</option>
                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <select v-model="schemeFilter" class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                <option value="">Semua Skema Bayar</option>
                <option value="kpr">🏦 KPR Bank</option>
                <option value="cash_installment">📅 Cash Bertahap</option>
                <option value="cash">💰 Cash Keras</option>
            </select>
            <select v-model="statusFilter" class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                <option value="">Semua Status</option>
                <option value="pending">⏳ Pending</option>
                <option value="approved">✅ Approved</option>
                <option value="completed">🏆 Completed</option>
                <option value="cancelled">❌ Cancelled</option>
            </select>
        </div>

        <!-- TABLE -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-x-auto max-w-full touch-pan-x">
            <table class="w-full text-left border-collapse min-w-[920px]">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-100">
                        <th class="px-5 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider">No. SPR & Relasi Asal</th>
                        <th class="px-5 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider">Pemesan & Sales</th>
                        <th class="px-5 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider">Unit Properti</th>
                        <th class="px-5 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider">Skema & Progres Bayar</th>
                        <th class="px-5 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider text-right">Harga Kesepakatan</th>
                        <th class="px-5 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider text-center">Status</th>
                        <th class="px-5 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="booking in bookings.data" :key="booking.id" class="hover:bg-slate-50/60 transition-colors group">
                        <!-- NO SPR & CONNECTED ORIGIN BADGES -->
                        <td class="px-5 py-4">
                            <Link :href="`/bookings/${booking.id}`" class="text-xs font-mono font-black text-blue-700 hover:underline block">
                                {{ booking.spk_number }}
                            </Link>
                            <p class="text-[10px] text-slate-400 font-semibold mt-0.5">📅 {{ formatDate(booking.booking_date) }}</p>
                            <div v-if="booking.reservation || booking.negotiation" class="flex flex-wrap items-center gap-1 mt-1.5">
                                <Link
                                    v-if="booking.reservation"
                                    :href="`/reservations/${booking.reservation.id}`"
                                    class="px-1.5 py-0.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 rounded text-[9px] font-bold inline-flex items-center gap-0.5 transition-colors"
                                    title="Lihat Reservasi UTJ Terkait"
                                >
                                    <span>🔖</span>
                                    <span>{{ booking.reservation.reservation_number || 'Reservasi' }}</span>
                                </Link>
                                <Link
                                    v-if="booking.negotiation"
                                    :href="`/negotiations/${booking.negotiation.id}`"
                                    class="px-1.5 py-0.5 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 rounded text-[9px] font-bold inline-flex items-center gap-0.5 transition-colors"
                                    title="Lihat Dokumen Negosiasi Terkait"
                                >
                                    <span>🤝</span>
                                    <span>{{ booking.negotiation.negotiation_number || 'Negosiasi' }}</span>
                                </Link>
                            </div>
                        </td>

                        <!-- PEMESAN & SALES -->
                        <td class="px-5 py-4">
                            <div class="flex items-start gap-2.5">
                                <div class="w-8 h-8 bg-blue-100 rounded-xl flex items-center justify-center text-xs font-black text-blue-600 shrink-0 mt-0.5">
                                    {{ (booking.lead?.name || 'K').charAt(0) }}
                                </div>
                                <div class="min-w-0">
                                    <Link
                                        v-if="booking.lead_id"
                                        :href="`/leads/${booking.lead_id}`"
                                        class="text-sm font-black text-slate-800 hover:text-blue-600 transition-colors block truncate"
                                    >
                                        {{ booking.lead?.name || 'Konsumen' }}
                                    </Link>
                                    <span v-else class="text-sm font-black text-slate-800 block truncate">{{ booking.lead?.name || 'Konsumen' }}</span>
                                    <p v-if="booking.lead?.phone" class="text-[10px] text-slate-400 font-semibold">📱 {{ booking.lead.phone }}</p>
                                    <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                        <span class="text-[10px] text-slate-500 font-medium">👤 {{ booking.booked_by?.name || booking.bookedBy?.name || '-' }}</span>
                                        <span v-if="booking.inhouse_pic?.name || booking.inhousePic?.name" class="text-[10px] text-blue-600 font-bold">· 🏠 {{ booking.inhouse_pic?.name || booking.inhousePic?.name }}</span>
                                    </div>
                                    <span v-if="booking.lead?.deleted_at" class="text-[9px] font-bold text-rose-500 bg-rose-50 px-1.5 py-0.5 rounded w-fit mt-0.5 inline-block">Lead Dihapus</span>
                                </div>
                            </div>
                        </td>

                        <!-- UNIT PROPERTI -->
                        <td class="px-5 py-4">
                            <p class="text-xs font-black text-slate-800">
                                {{ booking.unit?.label || (`Blok ${booking.unit?.block || ''} No. ${booking.unit?.number || ''}`) }}
                            </p>
                            <p class="text-[10px] text-slate-500 font-semibold mt-0.5">
                                {{ booking.unit?.project?.name || booking.unit?.project?.code || '-' }}
                                <span v-if="booking.unit?.unit_type?.name">· {{ booking.unit.unit_type.name }}</span>
                            </p>
                        </td>

                        <!-- SKEMA & PROGRES BAYAR -->
                        <td class="px-5 py-4">
                            <div class="space-y-1.5 max-w-[190px]">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[10px] font-black bg-slate-100 text-slate-700 px-2 py-0.5 rounded-md">
                                        {{ schemeLabels[booking.payment_scheme] || booking.payment_scheme }}
                                    </span>
                                    <span v-if="booking.installment_months > 0" class="text-[10px] font-bold text-slate-400">
                                        {{ booking.installment_months }}x
                                    </span>
                                </div>
                                <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div
                                        class="h-full bg-gradient-to-r from-blue-500 to-emerald-500 rounded-full transition-all"
                                        :style="{ width: `${getBookingPaymentProgress(booking).pct}%` }"
                                    ></div>
                                </div>
                                <div class="flex items-center justify-between text-[10px]">
                                    <span class="font-bold text-slate-500">
                                        {{ getBookingPaymentProgress(booking).paidCount }}/{{ getBookingPaymentProgress(booking).totalCount }} Termin
                                    </span>
                                    <span class="font-black text-emerald-700 font-mono">
                                        {{ getBookingPaymentProgress(booking).pct }}%
                                    </span>
                                </div>
                            </div>
                        </td>

                        <!-- HARGA KESEPAKATAN -->
                        <td class="px-5 py-4 text-right">
                            <span class="text-sm font-black text-slate-900 font-mono block">{{ formatCurrency(booking.final_price) }}</span>
                            <span
                                v-if="Number(booking.ppn_amount) === 0 && (Number(booking.bphtb_amount) + Number(booking.ajb_bbn_amount)) === 0"
                                class="text-[9px] font-black text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded mt-0.5 inline-block"
                            >
                                ✨ Free All-in
                            </span>
                        </td>

                        <!-- STATUS -->
                        <td class="px-5 py-4 text-center">
                            <span :class="statusColors[booking.status]" class="inline-block px-2.5 py-1 rounded-lg border text-[10px] font-black uppercase tracking-wider">
                                {{ statusLabels[booking.status] || booking.status }}
                            </span>
                        </td>

                        <!-- AKSI -->
                        <td class="px-5 py-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <a
                                    :href="`/bookings/${booking.id}/spk`"
                                    target="_blank"
                                    title="Download / Cetak Dokumen SPR PDF"
                                    class="px-2.5 py-1.5 rounded-xl bg-slate-900 text-white hover:bg-slate-800 text-[10px] font-bold transition-all inline-flex items-center gap-1"
                                >
                                    <span>📄</span> <span>SPR</span>
                                </a>
                                <Link
                                    :href="`/bookings/${booking.id}`"
                                    title="Kelola Booking, Jadwal & Kwitansi"
                                    class="px-3 py-1.5 rounded-xl bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-600 hover:text-white text-[10px] font-black transition-all"
                                >
                                    Detail
                                </Link>
                                <button
                                    @click="deleteBooking(booking.id)"
                                    title="Hapus Booking"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-rose-50 text-rose-500 border border-rose-200 hover:bg-rose-600 hover:text-white transition-all cursor-pointer"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!bookings.data.length">
                        <td colspan="7" class="px-6 py-20 text-center">
                            <div class="text-4xl mb-4">📋</div>
                            <h3 class="text-lg font-bold text-slate-700">Belum ada booking</h3>
                            <p class="text-sm text-slate-400">Semua transaksi booking dan dokumen SPR akan muncul di sini.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <div v-if="bookings.links && bookings.links.length > 3" class="flex justify-center gap-1 mt-8">
            <Link v-for="link in bookings.links" :key="link.label" :href="link.url || '#'"
                :class="[link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-slate-600 hover:bg-slate-50', !link.url ? 'opacity-40 pointer-events-none' : '']"
                class="px-3.5 py-2 text-xs font-bold rounded-lg border border-slate-200 transition-all"
                v-html="link.label" />
        </div>
    </CrmLayout>
</template>
