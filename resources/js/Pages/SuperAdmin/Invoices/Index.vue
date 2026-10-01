<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    invoices: Object,
    companies: Array,
    filters: Object,
    stats: Object,
});

const showAddModal = ref(false);
const showPaymentModal = ref(false);
const selectedInvoice = ref(null);

const addForm = useForm({
    company_id: '',
    plan: 'pro',
    amount: 2500000,
    period_start: new Date().toISOString().substring(0, 10),
    period_end: new Date(Date.now() + 30 * 86400000).toISOString().substring(0, 10),
    due_date: new Date(Date.now() + 7 * 86400000).toISOString().substring(0, 10),
    notes: '',
});

const payForm = useForm({
    payment_method: 'BCA Transfer',
    paid_at: new Date().toISOString().substring(0, 10),
    notes: '',
});

function openAddModal() {
    addForm.reset();
    showAddModal.value = true;
}

function submitAdd() {
    addForm.post('/super-admin/invoices', {
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        }
    });
}

function openPaymentModal(invoice) {
    selectedInvoice.value = invoice;
    payForm.reset();
    showPaymentModal.value = true;
}

function submitPayment() {
    if (!selectedInvoice.value) return;
    payForm.post(`/super-admin/invoices/${selectedInvoice.value.id}/mark-paid`, {
        onSuccess: () => {
            showPaymentModal.value = false;
        }
    });
}

function deleteInvoice(inv) {
    if (confirm(`Hapus invoice ${inv.invoice_number}?`)) {
        router.delete(`/super-admin/invoices/${inv.id}`);
    }
}

function formatRupiah(num) {
    return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
}
</script>

<template>
    <Head title="Manajemen Pembayaran & Tagihan SaaS (Super Admin)" />

    <CrmLayout>
        <template #breadcrumb>SaaS Platform / Tagihan & Pembayaran</template>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">💳 Kontrol Tagihan & Pembayaran SaaS</h1>
                <p class="text-xs text-slate-400 mt-1">Kelola penagihan langganan bulanan/tahunan developer properti ke pemilik SaaS</p>
            </div>
            <button @click="openAddModal" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-blue-500/20 hover:-translate-y-0.5 transition-all flex items-center gap-2">
                <span>+</span> Terbitkan Tagihan Baru
            </button>
        </div>

        <!-- STATS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Tagihan Diterbitkan</p>
                <p class="text-xl font-black text-slate-800 mt-1">{{ formatRupiah(stats?.total_invoiced) }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Pembayaran Masuk</p>
                <p class="text-xl font-black text-emerald-600 mt-1">{{ formatRupiah(stats?.total_paid) }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Menunggu Pembayaran</p>
                <p class="text-xl font-black text-amber-600 mt-1">{{ formatRupiah(stats?.total_pending) }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tagihan Belum Lunas</p>
                <p class="text-xl font-black text-rose-600 mt-1">{{ stats?.unpaid_count }} Invoice</p>
            </div>
        </div>

        <!-- INVOICE LIST -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="p-4">No. Invoice</th>
                            <th class="p-4">Developer (Tenant)</th>
                            <th class="p-4">Paket</th>
                            <th class="p-4">Periode</th>
                            <th class="p-4">Jatuh Tempo</th>
                            <th class="p-4">Nominal</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 font-medium">
                        <tr v-for="inv in invoices?.data" :key="inv.id" class="hover:bg-slate-50/50 transition-colors">
                            <td class="p-4 font-bold text-slate-800">{{ inv.invoice_number }}</td>
                            <td class="p-4">
                                <p class="font-bold text-slate-800">{{ inv.company?.name }}</p>
                                <p class="text-[10px] text-slate-400">{{ inv.company?.email }}</p>
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                    :class="inv.plan === 'enterprise' ? 'bg-amber-100 text-amber-700' : (inv.plan === 'pro' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700')">
                                    {{ inv.plan }}
                                </span>
                            </td>
                            <td class="p-4 text-slate-500">
                                {{ new Date(inv.period_start).toLocaleDateString('id-ID') }} - {{ new Date(inv.period_end).toLocaleDateString('id-ID') }}
                            </td>
                            <td class="p-4 text-slate-500">
                                {{ new Date(inv.due_date).toLocaleDateString('id-ID') }}
                            </td>
                            <td class="p-4 font-bold text-slate-800">{{ formatRupiah(inv.amount) }}</td>
                            <td class="p-4">
                                <span v-if="inv.status === 'paid'" class="px-2 py-1 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700">LUNAS</span>
                                <span v-else class="px-2 py-1 rounded-full text-[10px] font-black bg-rose-100 text-rose-700">BELUM BAYAR</span>
                                <a v-if="inv.payment_proof" :href="'/storage/' + inv.payment_proof" target="_blank" class="block text-[9px] text-blue-600 underline mt-1">
                                    Lihat Bukti Bayar
                                </a>
                            </td>
                            <td class="p-4 text-right space-x-2">
                                <button v-if="inv.status !== 'paid'" @click="openPaymentModal(inv)" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-[10px]">
                                    ✓ Konfirmasi Lunas
                                </button>
                                <button @click="deleteInvoice(inv)" class="text-rose-500 hover:text-rose-700 font-bold text-[10px]">
                                    Hapus
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!invoices?.data?.length">
                            <td colspan="8" class="p-8 text-center text-slate-400">Belum ada tagihan invoice SaaS yang diterbitkan.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL TERBITKAN TAGIHAN -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white rounded-2xl p-6 w-full max-w-lg shadow-2xl">
                <h3 class="text-lg font-black text-slate-900 mb-4">Terbitkan Tagihan SaaS Baru</h3>
                <form @submit.prevent="submitAdd" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Developer (Tenant)</label>
                        <select v-model="addForm.company_id" required class="w-full rounded-xl border-slate-200">
                            <option value="">Pilih Perusahaan Developer</option>
                            <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }} ({{ c.subscription_plan }})</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Paket</label>
                            <select v-model="addForm.plan" class="w-full rounded-xl border-slate-200">
                                <option value="starter">Starter (10 users, 5 projects)</option>
                                <option value="pro">Pro (30 users, 15 projects)</option>
                                <option value="enterprise">Enterprise (Unlimited)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nominal (Rp)</label>
                            <input v-model="addForm.amount" type="number" required class="w-full rounded-xl border-slate-200" />
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Mulai</label>
                            <input v-model="addForm.period_start" type="date" required class="w-full rounded-xl border-slate-200" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Selesai</label>
                            <input v-model="addForm.period_end" type="date" required class="w-full rounded-xl border-slate-200" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Jatuh Tempo</label>
                            <input v-model="addForm.due_date" type="date" required class="w-full rounded-xl border-slate-200" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-4 border-t">
                        <button type="button" @click="showAddModal = false" class="px-4 py-2 text-slate-500 font-bold">Batal</button>
                        <button type="submit" :disabled="addForm.processing" class="px-5 py-2 bg-blue-600 text-white font-bold rounded-xl">Terbitkan Invoice</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL KONFIRMASI PEMBAYARAN -->
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl">
                <h3 class="text-lg font-black text-slate-900 mb-1">Konfirmasi Pembayaran Lunas</h3>
                <p class="text-xs text-slate-400 mb-4">Invoice: {{ selectedInvoice?.invoice_number }} ({{ selectedInvoice?.company?.name }})</p>
                <form @submit.prevent="submitPayment" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Metode Pembayaran</label>
                        <input v-model="payForm.payment_method" type="text" placeholder="BCA / Mandiri / Transfer Bank" required class="w-full rounded-xl border-slate-200" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tanggal Bayar</label>
                        <input v-model="payForm.paid_at" type="date" required class="w-full rounded-xl border-slate-200" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Catatan</label>
                        <textarea v-model="payForm.notes" rows="2" class="w-full rounded-xl border-slate-200" placeholder="Nomor referensi mutasi bank..."></textarea>
                    </div>
                    <p class="text-[11px] text-emerald-600 bg-emerald-50 p-2 rounded-xl">
                        💡 Konfirmasi lunas akan otomatis memperpanjang masa aktif akun developer selama 1 bulan dan mengaktifkan status akun jika sebelumnya suspended.
                    </p>
                    <div class="flex justify-end gap-2 pt-4 border-t">
                        <button type="button" @click="showPaymentModal = false" class="px-4 py-2 text-slate-500 font-bold">Batal</button>
                        <button type="submit" :disabled="payForm.processing" class="px-5 py-2 bg-emerald-600 text-white font-bold rounded-xl">Konfirmasi Lunas</button>
                    </div>
                </form>
            </div>
        </div>
    </CrmLayout>
</template>
