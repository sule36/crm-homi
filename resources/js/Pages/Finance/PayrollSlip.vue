<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    payroll: Object,
});

const formatCurrency = (v) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v || 0);

const monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function printSlip() {
    window.print();
}
</script>

<template>
    <Head :title="`Slip Gaji - ${payroll.user?.name}`" />
    <CrmLayout>
        <template #breadcrumb>Keuangan / Penggajian / Slip</template>

        <div class="max-w-2xl mx-auto">
            <!-- Actions -->
            <div class="flex items-center justify-between mb-6 print:hidden">
                <Link href="/finance/payroll" class="px-4 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all">
                    ← Kembali
                </Link>
                <button @click="printSlip" class="px-5 py-2.5 bg-slate-900 text-white text-xs font-bold rounded-xl hover:bg-slate-800 transition-all">
                    🖨️ Print Slip
                </button>
            </div>

            <!-- Slip Card -->
            <div class="bg-white rounded-3xl border border-slate-100 shadow-xl overflow-hidden" id="payroll-slip">
                <!-- Header -->
                <div class="bg-gradient-to-r from-slate-900 to-slate-800 px-8 py-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-xl font-black tracking-tight">SLIP GAJI</h1>
                            <p class="text-slate-400 text-xs font-bold mt-1">{{ monthNames[payroll.period_month] }} {{ payroll.period_year }}</p>
                        </div>
                        <div class="text-right">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center text-white font-black text-sm shadow-lg shadow-blue-500/30">H</div>
                            <p class="text-[9px] text-slate-400 font-bold mt-1">HOMI DEVELOPER</p>
                        </div>
                    </div>
                </div>

                <!-- Employee Info -->
                <div class="px-8 py-5 bg-slate-50 border-b border-slate-100">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Nama Karyawan</p>
                            <p class="text-sm font-black text-slate-900 mt-0.5">{{ payroll.user?.name }}</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Email</p>
                            <p class="text-sm font-bold text-slate-600 mt-0.5">{{ payroll.user?.email }}</p>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Status</p>
                            <span :class="payroll.status === 'paid' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'"
                                class="inline-block px-2.5 py-1 rounded-lg text-[9px] font-black uppercase mt-0.5">
                                {{ payroll.status === 'paid' ? '✅ Lunas' : '⏳ Draft' }}
                            </span>
                        </div>
                        <div v-if="payroll.paid_at">
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Tanggal Bayar</p>
                            <p class="text-sm font-bold text-slate-600 mt-0.5">{{ new Date(payroll.paid_at).toLocaleDateString('id-ID', { dateStyle: 'long' }) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Salary Breakdown -->
                <div class="px-8 py-6 space-y-4">
                    <!-- Income Section -->
                    <div>
                        <h3 class="text-[10px] font-black text-emerald-600 uppercase tracking-widest mb-3">Pendapatan</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center py-2 border-b border-dashed border-slate-100">
                                <span class="text-xs font-bold text-slate-700">Gaji Pokok</span>
                                <span class="text-xs font-black text-slate-900">{{ formatCurrency(payroll.basic_salary) }}</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b border-dashed border-slate-100">
                                <span class="text-xs font-bold text-slate-700">Total Tunjangan</span>
                                <span class="text-xs font-black text-blue-600">{{ formatCurrency(payroll.total_allowances) }}</span>
                            </div>
                            <div v-if="payroll.bonus > 0" class="flex justify-between items-center py-2 border-b border-dashed border-slate-100">
                                <span class="text-xs font-bold text-slate-700">Bonus</span>
                                <span class="text-xs font-black text-emerald-600">{{ formatCurrency(payroll.bonus) }}</span>
                            </div>
                            <div v-if="payroll.overtime > 0" class="flex justify-between items-center py-2 border-b border-dashed border-slate-100">
                                <span class="text-xs font-bold text-slate-700">Lembur</span>
                                <span class="text-xs font-black text-emerald-600">{{ formatCurrency(payroll.overtime) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Deductions -->
                    <div v-if="payroll.deductions?.length">
                        <h3 class="text-[10px] font-black text-rose-600 uppercase tracking-widest mb-3">Potongan</h3>
                        <div class="space-y-2">
                            <div v-for="d in payroll.deductions" :key="d.id" class="flex justify-between items-center py-2 border-b border-dashed border-slate-100">
                                <span class="text-xs font-bold text-slate-700">{{ d.description || d.type }}</span>
                                <span class="text-xs font-black text-rose-600">- {{ formatCurrency(d.amount) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Net Salary -->
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl p-5 border border-blue-100 mt-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-[10px] font-black text-blue-500 uppercase tracking-widest">Gaji Bersih (Take Home Pay)</p>
                            </div>
                            <p class="text-2xl font-black text-blue-700">{{ formatCurrency(payroll.net_salary) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-8 py-4 bg-slate-50 border-t border-slate-100 text-center">
                    <p class="text-[9px] text-slate-400 font-bold">Dokumen ini dicetak secara otomatis oleh sistem Homi Developer CRM</p>
                </div>
            </div>
        </div>
    </CrmLayout>
</template>

<style>
@media print {
    body * { visibility: hidden; }
    #payroll-slip, #payroll-slip * { visibility: visible; }
    #payroll-slip { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>
