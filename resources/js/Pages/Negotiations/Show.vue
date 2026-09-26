<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import NegotiationTemplateModal from '@/Components/Negotiations/NegotiationTemplateModal.vue';

const props = defineProps({
    negotiation: Object,
    units: {
        type: Array,
        default: () => [],
    }
});
const nego = computed(() => props.negotiation);

// Edit Form (Pengajuan & Ganti Unit & Jawaban Developer)
const showEditModal = ref(false);
const initialModalTab = ref('page1');

const formDetails = computed(() => props.negotiation.form_details || props.negotiation.form_data || {});
const jawaban = computed(() => formDetails.value.jawaban || {});
const pengajuan = computed(() => formDetails.value.pengajuan || {});

const editForm = useForm({
    unit_id: '',
    client_name: '',
    client_phone: '',
    client_email: '',
    offered_price: '',
    payment_scheme: 'kpr',
    dp_amount: '',
    installment_months: '',
    special_bonus_items: [],
    notes: '',
    developer_sig_name: '',
    developer_sig_title: '',
    status: 'draft',
    counter_price: '',
    counter_notes: '',
});

function openEditModal() {
    initialModalTab.value = 'page1';
    editForm.unit_id = nego.value.unit_id;
    editForm.client_name = nego.value.client_name || '';
    editForm.client_phone = nego.value.client_phone || '';
    editForm.client_email = nego.value.client_email || '';
    editForm.offered_price = nego.value.offered_price || '';
    editForm.payment_scheme = nego.value.payment_scheme || 'kpr';
    editForm.dp_amount = nego.value.dp_amount || '';
    editForm.installment_months = nego.value.installment_months || '';
    editForm.special_bonus_items = Array.isArray(nego.value.special_bonus_items) ? [...nego.value.special_bonus_items] : [];
    editForm.notes = nego.value.notes || '';
    editForm.developer_sig_name = nego.value.developer_sig_name || '';
    editForm.developer_sig_title = nego.value.developer_sig_title || '';
    editForm.status = nego.value.status || 'draft';
    editForm.counter_price = nego.value.counter_price || '';
    editForm.counter_notes = nego.value.counter_notes || '';
    showEditModal.value = true;
}

function openJawabanModal() {
    initialModalTab.value = 'page2';
    openEditModal();
    initialModalTab.value = 'page2';
}

function quickApprove() {
    if (confirm(`Apakah Anda yakin ingin menyetujui pengajuan negosiasi untuk ${nego.value.client_name}? Harga kesepakatan akan dikunci dan siap dilanjutkan ke reservasi/booking.`)) {
        reviewForm.action = 'approve';
        submitReview();
    }
}

function addBonusItem() {
    if (!Array.isArray(editForm.special_bonus_items)) {
        editForm.special_bonus_items = [];
    }
    editForm.special_bonus_items.push('');
}

function removeBonusItem(idx) {
    if (Array.isArray(editForm.special_bonus_items)) {
        editForm.special_bonus_items.splice(idx, 1);
    }
}

const selectedEditUnit = computed(() => {
    return props.units.find(u => u.id == editForm.unit_id) || nego.value.unit;
});

function submitEdit() {
    editForm.put(`/negotiations/${nego.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
        },
    });
}

// Review Form
const reviewForm = useForm({
    action: '',
    counter_price: '',
    counter_notes: '',
});

const showReviewModal = ref(false);
const reviewAction = ref('');

function openReview(action) {
    reviewAction.value = action;
    reviewForm.action = action;
    reviewForm.counter_price = action === 'counter' ? (nego.value.unit_listed_price || '') : '';
    reviewForm.counter_notes = '';
    showReviewModal.value = true;
}

function submitReview() {
    reviewForm.post(`/negotiations/${nego.value.id}/review`, {
        preserveScroll: true,
        onSuccess: () => { showReviewModal.value = false; reviewForm.reset(); },
    });
}

function convertToBooking() {
    router.post(`/negotiations/${nego.value.id}/convert`);
}

// Helpers
function formatCurrency(val) {
    if (!val) return 'Rp 0';
    return 'Rp ' + Number(val).toLocaleString('id-ID');
}

function formatDate(d) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const discountPercent = computed(() => {
    if (!nego.value.offered_price || !nego.value.unit_listed_price) return 0;
    return ((1 - nego.value.offered_price / nego.value.unit_listed_price) * 100).toFixed(1);
});

const statusConfig = {
    draft: { label: 'Draft (Belum Diisi Client)', color: 'bg-slate-100 text-slate-600 border-slate-200', icon: '📝' },
    pending: { label: 'Menunggu Review Developer', color: 'bg-amber-100 text-amber-700 border-amber-300', icon: '⏳' },
    counter_offer: { label: 'Counter Offer Dikirim', color: 'bg-purple-100 text-purple-700 border-purple-300', icon: '🔄' },
    approved: { label: 'Disetujui', color: 'bg-emerald-100 text-emerald-700 border-emerald-300', icon: '✅' },
    rejected: { label: 'Ditolak', color: 'bg-rose-100 text-rose-700 border-rose-300', icon: '❌' },
    expired: { label: 'Kedaluwarsa', color: 'bg-slate-100 text-slate-400 border-slate-200', icon: '⏰' },
};

const paymentLabels = { cash_keras: 'Cash Keras', cash_bertahap: 'Cash Bertahap', kpr: 'KPR Bank' };

function copyLink() {
    navigator.clipboard.writeText(nego.value.token ? `${window.location.origin}/nego/${nego.value.token}` : '');
}

function openPdf() {
    window.open(`/negotiations/${nego.value.id}/pdf`, '_blank');
}

function shareWhatsApp() {
    const link = `${window.location.origin}/nego/${nego.value.token}`;
    const msg = `Halo Bapak/Ibu *${nego.value.client_name}*,\n\nSilakan isi Form Pengajuan Negosiasi untuk unit *${nego.value.unit?.code}* melalui link berikut:\n\n🔗 ${link}\n\nTerima kasih!`;
    const phone = (nego.value.client_phone || '').replace(/[^0-9]/g, '');
    const waPhone = phone.startsWith('0') ? '62' + phone.substring(1) : phone;
    window.open(`https://wa.me/${waPhone}?text=${encodeURIComponent(msg)}`, '_blank');
}
</script>

<template>
    <Head :title="`Negosiasi: ${negotiation.client_name}`" />
    <CrmLayout>
        <template #breadcrumb>
            <Link href="/negotiations" class="text-slate-400 hover:text-slate-600">Negosiasi</Link> / {{ negotiation.client_name }}
        </template>

        <!-- HEADER -->
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 mb-8">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-gradient-to-br from-amber-500 to-orange-500 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg">📋</div>
                <div>
                    <h1 class="text-xl font-black text-slate-900 tracking-tight">{{ nego.client_name }}</h1>
                    <p class="text-xs text-slate-500 mt-0.5">📱 {{ nego.client_phone }} <span v-if="nego.client_email">· 📧 {{ nego.client_email }}</span></p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <span :class="(statusConfig[nego.status] || statusConfig.draft).color" class="px-4 py-2 text-xs font-black rounded-xl border inline-flex items-center gap-1.5">
                    {{ (statusConfig[nego.status] || statusConfig.draft).icon }}
                    {{ (statusConfig[nego.status] || statusConfig.draft).label }}
                </span>
                <Link v-if="nego.status === 'approved' || nego.client_response === 'accepted'" :href="`/reservations/create?negotiation_id=${nego.id}&unit_id=${nego.unit_id}&lead_id=${nego.lead_id}`" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-1.5">
                    <span>🔖</span> Buat Reservasi Unit
                </Link>
                <button @click="openJawabanModal" class="px-4 py-2 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white text-xs font-black rounded-xl shadow-md shadow-purple-500/25 transition-all flex items-center gap-1.5 cursor-pointer">
                    <span>💼</span> Input Jawaban Developer & Setujui
                </button>
                <button @click="openEditModal" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-1.5 cursor-pointer">
                    <span>✏️</span> Edit Form Pengajuan
                </button>
                <button @click="openPdf" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-1.5">📄 Download PDF</button>
                <button @click="copyLink" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-all">📋 Salin Link</button>
                <button @click="shareWhatsApp" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold rounded-xl shadow-lg transition-all flex items-center gap-1.5">💬 Kirim WA</button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- LEFT: Negosiasi Detail -->
            <div class="lg:col-span-2 space-y-6">
                <!-- PRICE COMPARISON -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-900 mb-5">Perbandingan Harga</h3>
                    <div class="grid grid-cols-2 gap-4 mb-5">
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 text-center">
                            <p class="text-[10px] font-black text-slate-400 uppercase">Harga Listing</p>
                            <p class="text-lg font-black text-slate-900 font-mono mt-1">{{ formatCurrency(nego.unit_listed_price) }}</p>
                        </div>
                        <div class="p-4 rounded-2xl border text-center" :class="nego.offered_price ? (nego.offered_price < nego.unit_listed_price ? 'bg-rose-50 border-rose-200' : 'bg-emerald-50 border-emerald-200') : 'bg-slate-50 border-slate-100'">
                            <p class="text-[10px] font-black uppercase" :class="nego.offered_price ? (nego.offered_price < nego.unit_listed_price ? 'text-rose-500' : 'text-emerald-500') : 'text-slate-400'">Harga Diajukan Client</p>
                            <p v-if="nego.offered_price" class="text-lg font-black font-mono mt-1" :class="nego.offered_price < nego.unit_listed_price ? 'text-rose-600' : 'text-emerald-600'">
                                {{ formatCurrency(nego.offered_price) }}
                            </p>
                            <p v-else class="text-sm text-slate-300 italic mt-1">Belum diisi</p>
                            <p v-if="nego.offered_price && nego.offered_price < nego.unit_listed_price" class="text-[10px] font-bold text-rose-500 mt-1">↓ {{ discountPercent }}% dari listing</p>
                        </div>
                    </div>

                    <!-- Counter Offer -->
                    <div v-if="nego.counter_price" class="p-4 bg-purple-50 border border-purple-200 rounded-2xl mb-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-black text-purple-600 uppercase">🔄 Counter Offer Developer</p>
                                <p class="text-lg font-black text-purple-700 font-mono mt-1">{{ formatCurrency(nego.counter_price) }}</p>
                            </div>
                            <div v-if="nego.client_response" class="text-right">
                                <p class="text-[10px] font-black uppercase" :class="nego.client_response === 'accepted' ? 'text-emerald-600' : nego.client_response === 'rejected' ? 'text-rose-600' : 'text-amber-600'">
                                    Respon Client: {{ nego.client_response === 'accepted' ? '✅ Diterima' : nego.client_response === 'rejected' ? '❌ Ditolak' : '🔄 Revisi' }}
                                </p>
                                <p class="text-[9px] text-slate-400 mt-0.5">{{ formatDate(nego.client_response_at) }}</p>
                            </div>
                        </div>
                        <p v-if="nego.counter_notes" class="text-xs text-purple-800 mt-2 italic">{{ nego.counter_notes }}</p>
                    </div>

                    <!-- Progress Bar -->
                    <div v-if="nego.offered_price" class="relative h-3 bg-slate-100 rounded-full overflow-hidden">
                        <div class="absolute inset-y-0 left-0 bg-gradient-to-r from-rose-500 to-amber-500 rounded-full transition-all duration-500" :style="{ width: Math.min(100, (nego.offered_price / nego.unit_listed_price) * 100) + '%' }"></div>
                    </div>
                </div>

                <!-- DETAIL NEGOSIASI -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-900 mb-5">Detail Pengajuan</h3>
                    <div class="grid grid-cols-2 gap-4 text-xs">
                        <div class="space-y-3">
                            <div><span class="font-black text-slate-400 block text-[10px] uppercase">Skema Pembayaran</span><span class="font-bold text-slate-800">{{ paymentLabels[nego.payment_scheme] || nego.payment_scheme || '-' }}</span></div>
                            <div v-if="nego.dp_amount"><span class="font-black text-slate-400 block text-[10px] uppercase">Nominal DP</span><span class="font-bold text-slate-800">{{ formatCurrency(nego.dp_amount) }}</span></div>
                            <div v-if="nego.installment_months"><span class="font-black text-slate-400 block text-[10px] uppercase">Tenor Cicilan</span><span class="font-bold text-slate-800">{{ nego.installment_months }} bulan</span></div>
                        </div>
                        <div class="space-y-3">
                            <div><span class="font-black text-slate-400 block text-[10px] uppercase">Dibuat Oleh</span><span class="font-bold text-slate-800">{{ nego.creator?.name || '-' }}</span></div>
                            <div><span class="font-black text-slate-400 block text-[10px] uppercase">Tanggal Dibuat</span><span class="font-bold text-slate-800">{{ formatDate(nego.created_at) }}</span></div>
                            <div><span class="font-black text-slate-400 block text-[10px] uppercase">Berlaku Sampai</span><span class="font-bold" :class="new Date(nego.expired_at) < new Date() ? 'text-rose-600' : 'text-slate-800'">{{ formatDate(nego.expired_at) }}</span></div>
                        </div>
                    </div>
                    <!-- Special Bonus Items -->
                    <div v-if="nego.special_bonus_items && nego.special_bonus_items.length > 0" class="mt-5 p-4 bg-purple-50 border border-purple-200 rounded-xl space-y-2">
                        <p class="text-[10px] font-black text-purple-800 uppercase flex items-center gap-1.5">
                            🎁 Item Bonus & Benefit Khusus yang Diajukan
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="(item, idx) in nego.special_bonus_items" :key="'show-b-' + idx" class="px-2.5 py-1 bg-white border border-purple-200 rounded-lg text-xs font-bold text-purple-900 shadow-2xs">
                                ✨ {{ item }}
                            </span>
                        </div>
                    </div>

                    <!-- Custom Layout Options & Notes -->
                    <div v-if="nego.custom_layout_options && nego.custom_layout_options.length > 0" class="mt-4 p-4 bg-indigo-50 border border-indigo-200 rounded-xl space-y-2">
                        <p class="text-[10px] font-black text-indigo-700 uppercase">🏗️ Modifikasi Custom Layout & Denah</p>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="(opt, idx) in nego.custom_layout_options" :key="idx" class="px-2.5 py-1 bg-white border border-indigo-200 rounded-lg text-xs font-bold text-slate-800">
                                {{ opt }}
                            </span>
                        </div>
                        <p v-if="nego.custom_layout_notes" class="text-xs text-slate-700 italic pt-1 border-t border-indigo-100">
                            "{{ nego.custom_layout_notes }}"
                        </p>
                    </div>

                    <div v-if="nego.special_requests" class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-xl">
                        <p class="text-[10px] font-black text-amber-600 uppercase mb-1">📝 Permintaan Khusus Client</p>
                        <p class="text-xs text-amber-900 leading-relaxed whitespace-pre-wrap">{{ nego.special_requests }}</p>
                    </div>

                    <div v-if="nego.client_signature" class="mt-4 p-4 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-black text-slate-500 uppercase">Tanda Tangan Digital Pembeli</p>
                            <p class="text-xs font-bold text-slate-800 mt-0.5">{{ nego.client_name }}</p>
                        </div>
                        <img :src="nego.client_signature" class="h-12 max-w-[150px] object-contain border border-slate-200 rounded-lg bg-white p-1" />
                    </div>

                    <div v-if="nego.notes" class="mt-3 p-4 bg-slate-50 border border-slate-100 rounded-xl">
                        <p class="text-[10px] font-black text-slate-400 uppercase mb-1">Catatan</p>
                        <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-wrap">{{ nego.notes }}</p>
                    </div>
                </div>

                <!-- JAWABAN DEVELOPER SECTION -->
                <div class="bg-white border-2 border-purple-200/90 rounded-2xl p-6 shadow-sm relative overflow-hidden space-y-5">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-4 border-b border-purple-100">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center font-black text-lg">
                                🏛️
                            </div>
                            <div>
                                <h3 class="text-xs font-black uppercase tracking-widest text-purple-950">2. Jawaban Resmi Developer</h3>
                                <p class="text-[11px] text-slate-500 font-medium">Keputusan, kesepakatan harga, jadwal termin pembayaran, & klausul developer</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span v-if="nego.status === 'approved'" class="px-3 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-black rounded-lg border border-emerald-200 inline-flex items-center gap-1">
                                ✅ Disetujui Resmi
                            </span>
                            <span v-else-if="nego.status === 'counter_offer'" class="px-3 py-1 bg-purple-100 text-purple-700 text-[10px] font-black rounded-lg border border-purple-200 inline-flex items-center gap-1">
                                🔄 Counter Offer
                            </span>
                            <span v-else class="px-3 py-1 bg-amber-100 text-amber-700 text-[10px] font-black rounded-lg border border-amber-200 inline-flex items-center gap-1">
                                ⏳ Menunggu Keputusan
                            </span>
                            <button @click="openJawabanModal" class="px-3 py-1.5 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white text-xs font-bold rounded-lg shadow-sm transition-all flex items-center gap-1">
                                <span>✏️</span> Input / Edit Jawaban
                            </button>
                        </div>
                    </div>

                    <!-- Info Grid: Cara Bayar & Harga Kesepakatan -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Skema Cara Bayar Disetujui</p>
                            <p class="text-sm font-black text-slate-900 mt-1">{{ jawaban.cara_bayar || paymentLabels[nego.payment_scheme] || 'Cash Bertahap 3X' }}</p>
                        </div>
                        <div class="p-3.5 bg-purple-50/70 rounded-xl border border-purple-200">
                            <p class="text-[10px] font-black text-purple-600 uppercase tracking-wider">Harga Kesepakatan (Deal Price)</p>
                            <p class="text-lg font-black text-purple-900 font-mono mt-1">
                                {{ jawaban.price || (nego.counter_price ? formatCurrency(nego.counter_price) : formatCurrency(nego.offered_price)) }}
                            </p>
                        </div>
                    </div>

                    <!-- Jadwal Termin Pembayaran Jawaban -->
                    <div>
                        <h4 class="text-[11px] font-black uppercase tracking-wider text-slate-700 mb-2.5">
                            📅 Jadwal Termin Pembayaran yang Disetujui Developer
                        </h4>
                        <div class="divide-y divide-slate-100 border border-slate-200 rounded-xl overflow-hidden text-xs bg-slate-50/30">
                            <div class="grid grid-cols-12 p-2.5 bg-slate-100/60 font-black text-[10px] text-slate-500 uppercase tracking-wider">
                                <div class="col-span-4">Termin</div>
                                <div class="col-span-4 text-right">Nominal</div>
                                <div class="col-span-4 text-right">Jatuh Tempo</div>
                            </div>
                            <div class="grid grid-cols-12 p-2.5 items-center">
                                <div class="col-span-4 font-bold text-slate-800">1. Tanda Jadi (Reservasi)</div>
                                <div class="col-span-4 text-right font-black font-mono text-slate-900">{{ jawaban.reservasi || '10.000.000,-' }}</div>
                                <div class="col-span-4 text-right text-slate-500 font-medium">{{ jawaban.reservasi_date || '08 Sept 2026' }}</div>
                            </div>
                            <div class="grid grid-cols-12 p-2.5 items-center">
                                <div class="col-span-4 font-bold text-slate-800">2. Booking Fee (UTJ)</div>
                                <div class="col-span-4 text-right font-black font-mono text-slate-900">{{ jawaban.booking_fee || '7.000.000,-' }} <span class="text-[10px] text-slate-400 font-normal">{{ jawaban.booking_fee_total }}</span></div>
                                <div class="col-span-4 text-right text-slate-500 font-medium">{{ jawaban.booking_fee_date || '24 Sept 2026' }}</div>
                            </div>
                            <div class="grid grid-cols-12 p-2.5 items-center">
                                <div class="col-span-4 font-bold text-slate-800">3. Uang Muka (DP 1)</div>
                                <div class="col-span-4 text-right font-black font-mono text-slate-900">{{ jawaban.dp1_amount || '200.000.000,-' }}</div>
                                <div class="col-span-4 text-right text-slate-500 font-medium">{{ jawaban.dp1_date || '1 Okt 2026' }}</div>
                            </div>
                            <div v-if="jawaban.dp2_amount" class="grid grid-cols-12 p-2.5 items-center">
                                <div class="col-span-4 font-bold text-slate-800">4. Uang Muka (DP 2)</div>
                                <div class="col-span-4 text-right font-black font-mono text-slate-900">{{ jawaban.dp2_amount }}</div>
                                <div class="col-span-4 text-right text-slate-500 font-medium">{{ jawaban.dp2_date || '25 Des 2026' }}</div>
                            </div>
                            <div class="grid grid-cols-12 p-2.5 items-center bg-purple-50/40">
                                <div class="col-span-4 font-black text-purple-950">5. Pelunasan Sisa</div>
                                <div class="col-span-4 text-right font-black font-mono text-purple-900">{{ jawaban.pelunasan_amount || '-' }}</div>
                                <div class="col-span-4 text-right text-slate-500 font-medium">{{ jawaban.pelunasan_date || '25 Des 2027' }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Klausul Ketentuan Developer (Notes) -->
                    <div v-if="jawaban.notes && jawaban.notes.length > 0" class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                        <p class="text-[10px] font-black text-slate-700 uppercase tracking-wider">
                            ⚖️ Klausul & Ketentuan Resmi Developer:
                        </p>
                        <ol class="list-decimal pl-5 space-y-1.5 text-xs text-slate-700 leading-relaxed">
                            <li v-for="(jn, idx) in jawaban.notes" :key="idx" class="pl-1">
                                {{ jn }}
                            </li>
                        </ol>
                    </div>

                    <!-- Lembar TTD Resmi -->
                    <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl">
                        <div class="text-[10px] text-slate-500 font-bold mb-2">{{ jawaban.sig_city_date || 'Jakarta' }}</div>
                        <div class="grid grid-cols-3 gap-2 text-center text-xs">
                            <div class="p-2 bg-white rounded-lg border border-slate-100">
                                <span class="text-[9px] font-bold text-slate-400 block uppercase">1. Pengaju (Client)</span>
                                <p class="font-black text-slate-800 mt-1">{{ jawaban.sig_pengaju_name || nego.client_name }}</p>
                            </div>
                            <div class="p-2 bg-white rounded-lg border border-slate-100">
                                <span class="text-[9px] font-bold text-slate-400 block uppercase">2. Mengetahui</span>
                                <p class="font-black text-slate-800 mt-1">{{ jawaban.sig_mengetahui_name || 'Maulizar' }}</p>
                                <span class="text-[9px] text-emerald-600 font-semibold block">✓ Agent Coord</span>
                            </div>
                            <div class="p-2 bg-white rounded-lg border border-slate-100">
                                <span class="text-[9px] font-bold text-slate-400 block uppercase">3. Menyetujui</span>
                                <p class="font-black text-purple-900 mt-1">{{ jawaban.sig_menyetujui_name || 'Ch. Bramantyo P.' }}</p>
                                <span class="text-[9px] text-emerald-600 font-semibold block">✓ Developer</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Aksi Cepat Setujui -->
                    <div v-if="nego.status !== 'approved'" class="pt-4 border-t border-purple-100 flex flex-wrap gap-2.5 justify-end">
                        <button @click="openJawabanModal" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5">
                            <span>✏️</span> Sesuaikan Nilai Jawaban
                        </button>
                        <button @click="quickApprove" class="px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white text-xs font-black rounded-xl shadow-lg shadow-emerald-500/25 transition-all flex items-center gap-1.5">
                            <span>✅</span> Setujui Negosiasi Ini
                        </button>
                    </div>
                </div>

                <!-- ACTION BUTTONS (Always available for unapproved negotiations) -->
                <div v-if="nego.status !== 'approved' && !nego.booking_id" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-900 mb-5">Aksi Persetujuan Developer</h3>
                    <div class="flex flex-wrap gap-3">
                        <button @click="openJawabanModal" class="flex-1 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-black text-xs rounded-xl shadow-lg shadow-purple-500/25 transition-all flex items-center justify-center gap-1.5">
                            <span>💼</span> Input Jawaban & Setujui
                        </button>
                        <button @click="openReview('approve')" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-xl shadow-lg transition-all">
                            ✅ Setujui Langsung
                        </button>
                        <button @click="openReview('counter')" class="flex-1 py-3 bg-purple-600 hover:bg-purple-700 text-white font-black text-xs rounded-xl shadow-lg transition-all">
                            🔄 Counter Offer
                        </button>
                        <button @click="openReview('reject')" class="py-3 px-5 bg-rose-600 hover:bg-rose-700 text-white font-black text-xs rounded-xl shadow-lg transition-all">
                            ❌ Tolak
                        </button>
                    </div>
                </div>

                <div v-if="nego.status === 'approved' && !nego.booking_id" class="bg-emerald-50 border border-emerald-200 rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-black text-emerald-900">✅ Negosiasi Disetujui</h3>
                            <p class="text-xs text-emerald-700 mt-0.5">Lanjutkan ke proses booking untuk unit ini.</p>
                        </div>
                        <button @click="convertToBooking" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-xl shadow-lg transition-all flex items-center gap-2">
                            📝 Lanjutkan ke Booking
                        </button>
                    </div>
                </div>

                <div v-if="nego.booking_id" class="bg-blue-50 border border-blue-200 rounded-2xl p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-black text-blue-900">📝 Sudah Dikonversi ke Booking</h3>
                            <p class="text-xs text-blue-700 mt-0.5">Negosiasi ini sudah dilanjutkan ke proses booking.</p>
                        </div>
                        <Link :href="`/bookings/${nego.booking_id}`" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-black text-xs rounded-xl shadow-lg transition-all">
                            Lihat Booking →
                        </Link>
                    </div>
                </div>
            </div>

            <!-- RIGHT SIDEBAR -->
            <div class="space-y-6">
                <!-- Unit Info -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-900 mb-4">🏠 Unit Diminati</h3>
                    <div class="space-y-3 text-xs">
                        <div><span class="font-black text-slate-400 block text-[10px] uppercase">Kode Unit</span><span class="font-black text-blue-600 text-sm">{{ nego.unit?.code || '-' }}</span></div>
                        <div><span class="font-black text-slate-400 block text-[10px] uppercase">Proyek</span><span class="font-bold text-slate-800">{{ nego.project?.name || '-' }}</span></div>
                        <div><span class="font-black text-slate-400 block text-[10px] uppercase">Tipe</span><span class="font-bold text-slate-800">{{ nego.unit?.unit_type?.name || '-' }}</span></div>
                        <div><span class="font-black text-slate-400 block text-[10px] uppercase">Harga Listing</span><span class="font-black text-slate-900 text-sm font-mono">{{ formatCurrency(nego.unit_listed_price) }}</span></div>
                    </div>
                </div>

                <!-- Lead Link -->
                <div v-if="nego.lead_id" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-900 mb-4">👤 Lead Terkait</h3>
                    <p class="text-sm font-bold text-slate-800">{{ nego.lead?.name || nego.client_name }}</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">📱 {{ nego.lead?.phone || nego.client_phone }}</p>
                    <Link :href="`/leads/${nego.lead_id}`" class="mt-3 inline-block px-4 py-2 bg-blue-50 text-blue-600 text-[10px] font-bold rounded-xl hover:bg-blue-100 transition-all">
                        Lihat Detail Lead →
                    </Link>
                </div>

                <!-- Review History -->
                <div v-if="nego.reviewed_at" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-900 mb-4">📜 Riwayat Review</h3>
                    <div class="space-y-2 text-xs">
                        <div class="p-3 bg-slate-50 rounded-xl">
                            <p class="text-[10px] font-black text-slate-400 uppercase">Direview oleh</p>
                            <p class="font-bold text-slate-800">{{ nego.reviewer?.name || '-' }}</p>
                            <p class="text-[9px] text-slate-400 mt-0.5">{{ formatDate(nego.reviewed_at) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- REVIEW MODAL -->
        <teleport to="body">
            <div v-if="showReviewModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 space-y-4 animate-in zoom-in duration-150">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <h3 class="text-base font-black text-slate-900">
                            {{ reviewAction === 'approve' ? '✅ Setujui Negosiasi' : reviewAction === 'counter' ? '🔄 Counter Offer' : '❌ Tolak Negosiasi' }}
                        </h3>
                        <button @click="showReviewModal = false" class="text-slate-400 hover:text-slate-700">✕</button>
                    </div>

                    <form @submit.prevent="submitReview" class="space-y-4">
                        <div class="p-4 bg-slate-50 rounded-2xl text-xs space-y-2">
                            <div class="flex justify-between"><span class="text-slate-500 font-bold">Client:</span><span class="font-black text-slate-900">{{ nego.client_name }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-500 font-bold">Harga Diajukan:</span><span class="font-black text-rose-600">{{ formatCurrency(nego.offered_price) }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-500 font-bold">Harga Listing:</span><span class="font-bold text-slate-700">{{ formatCurrency(nego.unit_listed_price) }}</span></div>
                        </div>

                        <div v-if="reviewAction === 'counter'">
                            <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Harga Counter Offer *</label>
                            <input v-model="reviewForm.counter_price" type="number" required min="1" placeholder="Masukkan harga penawaran balik" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-1 focus:ring-purple-500" />
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">{{ reviewAction === 'reject' ? 'Alasan Penolakan' : 'Catatan (Opsional)' }}</label>
                            <textarea v-model="reviewForm.counter_notes" rows="3" :placeholder="reviewAction === 'reject' ? 'Jelaskan alasan penolakan...' : 'Catatan untuk client...'" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-1 focus:ring-blue-500"></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                            <button type="button" @click="showReviewModal = false" class="px-4 py-2.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold">Batal</button>
                            <button type="submit" :disabled="reviewForm.processing" :class="reviewAction === 'approve' ? 'bg-emerald-600 hover:bg-emerald-700' : reviewAction === 'counter' ? 'bg-purple-600 hover:bg-purple-700' : 'bg-rose-600 hover:bg-rose-700'" class="px-6 py-2.5 text-white rounded-xl text-xs font-black shadow-lg transition-all disabled:opacity-40">
                                {{ reviewAction === 'approve' ? '✅ Setujui' : reviewAction === 'counter' ? '🔄 Kirim Counter Offer' : '❌ Tolak Negosiasi' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <!-- NEGOTIATION TEMPLATE MODAL -->
        <NegotiationTemplateModal 
            :is-open="showEditModal" 
            :negotiation="nego" 
            :units="units" 
            :initial-tab="initialModalTab"
            @close="showEditModal = false" 
            @saved="router.reload({ preserveScroll: true })" 
        />
    </CrmLayout>
</template>
