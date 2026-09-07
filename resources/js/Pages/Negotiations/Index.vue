<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    negotiations: Object,
    stats: Object,
    filters: Object,
    projects: Array,
    agents: Array,
});

const page = usePage();
const flash = computed(() => page.props.flash || {});

// Filters
const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const projectFilter = ref(props.filters?.project_id || '');

let timeout;
watch(search, () => { clearTimeout(timeout); timeout = setTimeout(() => applyFilters(), 400); });
watch([statusFilter, projectFilter], () => applyFilters());

function applyFilters() {
    router.get('/negotiations', {
        search: search.value, status: statusFilter.value, project_id: projectFilter.value,
    }, { preserveState: true, replace: true });
}

// Create Negotiation Modal
const showCreateModal = ref(false);
const createForm = useForm({
    unit_id: '',
    lead_id: '',
    client_name: '',
    client_phone: '',
    client_email: '',
});

function submitCreate() {
    createForm.post('/negotiations', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
            showLinkModal.value = true;
        },
    });
}

// Link Share Modal
const showLinkModal = ref(false);
const negoLink = computed(() => flash.value?.negotiation_link || '');

function copyLink() {
    navigator.clipboard.writeText(negoLink.value);
}

function shareWhatsApp() {
    const msg = `Halo Bapak/Ibu *${createForm.client_name || 'Client'}*,\n\nTerima kasih atas kunjungan Anda. Silakan isi Form Pengajuan Negosiasi melalui link berikut:\n\n🔗 ${negoLink.value}\n\nPengajuan Anda akan kami review dalam 1x24 jam.\n\nSalam, *Homi Developer*`;
    const phone = (createForm.client_phone || '').replace(/[^0-9]/g, '');
    const waPhone = phone.startsWith('0') ? '62' + phone.substring(1) : phone;
    window.open(`https://wa.me/${waPhone}?text=${encodeURIComponent(msg)}`, '_blank');
}

// Helpers
function formatCurrency(val) {
    if (!val) return 'Rp 0';
    return 'Rp ' + Number(val).toLocaleString('id-ID');
}

function timeAgo(date) {
    if (!date) return '-';
    const diff = Date.now() - new Date(date).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 60) return `${mins}m lalu`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}j lalu`;
    const days = Math.floor(hrs / 24);
    return `${days}h lalu`;
}

const statusConfig = {
    draft: { label: 'Draft', color: 'bg-slate-100 text-slate-600 border-slate-200', icon: '📝' },
    pending: { label: 'Menunggu Review', color: 'bg-amber-100 text-amber-700 border-amber-200', icon: '⏳' },
    reviewed: { label: 'Sedang Ditinjau', color: 'bg-blue-100 text-blue-700 border-blue-200', icon: '👁️' },
    counter_offer: { label: 'Counter Offer', color: 'bg-purple-100 text-purple-700 border-purple-200', icon: '🔄' },
    approved: { label: 'Disetujui', color: 'bg-emerald-100 text-emerald-700 border-emerald-200', icon: '✅' },
    rejected: { label: 'Ditolak', color: 'bg-rose-100 text-rose-700 border-rose-200', icon: '❌' },
    expired: { label: 'Kedaluwarsa', color: 'bg-slate-100 text-slate-400 border-slate-200', icon: '⏰' },
};

const paymentLabels = { cash_keras: 'Cash Keras', cash_bertahap: 'Cash Bertahap', kpr: 'KPR Bank' };
</script>

<template>
    <Head title="Negosiasi" />
    <CrmLayout>
        <template #breadcrumb>Pengajuan Negosiasi</template>

        <!-- HEADER -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">📋 Pengajuan Negosiasi</h1>
                <p class="text-sm text-slate-500 mt-1">Kelola pengajuan negosiasi harga dari calon pembeli. <Link href="/leads" class="text-blue-600 hover:underline font-semibold">→ Leads</Link></p>
            </div>
            <button @click="showCreateModal = true" class="px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold rounded-xl shadow-lg flex items-center gap-2 transition-all">
                <span>📋</span> Buat Form Negosiasi
            </button>
        </div>

        <!-- STATS -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-8">
            <div class="bg-white border border-slate-200 p-4 rounded-2xl">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total</div>
                <div class="text-2xl font-black text-slate-900 mt-1">{{ stats.total }}</div>
            </div>
            <div class="bg-amber-50 border border-amber-200 p-4 rounded-2xl">
                <div class="text-[10px] font-black text-amber-600 uppercase tracking-widest">⏳ Pending</div>
                <div class="text-2xl font-black text-amber-700 mt-1">{{ stats.pending }}</div>
            </div>
            <div class="bg-purple-50 border border-purple-200 p-4 rounded-2xl">
                <div class="text-[10px] font-black text-purple-600 uppercase tracking-widest">🔄 Counter</div>
                <div class="text-2xl font-black text-purple-700 mt-1">{{ stats.counter_offer }}</div>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 p-4 rounded-2xl">
                <div class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">✅ Disetujui</div>
                <div class="text-2xl font-black text-emerald-700 mt-1">{{ stats.approved }}</div>
            </div>
            <div class="bg-rose-50 border border-rose-200 p-4 rounded-2xl">
                <div class="text-[10px] font-black text-rose-600 uppercase tracking-widest">❌ Ditolak</div>
                <div class="text-2xl font-black text-rose-700 mt-1">{{ stats.rejected }}</div>
            </div>
            <div class="bg-blue-50 border border-blue-200 p-4 rounded-2xl">
                <div class="text-[10px] font-black text-blue-600 uppercase tracking-widest">📝 Booking</div>
                <div class="text-2xl font-black text-blue-700 mt-1">{{ stats.converted }}</div>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="bg-white border border-slate-200 rounded-2xl p-4 mb-6 flex flex-wrap gap-3">
            <input v-model="search" type="text" placeholder="🔍 Cari nama / no HP client..." class="flex-1 min-w-[200px] px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-1 focus:ring-blue-500" />
            <select v-model="statusFilter" class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold cursor-pointer">
                <option value="">Semua Status</option>
                <option value="draft">📝 Draft</option>
                <option value="pending">⏳ Pending</option>
                <option value="counter_offer">🔄 Counter Offer</option>
                <option value="approved">✅ Disetujui</option>
                <option value="rejected">❌ Ditolak</option>
                <option value="expired">⏰ Expired</option>
            </select>
            <select v-model="projectFilter" class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold cursor-pointer">
                <option value="">Semua Proyek</option>
                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
        </div>

        <!-- TABLE -->
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[900px]">
                    <thead>
                        <tr class="bg-slate-50/80 text-[10px] font-black text-slate-500 uppercase tracking-widest border-b border-slate-100">
                            <th class="px-6 py-4">Client</th>
                            <th class="px-6 py-4">Unit / Proyek</th>
                            <th class="px-6 py-4 text-right">Harga Listing</th>
                            <th class="px-6 py-4 text-right">Harga Diajukan</th>
                            <th class="px-6 py-4">Skema</th>
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4">Dibuat</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="nego in negotiations.data" :key="nego.id" class="hover:bg-slate-50/50 transition-all">
                            <td class="px-6 py-5">
                                <p class="text-sm font-black text-slate-900">{{ nego.client_name }}</p>
                                <p class="text-[10px] text-slate-400 font-bold mt-0.5">📱 {{ nego.client_phone }}</p>
                            </td>
                            <td class="px-6 py-5">
                                <p class="text-xs font-black text-slate-800">{{ nego.unit?.code || '-' }}</p>
                                <p class="text-[10px] text-slate-400 font-bold">{{ nego.project?.name || '-' }} · {{ nego.unit?.unit_type?.name || '' }}</p>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <p class="text-xs font-bold text-slate-600 font-mono">{{ formatCurrency(nego.unit_listed_price) }}</p>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <p v-if="nego.offered_price" class="text-sm font-black font-mono" :class="nego.offered_price < nego.unit_listed_price ? 'text-rose-600' : 'text-emerald-600'">
                                    {{ formatCurrency(nego.offered_price) }}
                                </p>
                                <p v-else class="text-xs text-slate-300 italic">Belum diisi</p>
                                <p v-if="nego.counter_price" class="text-[10px] text-purple-500 font-bold mt-0.5">Counter: {{ formatCurrency(nego.counter_price) }}</p>
                            </td>
                            <td class="px-6 py-5">
                                <span v-if="nego.payment_scheme" class="text-[10px] font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded-lg">{{ paymentLabels[nego.payment_scheme] || nego.payment_scheme }}</span>
                                <span v-else class="text-[10px] text-slate-300 italic">-</span>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <span :class="(statusConfig[nego.status] || statusConfig.draft).color" class="px-2.5 py-1 text-[10px] font-black rounded-lg border inline-flex items-center gap-1">
                                    {{ (statusConfig[nego.status] || statusConfig.draft).icon }}
                                    {{ (statusConfig[nego.status] || statusConfig.draft).label }}
                                </span>
                            </td>
                            <td class="px-6 py-5">
                                <p class="text-[10px] text-slate-400 font-bold">{{ timeAgo(nego.created_at) }}</p>
                                <p class="text-[10px] text-slate-400">oleh {{ nego.creator?.name || '-' }}</p>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/negotiations/${nego.id}`" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-[10px] font-bold transition-all">
                                        Detail
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!negotiations.data?.length">
                            <td colspan="8" class="px-6 py-16 text-center">
                                <p class="text-4xl mb-3">📋</p>
                                <p class="text-xs font-black text-slate-400 uppercase tracking-widest">Belum Ada Pengajuan Negosiasi</p>
                                <p class="text-[10px] text-slate-400 mt-1">Klik "Buat Form Negosiasi" untuk membuat link pengajuan baru.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="negotiations.last_page > 1" class="flex items-center justify-between px-6 py-3 border-t border-slate-100 bg-slate-50/50">
                <div class="text-[10px] text-slate-400 font-bold">{{ negotiations.from }}-{{ negotiations.to }} dari {{ negotiations.total }}</div>
                <div class="flex items-center gap-1">
                    <button v-for="link in negotiations.links" :key="link.label" @click="link.url && router.get(link.url, {}, { preserveState: true })" :disabled="!link.url" :class="[link.active ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-100', !link.url ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer']" class="px-3 py-1.5 rounded-lg text-[10px] font-bold border border-slate-200" v-html="link.label"></button>
                </div>
            </div>
        </div>

        <!-- CREATE MODAL -->
        <teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 space-y-5 animate-in zoom-in duration-150">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div>
                            <h3 class="text-base font-black tracking-tight text-slate-900">📋 Buat Form Negosiasi</h3>
                            <p class="text-[10px] text-slate-400 mt-0.5">Generate link form pengajuan untuk dikirim ke client via WhatsApp.</p>
                        </div>
                        <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-700 text-lg">✕</button>
                    </div>

                    <form @submit.prevent="submitCreate" class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Nama Client *</label>
                            <input v-model="createForm.client_name" type="text" required placeholder="Nama lengkap calon pembeli" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-1 focus:ring-blue-500" />
                            <p v-if="createForm.errors.client_name" class="text-[10px] text-rose-500 mt-1">{{ createForm.errors.client_name }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">No HP Client *</label>
                                <input v-model="createForm.client_phone" type="text" required placeholder="08xxxxxxxxxx" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-1 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Email (Opsional)</label>
                                <input v-model="createForm.client_email" type="email" placeholder="email@example.com" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-1 focus:ring-blue-500" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Pilih Unit Rumah *</label>
                            <input v-model="createForm.unit_id" type="number" required placeholder="ID Unit (misal: 1, 2, 3...)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-1 focus:ring-blue-500" />
                            <p class="text-[9px] text-slate-400 mt-1">Masukkan ID unit dari halaman Inventori Unit.</p>
                            <p v-if="createForm.errors.unit_id" class="text-[10px] text-rose-500 mt-1">{{ createForm.errors.unit_id }}</p>
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                            <button type="button" @click="showCreateModal = false" class="px-4 py-2.5 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold">Batal</button>
                            <button type="submit" :disabled="createForm.processing" class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-black shadow-lg transition-all disabled:opacity-40">
                                📋 Buat Link Negosiasi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <!-- LINK SHARE MODAL -->
        <teleport to="body">
            <div v-if="showLinkModal && negoLink" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 space-y-5 animate-in zoom-in duration-150">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center text-3xl mx-auto mb-3">✅</div>
                        <h3 class="text-base font-black text-slate-900">Form Negosiasi Siap!</h3>
                        <p class="text-xs text-slate-400 mt-1">Bagikan link berikut ke client melalui WhatsApp.</p>
                    </div>

                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl">
                        <p class="text-[10px] font-black text-slate-500 uppercase mb-2">Link Form Negosiasi</p>
                        <div class="flex items-center gap-2">
                            <input :value="negoLink" readonly class="flex-1 px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-mono text-blue-600" />
                            <button @click="copyLink" class="px-3 py-2 bg-slate-900 text-white rounded-xl text-[10px] font-bold hover:bg-slate-800 transition-all shrink-0">📋 Salin</button>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button @click="shareWhatsApp" class="flex-1 py-3 bg-emerald-500 hover:bg-emerald-600 text-white font-black text-xs rounded-xl shadow-lg transition-all flex items-center justify-center gap-2">
                            💬 Kirim via WhatsApp
                        </button>
                        <button @click="showLinkModal = false" class="px-6 py-3 bg-slate-100 text-slate-600 font-bold text-xs rounded-xl hover:bg-slate-200 transition-all">Tutup</button>
                    </div>
                </div>
            </div>
        </teleport>
    </CrmLayout>
</template>
