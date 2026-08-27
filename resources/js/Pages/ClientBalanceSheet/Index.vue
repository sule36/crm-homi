<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const props = defineProps({
    sheets: Object,
    stats: Object,
    leads: Array,
    bookings: Array,
    filters: Object,
});

const showModal = ref(false);
const editingSheet = ref(null);

const form = useForm({
    client_name: '',
    company_name: '',
    business_type: 'Perdagangan / Usaha',
    phone: '',
    lead_id: null,
    booking_id: null,

    // Aktiva Lancar
    cash_and_bank: 150000000,
    inventory: 200000000,
    receivables: 100000000,
    other_current_assets: 0,

    // Aktiva Tetap
    equipment: 50000000,
    vehicles: 250000000,
    machinery_and_buildings: 500000000,
    accumulated_depreciation: 50000000,

    // Pasiva: Kewajiban
    trade_payables: 100000000,
    bank_loans: 150000000,
    other_liabilities: 0,

    // Pasiva: Ekuitas
    capital: 500000000,
    retained_earnings: 500000000,
    drawings_prive: 50000000,

    // Omset & Profit Bulanan
    monthly_revenue: 120000000,
    monthly_net_profit: 35000000,
    existing_monthly_debt_service: 5000000,

    // Target KPR
    target_kpr_amount: 3500000000,
    target_tenor_years: 15,
    target_interest_rate: 5.0,
    notes: '',
});

// Live Computed Totals
const totalAktivaLancar = computed(() => Number(form.cash_and_bank || 0) + Number(form.inventory || 0) + Number(form.receivables || 0) + Number(form.other_current_assets || 0));
const totalAktivaTetap = computed(() => (Number(form.equipment || 0) + Number(form.vehicles || 0) + Number(form.machinery_and_buildings || 0)) - Number(form.accumulated_depreciation || 0));
const totalAktiva = computed(() => totalAktivaLancar.value + totalAktivaTetap.value);

const totalKewajiban = computed(() => Number(form.trade_payables || 0) + Number(form.bank_loans || 0) + Number(form.other_liabilities || 0));
const totalEkuitas = computed(() => (Number(form.capital || 0) + Number(form.retained_earnings || 0)) - Number(form.drawings_prive || 0));
const totalPasiva = computed(() => totalKewajiban.value + totalEkuitas.value);

const isBalanced = computed(() => Math.abs(totalAktiva.value - totalPasiva.value) < 100);

// Live Estimated New KPR Installment
const liveNewInstallment = computed(() => {
    const amount = Number(form.target_kpr_amount || 0);
    const years = Number(form.target_tenor_years || 15);
    const ratePercent = Number(form.target_interest_rate || 5.0);
    if (amount <= 0 || years <= 0) return 0;
    const months = years * 12;
    const monthlyRate = (ratePercent / 100) / 12;
    if (monthlyRate <= 0) return amount / months;
    return (amount * monthlyRate) / (1 - Math.pow(1 + monthlyRate, -months));
});

// Live Ratios & Score
const liveCurrentRatio = computed(() => totalKewajiban.value > 0 ? (totalAktivaLancar.value / totalKewajiban.value).toFixed(2) : '9.99');
const liveDerRatio = computed(() => totalEkuitas.value > 0 ? (totalKewajiban.value / totalEkuitas.value).toFixed(2) : '9.99');
const liveDsrRatio = computed(() => {
    const profit = Number(form.monthly_net_profit || 0);
    const totalDebtService = Number(form.existing_monthly_debt_service || 0) + liveNewInstallment.value;
    return profit > 0 ? ((totalDebtService / profit) * 100).toFixed(1) : '100.0';
});

const liveApprovalScore = computed(() => {
    const cr = parseFloat(liveCurrentRatio.value);
    const der = parseFloat(liveDerRatio.value);
    const dsr = parseFloat(liveDsrRatio.value);
    if (cr >= 1.5 && der <= 1.5 && dsr <= 35.0) return 'HIGH';
    if (cr >= 1.0 && der <= 2.5 && dsr <= 50.0) return 'MEDIUM';
    return 'LOW';
});

function openModal(sheet = null) {
    editingSheet.value = sheet;
    if (sheet) {
        Object.keys(form.data()).forEach(k => {
            if (sheet[k] !== undefined) form[k] = sheet[k];
        });
    } else {
        form.reset();
    }
    showModal.value = true;
}

function submitForm() {
    if (editingSheet.value) {
        form.put(route('client-balance-sheets.update', editingSheet.value.id), {
            onSuccess: () => { showModal.value = false; }
        });
    } else {
        form.post(route('client-balance-sheets.store'), {
            onSuccess: () => { showModal.value = false; }
        });
    }
}

function deleteSheet(sheet) {
    if (confirm(`Hapus analisis neraca atas nama ${sheet.client_name}?`)) {
        router.delete(route('client-balance-sheets.destroy', sheet.id));
    }
}

const formatCurrency = (val) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0);
</script>

<template>
    <Head title="Analisis Neraca Client & Skor Kelayakan KPR" />

    <CrmLayout>
        <div class="space-y-6">
            <!-- Header Banner -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/60 backdrop-blur-xl border border-slate-800 p-6 rounded-2xl">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
                        <span>📊</span>
                        <span>Analisis Neraca Client & Skor KPR Bank</span>
                    </h1>
                    <p class="text-sm text-slate-400 mt-1">
                        Formulasi struktur Neraca Keuangan Client (Aktiva vs Pasiva) & otomatisasi rasio Likuiditas / Solvabilitas / DSR untuk percepatan approval KPR Bank.
                    </p>
                </div>
                <div>
                    <button 
                        @click="openModal()"
                        class="px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold rounded-xl text-sm transition-all shadow-lg shadow-amber-500/20 flex items-center gap-2"
                    >
                        <span>➕</span>
                        <span>Input Neraca Client Baru</span>
                    </button>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-slate-900/40 border border-slate-800 p-4 rounded-xl flex items-center justify-between">
                    <div>
                        <div class="text-xs text-slate-400 font-medium">Total Neraca Dianalisis</div>
                        <div class="text-2xl font-bold text-white mt-1">{{ stats.total_analyzed }} Client</div>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center font-bold text-lg">📄</div>
                </div>
                <div class="bg-slate-900/40 border border-slate-800 p-4 rounded-xl flex items-center justify-between">
                    <div>
                        <div class="text-xs text-emerald-400 font-medium">High Approval Chance</div>
                        <div class="text-2xl font-bold text-emerald-400 mt-1">{{ stats.high_approval }} Client</div>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold text-lg">🟢</div>
                </div>
                <div class="bg-slate-900/40 border border-slate-800 p-4 rounded-xl flex items-center justify-between">
                    <div>
                        <div class="text-xs text-amber-400 font-medium">Medium Approval (Pertimbangan)</div>
                        <div class="text-2xl font-bold text-amber-400 mt-1">{{ stats.medium_approval }} Client</div>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center font-bold text-lg">🟡</div>
                </div>
                <div class="bg-slate-900/40 border border-slate-800 p-4 rounded-xl flex items-center justify-between">
                    <div>
                        <div class="text-xs text-rose-400 font-medium">Low Approval (High Risk)</div>
                        <div class="text-2xl font-bold text-rose-400 mt-1">{{ stats.low_approval }} Client</div>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-rose-500/10 text-rose-400 flex items-center justify-center font-bold text-lg">🔴</div>
                </div>
            </div>

            <!-- List Table -->
            <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden">
                <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <span>📑</span> Daftar Analisis Neraca Prospek KPR
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="bg-slate-800/80 text-xs uppercase tracking-wider text-slate-400 border-b border-slate-700">
                            <tr>
                                <th class="p-4">Nama Client & Usaha</th>
                                <th class="p-4">Total Aktiva (Rp)</th>
                                <th class="p-4">Total Pasiva (Rp)</th>
                                <th class="p-4">Rasio Finansial</th>
                                <th class="p-4">Target Plafond KPR</th>
                                <th class="p-4 text-center">Skor Kelayakan Bank</th>
                                <th class="p-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="item in sheets.data" :key="item.id" class="hover:bg-slate-800/40 transition-colors">
                                <td class="p-4">
                                    <div class="font-bold text-white">{{ item.client_name }}</div>
                                    <div class="text-xs text-amber-400 font-medium">{{ item.company_name || item.business_type }}</div>
                                    <div class="text-xs text-slate-500">{{ item.phone || '-' }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-emerald-400">{{ formatCurrency(item.cash_and_bank + item.inventory + item.receivables + item.other_current_assets + item.equipment + item.vehicles + item.machinery_and_buildings - item.accumulated_depreciation) }}</div>
                                    <div class="text-xs text-slate-400">AL: {{ formatCurrency(item.cash_and_bank + item.inventory + item.receivables + item.other_current_assets) }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-blue-400">{{ formatCurrency(item.trade_payables + item.bank_loans + item.other_liabilities + item.capital + item.retained_earnings - item.drawings_prive) }}</div>
                                    <div class="text-xs text-slate-400">Hutang: {{ formatCurrency(item.trade_payables + item.bank_loans + item.other_liabilities) }}</div>
                                </td>
                                <td class="p-4 text-xs space-y-0.5">
                                    <div><span class="text-slate-400">CR:</span> <strong class="text-white">{{ item.current_ratio }}x</strong></div>
                                    <div><span class="text-slate-400">DER:</span> <strong class="text-white">{{ item.der_ratio }}x</strong></div>
                                    <div><span class="text-slate-400">DSR:</span> <strong class="text-white">{{ item.dsr_ratio }}%</strong></div>
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-amber-400">{{ formatCurrency(item.target_kpr_amount) }}</div>
                                    <div class="text-xs text-slate-400">{{ item.target_tenor_years }} Thn @ {{ item.target_interest_rate }}%</div>
                                </td>
                                <td class="p-4 text-center">
                                    <span v-if="item.approval_score === 'high'" class="px-3 py-1 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-full text-xs font-bold">🟢 High (Sangat Layak)</span>
                                    <span v-else-if="item.approval_score === 'medium'" class="px-3 py-1 bg-amber-500/10 border border-amber-500/20 text-amber-400 rounded-full text-xs font-bold">🟡 Medium (Pertimbangan)</span>
                                    <span v-else class="px-3 py-1 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-full text-xs font-bold">🔴 Low (Risiko Tinggi)</span>
                                </td>
                                <td class="p-4 text-right space-x-2">
                                    <button @click="openModal(item)" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-amber-400 rounded-lg text-xs font-semibold">Edit</button>
                                    <button @click="deleteSheet(item)" class="px-3 py-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-lg text-xs font-semibold">Hapus</button>
                                </td>
                            </tr>
                            <tr v-if="sheets.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-slate-500">Belum ada analisis neraca client. Klik "Input Neraca Client Baru" di atas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL FORM NERACA CLIENT (STRUCTURED LIKE HANDWRITTEN NOTE) -->
        <div v-if="showModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 overflow-y-auto">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-5xl w-full p-6 space-y-6 my-8 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                    <div>
                        <h3 class="text-xl font-bold text-white">{{ editingSheet ? 'Edit Analisis Neraca Client' : 'Input Analisis Neraca Keuangan Client' }}</h3>
                        <p class="text-xs text-slate-400">Format struktur Neraca Keuangan (Aktiva vs Pasiva) untuk simulasi kelayakan KPR Bank.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="isBalanced ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border-rose-500/30 text-rose-400'" class="px-3 py-1.5 border rounded-xl text-xs font-bold flex items-center gap-1.5">
                            <span>{{ isBalanced ? '✅ BALANCED' : '❌ UNBALANCED' }}</span>
                            <span>(Aktiva: {{ formatCurrency(totalAktiva) }} | Pasiva: {{ formatCurrency(totalPasiva) }})</span>
                        </span>
                    </div>
                </div>

                <form @submit.prevent="submitForm" class="space-y-6 text-sm">
                    <!-- Client Info Section -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 bg-slate-950 p-4 rounded-xl border border-slate-800">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Client *</label>
                            <input v-model="form.client_name" required type="text" placeholder="Nama Pembeli KPR" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Perusahaan / Usaha</label>
                            <input v-model="form.company_name" type="text" placeholder="misal: PT/CV Merdeka" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Jenis Bidang Usaha</label>
                            <input v-model="form.business_type" type="text" placeholder="misal: Perdagangan/Jasa" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">No. HP Client</label>
                            <input v-model="form.phone" type="text" placeholder="0812..." class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white" />
                        </div>
                    </div>

                    <!-- BALANCE SHEET GRID 2-COLUMNS (AKTIVA VS PASIVA) -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- LEFT COLUMN: AKTIVA -->
                        <div class="bg-slate-950 p-5 rounded-2xl border border-slate-800 space-y-4">
                            <div class="border-b border-slate-800 pb-2 flex items-center justify-between">
                                <h4 class="font-bold text-emerald-400 text-base flex items-center gap-2">
                                    <span>🟦</span> <span>AKTIVA (ASSETS)</span>
                                </h4>
                                <span class="text-xs font-bold text-emerald-400">Total: {{ formatCurrency(totalAktiva) }}</span>
                            </div>

                            <!-- Aktiva Lancar -->
                            <div class="space-y-2">
                                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">✳️ Aktiva Lancar (Current Assets)</div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <label class="block text-slate-400 mb-1">Kas & Bank (Rp)</label>
                                        <input v-model.number="form.cash_and_bank" type="number" step="1000000" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono" />
                                    </div>
                                    <div>
                                        <label class="block text-slate-400 mb-1">Persediaan Barang (Rp)</label>
                                        <input v-model.number="form.inventory" type="number" step="1000000" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono" />
                                    </div>
                                    <div>
                                        <label class="block text-slate-400 mb-1">Piutang Dagang (Rp)</label>
                                        <input v-model.number="form.receivables" type="number" step="1000000" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono" />
                                    </div>
                                    <div>
                                        <label class="block text-slate-400 mb-1">Aktiva Lancar Lain (Rp)</label>
                                        <input v-model.number="form.other_current_assets" type="number" step="1000000" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono" />
                                    </div>
                                </div>
                                <div class="p-2 bg-slate-900 rounded-lg text-right font-bold text-xs text-emerald-400 border border-slate-800/80">
                                    Total Aktiva Lancar (AL): {{ formatCurrency(totalAktivaLancar) }}
                                </div>
                            </div>

                            <!-- Aktiva Tetap -->
                            <div class="space-y-2 pt-2 border-t border-slate-800/60">
                                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">✳️ Aktiva Tetap (Fixed Assets)</div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <label class="block text-slate-400 mb-1">Peralatan (Rp)</label>
                                        <input v-model.number="form.equipment" type="number" step="1000000" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono" />
                                    </div>
                                    <div>
                                        <label class="block text-slate-400 mb-1">Kendaraan Operasional (Rp)</label>
                                        <input v-model.number="form.vehicles" type="number" step="1000000" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono" />
                                    </div>
                                    <div>
                                        <label class="block text-slate-400 mb-1">Mesin & Tempat Usaha (Rp)</label>
                                        <input v-model.number="form.machinery_and_buildings" type="number" step="1000000" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono" />
                                    </div>
                                    <div>
                                        <label class="block text-rose-400 mb-1">Akum. Penyusutan (-) (Rp)</label>
                                        <input v-model.number="form.accumulated_depreciation" type="number" step="1000000" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-rose-400 font-mono" />
                                    </div>
                                </div>
                                <div class="p-2 bg-slate-900 rounded-lg text-right font-bold text-xs text-emerald-400 border border-slate-800/80">
                                    Total Aktiva Tetap (AT): {{ formatCurrency(totalAktivaTetap) }}
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: PASIVA -->
                        <div class="bg-slate-950 p-5 rounded-2xl border border-slate-800 space-y-4">
                            <div class="border-b border-slate-800 pb-2 flex items-center justify-between">
                                <h4 class="font-bold text-blue-400 text-base flex items-center gap-2">
                                    <span>🟪</span> <span>PASIVA (LIABILITIES & EQUITY)</span>
                                </h4>
                                <span class="text-xs font-bold text-blue-400">Total: {{ formatCurrency(totalPasiva) }}</span>
                            </div>

                            <!-- Kewajiban -->
                            <div class="space-y-2">
                                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">✳️ Kewajiban / Liabilitas (Liabilities)</div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <label class="block text-slate-400 mb-1">Hutang Dagang (Rp)</label>
                                        <input v-model.number="form.trade_payables" type="number" step="1000000" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono" />
                                    </div>
                                    <div>
                                        <label class="block text-slate-400 mb-1">Hutang Bank / Pinjaman (Rp)</label>
                                        <input v-model.number="form.bank_loans" type="number" step="1000000" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono" />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-slate-400 mb-1">Hutang Lainnya (Rp)</label>
                                        <input v-model.number="form.other_liabilities" type="number" step="1000000" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono" />
                                    </div>
                                </div>
                                <div class="p-2 bg-slate-900 rounded-lg text-right font-bold text-xs text-blue-400 border border-slate-800/80">
                                    Total Kewajiban: {{ formatCurrency(totalKewajiban) }}
                                </div>
                            </div>

                            <!-- Ekuitas -->
                            <div class="space-y-2 pt-2 border-t border-slate-800/60">
                                <div class="text-xs font-bold text-slate-400 uppercase tracking-wider">✳️ Ekuitas / Modal (Equity)</div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <label class="block text-slate-400 mb-1">Modal Disetor (Rp)</label>
                                        <input v-model.number="form.capital" type="number" step="1000000" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono" />
                                    </div>
                                    <div>
                                        <label class="block text-slate-400 mb-1">Laba Tahun Berjalan (Rp)</label>
                                        <input v-model.number="form.retained_earnings" type="number" step="1000000" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono" />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-rose-400 mb-1">Prive / Pengambilan Pribadi (-) (Rp)</label>
                                        <input v-model.number="form.drawings_prive" type="number" step="1000000" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-rose-400 font-mono" />
                                    </div>
                                </div>
                                <div class="p-2 bg-slate-900 rounded-lg text-right font-bold text-xs text-blue-400 border border-slate-800/80">
                                    Total Ekuitas: {{ formatCurrency(totalEkuitas) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TARGET KPR & LABA RUGI SECTION -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-950 p-4 rounded-xl border border-slate-800">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">Omset / Pendapatan Bulanan (Rp)</label>
                            <input v-model.number="form.monthly_revenue" type="number" step="1000000" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-emerald-400 mb-1">Laba Bersih Bulanan Client (Rp) *</label>
                            <input v-model.number="form.monthly_net_profit" required type="number" step="1000000" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-emerald-400 font-bold font-mono" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-rose-400 mb-1">Angsuran Pinjaman Existing (Rp)</label>
                            <input v-model.number="form.existing_monthly_debt_service" type="number" step="500000" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-rose-400 font-mono" />
                        </div>
                    </div>

                    <!-- KPR TARGET SIMULATION & LIVE BANK SCORING CARD -->
                    <div class="bg-gradient-to-br from-slate-950 to-slate-900 p-5 rounded-2xl border border-slate-800 space-y-4">
                        <h4 class="font-bold text-amber-400 text-sm uppercase tracking-wider flex items-center gap-2">
                            <span>🎯</span> <span>Target Pengajuan KPR & Live Score Bank</span>
                        </h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Plafond KPR Diajukan (Rp)</label>
                                <input v-model.number="form.target_kpr_amount" type="number" step="10000000" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white font-bold text-amber-400 font-mono" />
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Tenor (Tahun)</label>
                                <input v-model.number="form.target_tenor_years" type="number" min="1" max="30" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono" />
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Bunga KPR (%)</label>
                                <input v-model.number="form.target_interest_rate" type="number" step="0.1" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono" />
                            </div>
                        </div>

                        <!-- LIVE RATIO PREVIEW -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 pt-3 border-t border-slate-800">
                            <div class="p-3 bg-slate-900/80 rounded-xl border border-slate-800">
                                <div class="text-[10px] text-slate-400 uppercase">Est. Angsuran KPR Baru</div>
                                <div class="text-sm font-bold text-white mt-0.5">{{ formatCurrency(liveNewInstallment) }}/bln</div>
                            </div>
                            <div class="p-3 bg-slate-900/80 rounded-xl border border-slate-800">
                                <div class="text-[10px] text-slate-400 uppercase">Current Ratio (Likuiditas)</div>
                                <div class="text-sm font-bold text-blue-400 mt-0.5">{{ liveCurrentRatio }}x <span class="text-[10px] text-slate-400">(Min 1.5x)</span></div>
                            </div>
                            <div class="p-3 bg-slate-900/80 rounded-xl border border-slate-800">
                                <div class="text-[10px] text-slate-400 uppercase">DER (Solvabilitas)</div>
                                <div class="text-sm font-bold text-purple-400 mt-0.5">{{ liveDerRatio }}x <span class="text-[10px] text-slate-400">(Max 1.5x)</span></div>
                            </div>
                            <div class="p-3 bg-slate-900/80 rounded-xl border border-slate-800">
                                <div class="text-[10px] text-slate-400 uppercase">DSR (Beban Angsuran)</div>
                                <div class="text-sm font-bold text-amber-400 mt-0.5">{{ liveDsrRatio }}% <span class="text-[10px] text-slate-400">(Max 35%)</span></div>
                            </div>
                        </div>

                        <!-- LIVE APPROVAL SCORE PREVIEW -->
                        <div class="p-4 rounded-xl flex items-center justify-between border"
                            :class="{
                                'bg-emerald-500/10 border-emerald-500/30 text-emerald-400': liveApprovalScore === 'HIGH',
                                'bg-amber-500/10 border-amber-500/30 text-amber-400': liveApprovalScore === 'MEDIUM',
                                'bg-rose-500/10 border-rose-500/30 text-rose-400': liveApprovalScore === 'LOW',
                            }"
                        >
                            <div>
                                <div class="text-xs uppercase font-bold tracking-wider">Hasil Skor Kelayakan Pengajuan KPR</div>
                                <div class="text-lg font-black mt-0.5">
                                    <span v-if="liveApprovalScore === 'HIGH'">🟢 HIGH APPROVAL CHANCE — Sangat Layak Disetujui Bank</span>
                                    <span v-if="liveApprovalScore === 'MEDIUM'">🟡 MEDIUM APPROVAL — Perlu Pertimbangan (Co-Applicant / DP Tambahan)</span>
                                    <span v-if="liveApprovalScore === 'LOW'">🔴 LOW APPROVAL (HIGH RISK) — DSR / DER Terlalu Tinggi</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-800">
                        <button type="button" @click="showModal = false" class="px-5 py-2.5 bg-slate-800 text-slate-300 font-bold rounded-xl">Batal</button>
                        <button type="submit" class="px-5 py-2.5 bg-amber-500 text-slate-950 font-bold rounded-xl shadow-lg shadow-amber-500/20">Simpan & Hitung Skor Bank</button>
                    </div>
                </form>
            </div>
        </div>
    </CrmLayout>
</template>
