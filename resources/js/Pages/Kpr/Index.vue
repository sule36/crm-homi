<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Bar } from 'vue-chartjs';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

const props = defineProps({
  banks: { type: Array, default: () => [] }
});

const isCapturing = ref(false);

// --- INDONESIAN BANK PROGRAMS DEFAULT DATASET ---
const DEFAULT_PROGRAMS = [
  {
    id: 'default-bca-1',
    bank_name: 'BCA',
    program_name: 'BCA KPR Fix 3 Tahun Promo',
    type: 'conventional',
    interest_rate_fixed: 3.85,
    interest_rate_floating: 11.00,
    fixed_duration: 3,
    logo_text: 'BCA',
    logo_color: 'bg-blue-700',
    logo: null,
    description: 'Promo bunga fixed 3.85% selama 3 tahun pertama, suku bunga floating transparan setelahnya.',
    is_promo: true,
  },
  {
    id: 'default-mandiri-1',
    bank_name: 'Mandiri',
    program_name: 'Mandiri KPR Livin Super Promo',
    type: 'conventional',
    interest_rate_fixed: 3.99,
    interest_rate_floating: 11.50,
    fixed_duration: 3,
    logo_text: 'MANDIRI',
    logo_color: 'bg-amber-600',
    logo: null,
    description: 'Suku bunga spesial fixed 3.99% 3 tahun khusus nasabah payroll & properti pilihan.',
    is_promo: true,
  },
  {
    id: 'default-btn-1',
    bank_name: 'BTN',
    program_name: 'BTN KPR Platinum Fix 2 Thn',
    type: 'conventional',
    interest_rate_fixed: 4.25,
    interest_rate_floating: 12.00,
    fixed_duration: 2,
    logo_text: 'BTN',
    logo_color: 'bg-blue-900',
    logo: null,
    description: 'Solusi KPR dari bank spesialis perumahan dengan proses persetujuan cepat.',
    is_promo: true,
  },
  {
    id: 'default-bsi-1',
    bank_name: 'BSI',
    program_name: 'BSI Griya Simpel Flat 15 Thn',
    type: 'syariah',
    calculation_type: 'flat',
    interest_rate_fixed: 6.50,
    interest_rate_floating: 0,
    fixed_duration: 0,
    logo_text: 'BSI',
    logo_color: 'bg-teal-700',
    logo: null,
    description: 'Pembiayaan Syariah dengan margin flat fixed tanpa fluktuasi cicilan hingga lunas.',
    is_promo: true,
  },
  {
    id: 'default-bca-tiered',
    bank_name: 'BCA',
    program_name: 'BCA KPR Fix Berjenjang 10 Thn',
    type: 'tiered',
    interest_rate_fixed: 3.85,
    logo_text: 'BCA',
    logo_color: 'bg-blue-700',
    logo: null,
    description: 'Bunga pasti berjenjang: Thn 1-3 (3.85%), Thn 4-6 (6.85%), Thn 7-10 (8.85%).',
    is_promo: true,
    tiers: [
      { rate: 3.85, years: 3 },
      { rate: 6.85, years: 3 },
      { rate: 8.85, years: 4 }
    ]
  }
];

// Combine database banks with default programs
const allPrograms = computed(() => {
  const list = DEFAULT_PROGRAMS.map(prog => {
    const progBankName = (prog.bank_name || '').trim().toLowerCase();
    const dbBank = props.banks?.find(b => {
      const dbName = (b.name || '').trim().toLowerCase();
      return dbName === progBankName || progBankName.includes(dbName) || dbName.includes(progBankName);
    });
    
    const hasExactDbBank = props.banks?.some(b => (b.name || '').trim().toLowerCase() === progBankName);

    return {
      ...prog,
      logo: dbBank ? dbBank.logo : null,
      isOverridden: hasExactDbBank
    };
  }).filter(prog => !prog.isOverridden);

  if (props.banks && props.banks.length > 0) {
    props.banks.forEach(dbBank => {
      const id = `db-${dbBank.id}`;
      const bankName = dbBank.name || 'Bank';
      const logoText = bankName.substring(0, 4);

      if (dbBank.is_syariah) {
        if (dbBank.syariah_margin_rate && parseFloat(dbBank.syariah_margin_rate) > 0) {
          if (!list.some(p => p.id === `${id}-syariah-flat`)) {
            list.push({
              id: `${id}-syariah-flat`,
              bank_name: bankName,
              program_name: `${bankName} Syariah iB Flat`,
              type: 'syariah',
              calculation_type: 'flat',
              interest_rate_fixed: parseFloat(dbBank.syariah_margin_rate || 6.5),
              interest_rate_floating: 0,
              fixed_duration: 0,
              logo_text: logoText,
              logo_color: 'bg-slate-700',
              logo: dbBank.logo,
              description: 'Program margin profit flat tetap dari bank partner resmi.',
              is_promo: false,
              is_db: true
            });
          }
        }
      } else {
        let progsData = dbBank.fixed_programs;
        if (typeof progsData === 'string') {
          try { progsData = JSON.parse(progsData); } catch (e) { progsData = []; }
        }

        if (Array.isArray(progsData) && progsData.length > 0) {
          progsData.forEach((pItem, pIdx) => {
            const pId = `${id}-conventional-${pIdx}`;
            if (!list.some(p => p.id === pId)) {
              list.push({
                id: pId,
                bank_name: bankName,
                program_name: pItem.name ? `${bankName} (${pItem.name})` : `${bankName} KPR Fix ${pItem.fixed_duration} Thn`,
                type: 'conventional',
                interest_rate_fixed: parseFloat(pItem.interest_rate_fixed || 5.0),
                interest_rate_floating: parseFloat(pItem.interest_rate_floating || 11.0),
                fixed_duration: parseInt(pItem.fixed_duration || 3),
                logo_text: logoText,
                logo_color: 'bg-slate-700',
                logo: dbBank.logo,
                description: `Program ${pItem.name || 'Fix Floating'} resmi terdaftar di sistem.`,
                is_promo: false,
                is_db: true
              });
            }
          });
        } else if (dbBank.interest_rate_fixed && parseFloat(dbBank.interest_rate_fixed) > 0) {
          if (!list.some(p => p.id === `${id}-conventional`)) {
            list.push({
              id: `${id}-conventional`,
              bank_name: bankName,
              program_name: `${bankName} KPR Fixed`,
              type: 'conventional',
              interest_rate_fixed: parseFloat(dbBank.interest_rate_fixed || 5.0),
              interest_rate_floating: parseFloat(dbBank.interest_rate_floating || 11.0),
              fixed_duration: parseInt(dbBank.fixed_duration || 3),
              logo_text: logoText,
              logo_color: 'bg-slate-700',
              logo: dbBank.logo,
              description: 'Program bank partner resmi.',
              is_promo: false,
              is_db: true
            });
          }
        }
        
        if (dbBank.is_tiered) {
          let tiersData = dbBank.tiered_rates;
          if (typeof tiersData === 'string') {
            try { tiersData = JSON.parse(tiersData); } catch(e) { tiersData = []; }
          }
          if (Array.isArray(tiersData) && tiersData.length > 0) {
            if (!list.some(p => p.id === `${id}-tiered`)) {
              list.push({
                id: `${id}-tiered`,
                bank_name: bankName,
                program_name: `${bankName} KPR Berjenjang`,
                type: 'tiered',
                interest_rate_fixed: parseFloat(tiersData[0]?.rate || 2.95),
                logo_text: logoText,
                logo_color: 'bg-slate-700',
                logo: dbBank.logo,
                description: 'Program bunga berjenjang resmi dari bank partner.',
                is_promo: false,
                is_db: true,
                tiers: tiersData.map(t => ({ rate: parseFloat(t.rate), years: parseInt(t.years) }))
              });
            }
          }
        }
      }
    });
  }

  return list;
});

// --- STATE REACTIVE KALKULATOR ---
const calculatorMode = ref('simulasi'); // 'simulasi' | 'kelayakan'
const activeTab = ref('angsuran'); // 'angsuran' | 'insight' | 'amortisasi'
const kprType = ref('conventional'); // 'conventional' | 'syariah' | 'tiered'
const sukuBungaProduct = ref('floating'); // 'flat' | 'tiered' | 'floating'

// Inputs Mode 1: Simulasi
const propertyPrice = ref(500000000);
const isPriceFocused = ref(false);
const dpPercentage = ref(10);
const dpAmount = ref(50000000);
const isDpAmountFocused = ref(false);
const dpInputMode = ref('percent'); // 'percent' | 'nominal'

const useBankProgram = ref(false);
const selectedBank = ref(null);
const showProgramModal = ref(false);
const modalCategory = ref('conventional'); // 'conventional' | 'syariah' | 'tiered'

const annualInterest = ref(3.85); // % fixed rate
const fixedDuration = ref(3); // years fixed
const floatingInterest = ref(11.00); // % floating rate

// Syariah margin
const syariahMarginRate = ref(6.50);

// Tiered Configuration
const numTiers = ref(3);
const tiers = ref([
  { rate: 3.85, years: 3 },
  { rate: 6.85, years: 3 },
  { rate: 8.85, years: 4 }
]);

const tenorMonths = ref(180); // 15 Years
const tenorYears = computed(() => Math.max(1, Math.round(tenorMonths.value / 12)));

// FITUR 3: Pelunasan Dipercepat
const showExtraPayment = ref(false);
const extraMonthlyPayment = ref(0);
const lumpSumAmount = ref(0);
const lumpSumYear = ref(5);

// FITUR 4: Reverse DSR / Kelayakan KPR
const affIncome = ref(15000000);
const affOtherDebt = ref(0);
const affDpReady = ref(100000000);
const affInterest = ref(5.0);
const affTenor = ref(15);
const affDsrTarget = ref(35); // %

// FITUR 5: Promo Developer Toggles
const promoBphtb = ref(false);
const promoNotaris = ref(false);
const promoSubsidiDp = ref(false);
const promoFreeKpr = ref(false);

const monthlyIncome = ref(15000000);

// Synchronize DP percent and nominal
watch([dpPercentage, propertyPrice], () => {
  if (dpInputMode.value === 'percent') {
    dpAmount.value = Math.round((propertyPrice.value * dpPercentage.value) / 100);
  }
});

watch(dpAmount, () => {
  if (dpInputMode.value === 'nominal' && propertyPrice.value > 0) {
    const pct = (dpAmount.value / propertyPrice.value) * 100;
    dpPercentage.value = Math.min(90, Math.max(0, Math.round(pct * 100) / 100));
  }
});

// Watch bank program selection
watch(selectedBank, (program) => {
  if (program) {
    useBankProgram.value = true;
    if (program.type === 'syariah') {
      kprType.value = 'syariah';
      if (program.calculation_type === 'flat') {
        sukuBungaProduct.value = 'flat';
        annualInterest.value = program.interest_rate_fixed || 6.5;
      } else {
        sukuBungaProduct.value = 'floating';
        annualInterest.value = program.interest_rate_fixed || 5.0;
        floatingInterest.value = program.interest_rate_floating || 11.0;
        fixedDuration.value = program.fixed_duration || 3;
      }
    } else if (program.type === 'tiered') {
      kprType.value = 'tiered';
      sukuBungaProduct.value = 'tiered';
      if (program.tiers && program.tiers.length > 0) {
        numTiers.value = program.tiers.length;
        tiers.value = program.tiers.map(t => ({ rate: t.rate, years: t.years }));
      }
    } else {
      kprType.value = 'conventional';
      sukuBungaProduct.value = 'floating';
      annualInterest.value = program.interest_rate_fixed || 3.85;
      floatingInterest.value = program.interest_rate_floating || 11.0;
      fixedDuration.value = program.fixed_duration || 3;
    }
  }
});

watch(useBankProgram, (enabled) => {
  if (!enabled) {
    selectedBank.value = null;
  }
});

// Sync sukuBungaProduct button with kprType
watch(sukuBungaProduct, (val) => {
  if (val === 'flat') {
    kprType.value = 'syariah';
  } else if (val === 'tiered') {
    kprType.value = 'tiered';
  } else if (val === 'floating') {
    kprType.value = 'conventional';
  }
});

// Calculation derived states
const loanPrincipal = computed(() => Math.max(0, propertyPrice.value - dpAmount.value));
const actualFixedYears = computed(() => Math.min(fixedDuration.value, tenorYears.value));

// Annuity calculation helper
function calculateMonthlyAnnuity(principal, annualRatePct, totalMonths) {
  if (principal <= 0 || totalMonths <= 0) return 0;
  const r = (annualRatePct / 100) / 12;
  if (r === 0) return Math.round(principal / totalMonths);
  const factor = Math.pow(1 + r, totalMonths);
  return Math.round((principal * r * factor) / (factor - 1));
}

// Conventional Fixed Installment
const monthlyInstallmentFixed = computed(() => {
  return calculateMonthlyAnnuity(loanPrincipal.value, annualInterest.value, tenorMonths.value);
});

// Balance remaining after fixed period
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

// Conventional Floating Installment
const monthlyInstallmentFloating = computed(() => {
  const remainingM = tenorMonths.value - (actualFixedYears.value * 12);
  if (remainingM <= 0) return 0;
  return calculateMonthlyAnnuity(remainingBalanceAfterFixed.value, floatingInterest.value, remainingM);
});

// Syariah Flat Installment
const monthlyInstallmentSyariah = computed(() => {
  const principal = loanPrincipal.value;
  const totalMargin = principal * (annualInterest.value / 100) * tenorYears.value;
  const totalPayable = principal + totalMargin;
  return tenorMonths.value > 0 ? Math.round(totalPayable / tenorMonths.value) : 0;
});

// Tiered Adjustments
const adjustedTiers = computed(() => {
  const list = [];
  let allocatedYears = 0;

  for (let i = 0; i < numTiers.value; i++) {
    const t = tiers.value[i] || { rate: 8.85, years: 3 };
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

// Dynamic Tiered Installments
const dynamicTieredInstallments = computed(() => {
  const principal = loanPrincipal.value;
  const totalM = tenorMonths.value;
  const list = [];

  let currentBal = principal;
  let accumYears = 0;
  let accumMonths = 0;

  for (let i = 0; i < adjustedTiers.value.length; i++) {
    const tier = adjustedTiers.value[i];
    const rate = Number(tier.rate) || 0;
    const years = Number(tier.years) || 0;
    const remainingM = totalM - accumMonths;

    let inst = 0;
    if (remainingM > 0 && currentBal > 0) {
      inst = calculateMonthlyAnnuity(currentBal, rate, remainingM);
    }

    const startYear = accumYears + 1;
    const endYear = accumYears + years;
    accumYears += years;
    accumMonths += years * 12;

    list.push({
      tierIndex: i + 1,
      startYear,
      endYear,
      rate,
      years,
      installment: inst
    });
  }
  return list;
});

// Primary Monthly Installment for display
const monthlyInstallment = computed(() => {
  if (kprType.value === 'syariah') return monthlyInstallmentSyariah.value;
  if (kprType.value === 'tiered') return dynamicTieredInstallments.value[0]?.installment || 0;
  return monthlyInstallmentFixed.value;
});

// FITUR 2: Payment Shock Metrics
const paymentShockPercent = computed(() => {
  if (kprType.value !== 'conventional') return 0;
  if (monthlyInstallmentFixed.value <= 0) return 0;
  const diff = monthlyInstallmentFloating.value - monthlyInstallmentFixed.value;
  return Math.max(0, (diff / monthlyInstallmentFixed.value) * 100);
});

const paymentShockClass = computed(() => {
  if (paymentShockPercent.value > 50) return 'bg-rose-100 text-rose-700 border border-rose-200';
  if (paymentShockPercent.value > 20) return 'bg-amber-100 text-amber-700 border border-amber-200';
  return 'bg-emerald-100 text-emerald-700 border border-emerald-200';
});

const paymentShockLabel = computed(() => {
  if (paymentShockPercent.value > 50) return '⚠️ RISIKO SHOCK TINGGI';
  if (paymentShockPercent.value > 20) return '⚡ SHOCK MODERAT';
  return '✅ SHOCK RENDAH';
});

const fixedWidthPercent = computed(() => {
  if (tenorYears.value <= 0) return 30;
  return Math.min(100, Math.max(10, (actualFixedYears.value / tenorYears.value) * 100));
});

// Upfront Fees Breakdown
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

const firstPaymentEstimate = computed(() => upfrontFees.value.total);

// FITUR 3: Early Payoff Result (Pelunasan Dipercepat)
const earlyPayoffResult = computed(() => {
  const extra = extraMonthlyPayment.value || 0;
  const lumpSum = lumpSumAmount.value || 0;
  const principal = loanPrincipal.value;

  if ((extra <= 0 && lumpSum <= 0) || principal <= 0) {
    return {
      hasExtraPayment: false,
      yearsSaved: 0,
      monthsSaved: 0,
      interestSaved: 0,
      newTenorYears: tenorYears.value,
      newTenorMonths: 0
    };
  }

  const baseInstallment = monthlyInstallmentFixed.value;
  const monthlyRate = (annualInterest.value / 100) / 12;

  let currentBal = principal;
  let totalMonths = 0;
  let totalInterestPaidNew = 0;

  const lumpSumMonth = (lumpSumYear.value || 5) * 12;

  while (currentBal > 0 && totalMonths < 420) {
    totalMonths++;
    const interestOfMonth = currentBal * monthlyRate;
    totalInterestPaidNew += interestOfMonth;

    let paymentThisMonth = baseInstallment + extra;

    if (totalMonths === lumpSumMonth && lumpSum > 0) {
      paymentThisMonth += lumpSum;
    }

    const principalPaidThisMonth = paymentThisMonth - interestOfMonth;

    if (principalPaidThisMonth >= currentBal) {
      currentBal = 0;
    } else {
      currentBal -= principalPaidThisMonth;
    }
  }

  const totalInterestOld = (baseInstallment * tenorMonths.value) - principal;
  const interestSaved = Math.max(0, totalInterestOld - totalInterestPaidNew);

  const monthsSavedTotal = Math.max(0, tenorMonths.value - totalMonths);
  const yearsSaved = Math.floor(monthsSavedTotal / 12);
  const monthsSaved = monthsSavedTotal % 12;

  const newTenorYears = Math.floor(totalMonths / 12);
  const newTenorMonths = totalMonths % 12;

  return {
    hasExtraPayment: true,
    yearsSaved,
    monthsSaved,
    interestSaved,
    newTenorYears,
    newTenorMonths
  };
});

// FITUR 4: Reverse DSR / Affordability Result
const affordabilityResult = computed(() => {
  const income = affIncome.value || 0;
  const debts = affOtherDebt.value || 0;
  const targetDsrPct = affDsrTarget.value || 35;
  const dpPrepared = affDpReady.value || 0;
  const ratePct = affInterest.value || 5.0;
  const years = affTenor.value || 15;
  const totalM = years * 12;

  const maxTotalDebtCapacity = Math.round((income * targetDsrPct) / 100);
  const maxInstallment = Math.max(0, maxTotalDebtCapacity - debts);

  if (maxInstallment <= 0 || totalM <= 0) {
    return {
      maxInstallment: 0,
      maxLoan: 0,
      maxPropertyPrice: dpPrepared
    };
  }

  const r = (ratePct / 100) / 12;
  let maxLoan = 0;

  if (r === 0) {
    maxLoan = maxInstallment * totalM;
  } else {
    const factor = Math.pow(1 + r, totalM);
    maxLoan = Math.round((maxInstallment * (factor - 1)) / (r * factor));
  }

  const maxPropertyPrice = maxLoan + dpPrepared;

  return {
    maxInstallment,
    maxLoan,
    maxPropertyPrice
  };
});

// DSR score & advice
const dsrScore = computed(() => {
  if (monthlyIncome.value <= 0) return 0;
  return (monthlyInstallment.value / monthlyIncome.value) * 100;
});

const dsrAdvice = computed(() => {
  if (dsrScore.value <= 30) return '✅ Rasio DSR Sangat Ideal (<=30%). Peluang persetujuan KPR bank sangat tinggi!';
  if (dsrScore.value <= 50) return '⚡ Rasio DSR Moderat (30-50%). Disarankan menambah DP atau memperpanjang tenor agar lebih aman.';
  return '⚠️ Rasio DSR Tinggi (>50%). Berpotensi mendapat kendala persetujuan bank. Disarankan menggunakan gabungan penghasilan (joint income).';
});

// Amortization Schedule & Chart Data
const scheduleData = computed(() => {
  const principal = loanPrincipal.value;
  const totalM = tenorMonths.value;
  if (principal <= 0 || totalM <= 0) return [];

  const rows = [];
  let balance = principal;
  const rFixed = (annualInterest.value / 100) / 12;
  const rFloat = (floatingInterest.value / 100) / 12;
  const fixedM = actualFixedYears.value * 12;

  let baseInst = monthlyInstallmentFixed.value;

  for (let m = 1; m <= totalM; m++) {
    const isFixedPeriod = m <= fixedM;
    const currentRate = isFixedPeriod ? rFixed : rFloat;

    if (m === fixedM + 1 && kprType.value === 'conventional') {
      baseInst = monthlyInstallmentFloating.value;
    }

    const interestPart = Math.round(balance * currentRate);
    const principalPart = Math.min(balance, baseInst - interestPart);
    balance = Math.max(0, balance - principalPart);

    rows.push({
      month: m,
      year: Math.ceil(m / 12),
      installment: baseInst,
      interestPart,
      principalPart,
      remainingBalance: balance
    });
  }
  return rows;
});

// Yearly aggregated schedule
const yearlySchedule = computed(() => {
  const map = {};
  scheduleData.value.forEach(row => {
    if (!map[row.year]) {
      map[row.year] = {
        year: row.year,
        totalInstallment: 0,
        totalInterest: 0,
        totalPrincipal: 0,
        endingBalance: 0
      };
    }
    map[row.year].totalInstallment += row.installment;
    map[row.year].totalInterest += row.interestPart;
    map[row.year].totalPrincipal += row.principalPart;
    map[row.year].endingBalance = row.remainingBalance;
  });
  return Object.values(map);
});

// Chart.js Data
const chartData = computed(() => {
  const labels = yearlySchedule.value.map(y => `Thn ${y.year}`);
  const principalData = yearlySchedule.value.map(y => Math.round(y.totalPrincipal / 1000000));
  const interestData = yearlySchedule.value.map(y => Math.round(y.totalInterest / 1000000));

  return {
    labels,
    datasets: [
      {
        label: 'Pokok (Juta Rp)',
        backgroundColor: '#2563eb',
        data: principalData
      },
      {
        label: 'Bunga (Juta Rp)',
        backgroundColor: '#f59e0b',
        data: interestData
      }
    ]
  };
});

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'top' },
    tooltip: {
      callbacks: {
        label: (ctx) => `${ctx.dataset.label}: Rp ${ctx.raw} Juta`
      }
    }
  },
  scales: {
    x: { stacked: true },
    y: { stacked: true }
  }
};

// Utilities & Formatters
function formatRupiah(num) {
  if (!num || isNaN(num)) return 'Rp 0';
  return 'Rp ' + Math.round(num).toLocaleString('id-ID');
}

function formatRupiahSingkat(num) {
  if (!num || isNaN(num)) return 'Rp 0';
  if (num >= 1000000000) return `Rp ${(num / 1000000000).toFixed(1)} M`;
  if (num >= 1000000) return `Rp ${(num / 1000000).toFixed(0)} Jt`;
  if (num >= 1000) return `Rp ${(num / 1000).toFixed(0)} Rb`;
  return `Rp ${num}`;
}

function formatPriceSingkatNoRp(num) {
  if (!num || isNaN(num)) return '0';
  return Math.round(num).toLocaleString('id-ID');
}

const tenorDisplayText = computed(() => {
  const yrs = Math.floor(tenorMonths.value / 12);
  const mos = tenorMonths.value % 12;
  if (mos === 0) return `${yrs} Tahun`;
  return `${yrs} Thn ${mos} Bln`;
});

function scrollToResult() {
  const el = document.getElementById('kpr-result-card');
  if (el) el.scrollIntoView({ behavior: 'smooth' });
}

// WhatsApp Share
function shareToWhatsApp() {
  const selectedBankName = selectedBank.value ? selectedBank.value.name : 'Custom Bank';
  const text = `*SIMULASI KPR PINTAR HOMI DEVELOPER* 🏠\n\n` +
    `Harga Properti: *${formatRupiah(propertyPrice.value)}*\n` +
    `Uang Muka (DP ${dpPercentage.value}%): *${formatRupiah(dpAmount.value)}*\n` +
    `Plafond KPR: *${formatRupiah(loanPrincipal.value)}*\n` +
    `Tenor KPR: *${tenorDisplayText.value}*\n` +
    `Bank Partner: *${selectedBankName}*\n\n` +
    `Estimasi Cicilan Bulanan:\n` +
    `👉 *${formatRupiah(monthlyInstallment.value)} /bulan*\n` +
    (kprType.value === 'conventional' && tenorYears.value > actualFixedYears.value ? `   (Est. Floating: ${formatRupiah(monthlyInstallmentFloating.value)}/bln)\n` : '') +
    `\nEstimasi Total Dana Awal: *${formatRupiah(upfrontFees.value.total)}*\n\n` +
    `_Hitung simulasi KPR properti impian Anda bersama Homi Developer._`;

  const waUrl = `https://wa.me/?text=${encodeURIComponent(text)}`;
  window.open(waUrl, '_blank');
}

function printReport() {
  window.print();
}
</script>

<template>
  <Head title="Kalkulator KPR Pintar - Simulasi & Kelayakan KPR" />

  <CrmLayout>
    <template #breadcrumb>
      <span class="text-slate-700 font-bold">Kalkulator KPR Pintar</span>
    </template>

    <div class="space-y-6 pb-12">
      <!-- HERO BANNER -->
      <div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white rounded-3xl p-6 sm:p-8 shadow-2xl relative overflow-hidden border border-white/10">
        <div class="absolute -top-32 -right-32 w-80 h-80 bg-blue-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-32 -left-32 w-80 h-80 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 max-w-3xl space-y-3">
          <span class="inline-flex items-center gap-2 px-3 py-1 bg-white/10 backdrop-blur-md text-blue-300 text-[10px] font-black uppercase tracking-widest rounded-full border border-white/10">
            <span class="animate-pulse">✨</span> HOMI SMART CALCULATOR
          </span>
          <h1 class="text-2xl sm:text-4xl font-black tracking-tight leading-tight">
            Simulasi <span class="bg-gradient-to-r from-blue-400 to-indigo-300 bg-clip-text text-transparent">KPR Detail & Kelayakan</span>
          </h1>
          <p class="text-xs sm:text-sm text-slate-350 leading-relaxed font-medium">
            Hitung estimasi cicilan bulanan, kelayakan kredit (DSR), risiko payment shock, hingga proyeksi pelunasan dipercepat secara akurat.
          </p>
        </div>
      </div>

      <!-- MAIN LAYOUT (2 COLUMNS) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- LEFT PANEL (7 Columns): Inputs & Calculators -->
        <div class="lg:col-span-7 space-y-6">

          <!-- Mode Toggle -->
          <div class="bg-white p-2 rounded-2xl shadow-sm border border-slate-200/80 flex gap-2">
            <button @click="calculatorMode = 'simulasi'"
                    :class="calculatorMode === 'simulasi' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 hover:text-slate-900 bg-slate-50'"
                    class="flex-1 py-3 px-4 rounded-xl text-xs font-black uppercase tracking-wider transition-all cursor-pointer flex items-center justify-center gap-2">
              🏠 Simulasi KPR
            </button>
            <button @click="calculatorMode = 'kelayakan'"
                    :class="calculatorMode === 'kelayakan' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 hover:text-slate-900 bg-slate-50'"
                    class="flex-1 py-3 px-4 rounded-xl text-xs font-black uppercase tracking-wider transition-all cursor-pointer flex items-center justify-center gap-2">
              🔄 Kalkulator Kelayakan (Reverse)
            </button>
          </div>

          <!-- MODE 1: SIMULASI KPR -->
          <template v-if="calculatorMode === 'simulasi'">
            <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/80 space-y-6">
              <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h2 class="text-base font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                  📝 Isi Rencana KPR
                </h2>
                <span class="text-[10px] font-black text-blue-600 bg-blue-50 px-2.5 py-1 rounded-lg uppercase">Standard Mode</span>
              </div>

              <div class="space-y-5">
                <!-- Harga Properti -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-1">
                  <label class="text-sm font-bold text-slate-700">Harga Properti <span class="text-rose-500">*</span></label>
                  <div class="flex items-center border border-slate-200 rounded-xl overflow-hidden bg-white w-full sm:w-64 focus-within:ring-2 focus-within:ring-blue-500/20 focus-within:border-blue-500 transition-all">
                    <span class="bg-slate-50 border-r border-slate-200 px-3 py-2.5 text-slate-500 text-xs font-bold">Rp</span>
                    <input v-if="isPriceFocused" 
                           v-model.number="propertyPrice" 
                           type="number" 
                           @blur="isPriceFocused = false" 
                           @focus="dpInputMode = 'percent'"
                           class="w-full text-right px-3 py-2.5 text-xs font-black text-slate-800 outline-none border-0 bg-transparent">
                    <input v-else 
                           :value="formatPriceSingkatNoRp(propertyPrice)" 
                           type="text" 
                           @focus="isPriceFocused = true; dpInputMode = 'percent'"
                           class="w-full text-right px-3 py-2.5 text-xs font-black text-slate-800 outline-none border-0 bg-transparent cursor-pointer">
                  </div>
                </div>

                <!-- Uang Muka (DP) -->
                <div class="space-y-2">
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-1">
                    <label class="text-sm font-bold text-slate-700">Uang Muka (DP)</label>
                    <div class="flex items-center gap-2">
                      <div class="flex items-center border border-slate-200 rounded-xl overflow-hidden bg-white w-20 focus-within:ring-2 focus-within:ring-blue-500/20 focus-within:border-blue-500 transition-all">
                        <input v-model.number="dpPercentage" 
                               type="number" 
                               min="0" 
                               max="90"
                               @focus="dpInputMode = 'percent'"
                               class="w-full text-center py-2 px-1 text-xs font-bold text-slate-800 outline-none border-0 bg-transparent">
                        <span class="bg-slate-50 border-l border-slate-200 px-2 py-2 text-slate-500 text-xs font-bold">%</span>
                      </div>
                      
                      <div class="flex items-center border border-slate-200 rounded-xl overflow-hidden bg-white w-36 sm:w-44 focus-within:ring-2 focus-within:ring-blue-500/20 focus-within:border-blue-500 transition-all">
                        <span class="bg-slate-50 border-r border-slate-200 px-2.5 py-2 text-slate-500 text-xs font-bold">Rp</span>
                        <input v-if="isDpAmountFocused" 
                               v-model.number="dpAmount" 
                               type="number" 
                               @blur="isDpAmountFocused = false" 
                               @focus="dpInputMode = 'nominal'"
                               class="w-full text-right px-2.5 py-2 text-xs font-black text-slate-800 outline-none border-0 bg-transparent">
                        <input v-else 
                               :value="formatPriceSingkatNoRp(dpAmount)" 
                               type="text" 
                               @focus="isDpAmountFocused = true; dpInputMode = 'nominal'"
                               class="w-full text-right px-2.5 py-2 text-xs font-black text-slate-800 outline-none border-0 bg-transparent cursor-pointer">
                      </div>
                    </div>
                  </div>
                  <input v-model.number="dpPercentage" type="range" min="0" max="90" step="1" @input="dpInputMode = 'percent'" class="w-full h-1.5 bg-slate-100 rounded-full appearance-none cursor-pointer accent-blue-600">
                </div>

                <!-- Gunakan Program Bank Toggle & Selector -->
                <div class="space-y-3 pt-3 border-t border-slate-100">
                  <div class="flex items-center justify-between">
                    <div>
                      <span class="block text-xs font-bold text-slate-700">Gunakan Program Bank Partner</span>
                      <span class="block text-[10px] font-medium text-slate-400 mt-0.5">Gunakan promo suku bunga resmi dari bank terdaftar</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer select-none">
                      <input v-model="useBankProgram" type="checkbox" class="sr-only peer">
                      <div class="w-10 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                  </div>

                  <div v-if="useBankProgram" class="space-y-2">
                    <div v-if="selectedBank" class="flex items-center justify-between bg-blue-50/60 p-3.5 rounded-2xl border border-blue-200 shadow-sm">
                      <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl overflow-hidden flex items-center justify-center flex-shrink-0 bg-white border border-slate-200">
                          <img v-if="selectedBank.logo" :src="selectedBank.logo" class="w-full h-full object-contain p-1" alt="Logo Bank" />
                          <span v-else class="text-white text-[9px] font-black bg-blue-600 rounded px-1.5 py-0.5 uppercase flex items-center justify-center w-full h-full">{{ (selectedBank.bank_name || selectedBank.name || 'BANK').substring(0, 4) }}</span>
                        </div>
                        <div>
                          <h4 class="text-xs font-black text-slate-900 leading-tight">{{ selectedBank.program_name || selectedBank.name }}</h4>
                          <p class="text-[10px] font-bold text-blue-600 mt-0.5">{{ selectedBank.description || 'Program Bank Partner' }}</p>
                        </div>
                      </div>
                      <button @click="showProgramModal = true" class="px-3 py-1.5 bg-white hover:bg-slate-50 text-[10px] font-black text-blue-600 rounded-xl border border-slate-200 shadow-sm transition-all cursor-pointer">
                        Ubah Bank
                      </button>
                    </div>
                    <div v-else class="flex items-center justify-between bg-slate-50 p-3.5 rounded-2xl border border-slate-200 shadow-sm">
                      <span class="text-xs font-medium text-slate-400 italic">Pilih bank partner untuk memulai simulasi program</span>
                      <button @click="showProgramModal = true" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase tracking-wider rounded-xl transition-all cursor-pointer">
                        Pilih Bank Partner ▼
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Pilihan Suku Bunga (Rate) -->
                <div class="space-y-2 pt-3 border-t border-slate-100">
                  <label class="block text-xs font-bold text-slate-700">Pilihan Skema Suku Bunga (Rate)</label>
                  <div class="flex flex-wrap gap-2">
                    <button @click="sukuBungaProduct = 'flat'" 
                            :class="sukuBungaProduct === 'flat' ? 'bg-blue-600 text-white shadow-sm border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                            class="py-2 px-4 rounded-xl border text-xs font-bold transition-all cursor-pointer">
                      Flat (Fix All Tenor / Syariah)
                    </button>
                    <button @click="sukuBungaProduct = 'tiered'" 
                            :class="sukuBungaProduct === 'tiered' ? 'bg-blue-600 text-white shadow-sm border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                            class="py-2 px-4 rounded-xl border text-xs font-bold transition-all cursor-pointer">
                      Fix Berjenjang
                    </button>
                    <button @click="sukuBungaProduct = 'floating'" 
                            :class="sukuBungaProduct === 'floating' ? 'bg-blue-600 text-white shadow-sm border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                            class="py-2 px-4 rounded-xl border text-xs font-bold transition-all cursor-pointer">
                      Fix Floating
                    </button>
                  </div>
                </div>

                <!-- Rate parameters depending on selection -->
                <div v-if="kprType === 'conventional'" class="space-y-3 pt-3 border-t border-slate-100">
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <label class="text-sm font-bold text-slate-700">Suku Bunga Fixed Asumsi</label>
                    <div class="relative flex items-center border border-slate-200 rounded-xl overflow-hidden w-28 bg-white">
                      <input v-model.number="annualInterest" :disabled="useBankProgram" type="number" step="0.1" 
                             :class="useBankProgram ? 'text-slate-400 cursor-not-allowed bg-slate-50' : 'text-slate-800'"
                             class="w-full text-center py-2 px-1 text-xs font-bold outline-none border-0 bg-transparent">
                      <span class="bg-slate-50 border-l border-slate-200 px-2 py-2 text-slate-500 text-xs font-bold">%</span>
                    </div>
                  </div>
                  <div v-if="sukuBungaProduct !== 'flat'" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <label class="text-sm font-bold text-slate-700">Masa Kredit Fixed</label>
                    <div class="relative flex items-center border border-slate-200 rounded-xl overflow-hidden w-28 bg-white">
                      <input v-model.number="fixedDuration" :disabled="useBankProgram" type="number" min="1" :max="tenorYears" 
                             :class="useBankProgram ? 'text-slate-400 cursor-not-allowed bg-slate-50' : 'text-slate-800'"
                             class="w-full text-center py-2 px-1 text-xs font-bold outline-none border-0 bg-transparent">
                      <span class="bg-slate-50 border-l border-slate-200 px-2.5 py-2 text-slate-500 text-xs font-bold">Thn</span>
                    </div>
                  </div>
                  <div v-if="sukuBungaProduct !== 'flat'" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <label class="text-sm font-bold text-slate-700">Suku Bunga Floating Asumsi</label>
                    <div class="relative flex items-center border border-slate-200 rounded-xl overflow-hidden w-28 bg-white">
                      <input v-model.number="floatingInterest" :disabled="useBankProgram" type="number" step="0.1" 
                             :class="useBankProgram ? 'text-slate-400 cursor-not-allowed bg-slate-50' : 'text-slate-800'"
                             class="w-full text-center py-2 px-1 text-xs font-bold outline-none border-0 bg-transparent">
                      <span class="bg-slate-50 border-l border-slate-200 px-2 py-2 text-slate-500 text-xs font-bold">%</span>
                    </div>
                  </div>
                </div>

                <!-- Syariah Flat Margin -->
                <div v-else-if="kprType === 'syariah'" class="space-y-3 pt-3 border-t border-slate-100">
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <label class="text-sm font-bold text-slate-700">Margin Keuntungan Flat</label>
                    <div class="relative flex items-center border border-slate-200 rounded-xl overflow-hidden w-28 bg-white">
                      <input v-model.number="annualInterest" :disabled="useBankProgram" type="number" step="0.1" 
                             :class="useBankProgram ? 'text-slate-400 cursor-not-allowed bg-slate-50' : 'text-slate-800'"
                             class="w-full text-center py-2 px-1 text-xs font-bold outline-none border-0 bg-transparent">
                      <span class="bg-slate-50 border-l border-slate-200 px-2 py-2 text-slate-500 text-xs font-bold">%</span>
                    </div>
                  </div>
                </div>

                <!-- Tiered (Berjenjang) Config -->
                <div v-else class="space-y-3 pt-3 border-t border-slate-100 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                  <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-600 flex items-center gap-1">⚙️ Konfigurasi Bunga Berjenjang</span>
                    <span class="text-[9px] font-black bg-blue-100 text-blue-700 px-2.5 py-0.5 rounded uppercase">{{ numTiers }} Tahapan Bunga</span>
                  </div>

                  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-1.5">
                    <span class="text-xs font-bold text-slate-600">Jumlah Tahapan Bunga</span>
                    <div class="flex items-center gap-2">
                      <button :disabled="useBankProgram" @click="numTiers = Math.max(1, numTiers - 1)" 
                              :class="useBankProgram ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-100 cursor-pointer'"
                              class="w-7 h-7 rounded-lg bg-white border border-slate-200 flex items-center justify-center font-black text-slate-600 shadow-smselect-none">-</button>
                      <span class="text-xs font-black text-slate-900 px-1">{{ numTiers }} Tahap</span>
                      <button :disabled="useBankProgram" @click="numTiers = Math.min(10, numTiers + 1)" 
                              :class="useBankProgram ? 'opacity-40 cursor-not-allowed' : 'hover:bg-slate-100 cursor-pointer'"
                              class="w-7 h-7 rounded-lg bg-white border border-slate-200 flex items-center justify-center font-black text-slate-600 shadow-sm select-none">+</button>
                    </div>
                  </div>

                  <div class="space-y-2 pb-2">
                    <div v-for="idx in numTiers" :key="'tahap-input-'+(idx - 1)" class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                      <span class="text-xs font-black text-slate-700">Tahap {{ idx }}</span>
                      
                      <div class="flex items-center gap-2">
                        <div class="relative flex items-center border border-slate-200 rounded-lg overflow-hidden w-24 bg-white">
                          <input v-model.number="tiers[idx - 1].rate" :disabled="useBankProgram" type="number" step="0.01" 
                                 :class="useBankProgram ? 'text-slate-400 cursor-not-allowed bg-slate-50' : 'text-slate-800'"
                                 class="w-full text-center py-1.5 px-1 text-xs font-bold outline-none border-0 bg-transparent">
                          <span class="bg-slate-50 border-l border-slate-200 px-2 py-1.5 text-slate-500 text-xs font-bold">%</span>
                        </div>
                        
                        <div class="relative flex items-center border border-slate-200 rounded-lg overflow-hidden w-28 bg-white">
                          <input v-if="idx < numTiers" v-model.number="tiers[idx - 1].years" :disabled="useBankProgram" type="number" min="1" 
                                 :class="useBankProgram ? 'text-slate-400 cursor-not-allowed bg-slate-50' : 'text-slate-800'"
                                 class="w-full text-center py-1.5 px-1 text-xs font-bold outline-none border-0 bg-transparent">
                          <div v-else class="w-full text-center py-1.5 px-1 text-xs font-bold text-slate-400 bg-slate-100 flex items-center justify-center">
                            {{ adjustedTiers[idx - 1]?.years || 0 }}
                          </div>
                          <span class="bg-slate-50 border-l border-slate-200 px-2 py-1.5 text-slate-500 text-xs font-bold">Thn</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Tenor KPR -->
                <div class="space-y-2 pt-3 border-t border-slate-100">
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-1">
                    <div>
                      <label class="text-sm font-bold text-slate-700">Jangka Waktu KPR (Tenor)</label>
                      <span class="block text-[10px] font-bold text-blue-600 mt-0.5">{{ tenorDisplayText }}</span>
                    </div>
                    <div class="relative flex items-center border border-slate-200 rounded-xl overflow-hidden bg-white w-28">
                      <input v-model.number="tenorMonths" type="number" min="12" max="420" class="w-full text-center py-2 px-1 text-xs font-bold text-slate-800 outline-none border-0 bg-transparent">
                      <span class="bg-slate-50 border-l border-slate-200 px-2.5 py-2 text-slate-500 text-xs font-bold">Bln</span>
                    </div>
                  </div>
                  
                  <div class="flex flex-wrap gap-1.5">
                    <button v-for="yr in [5, 10, 15, 20, 25, 30]" :key="yr" @click="tenorMonths = yr * 12"
                            :class="tenorMonths === yr * 12 ? 'bg-blue-600 text-white shadow-sm border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                            class="py-1 px-3 rounded-lg border text-[10px] font-bold transition-all cursor-pointer">
                      {{ yr * 12 }} bln ({{ yr }} thn)
                    </button>
                  </div>
                  <input v-model.number="tenorMonths" type="range" min="12" max="420" step="1" class="w-full h-1.5 bg-slate-100 rounded-full appearance-none cursor-pointer accent-blue-600">
                </div>

                <div class="pt-4 border-t border-slate-100">
                  <button @click="scrollToResult" class="w-full bg-blue-600 text-white py-3.5 rounded-xl font-extrabold text-xs uppercase tracking-wider hover:bg-blue-700 shadow-lg shadow-blue-600/20 transition-all cursor-pointer text-center">
                    Scroll Ke Hasil Simulasi ↓
                  </button>
                </div>

              </div>
            </div>

            <!-- FITUR 2: PAYMENT SHOCK SIMULATOR -->
            <div v-if="kprType === 'conventional' && tenorYears > actualFixedYears" 
                 class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/80 space-y-5 relative overflow-hidden">
              <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                  <span class="w-7 h-7 rounded-lg bg-amber-500 text-white flex items-center justify-center text-xs shadow-sm">⚡</span>
                  Payment Shock Simulator
                </h2>
                <span class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-wider" :class="paymentShockClass">
                  {{ paymentShockLabel }}
                </span>
              </div>

              <!-- Timeline Bar -->
              <div class="space-y-2">
                <div class="flex items-center gap-1 h-9 rounded-xl overflow-hidden">
                  <div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-400 rounded-l-xl flex items-center justify-center px-3 transition-all duration-500"
                       :style="{ width: fixedWidthPercent + '%' }">
                    <span class="text-[9px] font-black text-white uppercase tracking-wider whitespace-nowrap">Fixed {{ actualFixedYears }} Thn</span>
                  </div>
                  <div class="h-full bg-gradient-to-r from-rose-500 to-rose-400 rounded-r-xl flex items-center justify-center px-3 flex-1 transition-all duration-500">
                    <span class="text-[9px] font-black text-white uppercase tracking-wider whitespace-nowrap">Floating {{ tenorYears - actualFixedYears }} Thn</span>
                  </div>
                </div>
                <div class="flex justify-between text-[9px] font-bold text-slate-400">
                  <span>Tahun 1</span>
                  <span>Tahun {{ actualFixedYears }} → {{ actualFixedYears + 1 }}</span>
                  <span>Tahun {{ tenorYears }}</span>
                </div>
              </div>

              <!-- Comparison Cards -->
              <div class="grid grid-cols-2 gap-4">
                <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-4 space-y-1">
                  <span class="text-[9px] font-black text-emerald-600 uppercase tracking-wider">Cicilan Masa Fixed</span>
                  <div class="text-lg font-black text-emerald-700 tracking-tight">{{ formatRupiah(monthlyInstallmentFixed) }}</div>
                  <span class="text-[10px] font-bold text-emerald-500">Tahun 1 - {{ actualFixedYears }}</span>
                </div>
                <div class="bg-rose-50 border border-rose-100 rounded-2xl p-4 space-y-1">
                  <span class="text-[9px] font-black text-rose-600 uppercase tracking-wider">Cicilan Masa Floating</span>
                  <div class="text-lg font-black text-rose-700 tracking-tight">{{ formatRupiah(monthlyInstallmentFloating) }}</div>
                  <span class="text-[10px] font-bold text-rose-500">Tahun {{ actualFixedYears + 1 }} - {{ tenorYears }}</span>
                </div>
              </div>

              <!-- Shock Alert -->
              <div class="p-4 rounded-2xl border flex items-start gap-3"
                   :class="paymentShockPercent > 50 ? 'bg-rose-50 border-rose-200' : paymentShockPercent > 20 ? 'bg-amber-50 border-amber-200' : 'bg-emerald-50 border-emerald-200'">
                <span class="text-xl flex-shrink-0 mt-0.5">⚡</span>
                <div>
                  <p class="text-xs font-black" :class="paymentShockPercent > 50 ? 'text-rose-700' : paymentShockPercent > 20 ? 'text-amber-700' : 'text-emerald-700'">
                    Kenaikan Cicilan +{{ paymentShockPercent.toFixed(1) }}% di Tahun ke-{{ actualFixedYears + 1 }}!
                  </p>
                  <p class="text-[10px] font-medium mt-1" :class="paymentShockPercent > 50 ? 'text-rose-600' : paymentShockPercent > 20 ? 'text-amber-600' : 'text-emerald-600'">
                    Cicilan akan naik sebesar <span class="font-black">{{ formatRupiah(monthlyInstallmentFloating - monthlyInstallmentFixed) }}/bulan</span> ketika masa fixed berakhir.
                  </p>
                </div>
              </div>
            </div>

            <!-- FITUR 3: PELUNASAN DIPERCEPAT (EXTRA PAYMENT) -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200/80 overflow-hidden">
              <button @click="showExtraPayment = !showExtraPayment" 
                      class="w-full p-6 flex items-center justify-between cursor-pointer hover:bg-slate-50 transition-colors">
                <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-3">
                  <span class="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-xs shadow-sm">⏱️</span>
                  Simulasi Pelunasan Dipercepat (Extra Payment)
                </h2>
                <span class="text-base font-bold text-slate-400 transition-transform duration-300" :class="showExtraPayment ? 'rotate-180' : ''">▼</span>
              </button>

              <div v-if="showExtraPayment" class="px-6 pb-6 space-y-5 border-t border-slate-100 pt-5">
                <div class="space-y-3">
                  <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500">Tambahan Cicilan Bulanan</label>
                  <div class="relative flex items-center">
                    <span class="absolute left-4 font-black text-slate-400 text-sm">Rp</span>
                    <input v-model.number="extraMonthlyPayment" type="number" 
                           class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-black text-slate-900 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none"
                           placeholder="0">
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <button v-for="amt in [500000, 1000000, 2000000, 5000000]" :key="'extra-'+amt" 
                            @click="extraMonthlyPayment = amt"
                            :class="extraMonthlyPayment === amt ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                            class="px-3 py-1.5 border rounded-xl text-[10px] font-black transition-all cursor-pointer">
                      +{{ formatRupiahSingkat(amt) }}
                    </button>
                  </div>
                </div>

                <div class="space-y-3">
                  <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500">Pelunasan Sekaligus (Lump-sum)</label>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="relative flex items-center">
                      <span class="absolute left-4 font-black text-slate-400 text-sm">Rp</span>
                      <input v-model.number="lumpSumAmount" type="number" 
                             class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-black text-slate-900 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none"
                             placeholder="50000000">
                    </div>
                    <div class="relative flex items-center">
                      <input v-model.number="lumpSumYear" type="number" min="1" :max="tenorYears" 
                             class="w-full pr-20 pl-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-black text-slate-900 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none">
                      <span class="absolute right-4 font-black text-slate-400 text-xs">Tahun ke-</span>
                    </div>
                  </div>
                </div>

                <div v-if="earlyPayoffResult.hasExtraPayment" class="space-y-4 pt-2">
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-4 text-center space-y-1">
                      <span class="text-2xl">⏰</span>
                      <div class="text-base font-black text-emerald-700">{{ earlyPayoffResult.yearsSaved }} Thn {{ earlyPayoffResult.monthsSaved }} Bln</div>
                      <span class="text-[9px] font-black text-emerald-600 uppercase tracking-wider">Lebih Cepat Lunas</span>
                    </div>
                    <div class="bg-sky-50 border border-sky-100 rounded-2xl p-4 text-center space-y-1">
                      <span class="text-2xl">💰</span>
                      <div class="text-base font-black text-sky-700">{{ formatRupiahSingkat(earlyPayoffResult.interestSaved) }}</div>
                      <span class="text-[9px] font-black text-sky-600 uppercase tracking-wider">Hemat Bunga</span>
                    </div>
                    <div class="bg-purple-50 border border-purple-100 rounded-2xl p-4 text-center space-y-1">
                      <span class="text-2xl">📅</span>
                      <div class="text-base font-black text-purple-700">{{ earlyPayoffResult.newTenorYears }} Thn {{ earlyPayoffResult.newTenorMonths }} Bln</div>
                      <span class="text-[9px] font-black text-purple-600 uppercase tracking-wider">Tenor Baru</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- MODE 2: KALKULATOR KELAYAKAN (REVERSE DSR) -->
          <template v-else>
            <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/80 space-y-6">
              <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                  🔄 Kalkulator Kelayakan KPR
                </h2>
                <span class="text-[10px] font-black text-purple-600 bg-purple-50 px-2.5 py-1 rounded-lg">Reverse DSR</span>
              </div>

              <p class="text-xs font-medium text-slate-500 leading-relaxed">
                Masukkan informasi penghasilan & cicilan Anda saat ini untuk menghitung batas harga rumah maksimal yang sanggup Anda beli.
              </p>

              <div class="space-y-5">
                <div class="space-y-2">
                  <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500">Gaji Bersih Bulanan (Keluarga)</label>
                  <div class="relative flex items-center">
                    <span class="absolute left-4 font-black text-slate-400 text-sm">Rp</span>
                    <input v-model.number="affIncome" type="number" 
                           class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-black text-slate-900 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none"
                           placeholder="15000000">
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <button v-for="inc in [10000000, 15000000, 25000000, 50000000]" :key="'inc-'+inc" 
                            @click="affIncome = inc"
                            class="px-3 py-1.5 bg-slate-50 hover:bg-purple-50 hover:text-purple-600 border border-slate-200 rounded-xl text-[10px] font-black transition-all cursor-pointer">
                      {{ formatRupiahSingkat(inc) }}
                    </button>
                  </div>
                </div>

                <div class="space-y-2">
                  <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500">Cicilan Bulanan Lain (Mobil, KTA, Kartu Kredit)</label>
                  <div class="relative flex items-center">
                    <span class="absolute left-4 font-black text-slate-400 text-sm">Rp</span>
                    <input v-model.number="affOtherDebt" type="number" 
                           class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-black text-slate-900 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none"
                           placeholder="0">
                  </div>
                </div>

                <div class="space-y-2">
                  <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500">Uang Muka (DP) yang Disiapkan</label>
                  <div class="relative flex items-center">
                    <span class="absolute left-4 font-black text-slate-400 text-sm">Rp</span>
                    <input v-model.number="affDpReady" type="number" 
                           class="w-full pl-11 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-black text-slate-900 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none"
                           placeholder="100000000">
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div class="space-y-2">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500">Suku Bunga Asumsi</label>
                    <div class="relative flex items-center">
                      <input v-model.number="affInterest" type="number" step="0.1" 
                             class="w-full pr-10 pl-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-black text-slate-900 focus:bg-white outline-none">
                      <span class="absolute right-4 font-black text-slate-400 text-xs">%</span>
                    </div>
                  </div>
                  <div class="space-y-2">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500">Tenor KPR</label>
                    <div class="relative flex items-center">
                      <input v-model.number="affTenor" type="number" 
                             class="w-full pr-14 pl-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-black text-slate-900 focus:bg-white outline-none">
                      <span class="absolute right-4 font-black text-slate-400 text-xs">Tahun</span>
                    </div>
                  </div>
                </div>

                <div class="space-y-2">
                  <div class="flex justify-between items-center">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500">Target DSR (Debt Service Ratio)</label>
                    <span class="text-xs font-black" :class="affDsrTarget <= 30 ? 'text-emerald-600' : affDsrTarget <= 50 ? 'text-amber-600' : 'text-rose-600'">{{ affDsrTarget }}%</span>
                  </div>
                  <input v-model.number="affDsrTarget" type="range" min="20" max="60" step="5" class="w-full h-1.5 bg-slate-100 rounded-full appearance-none cursor-pointer accent-purple-600">
                  <div class="flex justify-between text-[9px] font-bold text-slate-400">
                    <span>20% (Aman)</span>
                    <span>35% (Standar Bank)</span>
                    <span>60% (Risiko)</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Affordability Result Card -->
            <div class="bg-gradient-to-br from-purple-700 via-indigo-800 to-slate-900 text-white p-6 sm:p-8 rounded-3xl shadow-xl space-y-6">
              <div class="text-center space-y-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-purple-200">Harga Rumah Maksimal Untuk Anda</span>
                <div class="text-3xl sm:text-5xl font-black tracking-tight">{{ formatRupiah(affordabilityResult.maxPropertyPrice) }}</div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 text-center border border-white/10">
                  <span class="text-[9px] font-black text-purple-200 uppercase tracking-widest">Cicilan Maksimal</span>
                  <div class="text-sm font-black text-white mt-1">{{ formatRupiah(affordabilityResult.maxInstallment) }}/bln</div>
                </div>
                <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 text-center border border-white/10">
                  <span class="text-[9px] font-black text-purple-200 uppercase tracking-widest">Maks Plafond KPR</span>
                  <div class="text-sm font-black text-white mt-1">{{ formatRupiah(affordabilityResult.maxLoan) }}</div>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- RIGHT PANEL (5 Columns): Results & Tabs -->
        <div class="lg:col-span-5 space-y-6">
          <div id="kpr-result-card" class="bg-white rounded-3xl shadow-sm border border-slate-200/80 overflow-hidden flex flex-col min-h-[560px]">
            
            <!-- Tabs Header -->
            <div class="flex border-b border-slate-100 bg-slate-50/50">
              <button @click="activeTab = 'angsuran'" 
                      :class="activeTab === 'angsuran' ? 'border-b-2 border-blue-600 text-slate-900 font-black bg-white' : 'text-slate-500 font-bold hover:text-slate-800'"
                      class="flex-1 py-4 text-[10px] uppercase tracking-wider transition-all cursor-pointer">
                💵 Angsuran
              </button>
              <button @click="activeTab = 'insight'" 
                      :class="activeTab === 'insight' ? 'border-b-2 border-blue-600 text-slate-900 font-black bg-white' : 'text-slate-500 font-bold hover:text-slate-800'"
                      class="flex-1 py-4 text-[10px] uppercase tracking-wider transition-all cursor-pointer">
                📊 Insight & Promo
              </button>
              <button @click="activeTab = 'amortisasi'" 
                      :class="activeTab === 'amortisasi' ? 'border-b-2 border-blue-600 text-slate-900 font-black bg-white' : 'text-slate-500 font-bold hover:text-slate-800'"
                      class="flex-1 py-4 text-[10px] uppercase tracking-wider transition-all cursor-pointer">
                📈 Amortisasi
              </button>
            </div>

            <!-- TAB 1: ANGSURAN -->
            <div v-if="activeTab === 'angsuran'" class="p-6 sm:p-8 flex-grow flex flex-col justify-between space-y-6">
              <div class="space-y-5">
                <div class="flex items-center gap-2">
                  <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                  <span class="text-[9px] font-black uppercase tracking-widest text-slate-500">Estimasi Cicilan Bulanan</span>
                </div>

                <div v-if="kprType === 'conventional'" class="space-y-3">
                  <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-600 block mb-1">Masa Fixed (Thn 1-{{ actualFixedYears }})</span>
                    <div class="text-3xl font-black tracking-tight text-slate-900">{{ formatRupiah(monthlyInstallmentFixed) }}<span class="text-xs font-bold text-slate-400"> /bln</span></div>
                  </div>
                  <div v-if="tenorYears > actualFixedYears" class="pt-2 border-t border-slate-100">
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-600 block mb-1">Estimasi Floating (Thn {{ actualFixedYears + 1 }}-{{ tenorYears }})</span>
                    <div class="text-2xl font-black tracking-tight text-slate-700">{{ formatRupiah(monthlyInstallmentFloating) }}<span class="text-xs font-bold text-slate-400"> /bln</span></div>
                  </div>
                </div>

                <div v-else-if="kprType === 'tiered'" class="space-y-3">
                  <span class="text-xs font-black text-slate-800 uppercase tracking-wider block">Angsuran Berjenjang</span>
                  <div v-for="(tier, idx) in dynamicTieredInstallments" :key="'tier-'+idx" class="bg-blue-50 border border-blue-100 p-3.5 rounded-2xl flex justify-between items-center">
                    <div>
                      <span class="block text-[10px] font-bold text-blue-700">Tahun {{ tier.startYear }} - {{ tier.endYear }} (Bunga {{ tier.rate }}%)</span>
                    </div>
                    <span class="text-sm font-black text-blue-900">{{ formatRupiah(tier.installment) }}</span>
                  </div>
                </div>

                <div v-else>
                  <span class="text-[10px] font-black uppercase tracking-wider text-emerald-600 block mb-1">Cicilan Flat (Hingga Lunas)</span>
                  <div class="text-3xl font-black tracking-tight text-slate-900">{{ formatRupiah(monthlyInstallmentSyariah) }}<span class="text-xs font-bold text-slate-400"> /bln</span></div>
                </div>
              </div>

              <!-- Ringkasan Dana Awal -->
              <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-4 space-y-3">
                <div class="flex justify-between items-center">
                  <span class="text-xs font-black text-slate-700">Dana Awal Disiapkan (DP + Biaya)</span>
                  <span class="text-sm font-black text-blue-600">{{ formatRupiah(firstPaymentEstimate) }}</span>
                </div>
                <div class="space-y-1.5 text-xs text-slate-500 font-medium">
                  <div class="flex justify-between">
                    <span>Uang Muka (DP {{ dpPercentage }}%)</span>
                    <span class="font-bold text-slate-800">{{ formatRupiah(upfrontFees.dpEffective) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span>Legalitas & Pajak (BPHTB 5% + Notaris 1%)</span>
                    <span class="font-bold text-slate-800">{{ formatRupiah(upfrontFees.bphtb + upfrontFees.notaris) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span>Biaya KPR & Asuransi (~3.5%)</span>
                    <span class="font-bold text-slate-800">{{ formatRupiah(upfrontFees.provisi + upfrontFees.adm + upfrontFees.notarisKpr + upfrontFees.asuransi) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span>PPN 12%</span>
                    <span class="font-bold text-slate-800">{{ formatRupiah(upfrontFees.ppn) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span>Blokir 1x Angsuran</span>
                    <span class="font-bold text-slate-800">{{ formatRupiah(upfrontFees.blockedInstallment) }}</span>
                  </div>
                </div>
              </div>

              <!-- Share Buttons -->
              <div class="grid grid-cols-2 gap-3 pt-4 border-t border-slate-100">
                <button @click="shareToWhatsApp" class="py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs uppercase tracking-wider rounded-xl transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer">
                  💬 Share WA
                </button>
                <button @click="printReport" class="py-3 px-4 bg-white text-blue-600 border border-blue-600 hover:bg-blue-50 font-black text-xs uppercase tracking-wider rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer">
                  🖨️ Cetak PDF
                </button>
              </div>
            </div>

            <!-- TAB 2: INSIGHT & PROMO -->
            <div v-else-if="activeTab === 'insight'" class="p-6 sm:p-8 flex-grow space-y-6">
              <!-- DSR Meter -->
              <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-3">
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500">Gaji Bersih Bulanan (Rp)</label>
                <div class="relative flex items-center">
                  <span class="absolute left-3 text-slate-400 text-xs font-bold">Rp</span>
                  <input v-model.number="monthlyIncome" type="number" class="w-full bg-white border border-slate-200 rounded-xl py-2 pl-9 pr-3 text-xs font-black text-slate-800 outline-none">
                </div>

                <div class="flex justify-between items-center text-xs font-bold pt-1">
                  <span class="text-slate-500">Debt Service Ratio (DSR)</span>
                  <span :class="dsrScore <= 30 ? 'text-emerald-600' : dsrScore <= 50 ? 'text-amber-600' : 'text-rose-600'" class="font-black text-sm">{{ dsrScore.toFixed(1) }}%</span>
                </div>
                <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                  <div class="h-full rounded-full transition-all duration-300"
                       :class="dsrScore <= 30 ? 'bg-emerald-500' : dsrScore <= 50 ? 'bg-amber-500' : 'bg-rose-500'"
                       :style="{ width: Math.min(100, dsrScore) + '%' }"></div>
                </div>
                <div class="text-[10px] font-semibold leading-relaxed p-3 rounded-xl border"
                     :class="dsrScore <= 30 ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : dsrScore <= 50 ? 'text-amber-700 bg-amber-50 border-amber-200' : 'text-rose-700 bg-rose-50 border-rose-200'">
                  {{ dsrAdvice }}
                </div>
              </div>

              <!-- Promo Toggles -->
              <div class="space-y-3">
                <span class="text-xs font-black text-slate-800 uppercase tracking-wider block">🎁 Promo Developer (Simulasi Potongan)</span>
                <div class="space-y-2">
                  <label class="flex items-center gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200 cursor-pointer">
                    <input v-model="promoBphtb" type="checkbox" class="w-4 h-4 rounded text-blue-600 border-slate-300">
                    <span class="text-xs font-bold text-slate-700">Free BPHTB (Bebas Pajak Pembelian)</span>
                  </label>
                  <label class="flex items-center gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200 cursor-pointer">
                    <input v-model="promoNotaris" type="checkbox" class="w-4 h-4 rounded text-blue-600 border-slate-300">
                    <span class="text-xs font-bold text-slate-700">Free AJB & Notaris</span>
                  </label>
                  <label class="flex items-center gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200 cursor-pointer">
                    <input v-model="promoFreeKpr" type="checkbox" class="w-4 h-4 rounded text-blue-600 border-slate-300">
                    <span class="text-xs font-bold text-slate-700">Free Biaya KPR & Asuransi</span>
                  </label>
                  <label class="flex items-center gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200 cursor-pointer">
                    <input v-model="promoSubsidiDp" type="checkbox" class="w-4 h-4 rounded text-blue-600 border-slate-300">
                    <span class="text-xs font-bold text-slate-700">Subsidi DP 100% (DP 0%)</span>
                  </label>
                </div>
              </div>
            </div>

            <!-- TAB 3: AMORTISASI -->
            <div v-else-if="activeTab === 'amortisasi'" class="p-6 sm:p-8 flex-grow space-y-6">
              <span class="text-xs font-black text-slate-800 uppercase tracking-wider block">📈 Visualisasi & Tabel Amortisasi</span>
              
              <!-- Chart Container -->
              <div class="h-56 w-full bg-slate-50 p-3 rounded-2xl border border-slate-200">
                <Bar :data="chartData" :options="chartOptions" />
              </div>

              <!-- Table Preview -->
              <div class="max-h-64 overflow-y-auto border border-slate-200 rounded-2xl">
                <table class="w-full text-xs">
                  <thead class="bg-slate-50 text-slate-500 font-bold sticky top-0">
                    <tr>
                      <th class="px-3 py-2 text-left">Tahun</th>
                      <th class="px-3 py-2 text-right">Pokok</th>
                      <th class="px-3 py-2 text-right">Bunga</th>
                      <th class="px-3 py-2 text-right">Sisa Pokok</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    <tr v-for="y in yearlySchedule" :key="'sch-'+y.year" class="hover:bg-blue-50/40">
                      <td class="px-3 py-2">Thn {{ y.year }}</td>
                      <td class="px-3 py-2 text-right">{{ formatRupiahSingkat(y.totalPrincipal) }}</td>
                      <td class="px-3 py-2 text-right text-amber-600">{{ formatRupiahSingkat(y.totalInterest) }}</td>
                      <td class="px-3 py-2 text-right font-bold">{{ formatRupiahSingkat(y.endingBalance) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </div>

      </div>
    </div>

    <!-- BANK SELECTION MODAL -->
    <div v-if="showProgramModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showProgramModal = false"></div>
      
      <div class="relative bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[85vh] flex flex-col overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-900 text-white">
          <h3 class="text-sm font-black uppercase tracking-wider">Pilih Program Bank Partner</h3>
          <button @click="showProgramModal = false" class="text-slate-400 hover:text-white font-bold text-xl">✕</button>
        </div>

        <div class="p-6 overflow-y-auto space-y-4">
          <div v-for="prog in allPrograms" :key="prog.id" 
               @click="selectedBank = prog; showProgramModal = false"
               class="p-4 rounded-2xl border border-slate-200 hover:border-blue-500 hover:bg-blue-50/50 transition-all cursor-pointer flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-xs text-white shrink-0"
                   :class="prog.logo_color || 'bg-blue-600'">
                <img v-if="prog.logo" :src="prog.logo" class="w-full h-full object-contain p-1" />
                <span v-else>{{ (prog.logo_text || 'BANK').substring(0, 4) }}</span>
              </div>
              <div>
                <h4 class="text-sm font-black text-slate-900">{{ prog.program_name }}</h4>
                <p class="text-xs text-slate-500 font-medium mt-0.5">{{ prog.description }}</p>
              </div>
            </div>
            <span class="px-3 py-1 bg-blue-600 text-white text-[10px] font-black rounded-lg shrink-0">Pilih</span>
          </div>
        </div>
      </div>
    </div>

  </CrmLayout>
</template>
