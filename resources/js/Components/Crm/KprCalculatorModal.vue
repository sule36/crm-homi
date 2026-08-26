<script setup>
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    show: Boolean,
    initialPrice: {
        type: Number,
        default: 500000000
    },
    unitCode: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['close']);

// Partner banks from props/middleware
const partnerBanks = computed(() => usePage().props.partner_banks || []);

const activeTab = ref('installments'); // 'installments' | 'eligibility' | 'shock' | 'promo'

// --- KPR PARAMETERS ---
const propertyPrice = ref(props.initialPrice);
const dpPercent = ref(10);
const dpAmount = ref(50000000);
const dpMode = ref('percent');

const kprType = ref('conventional'); // 'conventional' | 'syariah' | 'tiered'
const sukuBungaProduct = ref('floating'); // 'flat' | 'tiered' | 'floating'

const annualInterest = ref(3.85); // % fixed
const floatingInterest = ref(11.00); // % floating
const fixedDuration = ref(3); // years fixed
const tenorYears = ref(15);
const tenorMonths = computed(() => tenorYears.value * 12);

// Tiered Configuration
const numTiers = ref(3);
const tiers = ref([
    { rate: 3.85, years: 3 },
    { rate: 6.85, years: 3 },
    { rate: 8.85, years: 4 }
]);

// Bank Partner Selection
const selectedBankId = ref('');
const selectedBank = computed(() => {
    return partnerBanks.value.find(b => b.id === Number(selectedBankId.value)) || null;
});

// Extra Payment / Pelunasan Dipercepat
const extraMonthlyPayment = ref(0);
const lumpSumAmount = ref(0);
const lumpSumYear = ref(5);

// DSR & Income
const monthlyIncome = ref(15000000);
const monthlyDebts = ref(0);
const targetDsr = ref(35);

// Promo Toggles
const promoBphtb = ref(false);
const promoNotaris = ref(false);
const promoFreeKpr = ref(false);
const promoSubsidiDp = ref(false);

// Keep propertyPrice in sync with initialPrice prop
watch(() => props.initialPrice, (newVal) => {
    if (newVal) {
        propertyPrice.value = newVal;
        dpAmount.value = Math.round((newVal * dpPercent.value) / 100);
    }
}, { immediate: true });

// Sync DP Percent & Amount
watch([dpPercent, propertyPrice], () => {
    if (dpMode.value === 'percent') {
        dpAmount.value = Math.round((propertyPrice.value * dpPercent.value) / 100);
    }
});

watch(dpAmount, () => {
    if (dpMode.value === 'nominal' && propertyPrice.value > 0) {
        const pct = (dpAmount.value / propertyPrice.value) * 100;
        dpPercent.value = Math.min(90, Math.max(0, Math.round(pct * 100) / 100));
    }
});

// Auto-fill when bank selected
watch(selectedBank, (bank) => {
    if (bank) {
        if (bank.is_syariah) {
            kprType.value = 'syariah';
            sukuBungaProduct.value = 'flat';
            annualInterest.value = Number(bank.syariah_margin_rate || 6.5);
        } else if (bank.is_tiered) {
            kprType.value = 'tiered';
            sukuBungaProduct.value = 'tiered';
            if (bank.tiered_rates && Array.isArray(bank.tiered_rates)) {
                numTiers.value = bank.tiered_rates.length;
                tiers.value = bank.tiered_rates.map(t => ({ rate: Number(t.rate), years: Number(t.years) }));
            }
        } else {
            kprType.value = 'conventional';
            sukuBungaProduct.value = 'floating';
            annualInterest.value = Number(bank.interest_rate_fixed || 3.85);
            floatingInterest.value = Number(bank.interest_rate_floating || 11.0);
            fixedDuration.value = Number(bank.fixed_duration || 3);
        }
    }
});

// Sync product rate type buttons
watch(sukuBungaProduct, (val) => {
    if (val === 'flat') kprType.value = 'syariah';
    if (val === 'tiered') kprType.value = 'tiered';
    if (val === 'floating') kprType.value = 'conventional';
});

// Calculations
const loanPrincipal = computed(() => Math.max(0, propertyPrice.value - dpAmount.value));
const actualFixedYears = computed(() => Math.min(fixedDuration.value, tenorYears.value));

function calculateMonthlyAnnuity(principal, annualRatePct, totalMonths) {
    if (principal <= 0 || totalMonths <= 0) return 0;
    const r = (annualRatePct / 100) / 12;
    if (r === 0) return Math.round(principal / totalMonths);
    const factor = Math.pow(1 + r, totalMonths);
    return Math.round((principal * r * factor) / (factor - 1));
}

const monthlyInstallmentFixed = computed(() => {
    return calculateMonthlyAnnuity(loanPrincipal.value, annualInterest.value, tenorMonths.value);
});

const remainingBalanceAfterFixed = computed(() => {
    const principal = loanPrincipal.value;
    const totalM = tenorMonths.value;
    const fixedM = actualFixedYears.value * 12;
    const r = (annualInterest.value / 100) / 12;

    if (principal <= 0 || totalM <= 0) return 0;
    if (fixedM >= totalM) return 0;
    if (r === 0) return principal * (1 - fixedM / totalM);

    const factorM = Math.pow(1 + r, totalM);
    const factorK = Math.pow(1 + r, fixedM);
    return Math.max(0, principal * (factorM - factorK) / (factorM - 1));
});

const monthlyInstallmentFloating = computed(() => {
    const remainingM = tenorMonths.value - (actualFixedYears.value * 12);
    if (remainingM <= 0) return 0;
    return calculateMonthlyAnnuity(remainingBalanceAfterFixed.value, floatingInterest.value, remainingM);
});

const monthlyInstallmentSyariah = computed(() => {
    const principal = loanPrincipal.value;
    const totalMargin = principal * (annualInterest.value / 100) * tenorYears.value;
    const totalPayable = principal + totalMargin;
    return tenorMonths.value > 0 ? Math.round(totalPayable / tenorMonths.value) : 0;
});

const monthlyInstallment = computed(() => {
    if (kprType.value === 'syariah') return monthlyInstallmentSyariah.value;
    if (kprType.value === 'tiered') return calculateMonthlyAnnuity(loanPrincipal.value, tiers.value[0]?.rate || 3.85, tenorMonths.value);
    return monthlyInstallmentFixed.value;
});

// Payment Shock
const paymentShockPercent = computed(() => {
    if (kprType.value !== 'conventional') return 0;
    if (monthlyInstallmentFixed.value <= 0) return 0;
    const diff = monthlyInstallmentFloating.value - monthlyInstallmentFixed.value;
    return Math.max(0, (diff / monthlyInstallmentFixed.value) * 100);
});

// Upfront Costs Breakdown
const upfrontFees = computed(() => {
    const price = propertyPrice.value;
    const principal = loanPrincipal.value;

    const bphtb = promoBphtb.value ? 0 : Math.round(price * 0.05);
    const notaris = promoNotaris.value ? 0 : Math.round(price * 0.01);
    const provisi = promoFreeKpr.value ? 0 : Math.round(principal * 0.01);
    const adm = promoFreeKpr.value ? 0 : 500000;
    const notarisKpr = promoFreeKpr.value ? 0 : Math.round(principal * 0.01);
    const asuransi = promoFreeKpr.value ? 0 : Math.round(principal * 0.01);
    const ppn = Math.round(price * 0.12);
    const blockedInstallment = monthlyInstallment.value;

    const dpEffective = promoSubsidiDp.value ? 0 : dpAmount.value;
    const total = dpEffective + bphtb + notaris + provisi + adm + notarisKpr + asuransi + ppn + blockedInstallment;

    return {
        bphtb,
        notaris,
        provisi,
        adm,
        notarisKpr,
        asuransi,
        ppn,
        blockedInstallment,
        dpEffective,
        total
    };
});

// DSR score & advice
const dsrScore = computed(() => {
    if (monthlyIncome.value <= 0) return 0;
    return ((monthlyInstallment.value + monthlyDebts.value) / monthlyIncome.value) * 100;
});

const maxLoanCapacity = computed(() => {
    const maxMonthlyPay = (monthlyIncome.value * (targetDsr.value / 100)) - monthlyDebts.value;
    if (maxMonthlyPay <= 0) return 0;
    const r = (annualInterest.value / 100) / 12;
    if (r === 0) return maxMonthlyPay * tenorMonths.value;
    const factor = Math.pow(1 + r, tenorMonths.value);
    return Math.round((maxMonthlyPay * (factor - 1)) / (r * factor));
});

function formatCurrency(val) {
    if (!val || isNaN(val)) return 'Rp 0';
    return 'Rp ' + Math.round(val).toLocaleString('id-ID');
}

function copySimulation() {
    const text = `*SIMULASI KPR PINTAR* 🏠\n` +
                 (props.unitCode ? `Unit Properti: *${props.unitCode}*\n` : '') +
                 `Harga Properti: *${formatCurrency(propertyPrice.value)}*\n` +
                 `Uang Muka (DP ${dpPercent.value}%): *${formatCurrency(dpAmount.value)}*\n` +
                 `Plafond KPR: *${formatCurrency(loanPrincipal.value)}*\n` +
                 `Tenor: *${tenorYears.value} Tahun*\n` +
                 `Estimasi Cicilan: *${formatCurrency(monthlyInstallment.value)} /bulan*\n` +
                 (kprType.value === 'conventional' && tenorYears.value > actualFixedYears.value ? `(Floating: ${formatCurrency(monthlyInstallmentFloating.value)}/bln)\n` : '') +
                 `Dana Awal Disiapkan: *${formatCurrency(upfrontFees.value.total)}*`;

    navigator.clipboard.writeText(text);
    alert('Simulasi KPR berhasil disalin ke clipboard! Siap dipaste ke WhatsApp.');
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="emit('close')"></div>

        <!-- Modal Card -->
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden animate-in zoom-in-95 duration-200">
            <!-- Modal Header -->
            <div class="px-6 py-5 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-600/30 border border-blue-400/30 rounded-xl flex items-center justify-center text-xl">
                        🧮
                    </div>
                    <div>
                        <h3 class="text-base font-black uppercase tracking-wider">KPR Smart Simulator</h3>
                        <p class="text-xs text-slate-400 font-medium">
                            Simulasi cicilan bulanan, kelayakan DSR, & estimasi biaya awal {{ unitCode ? `(Unit ${unitCode})` : '' }}
                        </p>
                    </div>
                </div>
                <button @click="emit('close')" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white flex items-center justify-center text-lg font-bold transition-all">
                    ✕
                </button>
            </div>

            <!-- Tab Navigation -->
            <div class="flex border-b border-slate-200 bg-slate-50 px-6 pt-2">
                <button @click="activeTab = 'installments'" 
                        :class="activeTab === 'installments' ? 'border-b-2 border-blue-600 text-slate-900 font-black bg-white shadow-sm' : 'text-slate-500 font-bold hover:text-slate-800'"
                        class="px-5 py-3 text-xs uppercase tracking-wider rounded-t-xl transition-all cursor-pointer">
                    💵 Simulasi Cicilan
                </button>
                <button @click="activeTab = 'eligibility'" 
                        :class="activeTab === 'eligibility' ? 'border-b-2 border-blue-600 text-slate-900 font-black bg-white shadow-sm' : 'text-slate-500 font-bold hover:text-slate-800'"
                        class="px-5 py-3 text-xs uppercase tracking-wider rounded-t-xl transition-all cursor-pointer">
                    📊 Kelayakan (DSR)
                </button>
                <button @click="activeTab = 'shock'" 
                        :class="activeTab === 'shock' ? 'border-b-2 border-blue-600 text-slate-900 font-black bg-white shadow-sm' : 'text-slate-500 font-bold hover:text-slate-800'"
                        class="px-5 py-3 text-xs uppercase tracking-wider rounded-t-xl transition-all cursor-pointer">
                    ⚡ Payment Shock
                </button>
                <button @click="activeTab = 'promo'" 
                        :class="activeTab === 'promo' ? 'border-b-2 border-blue-600 text-slate-900 font-black bg-white shadow-sm' : 'text-slate-500 font-bold hover:text-slate-800'"
                        class="px-5 py-3 text-xs uppercase tracking-wider rounded-t-xl transition-all cursor-pointer">
                    🎁 Dana Awal & Promo
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto space-y-6 flex-grow">
                
                <!-- TAB 1: SIMULASI CICILAN -->
                <div v-if="activeTab === 'installments'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left: Form Inputs -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Harga Properti (Rp)</label>
                            <input v-model.number="propertyPrice" type="number" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-black text-slate-900 focus:bg-white focus:ring-2 focus:ring-blue-500/20">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Uang Muka (DP %)</label>
                            <div class="flex items-center gap-3">
                                <input v-model.number="dpPercent" type="number" min="0" max="90" @focus="dpMode = 'percent'" class="w-24 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-center">
                                <span class="text-xs font-bold text-slate-500">= {{ formatCurrency(dpAmount) }}</span>
                            </div>
                            <input v-model.number="dpPercent" type="range" min="0" max="90" step="1" @input="dpMode = 'percent'" class="w-full h-1.5 bg-slate-100 rounded-full appearance-none cursor-pointer accent-blue-600 mt-2">
                        </div>

                        <div v-if="partnerBanks.length > 0">
                            <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Bank Partner (Opsional)</label>
                            <select v-model="selectedBankId" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold cursor-pointer">
                                <option value="">Custom (Tanpa Bank Partner)</option>
                                <option v-for="bank in partnerBanks" :key="bank.id" :value="bank.id">
                                    {{ bank.name }} {{ bank.is_syariah ? '(Syariah)' : '' }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Skema Bunga</label>
                            <div class="flex gap-2">
                                <button @click="sukuBungaProduct = 'floating'" :class="sukuBungaProduct === 'floating' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'" class="flex-1 py-2 text-xs font-bold rounded-xl transition-all">Fix Floating</button>
                                <button @click="sukuBungaProduct = 'flat'" :class="sukuBungaProduct === 'flat' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'" class="flex-1 py-2 text-xs font-bold rounded-xl transition-all">Flat / Syariah</button>
                                <button @click="sukuBungaProduct = 'tiered'" :class="sukuBungaProduct === 'tiered' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'" class="flex-1 py-2 text-xs font-bold rounded-xl transition-all">Fix Berjenjang</button>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Bunga Fixed (%/thn)</label>
                                <input v-model.number="annualInterest" type="number" step="0.1" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-center">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Masa Fixed (Tahun)</label>
                                <input v-model.number="fixedDuration" type="number" min="1" :max="tenorYears" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-center">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Tenor KPR (Tahun)</label>
                            <div class="flex items-center gap-3">
                                <input v-model.number="tenorYears" type="number" min="1" max="35" class="w-24 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-center">
                                <span class="text-xs font-bold text-slate-500">{{ tenorMonths }} Bulan</span>
                            </div>
                            <input v-model.number="tenorYears" type="range" min="1" max="35" step="1" class="w-full h-1.5 bg-slate-100 rounded-full appearance-none cursor-pointer accent-blue-600 mt-2">
                        </div>
                    </div>

                    <!-- Right: Summary Box -->
                    <div class="bg-gradient-to-br from-slate-900 to-slate-800 rounded-3xl p-6 text-white flex flex-col justify-between space-y-6 shadow-xl">
                        <div class="space-y-4">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block">Ringkasan Estimasi KPR</span>
                            
                            <div>
                                <span class="text-[10px] font-bold text-emerald-400 uppercase tracking-wider block">Cicilan Masa Fixed (Thn 1-{{ actualFixedYears }})</span>
                                <div class="text-3xl font-black text-white tracking-tight mt-0.5">{{ formatCurrency(monthlyInstallment) }}<span class="text-xs font-normal text-slate-400">/bln</span></div>
                            </div>

                            <div v-if="kprType === 'conventional' && tenorYears > actualFixedYears">
                                <span class="text-[10px] font-bold text-amber-400 uppercase tracking-wider block">Est. Floating (Thn {{ actualFixedYears + 1 }}-{{ tenorYears }})</span>
                                <div class="text-2xl font-black text-slate-200 tracking-tight mt-0.5">{{ formatCurrency(monthlyInstallmentFloating) }}<span class="text-xs font-normal text-slate-400">/bln</span></div>
                            </div>

                            <div class="pt-4 border-t border-slate-700/60 space-y-2 text-xs">
                                <div class="flex justify-between text-slate-300">
                                    <span>Plafond KPR</span>
                                    <span class="font-bold text-white">{{ formatCurrency(loanPrincipal) }}</span>
                                </div>
                                <div class="flex justify-between text-slate-300">
                                    <span>Uang Muka (DP)</span>
                                    <span class="font-bold text-white">{{ formatCurrency(dpAmount) }}</span>
                                </div>
                                <div class="flex justify-between text-slate-300">
                                    <span>Est. Dana Awal (DP + Biaya)</span>
                                    <span class="font-bold text-blue-400">{{ formatCurrency(upfrontFees.total) }}</span>
                                </div>
                            </div>
                        </div>

                        <button @click="copySimulation" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs uppercase tracking-wider rounded-xl transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer">
                            💬 Salin Teks WhatsApp
                        </button>
                    </div>
                </div>

                <!-- TAB 2: KELAYAKAN DSR -->
                <div v-else-if="activeTab === 'eligibility'" class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Gaji Bersih Bulanan (Rp)</label>
                            <input v-model.number="monthlyIncome" type="number" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-black text-slate-900">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Cicilan Bulanan Lain (Rp)</label>
                            <input v-model.number="monthlyDebts" type="number" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-black text-slate-900">
                        </div>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
                        <div class="flex justify-between items-center text-xs font-bold">
                            <span class="text-slate-600">Debt Service Ratio (DSR)</span>
                            <span :class="dsrScore <= 30 ? 'text-emerald-600' : dsrScore <= 50 ? 'text-amber-600' : 'text-rose-600'" class="font-black text-base">{{ dsrScore.toFixed(1) }}%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2.5 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-300"
                                 :class="dsrScore <= 30 ? 'bg-emerald-500' : dsrScore <= 50 ? 'bg-amber-500' : 'bg-rose-500'"
                                 :style="{ width: Math.min(100, dsrScore) + '%' }"></div>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 p-4 rounded-2xl flex justify-between items-center">
                        <div>
                            <span class="text-xs font-bold text-blue-700 block">Kapasitas Maksimal Plafond KPR</span>
                            <span class="text-xs text-blue-500">Berdasarkan target DSR {{ targetDsr }}%</span>
                        </div>
                        <span class="text-lg font-black text-blue-900">{{ formatCurrency(maxLoanCapacity) }}</span>
                    </div>
                </div>

                <!-- TAB 3: PAYMENT SHOCK -->
                <div v-else-if="activeTab === 'shock'" class="space-y-5">
                    <div class="bg-amber-50 border border-amber-200 p-4 rounded-2xl space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-black text-amber-800">Visualisasi Lonjakan Cicilan (Fixed → Floating)</span>
                            <span class="px-2 py-0.5 bg-amber-200 text-amber-900 rounded text-[10px] font-black">+{{ paymentShockPercent.toFixed(1) }}%</span>
                        </div>
                        <p class="text-xs text-amber-700 font-medium">
                            Cicilan akan naik dari <strong class="text-slate-900">{{ formatCurrency(monthlyInstallmentFixed) }}</strong> ke <strong class="text-rose-700">{{ formatCurrency(monthlyInstallmentFloating) }}</strong> di tahun ke-{{ actualFixedYears + 1 }}.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-emerald-50 border border-emerald-100 p-4 rounded-2xl text-center">
                            <span class="text-[10px] font-black text-emerald-600 uppercase">Cicilan Fixed (Thn 1-{{ actualFixedYears }})</span>
                            <div class="text-base font-black text-emerald-800 mt-1">{{ formatCurrency(monthlyInstallmentFixed) }}</div>
                        </div>
                        <div class="bg-rose-50 border border-rose-100 p-4 rounded-2xl text-center">
                            <span class="text-[10px] font-black text-rose-600 uppercase">Cicilan Floating (Thn {{ actualFixedYears + 1 }}-{{ tenorYears }})</span>
                            <div class="text-base font-black text-rose-800 mt-1">{{ formatCurrency(monthlyInstallmentFloating) }}</div>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: DANA AWAL & PROMO -->
                <div v-else-if="activeTab === 'promo'" class="space-y-5">
                    <div class="space-y-2 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                        <span class="text-xs font-black text-slate-800 uppercase tracking-wider block mb-2">Simulasi Promo Developer</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                                <input v-model="promoBphtb" type="checkbox" class="rounded text-blue-600"> Bebas BPHTB (Pajak 5%)
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                                <input v-model="promoNotaris" type="checkbox" class="rounded text-blue-600"> Bebas Notaris & AJB
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                                <input v-model="promoFreeKpr" type="checkbox" class="rounded text-blue-600"> Bebas Biaya KPR & Asuransi
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                                <input v-model="promoSubsidiDp" type="checkbox" class="rounded text-blue-600"> Subsidi DP 100% (DP 0%)
                            </label>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 p-4 rounded-2xl space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-black text-blue-900">Total Dana Awal Disiapkan</span>
                            <span class="text-base font-black text-blue-600">{{ formatCurrency(upfrontFees.total) }}</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-3">
                <button @click="emit('close')" class="px-5 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-xl transition-all">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</template>
