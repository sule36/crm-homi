<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    negotiation: Object,
    settings: Object,
});

const page = usePage();
const flash = computed(() => page.props.flash || {});
const nego = computed(() => props.negotiation);

const isExpired = computed(() => nego.value.expired_at && new Date(nego.value.expired_at) < new Date());
const isSubmitted = computed(() => ['pending', 'reviewed', 'approved', 'rejected'].includes(nego.value.status));
const isCounterOffer = computed(() => nego.value.status === 'counter_offer');
const isDraft = computed(() => nego.value.status === 'draft');

// Submit Form
const form = useForm({
    client_name: nego.value.client_name || '',
    client_phone: nego.value.client_phone || '',
    client_email: nego.value.client_email || '',
    offered_price: nego.value.offered_price || '',
    payment_scheme: nego.value.payment_scheme || 'kpr',
    dp_amount: nego.value.dp_amount || '',
    installment_months: nego.value.installment_months || '',
    special_requests: nego.value.special_requests || '',
    notes: nego.value.notes || '',
});

function submitForm() {
    form.post(`/nego/${nego.value.token}`, { preserveScroll: true });
}

// Counter Response Form
const counterForm = useForm({
    response: '',
    offered_price: '',
    notes: '',
});

function respondToCounter(response) {
    counterForm.response = response;
    if (response === 'accepted') {
        counterForm.offered_price = nego.value.counter_price;
    }
    counterForm.post(`/nego/${nego.value.token}/respond`, { preserveScroll: true });
}

function submitRevised() {
    counterForm.response = 'revised';
    counterForm.post(`/nego/${nego.value.token}/respond`, { preserveScroll: true });
}

// Helpers
function formatCurrency(val) {
    if (!val) return 'Rp 0';
    return 'Rp ' + Number(val).toLocaleString('id-ID');
}
</script>

<template>
    <Head :title="`Form Negosiasi - ${negotiation.unit?.code || 'Unit'}`" />

    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-slate-100">
        <!-- HEADER -->
        <div class="bg-white border-b border-slate-200 shadow-sm">
            <div class="max-w-2xl mx-auto px-6 py-5 flex items-center gap-4">
                <div v-if="settings?.company_logo" class="shrink-0">
                    <img :src="`/storage/${settings.company_logo}`" class="h-10 max-w-[140px] object-contain" />
                </div>
                <div v-else class="w-10 h-10 bg-gradient-to-br from-slate-800 to-slate-900 rounded-xl flex items-center justify-center text-white font-black text-sm shrink-0">H</div>
                <div>
                    <p class="text-sm font-black text-slate-900">{{ settings?.company_name || 'Homi Developer' }}</p>
                    <p class="text-[10px] text-slate-400 font-bold">Form Pengajuan Negosiasi</p>
                </div>
            </div>
        </div>

        <div class="max-w-2xl mx-auto px-6 py-8 space-y-6">
            <!-- UNIT INFO CARD -->
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center text-white text-xl shadow-lg shrink-0">🏠</div>
                    <div class="flex-1">
                        <p class="text-[10px] font-black text-blue-600 uppercase tracking-widest">Unit Diminati</p>
                        <h2 class="text-lg font-black text-slate-900 mt-0.5">{{ nego.unit?.code || 'Unit' }}</h2>
                        <p class="text-xs text-slate-500 mt-1">{{ nego.project?.name || '-' }} · {{ nego.unit?.unit_type?.name || '' }}</p>
                        <div class="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl">
                            <span class="text-[10px] font-black text-slate-400 uppercase">Harga Listing:</span>
                            <span class="text-sm font-black text-slate-900 font-mono">{{ formatCurrency(nego.unit_listed_price) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EXPIRED STATE -->
            <div v-if="isExpired && nego.status !== 'approved'" class="bg-rose-50 border border-rose-200 rounded-2xl p-8 text-center">
                <div class="text-4xl mb-3">⏰</div>
                <h3 class="text-base font-black text-rose-900">Form Negosiasi Kedaluwarsa</h3>
                <p class="text-xs text-rose-600 mt-1">Form ini sudah melewati batas waktu. Silakan hubungi agent Anda untuk form baru.</p>
            </div>

            <!-- SUCCESS STATE (after submit) -->
            <div v-else-if="flash.success && !isCounterOffer" class="bg-emerald-50 border border-emerald-200 rounded-2xl p-8 text-center">
                <div class="text-4xl mb-3">✅</div>
                <h3 class="text-base font-black text-emerald-900">{{ flash.success }}</h3>
                <p class="text-xs text-emerald-600 mt-2">Tim developer akan meninjau pengajuan Anda. Anda bisa kembali ke halaman ini untuk melihat status terbaru.</p>
            </div>

            <!-- SUBMITTED STATE - Tracking -->
            <div v-else-if="isSubmitted && !isCounterOffer" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm text-center">
                    <div class="text-4xl mb-3">{{ nego.status === 'approved' ? '✅' : nego.status === 'rejected' ? '❌' : '⏳' }}</div>
                    <h3 class="text-base font-black text-slate-900">
                        {{ nego.status === 'approved' ? 'Negosiasi Disetujui!' : nego.status === 'rejected' ? 'Negosiasi Ditolak' : 'Pengajuan Sedang Ditinjau' }}
                    </h3>
                    <p class="text-xs text-slate-500 mt-2">
                        {{ nego.status === 'approved' ? 'Selamat! Harga yang Anda ajukan telah disetujui oleh developer. Agent Anda akan menghubungi untuk proses selanjutnya.' :
                           nego.status === 'rejected' ? 'Maaf, pengajuan Anda tidak dapat kami terima saat ini. Hubungi agent Anda untuk informasi lebih lanjut.' :
                           'Tim developer sedang meninjau pengajuan Anda. Anda akan dihubungi dalam 1x24 jam.' }}
                    </p>
                </div>

                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-900 mb-4">Ringkasan Pengajuan Anda</h3>
                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between p-3 bg-slate-50 rounded-xl"><span class="text-slate-500 font-bold">Harga Diajukan</span><span class="font-black text-slate-900 font-mono">{{ formatCurrency(nego.offered_price) }}</span></div>
                        <div class="flex justify-between p-3 bg-slate-50 rounded-xl"><span class="text-slate-500 font-bold">Skema Pembayaran</span><span class="font-bold text-slate-800">{{ { cash_keras: 'Cash Keras', cash_bertahap: 'Cash Bertahap', kpr: 'KPR Bank' }[nego.payment_scheme] || '-' }}</span></div>
                        <div v-if="nego.dp_amount" class="flex justify-between p-3 bg-slate-50 rounded-xl"><span class="text-slate-500 font-bold">DP</span><span class="font-bold text-slate-800 font-mono">{{ formatCurrency(nego.dp_amount) }}</span></div>
                        <div v-if="nego.installment_months" class="flex justify-between p-3 bg-slate-50 rounded-xl"><span class="text-slate-500 font-bold">Tenor</span><span class="font-bold text-slate-800">{{ nego.installment_months }} bulan</span></div>
                    </div>
                </div>
            </div>

            <!-- COUNTER OFFER STATE -->
            <div v-else-if="isCounterOffer" class="space-y-4">
                <div class="bg-purple-50 border border-purple-200 rounded-2xl p-6 shadow-sm">
                    <div class="text-center mb-5">
                        <div class="text-4xl mb-2">🔄</div>
                        <h3 class="text-base font-black text-purple-900">Counter Offer dari Developer</h3>
                        <p class="text-xs text-purple-600 mt-1">Developer memberikan penawaran balik untuk unit ini.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="p-4 bg-white rounded-xl text-center border border-purple-100">
                            <p class="text-[10px] font-black text-slate-400 uppercase">Harga Anda</p>
                            <p class="text-base font-black text-slate-600 font-mono line-through mt-1">{{ formatCurrency(nego.offered_price) }}</p>
                        </div>
                        <div class="p-4 bg-purple-100 rounded-xl text-center border border-purple-200">
                            <p class="text-[10px] font-black text-purple-600 uppercase">Penawaran Developer</p>
                            <p class="text-base font-black text-purple-800 font-mono mt-1">{{ formatCurrency(nego.counter_price) }}</p>
                        </div>
                    </div>
                    <p v-if="nego.counter_notes" class="text-xs text-purple-800 italic p-3 bg-white border border-purple-100 rounded-xl mb-4">💬 {{ nego.counter_notes }}</p>

                    <div class="space-y-3">
                        <button @click="respondToCounter('accepted')" :disabled="counterForm.processing" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm rounded-xl shadow-lg transition-all disabled:opacity-40">
                            ✅ Terima Penawaran {{ formatCurrency(nego.counter_price) }}
                        </button>

                        <div class="p-4 bg-white border border-slate-200 rounded-xl space-y-3">
                            <p class="text-[10px] font-black text-slate-500 uppercase">Atau ajukan harga revisi:</p>
                            <input v-model="counterForm.offered_price" type="number" min="1" placeholder="Harga revisi Anda..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-1 focus:ring-purple-500" />
                            <textarea v-model="counterForm.notes" rows="2" placeholder="Catatan tambahan..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-1 focus:ring-purple-500"></textarea>
                            <button @click="submitRevised" :disabled="counterForm.processing || !counterForm.offered_price" class="w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded-xl shadow-md transition-all disabled:opacity-40">
                                🔄 Kirim Harga Revisi
                            </button>
                        </div>

                        <button @click="respondToCounter('rejected')" :disabled="counterForm.processing" class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs rounded-xl transition-all disabled:opacity-40">
                            Tolak Penawaran
                        </button>
                    </div>
                </div>
            </div>

            <!-- DRAFT STATE - Fill Form -->
            <div v-else-if="isDraft" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                <h3 class="text-base font-black text-slate-900 mb-1">Form Pengajuan Negosiasi</h3>
                <p class="text-xs text-slate-400 mb-6">Isi form di bawah ini untuk mengajukan negosiasi harga unit rumah impian Anda.</p>

                <form @submit.prevent="submitForm" class="space-y-5">
                    <!-- Client Data -->
                    <div class="space-y-3">
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest pb-2 border-b border-slate-100">Data Pribadi</p>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Nama Lengkap *</label>
                            <input v-model="form.client_name" type="text" required placeholder="Masukkan nama lengkap Anda" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                            <p v-if="form.errors.client_name" class="text-[10px] text-rose-500 mt-1">{{ form.errors.client_name }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">No HP / WhatsApp *</label>
                                <input v-model="form.client_phone" type="tel" required placeholder="08xxxxxxxxxx" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Email (Opsional)</label>
                                <input v-model="form.client_email" type="email" placeholder="email@example.com" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Negotiation Data -->
                    <div class="space-y-3">
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest pb-2 border-b border-slate-100">Pengajuan Negosiasi</p>

                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-blue-700">Harga Listing Unit</span>
                                <span class="text-base font-black text-blue-900 font-mono">{{ formatCurrency(nego.unit_listed_price) }}</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Harga yang Anda Ajukan *</label>
                            <input v-model="form.offered_price" type="number" required min="1" placeholder="Masukkan harga yang Anda inginkan" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-500" />
                            <p v-if="form.errors.offered_price" class="text-[10px] text-rose-500 mt-1">{{ form.errors.offered_price }}</p>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Skema Pembayaran *</label>
                            <select v-model="form.payment_scheme" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-500 cursor-pointer">
                                <option value="kpr">🏦 KPR Bank</option>
                                <option value="cash_keras">💰 Cash Keras (Tunai Penuh)</option>
                                <option value="cash_bertahap">📅 Cash Bertahap (Cicilan Developer)</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Nominal DP yang Diinginkan</label>
                                <input v-model="form.dp_amount" type="number" min="0" placeholder="Nominal DP" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Tenor Cicilan (bulan)</label>
                                <input v-model="form.installment_months" type="number" min="1" max="360" placeholder="misal: 120" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-500" />
                            </div>
                        </div>
                    </div>

                    <!-- Special Requests -->
                    <div class="space-y-3">
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest pb-2 border-b border-slate-100">Permintaan Tambahan</p>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Permintaan Khusus</label>
                            <textarea v-model="form.special_requests" rows="3" placeholder="Contoh: tambah furniture, renovasi dapur, free canopy, dll..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Catatan Lainnya</label>
                            <textarea v-model="form.notes" rows="2" placeholder="Catatan tambahan untuk developer..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                    </div>

                    <button type="submit" :disabled="form.processing" class="w-full py-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-black text-sm rounded-xl shadow-lg shadow-blue-600/25 transition-all disabled:opacity-40">
                        {{ form.processing ? 'Mengirim...' : '📋 Kirim Pengajuan Negosiasi' }}
                    </button>
                </form>
            </div>

            <!-- Footer -->
            <div class="text-center py-6">
                <p class="text-[10px] text-slate-400 font-bold">Powered by <span class="font-black text-slate-500">Homi Developer CRM</span></p>
            </div>
        </div>
    </div>
</template>
