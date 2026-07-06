<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    payrolls: Array,
    salary_structures: Array,
    users: Array,
    stats: Object,
    filters: Object,
});

const formatCurrency = (v) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v || 0);

const activeTab = ref('payroll'); // payroll | salary

// Period selector
const selectedMonth = ref(props.filters?.month || new Date().getMonth() + 1);
const selectedYear = ref(props.filters?.year || new Date().getFullYear());

const monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function changePeriod() {
    router.get('/finance/payroll', { month: selectedMonth.value, year: selectedYear.value }, { preserveState: true, preserveScroll: true });
}

// Generate payroll
function generatePayroll() {
    if (confirm(`Generate slip gaji untuk ${monthNames[selectedMonth.value]} ${selectedYear.value}?`)) {
        router.post('/finance/payroll/generate', { month: selectedMonth.value, year: selectedYear.value }, { preserveScroll: true });
    }
}

function paySingle(payroll) {
    if (confirm(`Bayar gaji ${payroll.user?.name}?`)) {
        router.post(`/finance/payroll/${payroll.id}/pay`, { payment_method: 'transfer' }, { preserveScroll: true });
    }
}

function payAllDraft() {
    if (confirm('Bayar SEMUA gaji yang masih draft?')) {
        router.post('/finance/payroll/pay-all', { month: selectedMonth.value, year: selectedYear.value }, { preserveScroll: true });
    }
}

// Salary structure modal
const showSalaryModal = ref(false);
const editSalaryMode = ref(false);
const selectedSalary = ref(null);

const salaryForm = useForm({
    user_id: '',
    basic_salary: '',
    transport_allowance: 0,
    meal_allowance: 0,
    position_allowance: 0,
    other_allowance: 0,
    effective_date: new Date().toISOString().split('T')[0],
    notes: '',
});

function openSalaryCreate() {
    editSalaryMode.value = false;
    selectedSalary.value = null;
    salaryForm.reset();
    salaryForm.effective_date = new Date().toISOString().split('T')[0];
    showSalaryModal.value = true;
}

function openSalaryEdit(s) {
    editSalaryMode.value = true;
    selectedSalary.value = s;
    salaryForm.user_id = s.user_id;
    salaryForm.basic_salary = s.basic_salary;
    salaryForm.transport_allowance = s.transport_allowance;
    salaryForm.meal_allowance = s.meal_allowance;
    salaryForm.position_allowance = s.position_allowance;
    salaryForm.other_allowance = s.other_allowance;
    salaryForm.effective_date = s.effective_date?.split('T')[0] || '';
    salaryForm.notes = s.notes || '';
    showSalaryModal.value = true;
}

function submitSalary() {
    if (editSalaryMode.value && selectedSalary.value) {
        salaryForm.put(`/finance/salary/${selectedSalary.value.id}`, { preserveScroll: true, onSuccess: () => { showSalaryModal.value = false; } });
    } else {
        salaryForm.post('/finance/salary', { preserveScroll: true, onSuccess: () => { showSalaryModal.value = false; } });
    }
}

const computedGross = computed(() => {
    return (Number(salaryForm.basic_salary) || 0) + (Number(salaryForm.transport_allowance) || 0)
        + (Number(salaryForm.meal_allowance) || 0) + (Number(salaryForm.position_allowance) || 0)
        + (Number(salaryForm.other_allowance) || 0);
});
</script>

<template>
    <Head title="Penggajian" />
    <CrmLayout>
        <template #breadcrumb>Keuangan / Penggajian</template>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Penggajian Karyawan</h1>
                <p class="text-sm text-slate-500 mt-1">Kelola gaji, tunjangan, dan pembayaran karyawan.</p>
            </div>
            <Link href="/finance" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">
                ← Kas Utama
            </Link>
        </div>

        <!-- Tab Nav -->
        <div class="flex gap-1 mb-6 bg-slate-100 rounded-2xl p-1 w-fit">
            <button @click="activeTab = 'payroll'" :class="activeTab === 'payroll' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'"
                class="px-5 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all">💼 Slip Gaji</button>
            <button @click="activeTab = 'salary'" :class="activeTab === 'salary' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'"
                class="px-5 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all">📋 Struktur Gaji</button>
        </div>

        <!-- ═══ PAYROLL TAB ═══ -->
        <div v-if="activeTab === 'payroll'">
            <!-- Period Selector & Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-2xl p-6 border border-slate-100 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Periode</p>
                    <div class="flex gap-2">
                        <select v-model="selectedMonth" @change="changePeriod" class="flex-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">
                            <option v-for="m in 12" :key="m" :value="m">{{ monthNames[m] }}</option>
                        </select>
                        <select v-model="selectedYear" @change="changePeriod" class="w-20 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">
                            <option v-for="y in 5" :key="y" :value="new Date().getFullYear() - y + 1">{{ new Date().getFullYear() - y + 1 }}</option>
                        </select>
                    </div>
                </div>
                <div class="bg-amber-50 rounded-2xl p-6 border border-amber-100 shadow-sm">
                    <p class="text-[10px] font-black text-amber-500 uppercase tracking-widest mb-1">Belum Dibayar</p>
                    <h3 class="text-2xl font-black text-amber-700">{{ formatCurrency(stats.total_draft) }}</h3>
                    <p class="text-[10px] text-amber-400 font-bold mt-2">{{ stats.total_payrolls - stats.paid_count }} karyawan</p>
                </div>
                <div class="bg-emerald-50 rounded-2xl p-6 border border-emerald-100 shadow-sm">
                    <p class="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-1">Sudah Dibayar</p>
                    <h3 class="text-2xl font-black text-emerald-700">{{ formatCurrency(stats.total_paid) }}</h3>
                    <p class="text-[10px] text-emerald-400 font-bold mt-2">{{ stats.paid_count }} karyawan</p>
                </div>
                <div class="bg-slate-900 rounded-2xl p-6 shadow-xl flex flex-col justify-between">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Aksi Cepat</p>
                    <div class="space-y-2">
                        <button @click="generatePayroll" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase rounded-xl transition-all">⚡ Generate Gaji</button>
                        <button v-if="stats.total_draft > 0" @click="payAllDraft" class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black uppercase rounded-xl transition-all">💳 Bayar Semua</button>
                    </div>
                </div>
            </div>

            <!-- Payroll Table -->
            <div class="bg-white rounded-3xl border border-slate-100 overflow-hidden shadow-sm">
                <div class="px-6 py-5 border-b border-slate-100">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Daftar Slip Gaji — {{ monthNames[selectedMonth] }} {{ selectedYear }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                <th class="px-6 py-4">Karyawan</th>
                                <th class="px-6 py-4 text-right">Gaji Pokok</th>
                                <th class="px-6 py-4 text-right">Tunjangan</th>
                                <th class="px-6 py-4 text-right">Potongan</th>
                                <th class="px-6 py-4 text-right">Gaji Bersih</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="p in payrolls" :key="p.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <p class="font-black text-slate-900">{{ p.user?.name }}</p>
                                    <p class="text-[10px] text-slate-400 font-bold">{{ p.user?.email }}</p>
                                </td>
                                <td class="px-6 py-4 text-right font-bold text-slate-700">{{ formatCurrency(p.basic_salary) }}</td>
                                <td class="px-6 py-4 text-right font-bold text-blue-600">{{ formatCurrency(p.total_allowances) }}</td>
                                <td class="px-6 py-4 text-right font-bold text-rose-500">{{ formatCurrency(p.total_deductions) }}</td>
                                <td class="px-6 py-4 text-right font-black text-slate-900">{{ formatCurrency(p.net_salary) }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span :class="p.status === 'paid' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'"
                                        class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase">{{ p.status === 'paid' ? '✅ Lunas' : '⏳ Draft' }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Link :href="`/finance/payroll/${p.id}/slip`" class="px-3 py-1.5 bg-slate-100 text-slate-600 text-[10px] font-black rounded-lg hover:bg-slate-200 transition-all">Slip</Link>
                                        <button v-if="p.status === 'draft'" @click="paySingle(p)" class="px-3 py-1.5 bg-emerald-600 text-white text-[10px] font-black rounded-lg hover:bg-emerald-700 transition-all">Bayar</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!payrolls?.length">
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <p class="text-3xl mb-3">💼</p>
                                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest">Belum Ada Data Gaji</p>
                                    <p class="text-xs text-slate-500 mt-1">Buat struktur gaji terlebih dahulu, lalu klik "Generate Gaji".</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ═══ SALARY STRUCTURE TAB ═══ -->
        <div v-if="activeTab === 'salary'">
            <div class="flex items-center justify-between mb-6">
                <p class="text-sm text-slate-600 font-bold">Tentukan gaji pokok dan tunjangan untuk setiap karyawan. Data ini akan digunakan saat generate slip gaji.</p>
                <button @click="openSalaryCreate" class="px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all">
                    + Tambah Struktur Gaji
                </button>
            </div>

            <div class="bg-white rounded-3xl border border-slate-100 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                <th class="px-6 py-4">Karyawan</th>
                                <th class="px-6 py-4 text-right">Gaji Pokok</th>
                                <th class="px-6 py-4 text-right">T. Transport</th>
                                <th class="px-6 py-4 text-right">T. Makan</th>
                                <th class="px-6 py-4 text-right">T. Jabatan</th>
                                <th class="px-6 py-4 text-right">T. Lain</th>
                                <th class="px-6 py-4 text-right">Total Bruto</th>
                                <th class="px-6 py-4">Berlaku Sejak</th>
                                <th class="px-6 py-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="s in salary_structures" :key="s.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <p class="font-black text-slate-900">{{ s.user?.name }}</p>
                                </td>
                                <td class="px-6 py-4 text-right font-bold text-slate-700">{{ formatCurrency(s.basic_salary) }}</td>
                                <td class="px-6 py-4 text-right font-medium text-slate-600">{{ formatCurrency(s.transport_allowance) }}</td>
                                <td class="px-6 py-4 text-right font-medium text-slate-600">{{ formatCurrency(s.meal_allowance) }}</td>
                                <td class="px-6 py-4 text-right font-medium text-slate-600">{{ formatCurrency(s.position_allowance) }}</td>
                                <td class="px-6 py-4 text-right font-medium text-slate-600">{{ formatCurrency(s.other_allowance) }}</td>
                                <td class="px-6 py-4 text-right font-black text-blue-600">{{ formatCurrency(s.basic_salary + s.transport_allowance + s.meal_allowance + s.position_allowance + s.other_allowance) }}</td>
                                <td class="px-6 py-4 font-bold text-slate-600">{{ new Date(s.effective_date).toLocaleDateString('id-ID') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <button @click="openSalaryEdit(s)" class="px-3 py-1.5 bg-slate-100 text-slate-600 text-[10px] font-black rounded-lg hover:bg-slate-200 transition-all">✏️ Edit</button>
                                </td>
                            </tr>
                            <tr v-if="!salary_structures?.length">
                                <td colspan="9" class="px-6 py-16 text-center">
                                    <p class="text-3xl mb-3">📋</p>
                                    <p class="text-xs font-black text-slate-400 uppercase tracking-widest">Belum Ada Struktur Gaji</p>
                                    <p class="text-xs text-slate-500 mt-1">Klik tombol "Tambah Struktur Gaji" untuk memulai.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Salary Modal -->
        <teleport to="body">
            <div v-if="showSalaryModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showSalaryModal = false"></div>
                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                    <div class="sticky top-0 bg-white/95 backdrop-blur-sm px-8 py-6 border-b border-slate-100 z-10">
                        <h2 class="text-lg font-black text-slate-900">{{ editSalaryMode ? '✏️ Edit Struktur Gaji' : '💼 Tambah Struktur Gaji' }}</h2>
                    </div>
                    <form @submit.prevent="submitSalary" class="p-8 space-y-5">
                        <div v-if="!editSalaryMode">
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Karyawan *</label>
                            <select v-model="salaryForm.user_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500">
                                <option value="">Pilih Karyawan</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Gaji Pokok *</label>
                                <input v-model="salaryForm.basic_salary" type="number" required min="0" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Berlaku Sejak *</label>
                                <input v-model="salaryForm.effective_date" type="date" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tunj. Transport</label>
                                <input v-model="salaryForm.transport_allowance" type="number" min="0" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tunj. Makan</label>
                                <input v-model="salaryForm.meal_allowance" type="number" min="0" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tunj. Jabatan</label>
                                <input v-model="salaryForm.position_allowance" type="number" min="0" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tunj. Lain-lain</label>
                                <input v-model="salaryForm.other_allowance" type="number" min="0" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500" />
                            </div>
                        </div>
                        <div class="bg-blue-50 rounded-xl p-4 border border-blue-100">
                            <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Total Gaji Bruto</p>
                            <p class="text-xl font-black text-blue-700 mt-1">{{ formatCurrency(computedGross) }}</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Catatan</label>
                            <textarea v-model="salaryForm.notes" rows="2" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="showSalaryModal = false" class="px-6 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                            <button type="submit" :disabled="salaryForm.processing" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-xs font-bold rounded-xl shadow-lg disabled:opacity-50">
                                {{ salaryForm.processing ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>
    </CrmLayout>
</template>
