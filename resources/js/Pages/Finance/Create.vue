<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

const props = defineProps({
    bookings: Array,
    bank_accounts: Array,
});

const form = useForm({
    booking_id: '',
    amount: '',
    payment_method: 'transfer',
    bank_name: '',
    reference_number: '',
    notes: '',
    bank_account_id: props.bank_accounts?.length > 0 ? props.bank_accounts[0].id : '',
});

const submit = () => {
    form.post('/finance', {
        onSuccess: () => form.reset()
    });
};
</script>

<template>
    <Head title="Catat Pembayaran" />
    <CrmLayout>
        <template #breadcrumb>
            <Link href="/finance" class="text-slate-400 hover:text-blue-600">Keuangan</Link>
            <span class="mx-2 text-slate-300">/</span>
            <span class="text-slate-900 font-bold">Catat</span>
        </template>

        <div class="max-w-2xl mx-auto">
            <div class="mb-8">
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Catat Pembayaran Baru</h1>
                <p class="text-sm text-slate-500 mt-1">Masukkan detail transaksi pembayaran dari konsumen.</p>
            </div>

            <form @submit.prevent="submit" class="bg-white rounded-2xl border border-slate-100 p-8 shadow-sm space-y-6">
                <!-- Booking Selector -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">Pilih Booking / Konsumen <span class="text-rose-500">*</span></label>
                    <select v-model="form.booking_id" required class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                        <option value="">Cari Konsumen...</option>
                        <option v-for="b in bookings" :key="b.id" :value="b.id">
                            {{ b.booking_number }} - {{ b.lead?.name }} ({{ b.unit?.project?.code }}-{{ b.unit?.block }}{{ b.unit?.number }})
                        </option>
                    </select>
                    <p v-if="form.errors.booking_id" class="text-xs text-rose-500 mt-1">{{ form.errors.booking_id }}</p>
                </div>

                <!-- Amount & Method -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 md:col-span-1">
                        <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">Jumlah Pembayaran <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold">Rp</span>
                            <input v-model="form.amount" type="number" required placeholder="0" class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl text-sm font-black focus:ring-2 focus:ring-blue-500/20" />
                        </div>
                        <p v-if="form.errors.amount" class="text-xs text-rose-500 mt-1">{{ form.errors.amount }}</p>
                    </div>
                    <div class="col-span-2 md:col-span-1">
                        <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">Metode <span class="text-rose-500">*</span></label>
                        <select v-model="form.payment_method" required class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                            <option value="transfer">Transfer Bank</option>
                            <option value="cash">Tunai / Cash</option>
                            <option value="cheque">Cek / Giro</option>
                        </select>
                    </div>
                </div>

                <!-- Destination Bank Account -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">Setor ke Rekening / Kas <span class="text-rose-500">*</span></label>
                    <select v-model="form.bank_account_id" required class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                        <option value="">-- Pilih Kas / Rekening Tujuan --</option>
                        <option v-for="bank in bank_accounts" :key="bank.id" :value="bank.id">
                            {{ bank.name }} ({{ bank.bank_name || 'Cash Box' }})
                        </option>
                    </select>
                    <p v-if="form.errors.bank_account_id" class="text-xs text-rose-500 mt-1">{{ form.errors.bank_account_id }}</p>
                </div>

                <!-- Transfer Bank Details -->
                <div v-if="form.payment_method === 'transfer'" class="grid grid-cols-2 gap-4 animate-in fade-in slide-in-from-top-1 duration-300">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">Nama Bank Pengirim</label>
                        <input v-model="form.bank_name" type="text" placeholder="BCA / Mandiri / dll" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20" />
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">No. Ref / Bukti</label>
                        <input v-model="form.reference_number" type="text" placeholder="Trx ID / Reff No" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20" />
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase tracking-wider">Keterangan / Catatan</label>
                    <textarea v-model="form.notes" rows="3" placeholder="Contoh: Pembayaran UTJ Blok A-01" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 resize-none"></textarea>
                </div>

                <div class="pt-4 flex gap-3">
                    <button type="submit" :disabled="form.processing"
                        class="flex-1 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-black rounded-2xl shadow-xl shadow-blue-500/30 hover:-translate-y-1 transition-all active:scale-95 disabled:opacity-50">
                        {{ form.processing ? 'Menyimpan...' : 'SIMPAN PEMBAYARAN' }}
                    </button>
                    <Link href="/finance" class="px-8 py-4 bg-slate-100 text-slate-600 font-bold rounded-2xl hover:bg-slate-200 transition-colors">Batal</Link>
                </div>
            </form>
        </div>
    </CrmLayout>
</template>
