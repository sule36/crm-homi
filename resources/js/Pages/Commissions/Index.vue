<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    commissions: Object,
    stats: Object,
    filters: Object,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};

const payCommission = (id) => {
    if (confirm('Konfirmasi pembayaran komisi ini? Pastikan Anda sudah mentransfer ke rekening sales.')) {
        router.post(`/commissions/${id}/pay`);
    }
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

        <div class="mb-8">
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Elite Commission Dashboard</h1>
            <p class="text-sm text-slate-500 mt-1">Kelola dan pantau pembayaran insentif tim sales Anda.</p>
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

        <!-- COMMISSIONS TABLE -->
        <div class="bg-white rounded-[2.5rem] border border-slate-100 overflow-hidden shadow-sm">
            <div class="px-8 py-6 border-b border-slate-50 flex items-center justify-between">
                <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Daftar Komisi</h2>
                <div class="flex gap-2">
                    <Link href="/commissions" :class="!filters.status ? 'bg-slate-900 text-white' : 'bg-slate-50 text-slate-600'" class="px-4 py-2 rounded-xl text-xs font-black uppercase transition-all">Semua</Link>
                    <Link href="/commissions?status=pending" :class="filters.status === 'pending' ? 'bg-amber-500 text-white' : 'bg-slate-50 text-slate-600'" class="px-4 py-2 rounded-xl text-xs font-black uppercase transition-all">Pending</Link>
                    <Link href="/commissions?status=paid" :class="filters.status === 'paid' ? 'bg-emerald-500 text-white' : 'bg-slate-50 text-slate-600'" class="px-4 py-2 rounded-xl text-xs font-black uppercase transition-all">Paid</Link>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
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
                                <p class="text-[10px] text-slate-400 font-bold uppercase">{{ item.user?.email }}</p>
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
                                <p class="text-sm font-black text-blue-600">{{ formatCurrency(item.amount) }}</p>
                                <p class="text-[10px] text-slate-400 font-bold uppercase">Generated at {{ new Date(item.created_at).toLocaleDateString('id-ID') }}</p>
                            </td>
                            <td class="px-8 py-6">
                                <div v-if="item.user?.bank_account_number" class="p-3 bg-slate-50 rounded-2xl border border-slate-100 group-hover:bg-white transition-all">
                                    <p class="text-[10px] font-black text-slate-900 uppercase">{{ item.user.bank_name }}</p>
                                    <p class="text-xs font-black text-blue-600 tracking-wider">{{ item.user.bank_account_number }}</p>
                                    <p class="text-[9px] text-slate-400 font-bold uppercase mt-1">A/N {{ item.user.bank_account_name }}</p>
                                </div>
                                <p v-else class="text-[10px] text-rose-400 font-black uppercase">Data Bank Kosong!</p>
                            </td>
                            <td class="px-8 py-6 text-center">
                                <span :class="statusColors[item.status]" class="px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider">
                                    {{ item.status }}
                                </span>
                                <p v-if="item.paid_at" class="text-[9px] text-slate-400 mt-1 font-bold">{{ new Date(item.paid_at).toLocaleDateString('id-ID') }}</p>
                            </td>
                            <td class="px-8 py-6 text-right">
                                <div v-if="item.status === 'pending'" class="flex justify-end">
                                    <button @click="payCommission(item.id)" 
                                        class="px-5 py-2.5 bg-slate-900 text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:-translate-y-0.5 transition-all shadow-lg active:scale-95">
                                        Bayar & Cetak
                                    </button>
                                </div>
                                <div v-else class="text-right">
                                    <p class="text-[10px] font-black text-emerald-600 uppercase">{{ item.receipt_number }}</p>
                                    <button class="text-[9px] text-blue-500 font-bold uppercase hover:underline">Download Bukti</button>
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
        </div>
    </CrmLayout>
</template>
