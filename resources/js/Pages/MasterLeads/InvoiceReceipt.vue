<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    invoice: Object,
    spelled_text: String,
    settings: Object,
});

const form = useForm({});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value || 0);
};

const printInvoice = () => {
    window.print();
};

const sendWaInvoice = () => {
    const mlName = props.invoice.master_lead?.name || 'Master Lead';
    const invNo = props.invoice.invoice_number;
    const amountStr = formatCurrency(props.invoice.total_amount);
    const invoiceUrl = window.location.href;

    const message = `Halo Keuangan Developer,\n\nBerikut pengajuan Invoice Tagihan Komisi resmi dari *${mlName}*:\n- *No. Invoice*: ${invNo}\n- *Total Tagihan Net ML*: ${amountStr}\n\nLink Dokumen PDF Invoice:\n${invoiceUrl}\n\nMohon diproses untuk pencairan. Terima kasih.`;

    window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank');
};

const markAsPaid = () => {
    if (!confirm(`Konfirmasi penandaan Invoice ${props.invoice.invoice_number} sebagai LUNAS / SAH DICAIRKAN dari Developer?`)) return;
    form.post(route('master-leads.invoices.mark-paid', props.invoice.id));
};

const masterLead = computed(() => props.invoice.master_lead || {});
const developerName = computed(() => props.settings?.company_name || 'PT. SERANGKAI RODEN DEVELOPMENT');
</script>

<template>
    <Head :title="`Invoice: ${invoice.invoice_number}`" />
    <div class="min-h-screen bg-slate-100 py-12 px-4 print:bg-white print:py-0">
        <!-- Toolbar (Hidden during print) -->
        <div class="max-w-4xl mx-auto flex flex-wrap items-center justify-between gap-3 mb-6 print:hidden">
            <Link :href="route('master-leads.index')" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all shadow-sm">
                ← Kembali ke Dashboard Master Lead
            </Link>

            <div class="flex flex-wrap items-center gap-2">
                <button v-if="invoice.status !== 'paid'" @click="markAsPaid" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-all shadow-md flex items-center gap-1.5">
                    <span>🟢</span> <span>Tandai Lunas / Cair dari Dev</span>
                </button>

                <button @click="sendWaInvoice" class="px-4 py-2.5 bg-emerald-50 border border-emerald-300 text-emerald-700 hover:bg-emerald-100 text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                    <span>💬</span> <span>Kirim via WA</span>
                </button>

                <button @click="printInvoice" class="px-5 py-2.5 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition-all shadow-md flex items-center gap-1.5">
                    <span>🖨️</span> <span>Cetak / Download PDF</span>
                </button>
            </div>
        </div>

        <!-- Premium Invoice Paper -->
        <div id="invoice-paper" class="max-w-4xl mx-auto bg-white rounded-3xl border border-slate-200 shadow-xl overflow-hidden p-10 relative print:shadow-none print:border-none print:p-0">
            <!-- Background Watermark -->
            <div class="absolute inset-0 opacity-[0.02] flex items-center justify-center pointer-events-none select-none">
                <span class="text-9xl font-black rotate-[-20deg]">INVOICE</span>
            </div>

            <!-- Header Block -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 pb-6 border-b-2 border-purple-600">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">👑</span>
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ masterLead.name || 'MASTER LEAD PARTNER' }}</h1>
                    </div>
                    <p class="text-xs text-purple-600 font-bold uppercase tracking-wider mt-1">Invoice Tagihan Komisi Resmi Master Lead</p>
                    <div class="text-[11px] text-slate-500 mt-1">
                        <span v-if="masterLead.email">📧 {{ masterLead.email }}</span>
                        <span v-if="masterLead.phone"> • 📱 {{ masterLead.phone }}</span>
                    </div>
                </div>

                <div class="text-left sm:text-right bg-purple-50 dark:bg-purple-950/40 p-4 rounded-2xl border border-purple-100 shrink-0">
                    <div class="text-[10px] font-black text-purple-600 uppercase tracking-widest">NO. INVOICE</div>
                    <div class="text-base font-black text-slate-900 font-mono mt-0.5">{{ invoice.invoice_number }}</div>
                    <div class="text-[11px] text-slate-500 font-medium mt-1">
                        Tanggal: {{ new Date(invoice.submitted_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) }}
                    </div>
                    <div class="mt-2">
                        <span v-if="invoice.status === 'paid'" class="px-3 py-1 bg-emerald-500 text-white rounded-full font-black text-[10px] uppercase tracking-wider shadow-sm">
                            🟢 LUNAS / DICAIRKAN
                        </span>
                        <span v-else class="px-3 py-1 bg-amber-500 text-white rounded-full font-black text-[10px] uppercase tracking-wider shadow-sm">
                            ⏳ MENUNGGU PENCAIRAN DEV
                        </span>
                    </div>
                </div>
            </div>

            <!-- Bill To Section -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 my-8 p-4 bg-slate-50 rounded-2xl border border-slate-100">
                <div>
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">KEPADA YTH (DEVELOPER):</div>
                    <div class="text-sm font-black text-slate-900 mt-1">{{ developerName }}</div>
                    <div class="text-xs text-slate-600 mt-0.5">Bagian Keuangan & Direksi Properti</div>
                </div>
                <div>
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">REKENING TUJUAN PENCAIRAN (MASTER LEAD):</div>
                    <div class="text-xs font-bold text-purple-900 mt-1">
                        💳 {{ masterLead.bank_name || 'Bank' }} - {{ masterLead.bank_account_number || '-' }}
                    </div>
                    <div class="text-xs text-slate-700 font-semibold">a.n. {{ masterLead.bank_account_name || masterLead.name }}</div>
                </div>
            </div>

            <!-- Commission Items Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-purple-900 text-white uppercase text-[10px] font-bold">
                            <th class="p-3 rounded-l-xl">No</th>
                            <th class="p-3">Unit Properti & Proyek</th>
                            <th class="p-3">Konsumen / Pemesan</th>
                            <th class="p-3 text-right">Harga Jual Net</th>
                            <th class="p-3 text-center">Rate Fee</th>
                            <th class="p-3 text-right rounded-r-xl">Hak Komisi ML (Nett)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(comm, idx) in invoice.commissions" :key="comm.id" class="hover:bg-slate-50">
                            <td class="p-3 font-mono font-bold text-slate-400">{{ idx + 1 }}</td>
                            <td class="p-3">
                                <div class="font-bold text-slate-900">
                                    {{ comm.booking?.unit ? ('Blok ' + comm.booking.unit.block + ' No. ' + comm.booking.unit.number) : 'Unit Properti' }}
                                </div>
                                <div class="text-[10px] text-slate-400">{{ comm.booking?.unit?.project?.name || '' }}</div>
                            </td>
                            <td class="p-3">
                                <div class="font-semibold text-slate-800">{{ comm.booking?.lead?.name || '-' }}</div>
                                <div class="text-[10px] text-slate-400">Agent: {{ comm.booking?.bookedBy?.name || '-' }}</div>
                            </td>
                            <td class="p-3 text-right font-mono font-bold text-slate-700">
                                {{ formatCurrency(comm.booking?.final_price || comm.booking?.unit_price || 0) }}
                            </td>
                            <td class="p-3 text-center font-mono font-bold text-purple-700">
                                {{ comm.rate_used || 4.5 }}% (1.5% ML Net)
                            </td>
                            <td class="p-3 text-right font-mono font-black text-purple-900 text-sm">
                                {{ formatCurrency(comm.amount) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Summary & Amount Block -->
            <div class="mt-8 pt-6 border-t-2 border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-6">
                <!-- Terbilang Text -->
                <div class="flex-1">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">TERBILANG NOMINAL TAGIHAN:</div>
                    <div class="text-xs font-bold text-slate-700 italic bg-purple-50/60 p-3 rounded-xl border border-purple-100 mt-1">
                        " {{ spelled_text }} "
                    </div>
                </div>

                <!-- Total Amount Block -->
                <div class="bg-gradient-to-r from-purple-900 to-indigo-900 text-white px-8 py-5 rounded-2xl shadow-lg shrink-0 text-right">
                    <div class="text-[10px] font-black text-purple-300 uppercase tracking-widest">TOTAL KLAIM INVOICE</div>
                    <div class="text-3xl font-black mt-1">{{ formatCurrency(invoice.total_amount) }}</div>
                </div>
            </div>

            <!-- Signatures Section -->
            <div class="mt-14 flex justify-between items-center gap-8">
                <div class="text-center w-52">
                    <p class="text-[10px] font-bold text-slate-400">Disetujui Oleh (Developer)</p>
                    <div class="h-20 flex items-center justify-center my-1 text-[10px] text-slate-300 italic font-bold">
                        Tanda Tangan & Cap Tinta Dev
                    </div>
                    <p class="text-xs font-bold text-slate-800 border-t border-slate-200 pt-1">Direksi Developer Properti</p>
                </div>

                <div class="text-center w-52">
                    <p class="text-[10px] font-bold text-slate-500">Hormat Kami (Master Lead)</p>
                    <div class="h-20 flex items-center justify-center my-1 text-[10px] text-purple-400 italic font-bold">
                        {{ masterLead.name }}
                    </div>
                    <p class="text-xs font-bold text-slate-900 border-t border-slate-200 pt-1">{{ masterLead.name }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    body * { visibility: hidden; }
    #invoice-paper, #invoice-paper * { visibility: visible; }
    #invoice-paper { position: absolute; left: 0; top: 0; width: 100%; border: none; padding: 0; margin: 0; }
}
</style>
