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

// Global dynamic partner banks loaded from Middleware
const partnerBanks = computed(() => usePage().props.partner_banks || []);

const activeTab = ref('installments'); // 'installments' or 'eligibility'

// --- KPR PRODUCT TYPE ---
// 'conventional' (Fix-Floating), 'syariah' (Flat Profit Margin), 'tiered' (Fix Berjenjang)
const kprType = ref('conventional'); 

// --- TABS: INSTALLMENTS SIMULATION ---
const propertyPrice = ref(props.initialPrice);
const dpPercent = ref(10);
const annualInterest = ref(3.85); // Asumsi Bunga Fixed
const floatingInterest = ref(11.00); // Asumsi Bunga Floating
const fixedDuration = ref(3); // Masa Kredit Fixed (Tahun)
const tenorYears = ref(15);

// Syariah Profit Margin
const syariahMarginRate = ref(6.50);

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

// Auto-fill inputs when bank is selected
watch(selectedBank, (bank) => {
    if (bank) {
        if (bank.is_syariah) {
            kprType.value = 'syariah';
            syariahMarginRate.value = Number(bank.syariah_margin_rate);
        } else if (bank.is_tiered) {
            kprType.value = 'tiered';
            if (bank.tiered_rates && Array.isArray(bank.tiered_rates)) {
                numTiers.value = bank.tiered_rates.length;
                tiers.value = bank.tiered_rates.map(t => ({ rate: Number(t.rate), years: Number(t.years) }));
            }
        } else {
            kprType.value = 'conventional';
            annualInterest.value = Number(bank.interest_rate_fixed);
            floatingInterest.value = Number(bank.interest_rate_floating);
            fixedDuration.value = Number(bank.fixed_duration);
        }
    }
});

// Sync type with select default options
watch(kprType, (val) => {
    // If user changes type manually, deselect bank if it doesn't match
    if (selectedBank.value) {
        if (val === 'syariah' && !selectedBank.value.is_syariah) selectedBankId.value = '';
        if (val === 'tiered' && !selectedBank.value.is_tiered) selectedBankId.value = '';
        if (val === 'conventional' && (selectedBank.value.is_syariah || selectedBank.value.is_tiered)) selectedBankId.value = '';
    }
});

// Keep propertyPrice synced with initial price when it changes
watch(() => props.initialPrice, (newVal) => {
    if (newVal) propertyPrice.value = newVal;
});

const dpAmount = computed(() => {
    return Math.round((propertyPrice.value * dpPercent.value) / 100);
});

const loanPrincipal = computed(() => {
    return Math.max(0, propertyPrice.value - dpAmount.value);
});

const actualFixedYears = computed(() => Math.min(fixedDuration.value, tenorYears.value));

// Conventional Fixed Installment (Annuity)
const monthlyInstallmentFixed = computed(() => {
    const principal = loanPrincipal.value;
    const totalMonths = tenorYears.value * 12;
    const monthlyInterestRate = (annualInterest.value / 100) / 12;

    if (principal <= 0 || totalMonths <= 0) return 0;
    if (monthlyInterestRate === 0) return Math.round(principal / totalMonths);

    const factor = Math.pow(1 + monthlyInterestRate, totalMonths);
    return Math.round((principal * monthlyInterestRate * factor) / (factor - 1));
});

// Remaining Principal Amortization Balance after Fixed Period
const remainingBalanceAfterFixed = computed(() => {
    const principal = loanPrincipal.value;
    const totalMonths = tenorYears.value * 12;
    const fixedMonths = actualFixedYears.value * 12;
    const monthlyInterestRate = (annualInterest.value / 100) / 12;

    if (principal <= 0 || totalMonths <= 0) return 0;
    if (fixedMonths >= totalMonths) return 0;
    if (monthlyInterestRate === 0) return principal * (1 - fixedMonths / totalMonths);

    const factorM = Math.pow(1 + monthlyInterestRate, totalMonths);
    const factorK = Math.pow(1 + monthlyInterestRate, fixedMonths);
    return Math.max(0, principal * (factorM - factorK) / (factorM - 1));
});

// Conventional Floating Installment (Annuity recalculated for remaining term)
const monthlyInstallmentFloating = computed(() => {
    const remainingMonths = (tenorYears.value - actualFixedYears.value) * 12;
    if (remainingMonths <= 0) return 0;

    const principal = remainingBalanceAfterFixed.value;
    const monthlyInterestRate = (floatingInterest.value / 100) / 12;

    if (principal <= 0) return 0;
    if (monthlyInterestRate === 0) return Math.round(principal / remainingMonths);

    const factor = Math.pow(1 + monthlyInterestRate, remainingMonths);
    return Math.round((principal * monthlyInterestRate * factor) / (factor - 1));
});

// Syariah Flat Margin Monthly Installment
const monthlyInstallmentSyariah = computed(() => {
    const principal = loanPrincipal.value;
    const totalMargin = principal * (syariahMarginRate.value / 100) * tenorYears.value;
    const totalPayable = principal + totalMargin;
    const totalMonths = tenorYears.value * 12;
    return totalMonths > 0 ? Math.round(totalPayable / totalMonths) : 0;
});

// Dynamic Tiered Adjustments
const adjustedTiers = computed(() => {
    const list = [];
    let allocatedYears = 0;
    
    for (let i = 0; i < numTiers.value; i++) {
        const t = tiers.value[i] || { rate: 8.95, years: 3 };
        const rateVal = Number(t.rate) || 0;
        const yearsVal = Number(t.years) || 0;
        
        if (i === numTiers.value - 1) {
            const remYears = Math.max(1, tenorYears.value - allocatedYears);
            list.push({ rate: rateVal, years: remYears });
        } else {
            const maxPossible = Math.max(1, tenorYears.value - allocatedYears - (numTiers.value - 1 - i));
            const y = Math.min(yearsVal, maxPossible);
            allocatedYears += y;
            list.push({ rate: rateVal, years: y });
        }
    }
    return list;
});

// Tiered Monthly Installments
const dynamicTieredInstallments = computed(() => {
    const principal = loanPrincipal.value;
    const totalTenorMonths = tenorYears.value * 12;
    const list = [];
    
    let currentBalance = principal;
    let accumulatedMonths = 0;
    
    for (let i = 0; i < adjustedTiers.value.length; i++) {
        const tier = adjustedTiers.value[i];
        const rate = Number(tier.rate) || 0;
        const years = Number(tier.years) || 0;
        const tierMonths = years * 12;
        
        const remainingMonthsAtStart = totalTenorMonths - accumulatedMonths;
        const r = (rate / 100) / 12;
        
        let installment = 0;
        if (remainingMonthsAtStart > 0 && currentBalance > 0) {
            if (r === 0) {
                installment = currentBalance / remainingMonthsAtStart;
            } else {
                installment = currentBalance * (r * Math.pow(1 + r, remainingMonthsAtStart)) / (Math.pow(1 + r, remainingMonthsAtStart) - 1);
            }
        }
        installment = Math.max(0, Math.round(installment) || 0);
        
        let endBalance = currentBalance;
        if (remainingMonthsAtStart > 0 && currentBalance > 0) {
            if (r === 0) {
                endBalance = currentBalance * (1 - Math.min(tierMonths, remainingMonthsAtStart) / remainingMonthsAtStart);
            } else {
                const factorN = Math.pow(1 + r, remainingMonthsAtStart);
                const factorM = Math.pow(1 + r, Math.min(tierMonths, remainingMonthsAtStart));
                endBalance = currentBalance * (factorN - factorM) / (factorN - 1);
            }
        }
        endBalance = Math.max(0, Math.round(endBalance) || 0);
        
        list.push({
            tierIndex: i + 1,
            rate: rate,
            years: years,
            installment: installment,
            startBalance: currentBalance,
            endBalance: endBalance,
            months: tierMonths
        });
        
        currentBalance = endBalance;
        accumulatedMonths += tierMonths;
    }
    return list;
});

// Main Display Installment
const monthlyInstallment = computed(() => {
    if (kprType.value === 'conventional') {
        return monthlyInstallmentFixed.value;
    } else if (kprType.value === 'tiered') {
        return dynamicTieredInstallments.value[0]?.installment || 0;
    } else {
        return monthlyInstallmentSyariah.value;
    }
});

// Total Payment (Principal + Interest/Margin)
const totalPayment = computed(() => {
    if (kprType.value === 'conventional') {
        const fixedMonths = actualFixedYears.value * 12;
        const remainingMonths = Math.max(0, (tenorYears.value - actualFixedYears.value) * 12);
        return (monthlyInstallmentFixed.value * fixedMonths) + (monthlyInstallmentFloating.value * remainingMonths);
    } else if (kprType.value === 'tiered') {
        return dynamicTieredInstallments.value.reduce((sum, t) => {
            const monthsToPay = Math.min(t.months, (tenorYears.value * 12) - (t.tierIndex - 1) * t.months);
            return sum + (t.installment * Math.max(0, monthsToPay));
        }, 0);
    } else {
        const principal = loanPrincipal.value;
        const totalMargin = principal * (syariahMarginRate.value / 100) * tenorYears.value;
        return principal + totalMargin;
    }
});

const totalInterestOrMargin = computed(() => {
    return Math.max(0, totalPayment.value - loanPrincipal.value);
});

// Payment Shock Calculator
const paymentShockPercent = computed(() => {
    if (kprType.value !== 'conventional' || monthlyInstallmentFixed.value <= 0) return 0;
    return ((monthlyInstallmentFloating.value - monthlyInstallmentFixed.value) / monthlyInstallmentFixed.value) * 100;
});

// Extra Prepayments Calculator
const extraMonthlyPayment = ref(0);
const lumpSumAmount = ref(0);
const lumpSumYear = ref(5);
const showExtraPayment = ref(false);

const earlyPayoffResult = computed(() => {
    if (!showExtraPayment.value || (extraMonthlyPayment.value <= 0 && lumpSumAmount.value <= 0)) {
        return { hasExtraPayment: false };
    }

    const principal = loanPrincipal.value;
    const totalMonths = tenorYears.value * 12;
    let balance = principal;
    let month = 0;
    let totalInterestPaid = 0;

    // Normal monthly rates
    const normalRateFixed = (annualInterest.value / 100) / 12;
    const normalRateFloat = (floatingInterest.value / 100) / 12;
    const fixedMonths = actualFixedYears.value * 12;

    while (balance > 0 && month < totalMonths) {
        month++;
        
        // Apply Lump Sum payment if matching the selected year
        if (lumpSumAmount.value > 0 && month === (lumpSumYear.value * 12)) {
            balance = Math.max(0, balance - lumpSumAmount.value);
        }

        let rate = month <= fixedMonths ? normalRateFixed : normalRateFloat;
        let interestPart = balance * rate;
        let basePayment = month <= fixedMonths ? monthlyInstallmentFixed.value : monthlyInstallmentFloating.value;

        let totalAvailablePayment = basePayment + extraMonthlyPayment.value;
        let principalPart = Math.min(balance, totalAvailablePayment - interestPart);

        totalInterestPaid += interestPart;
        balance = Math.max(0, balance - principalPart);

        if (balance <= 0) break;
    }

    const monthsSaved = Math.max(0, totalMonths - month);
    const normalInterest = totalInterestOrMargin.value;
    const interestSaved = Math.max(0, normalInterest - totalInterestPaid);

    return {
        hasExtraPayment: true,
        newTenorYears: Math.floor(month / 12),
        newTenorMonths: month % 12,
        yearsSaved: Math.floor(monthsSaved / 12),
        monthsSaved: monthsSaved % 12,
        interestSaved: interestSaved
    };
});

// --- TABS: ELIGIBILITY SIMULATION (REVERSE) ---
const netIncome = ref(15000000);
const otherDebts = ref(1500000);
const dpReady = ref(100000000);
const eligibilityInterest = ref(7.5);
const eligibilityTenor = ref(15);
const dsrPercent = ref(30);

const maxHealthyInstallment = computed(() => {
    const limit = (netIncome.value * dsrPercent.value) / 100;
    return Math.max(0, limit - otherDebts.value);
});

const maxLoanCapacity = computed(() => {
    const monthlyRate = (eligibilityInterest.value / 12) / 100;
    const totalMonths = eligibilityTenor.value * 12;
    if (maxHealthyInstallment.value <= 0) return 0;
    if (monthlyRate === 0) return maxHealthyInstallment.value * totalMonths;

    const factor = Math.pow(1 + monthlyRate, totalMonths);
    return Math.round((maxHealthyInstallment.value * (factor - 1)) / (monthlyRate * factor));
});

const maxPropertyPrice = computed(() => {
    return maxLoanCapacity.value + dpReady.value;
});

// --- UTILITIES ---
const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val);
};

const copySimulationText = () => {
    let text = '';
    if (activeTab.value === 'installments') {
        text = `*SIMULASI CICILAN KPR PINTAR* 🏠\n` +
               `-----------------------------------\n` +
               (props.unitCode ? `Unit Properti: *${props.unitCode}*\n` : '') +
               `Harga Properti: *${formatCurrency(propertyPrice.value)}*\n` +
               `Uang Muka (DP ${dpPercent.value}%): *${formatCurrency(dpAmount.value)}*\n` +
               `Jumlah KPR (Plafond): *${formatCurrency(loanPrincipal.value)}*\n` +
               `Skema Suku Bunga: *${kprType.value.toUpperCase()}*\n` +
               (selectedBank.value ? `Bank Partner: *${selectedBank.value.name}*\n` : '') +
               `Tenor Pinjaman: *${tenorYears.value} Tahun*\n` +
               `-----------------------------------\n`;

        if (kprType.value === 'conventional') {
            text += `Cicilan Fixed (Th 1-${actualFixedYears.value}): *${formatCurrency(monthlyInstallmentFixed.value)}/bln*\n` +
                    `Cicilan Floating (Estimasi): *${formatCurrency(monthlyInstallmentFloating.value)}/bln*\n`;
        } else if (kprType.value === 'tiered') {
            dynamicTieredInstallments.value.forEach(t => {
                text += `Cicilan Tahap ${t.tierIndex} (${t.rate}%): *${formatCurrency(t.installment)}/bln* (${t.years} Thn)\n`;
            });
        } else {
            text += `Cicilan Flat Syariah: *${formatCurrency(monthlyInstallmentSyariah.value)}/bln*\n`;
        }

        text += `-----------------------------------\n` +
                `Total Bayar KPR: *${formatCurrency(totalPayment.value)}*\n` +
                `Total Bunga/Margin: *${formatCurrency(totalInterestOrMargin.value)}*\n\n` +
                `_Catatan: Hasil simulasi merupakan estimasi awal._`;
    } else {
        text = `*ANALISIS KELAYAKAN KPR PINTAR* 📊\n` +
               `-----------------------------------\n` +
               `Pendapatan Bersih: *${formatCurrency(netIncome.value)}/bulan*\n` +
               `Cicilan Bulanan Lain: *${formatCurrency(otherDebts.value)}*\n` +
               `Uang Muka (DP) Disiapkan: *${formatCurrency(dpReady.value)}*\n` +
               `Tenor Dipilih: *${eligibilityTenor.value} Tahun*\n` +
               `Suku Bunga Asumsi: *${eligibilityInterest.value}%*\n` +
               `-----------------------------------\n` +
               `Maksimal Cicilan Bulanan: *${formatCurrency(maxHealthyInstallment.value)}*\n` +
               `Maksimal Pokok KPR: *${formatCurrency(maxLoanCapacity.value)}*\n` +
               `*Maksimal Harga Rumah Mampu Beli: ${formatCurrency(maxPropertyPrice.value)}*\n\n` +
               `_Hubungi konsultan kami untuk mengajukan KPR bank partner resmi._`;
    }
    
    navigator.clipboard.writeText(text);
    alert('Simulasi KPR berhasil disalin ke clipboard! Siap dipaste ke WhatsApp.');
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[200] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="emit('close')"></div>
        <div class="relative bg-white rounded-3xl w-full max-w-4xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
            
            <!-- HEADER -->
            <div class="sticky top-0 bg-white px-8 py-5 border-b border-slate-100 flex items-center justify-between z-10">
                <div>
                    <h3 class="text-lg font-black text-slate-900 flex items-center gap-2">
                        <span>🧮</span> KPR Smart Simulator
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Simulasikan cicilan bulanan atau analisis kelayakan KPR secara instan.</p>
                </div>
                <button @click="emit('close')" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-400 font-bold text-lg">&times;</button>
            </div>

            <!-- TABS SWITCH -->
            <div class="px-8 pt-4 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                <div class="flex bg-slate-200/60 rounded-xl p-0.5 w-72">
                    <button @click="activeTab = 'installments'" 
                        :class="activeTab === 'installments' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        class="flex-1 py-1.5 text-xs font-black uppercase tracking-wider rounded-lg transition-all">
                        🏠 Hitung Cicilan
                    </button>
                    <button @click="activeTab = 'eligibility'" 
                        :class="activeTab === 'eligibility' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        class="flex-1 py-1.5 text-xs font-black uppercase tracking-wider rounded-lg transition-all">
                        🔍 Kelayakan Gaji
                    </button>
                </div>

                <!-- Program Bank Selector Dropdown (Installments only) -->
                <div v-if="activeTab === 'installments' && partnerBanks.length" class="flex items-center gap-2">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Pilih Bank Partner:</span>
                    <select v-model="selectedBankId" class="px-3 py-1 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500 cursor-pointer">
                        <option value="">-- Bunga Custom --</option>
                        <option v-for="bank in partnerBanks" :key="bank.id" :value="bank.id">
                            {{ bank.name }} {{ bank.is_syariah ? '(Syariah)' : (bank.is_tiered ? '(Berjenjang)' : '(Promo)') }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- CONTENT BODY (Scrollable) -->
            <div class="flex-1 overflow-y-auto p-8 space-y-6">
                
                <!-- TAB 1: INSTALLMENT CALCULATOR -->
                <div v-if="activeTab === 'installments'" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        
                        <!-- Inputs Form -->
                        <div class="space-y-4">
                            <!-- KPR Type Tabs -->
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Skema Suku Bunga</label>
                                <div class="flex bg-slate-100 rounded-xl p-0.5 w-full">
                                    <button @click="kprType = 'conventional'" 
                                        :class="kprType === 'conventional' ? 'bg-[#1e40af] text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                                        class="flex-1 py-1.5 text-[10px] font-bold rounded-lg transition-all">
                                        Fix & Floating
                                    </button>
                                    <button @click="kprType = 'tiered'" 
                                        :class="kprType === 'tiered' ? 'bg-[#1e40af] text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                                        class="flex-1 py-1.5 text-[10px] font-bold rounded-lg transition-all">
                                        Berjenjang
                                    </button>
                                    <button @click="kprType = 'syariah'" 
                                        :class="kprType === 'syariah' ? 'bg-[#1e40af] text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                                        class="flex-1 py-1.5 text-[10px] font-bold rounded-lg transition-all">
                                        Flat Syariah
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Harga Properti (IDR)</label>
                                <input v-model.number="propertyPrice" type="number" step="1000000" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-600/20" />
                            </div>
                            
                            <div>
                                <div class="flex justify-between items-center mb-1.5">
                                    <label class="block text-[10px] font-black text-slate-400 uppercase">Uang Muka (DP): {{ dpPercent }}%</label>
                                    <span class="text-xs font-bold text-blue-600">{{ formatCurrency(dpAmount) }}</span>
                                </div>
                                <input v-model.number="dpPercent" type="range" min="0" max="90" step="5" class="w-full h-1.5 bg-slate-100 rounded-full appearance-none cursor-pointer accent-blue-600" />
                            </div>

                            <!-- Conventional Rates Setup -->
                            <div v-if="kprType === 'conventional'" class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Bunga Fixed (%/thn)</label>
                                    <input v-model.number="annualInterest" type="number" step="0.05" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-600/20" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tenor Fixed (thn)</label>
                                    <input v-model.number="fixedDuration" type="number" min="1" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-600/20" />
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Asumsi Bunga Floating (%/thn)</label>
                                    <input v-model.number="floatingInterest" type="number" step="0.1" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-600/20" />
                                </div>
                            </div>

                            <!-- Syariah Rates Setup -->
                            <div v-if="kprType === 'syariah'">
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Margin Keuntungan Flat (%/thn)</label>
                                <input v-model.number="syariahMarginRate" type="number" step="0.1" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-blue-600/20" />
                            </div>

                            <!-- Tiered Rates Setup -->
                            <div v-if="kprType === 'tiered'" class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-black uppercase text-slate-500">Konfigurasi Berjenjang</span>
                                    <div class="flex gap-1.5">
                                        <button type="button" @click="numTiers = Math.max(1, numTiers - 1)" class="w-5 h-5 bg-white border border-slate-200 rounded flex items-center justify-center text-xs font-bold">-</button>
                                        <span class="text-xs font-bold px-1">{{ numTiers }} Tahap</span>
                                        <button type="button" @click="numTiers = Math.min(5, numTiers + 1)" class="w-5 h-5 bg-white border border-slate-200 rounded flex items-center justify-center text-xs font-bold">+</button>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div v-for="i in numTiers" :key="i" class="flex gap-2 items-center">
                                        <span class="text-[10px] font-bold text-slate-400 w-12">Tahap {{ i }}</span>
                                        <input v-model.number="tiers[i-1].rate" type="number" step="0.1" placeholder="Bunga %" class="w-20 px-2 py-1 border border-slate-200 rounded text-xs text-center font-bold" />
                                        <span class="text-[10px] text-slate-400">%</span>
                                        <input v-if="i < numTiers" v-model.number="tiers[i-1].years" type="number" min="1" placeholder="Tahun" class="w-16 px-2 py-1 border border-slate-200 rounded text-xs text-center font-bold" />
                                        <span v-else class="text-xs font-bold text-slate-500 bg-slate-200/50 px-3 py-1 rounded w-16 text-center">
                                            {{ Math.max(1, tenorYears - adjustedTiers.slice(0, -1).reduce((sum, t) => sum + t.years, 0)) }}
                                        </span>
                                        <span class="text-[10px] text-slate-400">Tahun</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between items-center mb-1.5">
                                    <label class="block text-[10px] font-black text-slate-400 uppercase">Jangka Waktu KPR (Tenor): {{ tenorYears }} Tahun</label>
                                </div>
                                <input v-model.number="tenorYears" type="range" min="1" max="30" step="1" class="w-full h-1.5 bg-slate-100 rounded-full appearance-none cursor-pointer accent-blue-600" />
                            </div>
                        </div>

                        <!-- Output Results Panel -->
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100/50 p-6 rounded-3xl flex flex-col justify-between shadow-sm">
                            <div class="space-y-4">
                                <span class="text-[9px] font-black text-blue-600 uppercase tracking-widest bg-blue-100/60 px-2 py-0.5 rounded-full">Hasil Proyeksi</span>
                                
                                <div class="pt-2">
                                    <p class="text-xs text-slate-500 font-medium">
                                        {{ kprType === 'conventional' ? `Cicilan Fixed (Th 1-${actualFixedYears})` : (kprType === 'tiered' ? 'Cicilan Tahap Awal' : 'Cicilan Bulanan Flat') }}
                                    </p>
                                    <h3 class="text-3xl font-black text-blue-700 tracking-tight mt-1">{{ formatCurrency(monthlyInstallment) }}</h3>
                                </div>

                                <div v-if="kprType === 'conventional' && tenorYears > actualFixedYears" class="pt-1">
                                    <p class="text-xs text-slate-500 font-medium">Cicilan Floating (Tahun {{ actualFixedYears + 1 }} - {{ tenorYears }})</p>
                                    <h4 class="text-lg font-extrabold text-slate-700 tracking-tight mt-0.5">{{ formatCurrency(monthlyInstallmentFloating) }}</h4>
                                </div>

                                <div v-if="kprType === 'tiered'" class="bg-white/60 p-3 rounded-xl border border-blue-100/40 text-[10px] font-bold text-slate-600 space-y-1">
                                    <div v-for="t in dynamicTieredInstallments" :key="t.tierIndex">
                                        Tahap {{ t.tierIndex }} ({{ t.rate }}%): <span class="text-blue-700 font-black">{{ formatCurrency(t.installment) }}</span>/bln ({{ t.years }} thn)
                                    </div>
                                </div>

                                <div class="border-t border-blue-200/50 pt-4 space-y-2 text-xs">
                                    <div class="flex justify-between"><span class="text-slate-500">Harga Properti</span><span class="font-bold text-slate-800">{{ formatCurrency(propertyPrice) }}</span></div>
                                    <div class="flex justify-between"><span class="text-slate-500">Uang Muka (DP)</span><span class="font-bold text-slate-800">{{ formatCurrency(dpAmount) }}</span></div>
                                    <div class="flex justify-between"><span class="text-slate-500">Plafon Pinjaman KPR</span><span class="font-bold text-blue-700 font-sans">{{ formatCurrency(loanPrincipal) }}</span></div>
                                    <div class="flex justify-between"><span class="text-slate-500">Total Bunga / Margin</span><span class="font-bold text-emerald-600">{{ formatCurrency(totalInterestOrMargin) }}</span></div>
                                    <div class="flex justify-between"><span class="text-slate-500">Total Bayar KPR</span><span class="font-bold text-slate-900 font-sans">{{ formatCurrency(totalPayment) }}</span></div>
                                </div>
                            </div>
                            
                            <div class="pt-6">
                                <button @click="copySimulationText" class="w-full py-3 bg-blue-600 text-white font-black text-xs rounded-xl hover:bg-blue-700 transition-all uppercase tracking-widest flex items-center justify-center gap-2 shadow-lg shadow-blue-500/20">
                                    💬 Salin Hasil ke WA
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Shock Simulator Card -->
                    <div v-if="kprType === 'conventional' && tenorYears > actualFixedYears" class="bg-amber-50 border border-amber-100 rounded-3xl p-6 space-y-3">
                        <h4 class="text-xs font-black uppercase text-amber-800 tracking-wider flex items-center gap-1.5">⚡ Payment Shock Warning</h4>
                        <div class="flex items-center gap-4 text-xs">
                            <div class="flex-1 bg-white/60 p-3 rounded-2xl border border-amber-200/40">
                                <span class="text-slate-500 text-[10px] uppercase font-bold">Kenaikan Cicilan</span>
                                <p class="text-lg font-black text-amber-700 mt-0.5">+{{ paymentShockPercent.toFixed(1) }}%</p>
                            </div>
                            <div class="flex-[2] text-slate-600 text-[11px] leading-relaxed">
                                Cicilan Anda diproyeksikan akan naik dari <strong class="text-slate-900">{{ formatCurrency(monthlyInstallmentFixed) }}</strong> menjadi <strong class="text-slate-900">{{ formatCurrency(monthlyInstallmentFloating) }}</strong> ketika masa bunga fixed berakhir. Pastikan kesiapan bayar berkala prospek Anda aman.
                            </div>
                        </div>
                    </div>

                    <!-- Extra Payment Payoff Simulator -->
                    <div class="bg-slate-50 border border-slate-100 rounded-3xl overflow-hidden">
                        <button @click="showExtraPayment = !showExtraPayment" type="button" class="w-full p-5 flex justify-between items-center hover:bg-slate-100/50 transition-colors">
                            <h4 class="text-xs font-black uppercase text-slate-700 tracking-wider flex items-center gap-1.5">⏱️ Pelunasan Dipercepat & Hemat Bunga</h4>
                            <span class="text-slate-400 font-bold">{{ showExtraPayment ? 'Sembunyikan ▴' : 'Tampilkan Simulasi ▾' }}</span>
                        </button>
                        
                        <div v-if="showExtraPayment" class="p-5 border-t border-slate-100 space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tambahan Angsuran per Bulan</label>
                                    <input v-model.number="extraMonthlyPayment" type="number" step="100000" class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" placeholder="Misal: 1000000" />
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Lump-sum Sekaligus</label>
                                        <input v-model.number="lumpSumAmount" type="number" step="1000000" class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" placeholder="Misal: 50000000" />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Di Tahun Ke-</label>
                                        <input v-model.number="lumpSumYear" type="number" min="1" class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold" />
                                    </div>
                                </div>
                            </div>

                            <!-- Payoff Simulation Results -->
                            <div v-if="earlyPayoffResult.hasExtraPayment" class="bg-white p-4 rounded-2xl border border-slate-200/60 grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase">Hemat Tenor</span>
                                    <p class="text-base font-black text-blue-600 mt-0.5">{{ earlyPayoffResult.yearsSaved }} Thn {{ earlyPayoffResult.monthsSaved }} Bln</p>
                                </div>
                                <div>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase">Hemat Bunga</span>
                                    <p class="text-base font-black text-emerald-600 mt-0.5">{{ formatCurrency(earlyPayoffResult.interestSaved) }}</p>
                                </div>
                                <div>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase">Tenor Baru</span>
                                    <p class="text-base font-black text-slate-800 mt-0.5">{{ earlyPayoffResult.newTenorYears }} Thn {{ earlyPayoffResult.newTenorMonths }} Bln</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: ELIGIBILITY CALCULATOR (REVERSE) -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Inputs Form -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Gaji Bersih Gabungan Bulanan</label>
                            <input v-model.number="netIncome" type="number" step="500000" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-violet-600/20" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Cicilan Bulanan Lainnya (Mobil/CC/dll)</label>
                            <input v-model.number="otherDebts" type="number" step="100000" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-violet-600/20" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Uang Muka (DP) yang Disiapkan</label>
                            <input v-model.number="dpReady" type="number" step="5000000" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-violet-600/20" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tenor KPR (Tahun)</label>
                                <input v-model.number="eligibilityTenor" type="number" min="1" max="30" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-violet-600/20" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Asumsi Bunga (%/thn)</label>
                                <input v-model.number="eligibilityInterest" type="number" step="0.1" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm font-bold focus:ring-2 focus:ring-violet-600/20" />
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-1.5">
                                <label class="block text-[10px] font-black text-slate-400 uppercase">Limit DSR Keuangan: {{ dsrPercent }}%</label>
                            </div>
                            <input v-model.number="dsrPercent" type="range" min="10" max="60" step="5" class="w-full h-1.5 bg-slate-100 rounded-full appearance-none cursor-pointer accent-violet-600" />
                        </div>
                    </div>

                    <!-- Output Results Panel -->
                    <div class="bg-gradient-to-br from-violet-50 to-purple-50 border border-violet-100/50 p-6 rounded-3xl flex flex-col justify-between shadow-sm">
                        <div class="space-y-4">
                            <span class="text-[9px] font-black text-violet-600 uppercase tracking-widest bg-violet-100/60 px-2 py-0.5 rounded-full">Kelayakan Pembelian</span>
                            <div class="pt-2">
                                <p class="text-xs text-slate-500 font-medium">Harga Rumah Maksimal Mampu Beli</p>
                                <h3 class="text-3xl font-black text-violet-700 tracking-tight mt-1">{{ formatCurrency(maxPropertyPrice) }}</h3>
                            </div>
                            <div class="border-t border-violet-200/50 pt-4 space-y-2 text-xs">
                                <div class="flex justify-between"><span class="text-slate-500">Maks. Angsuran Sehat</span><span class="font-bold text-violet-700">{{ formatCurrency(maxHealthyInstallment) }}</span></div>
                                <div class="flex justify-between"><span class="text-slate-500">Maks. Pinjaman KPR</span><span class="font-bold text-slate-800">{{ formatCurrency(maxLoanCapacity) }}</span></div>
                                <div class="flex justify-between"><span class="text-slate-500">Dana DP Tersedia</span><span class="font-bold text-slate-800">{{ formatCurrency(dpReady) }}</span></div>
                            </div>
                        </div>
                        <div class="pt-6">
                            <button @click="copySimulationText" class="w-full py-3 bg-violet-600 text-white font-black text-xs rounded-xl hover:bg-violet-700 transition-all uppercase tracking-widest flex items-center justify-center gap-2">
                                💬 Salin Hasil ke WA
                            </button>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</template>
