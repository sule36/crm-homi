<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    commissions: Object,
    stats: Object,
    brokerCompanies: Array,
    defaultRates: Object,
    schemaConfig: Object,
    masterLeads: Array,
    bankAccounts: Array,
    payoutThresholdPercent: Number,
    filters: Object,
});

const activeTab = ref('commissions');

const showParamModal = ref(false);
const paramForm = useForm({
    inhouse_developer_rate: props.defaultRates?.inhouse_developer || 1.0,
    inhouse_master_lead_rate: props.defaultRates?.inhouse_master_lead || 1.5,
    master_lead_overriding_rate: props.defaultRates?.master_lead_overriding || 4.5,
    agency_rate: props.defaultRates?.agency || 3.0,
    independent_rate: props.defaultRates?.independent || 2.5,
    payout_threshold_percent: props.payoutThresholdPercent || 25.0,
    enable_master_lead: props.schemaConfig?.enable_master_lead ?? true,
    enable_inhouse_developer: props.schemaConfig?.enable_inhouse_developer ?? true,
    enable_inhouse_master_lead: props.schemaConfig?.enable_inhouse_master_lead ?? true,
});

function submitParameters() {
    paramForm.post('/commissions/parameters', {
        onSuccess: () => { showParamModal.value = false; }
    });
}

const showPayModal = ref(false);
const selectedCommission = ref(null);
const payForm = useForm({
    bank_account_id: '',
    notes: '',
});

const openPayModal = (item) => {
    selectedCommission.value = item;
    payForm.bank_account_id = props.bankAccounts && props.bankAccounts.length > 0 ? props.bankAccounts[0].id : '';
    payForm.notes = `Pembayaran komisi ${item.payout_recipient || 'agent'} untuk ${item.booking?.lead?.name || 'Konsumen'}`;
    showPayModal.value = true;
};

const submitPayCommission = () => {
    if (!selectedCommission.value) return;
    payForm.post(`/commissions/${selectedCommission.value.id}/pay`, {
        onSuccess: () => {
            showPayModal.value = false;
            selectedCommission.value = null;
        }
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};

const statusColors = {
    pending: 'bg-amber-100 text-amber-700',
    paid: 'bg-emerald-100 text-emerald-700',
};

// --- SIMULATOR KOMISI & PAJAK ---
const simHargaProperti = ref(1500000000); // 1.5 Milyar
const simBookingFee = ref(15000000); // 15 Juta
const simRateKomisi = ref(2.5); // 2.5%
const simShareUtjPercent = ref(10); // 10% dari Booking Fee cair cepat
const simTipeAgen = ref('independent'); // independent vs agency
const simNpwpStatus = ref('yes'); // yes vs no
const simAgencyStatus = ref('non_pkp'); // pkp vs non_pkp (only for agency)
const simBonusCash = ref(5000000); // 5 Juta bonus closing target
const simBonusBarang = ref('iPhone 15 Pro');

// Kalkulasi
const simGrossCommission = computed(() => {
    return simHargaProperti.value * (simRateKomisi.value / 100);
});

const simClosingFeeUtj = computed(() => {
    return simBookingFee.value * (simShareUtjPercent.value / 100);
});

const simSisaKomisi = computed(() => {
    return Math.max(0, simGrossCommission.value - simClosingFeeUtj.value);
});

// PPN 11% (jika Kantor Agen adalah PKP)
const simPpnNominal = computed(() => {
    if (simTipeAgen.value === 'agency' && simAgencyStatus.value === 'pkp') {
        return simGrossCommission.value * 0.11; // PPN 11%
    }
    return 0;
});

// PPh 21 untuk Agen Perorangan (Independen) & PPh 23 untuk Kantor Agen (Badan Usaha)
const simPajak = computed(() => {
    const gross = simGrossCommission.value;
    const hasNpwp = simNpwpStatus.value === 'yes';
    
    if (simTipeAgen.value === 'independent') {
        // PPh 21 Bukan Pegawai (Norma 50% * DPP * Tarif)
        // DPP = 50% dari bruto. Tarif Pasal 17 progresif (tier 1: 5% s.d 60jt DPP)
        const dpp = gross * 0.5;
        const rate = hasNpwp ? 0.05 : 0.06; // Jika tidak ber-NPWP dikenakan tarif 120% lebih tinggi (6%)
        const nominal = dpp * rate;
        return {
            label: `PPh 21 (Bukan Pegawai ${hasNpwp ? 'NPWP 5%' : 'Non-NPWP 6%'} dari DPP 50%)`,
            nominal: nominal,
            rateDescription: `${hasNpwp ? '2.5%' : '3.0%'} efektif dari komisi bruto`
        };
    } else {
        // PPh 23 untuk Badan Usaha (Kantor Agen / Broker)
        // Dengan NPWP: 2% dari bruto. Tanpa NPWP: 4% dari bruto.
        const rate = hasNpwp ? 0.02 : 0.04;
        const nominal = gross * rate;
        return {
            label: `PPh 23 (Jasa Keagenan Badan ${hasNpwp ? 'NPWP 2%' : 'Non-NPWP 4%'} dari Bruto)`,
            nominal: nominal,
            rateDescription: `${hasNpwp ? '2.0%' : '4.0%'} dari komisi bruto`
        };
    }
});

const simNetCommission = computed(() => {
    return simGrossCommission.value + simPpnNominal.value - simPajak.value.nominal + simBonusCash.value;
});

</script>

<template>
    <Head title="Manajemen Komisi" />
    <CrmLayout>
        <template #breadcrumb>Komisi Sales</template>

        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Elite Commission & Fee Dashboard</h1>
                <p class="text-sm text-slate-500 mt-1">Kelola dan pantau pembayaran komisi Inhouse Agent, Kantor Agency, & Freelance Independen.</p>
            </div>
            <button @click="showParamModal = true" class="px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-md flex items-center gap-2">
                <span>⚙️</span>
                <span>Set Parameter Komisi Bawaan</span>
            </button>
        </div>

        <!-- MODAL PARAMETER SETTINGS -->
        <div v-if="showParamModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-6 space-y-4 text-slate-900 animate-in zoom-in duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-black tracking-tight">Pengaturan Skema Master Lead & Komisi</h3>
                        <p class="text-xs text-slate-400">Atur sakelar keaktifan fitur dan persentase fee komisi bawaan sistem.</p>
                    </div>
                    <button @click="showParamModal = false" class="text-slate-400 hover:text-slate-700">✕</button>
                </div>

                <form @submit.prevent="submitParameters" class="space-y-4 text-xs font-bold">
                    <!-- SAKELAR FITUR (FEATURE TOGGLES) -->
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-3">
                        <span class="text-[10px] font-black text-slate-500 uppercase tracking-wider block mb-1">⚙️ SAKELAR KONSEP AGEN & MASTER LEAD</span>
                        
                        <label class="flex items-center justify-between cursor-pointer p-2 bg-white rounded-xl border border-slate-100">
                            <div>
                                <span class="text-xs font-black text-slate-800 block">👑 Fitur Master Lead</span>
                                <span class="text-[10px] text-slate-400 font-medium">Transaksi & fee dikendalikan oleh Master Lead</span>
                            </div>
                            <input v-model="paramForm.enable_master_lead" type="checkbox" class="w-4 h-4 text-blue-600 rounded" />
                        </label>

                        <label class="flex items-center justify-between cursor-pointer p-2 bg-white rounded-xl border border-slate-100">
                            <div>
                                <span class="text-xs font-black text-slate-800 block">🏠 In-House Developer</span>
                                <span class="text-[10px] text-slate-400 font-medium">Tim sales internal milik Developer langsung</span>
                            </div>
                            <input v-model="paramForm.enable_inhouse_developer" type="checkbox" class="w-4 h-4 text-blue-600 rounded" />
                        </label>

                        <label class="flex items-center justify-between cursor-pointer p-2 bg-white rounded-xl border border-slate-100">
                            <div>
                                <span class="text-xs font-black text-slate-800 block">🤝 In-House Master Lead</span>
                                <span class="text-[10px] text-slate-400 font-medium">Tim sales internal di bawah naungan Master Lead</span>
                            </div>
                            <input v-model="paramForm.enable_inhouse_master_lead" type="checkbox" class="w-4 h-4 text-blue-600 rounded" />
                        </label>
                    </div>

                    <!-- FEE RATES -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block uppercase text-[9px] font-black text-slate-400 mb-1">Fee Master Lead (%)</label>
                            <input v-model.number="paramForm.master_lead_overriding_rate" type="number" step="0.1" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold" />
                        </div>
                        <div>
                            <label class="block uppercase text-[9px] font-black text-slate-400 mb-1">Fee In-House Master Lead (%)</label>
                            <input v-model.number="paramForm.inhouse_master_lead_rate" type="number" step="0.1" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block uppercase text-[9px] font-black text-slate-400 mb-1">In-House Dev (%)</label>
                            <input v-model.number="paramForm.inhouse_developer_rate" type="number" step="0.1" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold" />
                        </div>
                        <div>
                            <label class="block uppercase text-[9px] font-black text-slate-400 mb-1">Agency Broker (%)</label>
                            <input v-model.number="paramForm.agency_rate" type="number" step="0.1" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold" />
                        </div>
                        <div>
                            <label class="block uppercase text-[9px] font-black text-slate-400 mb-1">Independen (%)</label>
                            <input v-model.number="paramForm.independent_rate" type="number" step="0.1" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold" />
                        </div>
                    </div>

                    <!-- THRESHOLD PENCAIRAN KOMISI -->
                    <div class="p-3 bg-emerald-50 rounded-2xl border border-emerald-200/80">
                        <label class="block uppercase text-[9px] font-black text-emerald-800 mb-1">🎯 Syarat Payout Komisi (% Dana Masuk Konsumen)</label>
                        <div class="flex items-center gap-2">
                            <input v-model.number="paramForm.payout_threshold_percent" type="number" step="1" min="0" max="100" class="w-28 px-3 py-2 bg-white border border-emerald-300 rounded-xl font-black text-emerald-900" />
                            <span class="text-xs font-bold text-emerald-800">% dari Harga Unit</span>
                        </div>
                        <p class="text-[10px] text-emerald-700 font-medium mt-1">
                            Komisi ditandai "Dapat Dicairkan (Wajib Bayar)" secara otomatis ketika akumulasi pembayaran konsumen telah mencapai/melewati persentase ini (default: 25%).
                        </p>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="showParamModal = false" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl">Batal</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-black rounded-xl shadow-lg">Simpan Skema Parameter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- STATS -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-3xl p-8 border border-slate-100 shadow-sm">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Total Pending</p>
                <h3 class="text-3xl font-black text-amber-600">{{ formatCurrency(stats.total_pending) }}</h3>
                <div class="mt-4 flex items-center gap-2">
                    <span class="w-2 h-2 bg-amber-500 rounded-full animate-pulse"></span>
                    <p class="text-xs text-slate-400 font-bold uppercase">Menunggu Pembayaran</p>
                </div>
            </div>
            <div class="bg-slate-900 rounded-3xl p-8 text-white shadow-xl">
                <p class="text-[10px] font-black text-blue-400 uppercase tracking-widest mb-2">Total Terbayar</p>
                <h3 class="text-3xl font-black text-white">{{ formatCurrency(stats.total_paid) }}</h3>
                <p class="text-xs text-slate-500 font-bold uppercase mt-4">Accumulated Performance</p>
            </div>
            <div class="bg-blue-600 rounded-3xl p-8 text-white shadow-xl shadow-blue-500/20">
                <p class="text-[10px] font-black text-blue-100 uppercase tracking-widest mb-2">Estimasi Bulan Ini</p>
                <h3 class="text-3xl font-black text-white">{{ formatCurrency(stats.this_month) }}</h3>
                <p class="text-xs text-blue-200 font-bold uppercase mt-4">Current Month Performance</p>
            </div>
        </div>

        <!-- INTERACTIVE COMMISSION & TAX SIMULATOR -->
        <div class="bg-white rounded-[2.5rem] border border-slate-100 p-8 shadow-sm mb-8">
            <div class="mb-6">
                <h2 class="text-base font-black text-slate-900 uppercase tracking-tight flex items-center gap-2">
                    <span>🧮</span> Simulator Benefit & Pajak Keagenan (Closing Fee & Rewards)
                </h2>
                <p class="text-xs text-slate-500 mt-1">Simulasikan perhitungan komisi, potongan pajak PPh 21/PPh 23, serta pembagian closing fee instan dari UTJ dan reward target.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
                <!-- Inputs Section -->
                <div class="lg:col-span-3 space-y-4 text-xs font-bold text-slate-700">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1.5 uppercase text-[10px] tracking-wider text-slate-400">Harga Jual Properti (IDR)</label>
                            <input v-model.number="simHargaProperti" type="number" step="1000000" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-600/20" />
                        </div>
                        <div>
                            <label class="block mb-1.5 uppercase text-[10px] tracking-wider text-slate-400">Booking Fee / UTJ (IDR)</label>
                            <input v-model.number="simBookingFee" type="number" step="100000" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-600/20" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1.5 uppercase text-[10px] tracking-wider text-slate-400">Rate Komisi (%)</label>
                            <input v-model.number="simRateKomisi" type="number" step="0.1" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-600/20" />
                        </div>
                        <div>
                            <label class="block mb-1.5 uppercase text-[10px] tracking-wider text-slate-400">Share Closing Fee UTJ (%)</label>
                            <input v-model.number="simShareUtjPercent" type="number" step="1" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-600/20" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block mb-1.5 uppercase text-[10px] tracking-wider text-slate-400">Kategori / Tipe Agen</label>
                            <select v-model="simTipeAgen" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-600/20 cursor-pointer">
                                <option value="independent">Agen Independen (Perorangan)</option>
                                <option value="agency">Kantor Agen / Broker (Badan Usaha)</option>
                            </select>
                        </div>
                        <div v-if="simTipeAgen === 'agency'">
                            <label class="block mb-1.5 uppercase text-[10px] tracking-wider text-slate-400">Status PKP Kantor</label>
                            <select v-model="simAgencyStatus" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-600/20 cursor-pointer">
                                <option value="pkp">PKP (Wajib PPN 11%)</option>
                                <option value="non_pkp">Non-PKP (Bebas PPN)</option>
                            </select>
                        </div>
                        <div :class="simTipeAgen === 'agency' ? '' : 'md:col-span-2'">
                            <label class="block mb-1.5 uppercase text-[10px] tracking-wider text-slate-400">Status Kepemilikan NPWP</label>
                            <select v-model="simNpwpStatus" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-600/20 cursor-pointer">
                                <option value="yes">Memiliki NPWP</option>
                                <option value="no">TIDAK Memiliki NPWP</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1.5 uppercase text-[10px] tracking-wider text-slate-400">Bonus Uang Tunai Tambahan (IDR)</label>
                            <input v-model.number="simBonusCash" type="number" step="500000" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-600/20" />
                        </div>
                        <div>
                            <label class="block mb-1.5 uppercase text-[10px] tracking-wider text-slate-400">Reward Barang / Trip</label>
                            <input v-model="simBonusBarang" type="text" placeholder="Misal: iPhone 15 Pro, Trip Thailand" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-blue-600/20" />
                        </div>
                    </div>
                </div>

                <!-- Simulation Result Receipt Section -->
                <div class="lg:col-span-2 bg-slate-900 rounded-3xl p-6 text-white shadow-xl flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-center pb-4 border-b border-white/10 mb-4">
                            <span class="text-[10px] font-black uppercase tracking-wider text-blue-400">Hasil Simulasi Pajak & Komisi</span>
                            <span class="px-2 py-0.5 bg-blue-500 text-white rounded text-[8px] font-black uppercase">Estimasi</span>
                        </div>

                        <div class="space-y-3 text-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Komisi Kotor (Gross):</span>
                                <span class="font-bold text-blue-300">{{ formatCurrency(simGrossCommission) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Closing Fee UTJ (Cair Awal):</span>
                                <span class="font-bold text-emerald-400">{{ formatCurrency(simClosingFeeUtj) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Sisa Komisi Progresif:</span>
                                <span class="font-bold">{{ formatCurrency(simSisaKomisi) }}</span>
                            </div>

                            <!-- PPN 11% Row (Agency PKP only) -->
                            <div v-if="simTipeAgen === 'agency' && simAgencyStatus === 'pkp'" class="pt-3 border-t border-white/10 space-y-1">
                                <div class="flex justify-between text-blue-400">
                                    <span class="text-slate-400">PPN 11% (Tagihan Tambahan):</span>
                                    <span class="font-bold">+ {{ formatCurrency(simPpnNominal) }}</span>
                                </div>
                                <p class="text-[9px] text-slate-500 italic font-medium text-right">Dipungut oleh kantor agen kepada developer PKP</p>
                            </div>

                            <div class="pt-3 border-t border-white/10 space-y-1">
                                <div class="flex justify-between text-rose-400">
                                    <span class="text-slate-400">Potongan Pajak:</span>
                                    <span class="font-bold">- {{ formatCurrency(simPajak.nominal) }}</span>
                                </div>
                                <p class="text-[9px] text-slate-500 italic font-medium text-right">{{ simPajak.label }}</p>
                                <p class="text-[9px] text-slate-500 italic font-medium text-right">Tarif efektif: {{ simPajak.rateDescription }}</p>
                            </div>

                            <div v-if="simBonusCash > 0 || simBonusBarang" class="pt-3 border-t border-white/10 space-y-1">
                                <div v-if="simBonusCash > 0" class="flex justify-between text-emerald-400">
                                    <span class="text-slate-400">Bonus Tunai:</span>
                                    <span class="font-bold">+ {{ formatCurrency(simBonusCash) }}</span>
                                </div>
                                <div v-if="simBonusBarang" class="flex justify-between text-yellow-400">
                                    <span class="text-slate-400">Reward Properti:</span>
                                    <span class="font-bold text-right text-[10px]">{{ simBonusBarang }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-white/10 mt-6">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-black uppercase text-slate-400">Total Cair Bersih (Nett)</span>
                            <span class="text-lg font-black text-emerald-400">{{ formatCurrency(simNetCommission) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- COMMISSIONS TABLE & MASTER LEAD INVOICES TAB -->
        <div class="bg-white rounded-[2.5rem] border border-slate-100 overflow-hidden shadow-sm">
            <div class="px-8 py-6 border-b border-slate-50 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex items-center gap-2 bg-slate-100 p-1 rounded-2xl">
                    <button 
                        @click="activeTab = 'commissions'" 
                        :class="[activeTab === 'commissions' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900']"
                        class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all"
                    >
                        💸 Daftar Komisi Sales & Agency
                    </button>
                    <button 
                        @click="activeTab = 'ml_invoices'" 
                        :class="[activeTab === 'ml_invoices' ? 'bg-purple-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900']"
                        class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center gap-1.5"
                    >
                        <span>📄 Invoice Master Lead</span>
                        <span v-if="masterLeadInvoices && masterLeadInvoices.length > 0" class="px-2 py-0.5 bg-purple-800 text-white text-[10px] rounded-full">
                            {{ masterLeadInvoices.length }}
                        </span>
                    </button>
                </div>

                <div v-if="activeTab === 'commissions'" class="flex gap-2">
                    <Link href="/commissions" :class="!filters.status ? 'bg-slate-900 text-white' : 'bg-slate-50 text-slate-600'" class="px-4 py-2 rounded-xl text-xs font-black uppercase transition-all">Semua</Link>
                    <Link href="/commissions?status=pending" :class="filters.status === 'pending' ? 'bg-amber-500 text-white' : 'bg-slate-50 text-slate-600'" class="px-4 py-2 rounded-xl text-xs font-black uppercase transition-all">Pending</Link>
                    <Link href="/commissions?status=paid" :class="filters.status === 'paid' ? 'bg-emerald-500 text-white' : 'bg-slate-50 text-slate-600'" class="px-4 py-2 rounded-xl text-xs font-black uppercase transition-all">Paid</Link>
                </div>
            </div>

            <!-- TAB 1: COMMISSIONS TABLE -->
            <div v-if="activeTab === 'commissions'" class="overflow-x-auto max-w-full touch-pan-x">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="bg-slate-50 text-[10px] font-black text-slate-500 uppercase tracking-widest border-b border-slate-100">
                            <th class="px-8 py-5">Sales Agent</th>
                            <th class="px-8 py-5">Detail Booking</th>
                            <th class="px-8 py-5 text-right">Jumlah Komisi</th>
                            <th class="px-8 py-5">Rekening Tujuan</th>
                            <th class="px-8 py-5 text-center">Status</th>
                            <th class="px-8 py-5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="item in commissions.data" :key="item.id" class="hover:bg-slate-50/50 transition-colors group">
                            <td class="px-8 py-6">
                                <p class="text-sm font-black text-slate-900">{{ item.user?.name }}</p>
                                <div class="mt-1">
                                    <span v-if="item.user?.agent_type === 'agency_agent' || item.broker_company || item.user?.broker_company_id" class="px-2 py-0.5 bg-amber-100 text-amber-800 text-[10px] font-bold rounded-md">
                                        🏢 Sub-Agent: {{ item.broker_company?.name || item.user?.broker_company?.name || 'Kantor Agency' }}
                                    </span>
                                    <span v-else-if="item.user?.agent_type === 'independent'" class="px-2 py-0.5 bg-emerald-100 text-emerald-800 text-[10px] font-bold rounded-md">
                                        💼 Freelance Independen
                                    </span>
                                    <span v-else class="px-2 py-0.5 bg-blue-100 text-blue-800 text-[10px] font-bold rounded-md">
                                        🏠 In-House Agent
                                    </span>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-lg border border-slate-100 group-hover:bg-white transition-all">🏠</div>
                                    <div>
                                        <p class="text-xs font-black text-slate-900">{{ item.booking?.unit?.code }} - {{ item.booking?.lead?.name }}</p>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter">SPK: {{ item.booking?.spk_number }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-6 text-right">
                                <p class="text-sm font-black text-blue-600">{{ formatCurrency(item.display_payout_amount || item.amount) }}</p>
                                <p class="text-[10px] text-slate-400 font-bold uppercase">Generated at {{ new Date(item.created_at).toLocaleDateString('id-ID') }}</p>
                            </td>
                            <td class="px-8 py-6">
                                <div v-if="item.user?.effective_bank_account?.is_joint" class="p-3 bg-purple-50/80 border border-purple-200 rounded-2xl text-xs space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[9px] font-black uppercase text-purple-900 bg-purple-200 px-1.5 py-0.5 rounded">👑 Joint ML (Kana x Homi)</span>
                                    </div>
                                    <div class="text-[10px] font-bold text-slate-800">
                                        • BCA <span class="font-mono font-black text-purple-900">4500959555</span> (Kana Project)
                                    </div>
                                    <div class="text-[10px] font-bold text-slate-800 border-t border-purple-200/60 pt-1">
                                        • BCA <span class="font-mono font-black text-purple-900">012001004640307</span> (Homi ID)
                                    </div>
                                </div>
                                <div v-else-if="item.user?.effective_bank_account" class="p-3 rounded-2xl border transition-all"
                                    :class="item.user.effective_bank_account.is_office ? 'bg-amber-50/60 border-amber-200 text-amber-950' : 'bg-slate-50 border-slate-100 text-slate-900'">
                                    <div class="flex items-center justify-between gap-1 mb-1">
                                        <span class="text-[10px] font-black uppercase" :class="item.user.effective_bank_account.is_office ? 'text-amber-900' : 'text-slate-900'">
                                            {{ item.user.effective_bank_account.bank_name }}
                                        </span>
                                        <span v-if="item.user.effective_bank_account.is_office" class="text-[9px] font-extrabold bg-amber-200 text-amber-900 px-1.5 py-0.5 rounded">
                                            🏢 Rekening Kantor Agency
                                        </span>
                                        <span v-else class="text-[9px] font-extrabold bg-slate-200 text-slate-700 px-1.5 py-0.5 rounded">
                                            💼 Rekening Agen
                                        </span>
                                    </div>
                                    <p class="text-xs font-black text-blue-600 tracking-wider">{{ item.user.effective_bank_account.bank_account_number }}</p>
                                    <p class="text-[9px] font-bold uppercase mt-1" :class="item.user.effective_bank_account.is_office ? 'text-amber-800' : 'text-slate-500'">
                                        A/N {{ item.user.effective_bank_account.bank_account_name }}
                                    </p>
                                </div>
                                <p v-else class="text-[10px] text-rose-400 font-black uppercase">Data Bank Kosong!</p>
                            </td>
                            <td class="px-8 py-6 text-center">
                                <span :class="statusColors[item.status]" class="px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider">
                                    {{ item.status }}
                                </span>

                                <!-- INDICATOR KELAYAKAN PAYOUT AUTOMATIS -->
                                <div class="mt-2 space-y-1">
                                    <div v-if="item.status === 'pending'">
                                        <span v-if="item.is_payout_eligible" class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 border border-emerald-200 text-emerald-800 text-[10px] font-black rounded-lg shadow-sm">
                                            <span>🟢</span> <span>Wajib Dibayar ({{ item.buyer_paid_percent }}% Masuk)</span>
                                        </span>
                                        <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 border border-amber-200 text-amber-800 text-[10px] font-bold rounded-lg">
                                            <span>🟡</span> <span>Pending ({{ item.buyer_paid_percent }}% / {{ item.payout_threshold_percent }}%)</span>
                                        </span>
                                    </div>
                                    <div v-else>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 border border-blue-200 text-blue-800 text-[10px] font-bold rounded-lg">
                                            <span>📘</span> <span>Tercatat Buku Besar</span>
                                        </span>
                                    </div>
                                </div>

                                <p v-if="item.paid_at" class="text-[9px] text-slate-400 mt-1 font-bold">{{ new Date(item.paid_at).toLocaleDateString('id-ID') }}</p>
                            </td>
                            <td class="px-8 py-6 text-right">
                                <div v-if="item.status === 'pending'" class="flex justify-end">
                                    <button @click="openPayModal(item)" 
                                        :class="item.is_payout_eligible ? 'bg-emerald-600 hover:bg-emerald-700 ring-2 ring-emerald-500/20' : 'bg-slate-900 hover:bg-slate-800'"
                                        class="px-5 py-2.5 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:-translate-y-0.5 transition-all shadow-lg active:scale-95 flex items-center gap-1.5">
                                        <span>💸</span> <span>Bayar Komisi</span>
                                    </button>
                                </div>
                                <div v-else class="text-right">
                                    <p class="text-[10px] font-black text-emerald-600 uppercase">{{ item.receipt_number }}</p>
                                    <p v-if="item.bank_account" class="text-[9px] text-slate-500 font-bold">via {{ item.bank_account.bank_name }}</p>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!commissions.data.length">
                            <td colspan="6" class="px-8 py-16 text-center">
                                <p class="text-4xl mb-3">💸</p>
                                <p class="text-xs font-black text-slate-400 uppercase tracking-widest">Belum Ada Riwayat Komisi</p>
                                <p class="text-xs text-slate-500 mt-1">Daftar komisi penjualan per-unit dari booking yang disetujui akan muncul di sini.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- TAB 2: MASTER LEAD INVOICES TABLE -->
            <div v-else-if="activeTab === 'ml_invoices'" class="overflow-x-auto max-w-full touch-pan-x">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="bg-purple-50/60 text-[10px] font-black text-purple-900 uppercase tracking-widest border-b border-purple-100">
                            <th class="px-8 py-5">No. Invoice & Kategori</th>
                            <th class="px-8 py-5">Master Lead Partner</th>
                            <th class="px-8 py-5">Target / Rincian Claim</th>
                            <th class="px-8 py-5 text-right">Nominal Tagihan Net ML</th>
                            <th class="px-8 py-5 text-center">Status Pencairan</th>
                            <th class="px-8 py-5 text-right">Aksi Developer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-purple-50">
                        <tr v-for="inv in masterLeadInvoices" :key="inv.id" class="hover:bg-purple-50/30 transition-all">
                            <td class="px-8 py-6">
                                <p class="text-sm font-black font-mono text-purple-900">{{ inv.invoice_number }}</p>
                                <div class="mt-1">
                                    <span v-if="inv.invoice_type === 'closing_fee'" class="px-2.5 py-1 bg-amber-100 text-amber-800 text-[10px] font-black rounded-md uppercase">
                                        ⚡ CLOSING FEE
                                    </span>
                                    <span v-else-if="inv.invoice_type === 'reward'" class="px-2.5 py-1 bg-emerald-100 text-emerald-800 text-[10px] font-black rounded-md uppercase">
                                        🎁 REWARD IPHONE
                                    </span>
                                    <span v-else class="px-2.5 py-1 bg-purple-100 text-purple-800 text-[10px] font-black rounded-md uppercase">
                                        📄 KOMISI OVERRIDING
                                    </span>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <p class="text-sm font-black text-slate-900">👑 {{ inv.master_lead?.name || 'KANAHOMI' }}</p>
                                <p class="text-[10px] text-slate-400 font-bold">Joint Operating (Kana Project x Homi ID)</p>
                            </td>
                            <td class="px-8 py-6">
                                <div v-if="inv.invoice_type === 'reward'" class="text-xs font-bold text-slate-800">
                                    🎁 {{ inv.reward_name || 'Reward iPhone 16 Pro (Konversi Cash)' }}
                                </div>
                                <div v-else-if="inv.invoice_type === 'closing_fee'" class="text-xs font-bold text-slate-800">
                                    ⚡ Claim Closing Fee ({{ formatCurrency(inv.fee_per_unit || 2500000) }} / Unit)
                                </div>
                                <div v-else class="text-xs font-bold text-slate-800">
                                    📄 {{ inv.commissions?.length || 0 }} Unit Properti Deal
                                </div>
                            </td>
                            <td class="px-8 py-6 text-right">
                                <p class="text-base font-black font-mono text-purple-700">{{ formatCurrency(inv.total_amount) }}</p>
                                <p class="text-[10px] text-slate-400 font-bold uppercase">Submitted {{ new Date(inv.submitted_at).toLocaleDateString('id-ID') }}</p>
                            </td>
                            <td class="px-8 py-6 text-center">
                                <span v-if="inv.status === 'paid'" class="px-3 py-1 bg-emerald-100 text-emerald-700 border border-emerald-300 rounded-full font-black text-[10px] uppercase">
                                    🟢 LUNAS / DICAIRKAN
                                </span>
                                <span v-else class="px-3 py-1 bg-amber-100 text-amber-700 border border-amber-300 rounded-full font-black text-[10px] uppercase">
                                    ⏳ MENUNGGU PENCAIRAN DEV
                                </span>
                            </td>
                            <td class="px-8 py-6 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a 
                                        :href="route('master-leads.invoices.show', inv.id)" 
                                        target="_blank" 
                                        class="px-3 py-1.5 bg-purple-100 hover:bg-purple-200 text-purple-900 border border-purple-200 rounded-xl text-[11px] font-bold transition-all"
                                    >
                                        <span>🖨️ Invoice PDF</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!masterLeadInvoices || !masterLeadInvoices.length">
                            <td colspan="6" class="px-8 py-16 text-center">
                                <p class="text-4xl mb-3">📄</p>
                                <p class="text-xs font-black text-slate-400 uppercase tracking-widest">Belum Ada Invoice Tagihan Master Lead</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL BAYAR KOMISI & INTEGRASI BUKU BESAR -->
        <div v-if="showPayModal && selectedCommission" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 space-y-4 text-slate-900 animate-in zoom-in duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-black tracking-tight">Cairkan Komisi & Catat Kas Out</h3>
                        <p class="text-xs text-slate-400">Konfirmasi pencairan dana komisi ke penerbit/agent.</p>
                    </div>
                    <button @click="showPayModal = false" class="text-slate-400 hover:text-slate-700">✕</button>
                </div>

                <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-500 font-bold">Penerima Komisi:</span>
                        <span class="font-black text-slate-900">{{ selectedCommission.user?.name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500 font-bold">Unit / Konsumen:</span>
                        <span class="font-bold text-slate-800">{{ selectedCommission.booking?.unit?.code }} - {{ selectedCommission.booking?.lead?.name }}</span>
                    </div>
                    <div class="flex justify-between border-t border-slate-200 pt-2">
                        <span class="text-slate-700 font-black">Nominal Komisi:</span>
                        <span class="font-black text-emerald-600 text-sm">{{ formatCurrency(selectedCommission.display_payout_amount || selectedCommission.amount) }}</span>
                    </div>
                </div>

                <!-- Recipient Bank Account Card -->
                <div v-if="selectedCommission.user?.effective_bank_account" class="p-3 bg-amber-50/70 border border-amber-200 rounded-2xl text-xs space-y-1">
                    <span class="text-[10px] font-black text-amber-900 uppercase block">🏦 Rekening Tujuan Transfer Agen</span>
                    <p class="font-black text-slate-900">{{ selectedCommission.user.effective_bank_account.bank_name }} - {{ selectedCommission.user.effective_bank_account.bank_account_number }}</p>
                    <p class="text-[10px] font-bold text-slate-600">A/N {{ selectedCommission.user.effective_bank_account.bank_account_name }}</p>
                </div>

                <form @submit.prevent="submitPayCommission" class="space-y-4 text-xs font-bold">
                    <div>
                        <label class="block uppercase text-[10px] font-black text-slate-500 mb-1">Pilih Akun Bank Perusahaan (Pengeluaran)</label>
                        <select v-model="payForm.bank_account_id" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold text-xs text-slate-800 focus:ring-1 focus:ring-blue-500">
                            <option value="">-- Pilih Akun Bank Perusahaan --</option>
                            <option v-for="acc in bankAccounts" :key="acc.id" :value="acc.id">
                                {{ acc.bank_name }} - {{ acc.account_number }} (A/N {{ acc.account_name }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block uppercase text-[10px] font-black text-slate-500 mb-1">Catatan Tambahan (Opsional)</label>
                        <textarea v-model="payForm.notes" rows="2" placeholder="Catatan transaksi..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-1 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="p-3 bg-blue-50/80 border border-blue-200 rounded-xl text-[11px] text-blue-900">
                        <span class="font-black block mb-0.5">📘 Integrasi Buku Besar (General Ledger)</span>
                        <p class="text-[10px] font-medium leading-relaxed">
                            Transaksi ini akan otomatis memotong saldo akun bank terpilih dan dicatat sebagai beban komisi penjualan pada laporan arus kas.
                        </p>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                        <button type="button" @click="showPayModal = false" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl">Batal</button>
                        <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl shadow-lg flex items-center gap-1.5">
                            <span>✅</span> <span>Konfirmasi Pembayaran</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </CrmLayout>
</template>
