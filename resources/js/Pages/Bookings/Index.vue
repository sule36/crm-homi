<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    bookings: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');

let timeout;
watch(search, (val) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get('/bookings', { search: val, status: statusFilter.value }, { preserveState: true, replace: true });
    }, 400);
});

watch(statusFilter, (val) => {
    router.get('/bookings', { search: search.value, status: val }, { preserveState: true, replace: true });
});

const statusColors = {
    pending: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-rose-100 text-rose-700',
    completed: 'bg-blue-100 text-blue-700',
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};
</script>

<template>
    <Head title="Booking" />
    <CrmLayout>
        <template #breadcrumb>Booking</template>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Daftar Booking</h1>
                <p class="text-sm text-slate-500 mt-1">Pantau semua pengajuan booking dan status unit</p>
            </div>
            <Link href="/bookings/create"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Booking Baru
            </Link>
        </div>

        <!-- FILTERS -->
        <div class="flex flex-col sm:flex-row gap-3 mb-6">
            <div class="relative flex-1 max-w-md">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input v-model="search" type="text" placeholder="Cari No. Booking atau Lead..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" />
            </div>
            <select v-model="statusFilter" class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                <option value="">Semua Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Disetujui</option>
                <option value="cancelled">Dibatalkan</option>
            </select>
        </div>

        <!-- TABLE -->
        <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider">No. SPK</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider">Konsumen</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider">Unit</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider">Skema</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider text-right">Total</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider text-center">Status</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="booking in bookings.data" :key="booking.id" class="hover:bg-slate-50/50 transition-colors group">
                        <td class="px-6 py-4">
                            <span class="text-sm font-black text-slate-900">{{ booking.spk_number }}</span>
                            <p class="text-[10px] text-slate-400 mt-0.5">{{ new Date(booking.booking_date).toLocaleDateString('id-ID') }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 bg-blue-100 rounded-lg flex items-center justify-center text-[10px] font-bold text-blue-600">
                                    {{ booking.lead?.name.charAt(0) }}
                                </div>
                                <span class="text-sm font-bold text-slate-700">{{ booking.lead?.name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-xs font-bold text-slate-600">{{ booking.unit?.project?.code }} - {{ booking.unit?.block }}{{ booking.unit?.number }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-[10px] font-black uppercase bg-slate-100 text-slate-500 px-2 py-0.5 rounded-md">{{ booking.payment_scheme }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="text-sm font-black text-slate-900">{{ formatCurrency(booking.final_price) }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span :class="statusColors[booking.status]" class="inline-block px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                {{ booking.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <Link :href="`/bookings/${booking.id}`" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-500 hover:bg-blue-600 hover:text-white transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="!bookings.data.length">
                        <td colspan="7" class="px-6 py-20 text-center">
                            <div class="text-4xl mb-4">📋</div>
                            <h3 class="text-lg font-bold text-slate-700">Belum ada booking</h3>
                            <p class="text-sm text-slate-400">Semua transaksi booking akan muncul di sini.</p>
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
