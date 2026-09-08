<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const props = defineProps({
    reservations: Object,
    stats: Object,
    filters: Object,
    projects: Array,
});

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const projectFilter = ref(props.filters.project_id || '');

const applyFilters = () => {
    router.get('/reservations', {
        search: search.value,
        status: statusFilter.value,
        project_id: projectFilter.value,
    }, { preserveState: true, replace: true });
};

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});

watch([statusFilter, projectFilter], () => {
    applyFilters();
});

const formatCurrency = (val) => {
    if (!val) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

const statusConfig = {
    active: { label: 'Reservasi Aktif', color: 'bg-emerald-100 text-emerald-800 border-emerald-300', icon: '🟢' },
    converted: { label: 'Dikonversi ke Booking', color: 'bg-blue-100 text-blue-800 border-blue-300', icon: '📝' },
    refunded: { label: 'Refund 100%', color: 'bg-amber-100 text-amber-800 border-amber-300', icon: '🔄' },
    cancelled: { label: 'Dibatalkan', color: 'bg-slate-100 text-slate-700 border-slate-300', icon: '❌' },
};

const deleteReservation = (id) => {
    if (confirm('Apakah Anda yakin ingin menghapus data reservasi ini?')) {
        router.delete(`/reservations/${id}`);
    }
};
</script>

<template>
    <CrmLayout title="Manajemen Reservasi Unit">
        <Head title="Manajemen Reservasi Unit (100% Refundable)" />

        <!-- PAGE HEADER -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">Manajemen Reservasi Unit</h1>
                    <span class="px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-700 border border-emerald-300 rounded-md">🛡️ Garansi 100% Refundable</span>
                </div>
                <p class="text-xs text-slate-500 mt-1">Kelola hold unit sementara & kwitansi reservasi resmi sebelum tahap Booking (SPR).</p>
            </div>
            <div class="flex items-center gap-2">
                <Link href="/reservations/create" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-blue-600/20 flex items-center gap-2">
                    <span>➕</span> Buat Reservasi Baru
                </Link>
            </div>
        </div>

        <!-- STATS CARDS -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            <div class="bg-white border border-slate-200 p-4 rounded-2xl">
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Reservasi</div>
                <div class="text-2xl font-black text-slate-900 mt-1">{{ stats.total }}</div>
            </div>
            <div class="bg-emerald-50 border border-emerald-200 p-4 rounded-2xl">
                <div class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">🟢 Aktif (Hold Unit)</div>
                <div class="text-2xl font-black text-emerald-700 mt-1">{{ stats.active }}</div>
                <p class="text-[10px] text-emerald-600 font-bold mt-0.5">{{ formatCurrency(stats.total_amount) }}</p>
            </div>
            <div class="bg-blue-50 border border-blue-200 p-4 rounded-2xl">
                <div class="text-[10px] font-black text-blue-600 uppercase tracking-widest">📝 Dikonversi Booking</div>
                <div class="text-2xl font-black text-blue-700 mt-1">{{ stats.converted }}</div>
            </div>
            <div class="bg-amber-50 border border-amber-200 p-4 rounded-2xl">
                <div class="text-[10px] font-black text-amber-600 uppercase tracking-widest">🔄 Refund 100%</div>
                <div class="text-2xl font-black text-amber-700 mt-1">{{ stats.refunded }}</div>
                <p class="text-[10px] text-amber-600 font-bold mt-0.5">{{ formatCurrency(stats.total_refunded) }}</p>
            </div>
            <div class="bg-slate-50 border border-slate-200 p-4 rounded-2xl col-span-2 md:col-span-1">
                <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest">🛡️ Policy</div>
                <div class="text-xs font-bold text-slate-800 mt-1">100% Refundable</div>
                <p class="text-[10px] text-slate-500 mt-0.5">Garansi dana kembali utuh jika pengajuan tidak disetujui.</p>
            </div>
        </div>

        <!-- FILTERS -->
        <div class="bg-white border border-slate-200 rounded-2xl p-4 mb-6 flex flex-wrap gap-3">
            <input v-model="search" type="text" placeholder="🔍 Cari nama / HP / No. Reservasi / Unit..." class="flex-1 min-w-[220px] px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-1 focus:ring-blue-500" />
            <select v-model="statusFilter" class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold cursor-pointer">
                <option value="">Semua Status</option>
                <option value="active">🟢 Reservasi Aktif</option>
                <option value="converted">📝 Dikonversi ke Booking</option>
                <option value="refunded">🔄 Refund 100%</option>
                <option value="cancelled">❌ Dibatalkan</option>
            </select>
            <select v-model="projectFilter" class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold cursor-pointer">
                <option value="">Semua Proyek</option>
                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
        </div>

        <!-- TABLE -->
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <th class="px-6 py-4">No. Reservasi</th>
                            <th class="px-6 py-4">Client / Pemohon</th>
                            <th class="px-6 py-4">Unit / Proyek</th>
                            <th class="px-6 py-4 text-right">Biaya Reservasi</th>
                            <th class="px-6 py-4">Agent Koordinator</th>
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="res in reservations.data" :key="res.id" class="hover:bg-slate-50/50 transition-all">
                            <td class="px-6 py-5">
                                <p class="text-xs font-mono font-black text-blue-700">{{ res.reservation_number }}</p>
                                <p class="text-[10px] text-slate-400 font-bold mt-0.5">Tgl: {{ formatDate(res.created_at) }}</p>
                            </td>
                            <td class="px-6 py-5">
                                <p class="text-sm font-black text-slate-900">{{ res.client_name }}</p>
                                <p class="text-[10px] text-slate-400 font-bold mt-0.5">📱 {{ res.client_phone }}</p>
                            </td>
                            <td class="px-6 py-5">
                                <p class="text-xs font-black text-slate-800">{{ res.unit?.code || res.unit?.number || '-' }}</p>
                                <p class="text-[10px] text-slate-400 font-bold">{{ res.project?.name || '-' }} · Tipe {{ res.unit?.unit_type?.name || '' }}</p>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <p class="text-sm font-black text-emerald-600 font-mono">{{ formatCurrency(res.amount) }}</p>
                                <span class="text-[9px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">100% Refundable</span>
                            </td>
                            <td class="px-6 py-5">
                                <p class="text-xs font-bold text-slate-700">{{ res.agent_coordinator_name || res.agent_coordinator?.name || '-' }}</p>
                                <p class="text-[10px] text-slate-400">{{ res.agent_coordinator_title || 'Agent Representative' }}</p>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <span :class="(statusConfig[res.status] || statusConfig.active).color" class="px-2.5 py-1 text-[10px] font-black rounded-lg border inline-flex items-center gap-1">
                                    {{ (statusConfig[res.status] || statusConfig.active).icon }}
                                    {{ (statusConfig[res.status] || statusConfig.active).label }}
                                </span>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a :href="`/reservations/${res.id}/receipt`" target="_blank" title="Cetak Kwitansi PDF Reservasi (TTD Agent Koordinator)" class="px-2.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-[10px] font-bold transition-all flex items-center gap-1">
                                        <span>📄</span> Kwitansi
                                    </a>
                                    <Link :href="`/reservations/${res.id}`" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-[10px] font-bold transition-all">
                                        Detail
                                    </Link>
                                    <Link v-if="res.status === 'active'" :href="`/reservations/${res.id}/convert`" title="Konversi ke Booking (Dipotong dari UTJ)" class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-[10px] font-bold transition-all flex items-center gap-1">
                                        <span>➡️</span> Booking
                                    </Link>
                                    <button @click="deleteReservation(res.id)" title="Hapus Data Reservasi" class="px-2 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-[10px] font-bold border border-rose-200 transition-all">
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!reservations.data?.length">
                            <td colspan="7" class="px-6 py-16 text-center">
                                <p class="text-4xl mb-3">🔖</p>
                                <p class="text-sm font-bold text-slate-700">Belum ada data reservasi unit</p>
                                <p class="text-xs text-slate-400 mt-1">Buat reservasi unit baru untuk me-lock unit dengan garansi 100% Refundable.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </CrmLayout>
</template>
