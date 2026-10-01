<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    company: Object,
    usage: Object,
    invoices: Array,
});

const showUploadModal = ref(false);
const activeInvoice = ref(null);

const uploadForm = useForm({
    payment_proof: null,
    payment_method: 'Transfer Bank',
    notes: '',
});

function openUploadModal(inv) {
    activeInvoice.value = inv;
    uploadForm.reset();
    showUploadModal.value = true;
}

function handleFileChange(e) {
    uploadForm.payment_proof = e.target.files[0];
}

function submitUpload() {
    if (!activeInvoice.value) return;
    uploadForm.post(`/subscription/invoices/${activeInvoice.value.id}/proof`, {
        onSuccess: () => {
            showUploadModal.value = false;
        }
    });
}

function formatRupiah(num) {
    return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
}
</script>

<template>
    <Head title="Langganan Platform SaaS & Tagihan" />

    <CrmLayout>
        <template #breadcrumb>Pengaturan / Langganan SaaS</template>

        <div class="mb-8">
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">🏢 Langganan Platform & Tagihan SaaS</h1>
            <p class="text-xs text-slate-400 mt-1">Status paket langganan sistem, batas kuota pengguna & proyek untuk {{ company?.name }}</p>
        </div>

        <!-- PLAN CARD & USAGE METRICS -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- CURRENT PLAN -->
            <div class="bg-gradient-to-br from-slate-900 to-indigo-950 text-white p-6 rounded-3xl shadow-xl flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-start">
                        <span class="px-3 py-1 bg-white/10 rounded-full text-[10px] font-bold uppercase tracking-wider text-blue-300">
                            Paket Anda
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase"
                            :class="company?.status === 'active' ? 'bg-emerald-500 text-white' : 'bg-amber-500 text-white'">
                            {{ company?.status }}
                        </span>
                    </div>
                    <h2 class="text-3xl font-black mt-4 capitalize tracking-tight">{{ company?.subscription_plan }} Plan</h2>
                    <p class="text-slate-400 text-xs mt-1">Berlaku untuk {{ company?.name }}</p>
                </div>
                <div class="pt-6 border-t border-white/10 mt-6">
                    <p class="text-[10px] uppercase font-bold text-slate-400">Masa Aktif Hingga</p>
                    <p class="text-base font-bold text-white mt-0.5">
                        {{ company?.expires_at ? new Date(company.expires_at).toLocaleDateString('id-ID', { dateStyle: 'long' }) : 'Langganan Aktif' }}
                    </p>
                </div>
            </div>

            <!-- USAGE: USERS -->
            <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-center text-slate-400 text-xs font-bold uppercase tracking-wider">
                        <span>👥 Kuota Pengguna (Staff)</span>
                        <span class="text-slate-800 font-black">{{ usage?.users_count }} / {{ usage?.users_limit }}</span>
                    </div>
                    <div class="w-full bg-slate-100 h-2.5 rounded-full mt-3 overflow-hidden">
                        <div class="h-full rounded-full transition-all"
                            :class="usage?.is_near_user_limit ? 'bg-amber-500' : 'bg-blue-600'"
                            :style="{ width: Math.min(100, (usage?.users_count / usage?.users_limit) * 100) + '%' }">
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 mt-4 leading-relaxed">
                        Anda telah menggunakan <strong class="text-slate-800">{{ usage?.users_count }}</strong> akun dari batas maksimal <strong class="text-slate-800">{{ usage?.users_limit }}</strong> staff & agen.
                    </p>
                </div>
                <div v-if="usage?.is_near_user_limit" class="mt-4 p-2.5 bg-amber-50 rounded-xl text-[11px] text-amber-700 font-medium">
                    ⚠️ Kuota pengguna hampir penuh. Hubungi pemilik platform SaaS untuk upgrade paket.
                </div>
            </div>

            <!-- USAGE: PROJECTS -->
            <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-center text-slate-400 text-xs font-bold uppercase tracking-wider">
                        <span>🏗️ Kuota Proyek Kawasan</span>
                        <span class="text-slate-800 font-black">{{ usage?.projects_count }} / {{ usage?.projects_limit }}</span>
                    </div>
                    <div class="w-full bg-slate-100 h-2.5 rounded-full mt-3 overflow-hidden">
                        <div class="h-full rounded-full transition-all"
                            :class="usage?.is_near_project_limit ? 'bg-amber-500' : 'bg-emerald-600'"
                            :style="{ width: Math.min(100, (usage?.projects_count / usage?.projects_limit) * 100) + '%' }">
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 mt-4 leading-relaxed">
                        Anda telah membuat <strong class="text-slate-800">{{ usage?.projects_count }}</strong> kawasan perumahan dari batas maksimal <strong class="text-slate-800">{{ usage?.projects_limit }}</strong> proyek.
                    </p>
                </div>
                <div v-if="usage?.is_near_project_limit" class="mt-4 p-2.5 bg-amber-50 rounded-xl text-[11px] text-amber-700 font-medium">
                    ⚠️ Kuota proyek kawasan hampir mencapai batas maksimal.
                </div>
            </div>
        </div>

        <!-- RIWAYAT TAGIHAN INVOICE SAAS -->
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden p-6">
            <h3 class="text-base font-black text-slate-900 mb-4">Riwayat Tagihan & Pembayaran SaaS</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="p-3">No. Invoice</th>
                            <th class="p-3">Periode</th>
                            <th class="p-3">Jatuh Tempo</th>
                            <th class="p-3">Nominal</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 font-medium">
                        <tr v-for="inv in invoices" :key="inv.id">
                            <td class="p-3 font-bold text-slate-800">{{ inv.invoice_number }}</td>
                            <td class="p-3 text-slate-500">
                                {{ new Date(inv.period_start).toLocaleDateString('id-ID') }} - {{ new Date(inv.period_end).toLocaleDateString('id-ID') }}
                            </td>
                            <td class="p-3 text-slate-500">
                                {{ new Date(inv.due_date).toLocaleDateString('id-ID') }}
                            </td>
                            <td class="p-3 font-bold text-slate-800">{{ formatRupiah(inv.amount) }}</td>
                            <td class="p-3">
                                <span v-if="inv.status === 'paid'" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-700">LUNAS</span>
                                <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-700">MENUNGGU PEMBAYARAN</span>
                            </td>
                            <td class="p-3 text-right">
                                <button v-if="inv.status !== 'paid'" @click="openUploadModal(inv)" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-[10px]">
                                    Upload Bukti Transfer
                                </button>
                                <span v-else class="text-slate-400 text-[10px]">Terverifikasi ✓</span>
                            </td>
                        </tr>
                        <tr v-if="!invoices?.length">
                            <td colspan="6" class="p-6 text-center text-slate-400">Belum ada riwayat tagihan invoice SaaS.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL UPLOAD BUKTI BAYAR -->
        <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-2xl">
                <h3 class="text-lg font-black text-slate-900 mb-1">Unggah Bukti Transfer Langganan</h3>
                <p class="text-xs text-slate-400 mb-4">Invoice: {{ activeInvoice?.invoice_number }} ({{ formatRupiah(activeInvoice?.amount) }})</p>
                <form @submit.prevent="submitUpload" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Bukti Transfer (Foto / PDF)</label>
                        <input type="file" @change="handleFileChange" accept="image/*,.pdf" required class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Metode / Bank Pengirim</label>
                        <input v-model="uploadForm.payment_method" type="text" placeholder="BCA / Mandiri / BNI a.n Perusahaan..." class="w-full rounded-xl border-slate-200" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Catatan Tambahan</label>
                        <textarea v-model="uploadForm.notes" rows="2" class="w-full rounded-xl border-slate-200" placeholder="Keterangan transfer..."></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-4 border-t">
                        <button type="button" @click="showUploadModal = false" class="px-4 py-2 text-slate-500 font-bold">Batal</button>
                        <button type="submit" :disabled="uploadForm.processing" class="px-5 py-2 bg-blue-600 text-white font-bold rounded-xl">Kirim Bukti Bayar</button>
                    </div>
                </form>
            </div>
        </div>
    </CrmLayout>
</template>
