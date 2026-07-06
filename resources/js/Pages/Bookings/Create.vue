<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, useForm, usePage, Link } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    unit: Object,
    lead: Object,
    availableUnits: Array,
    leads: Array,
    agents: Array,
});

const page = usePage();
const currentUser = computed(() => page.props.auth.user);

const form = useForm({
    unit_id: props.unit?.id || '',
    lead_id: props.lead?.id || '',
    booked_by: props.lead?.assigned_to || page.props.auth.user.id || '',
    booking_fee: '',
    base_price: props.unit?.final_price || '',
    free_ppn: false,
    free_legal: false,
    ppn_amount: 0,
    bphtb_amount: 0,
    ajb_bbn_amount: 0,
    other_legal_fees: 0,
    final_price: props.unit?.final_price || '',
    payment_scheme: 'kpr',
    notes: '',
});

const calculateTaxes = () => {
    const base = Number(form.base_price) || 0;
    
    // PPN 11%
    if (form.free_ppn) {
        form.ppn_amount = 0;
    } else {
        form.ppn_amount = Math.round(base * 0.11);
    }
    
    // BPHTB & AJB
    if (form.free_legal) {
        form.bphtb_amount = 0;
        form.ajb_bbn_amount = 0;
    } else {
        const npoptkp = 60000000;
        form.bphtb_amount = Math.max(0, Math.round((base - npoptkp) * 0.05));
        form.ajb_bbn_amount = Math.round(base * 0.01);
    }
    
    updateTotal();
};

const handleLeadChange = () => {
    const selectedLead = props.leads.find(l => l.id === form.lead_id);
    if (selectedLead && selectedLead.assigned_to) {
        form.booked_by = selectedLead.assigned_to;
    }
};

const updateTotal = () => {
    form.final_price = Number(form.base_price) + 
                       Number(form.ppn_amount) + 
                       Number(form.bphtb_amount) + 
                       Number(form.ajb_bbn_amount) + 
                       Number(form.other_legal_fees);
};

const selectedUnit = computed(() => {
    return props.availableUnits.find(u => u.id === form.unit_id) || props.unit;
});

const handleUnitChange = () => {
    if (selectedUnit.value) {
        form.base_price = selectedUnit.value.final_price;
        calculateTaxes();
    } else {
        form.base_price = '';
        form.ppn_amount = 0;
        form.bphtb_amount = 0;
        form.ajb_bbn_amount = 0;
        form.final_price = 0;
    }
};

// Calculate initial taxes if unit is pre-selected
if (props.unit) {
    calculateTaxes();
}

const submit = () => {
    form.post('/bookings');
};

const formatCurrency = (value) => {
    if (!value) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};
</script>

<template>
    <Head title="Buat Booking" />
    <CrmLayout>
        <template #breadcrumb>
            <Link href="/bookings" class="text-slate-400 hover:text-blue-600 transition-colors">Booking</Link>
            <span class="mx-2 text-slate-300">/</span>
            <span class="text-slate-900 font-bold">Baru</span>
        </template>

        <div class="max-w-4xl mx-auto">
            <div class="mb-8">
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Formulir Booking</h1>
                <p class="text-sm text-slate-500 mt-1">Isi detail unit dan data konsumen untuk membuat pesanan.</p>
            </div>

            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- MAIN FORM -->
                <div class="md:col-span-2 space-y-6">
                    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-5 flex items-center gap-2">
                            <span class="w-7 h-7 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 text-xs">📋</span>
                            Detail Pesanan
                        </h2>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Pilih Konsumen (Lead) <span class="text-rose-500">*</span></label>
                                <select v-model="form.lead_id" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                    <option value="">Pilih Lead...</option>
                                    <option v-for="l in leads" :key="l.id" :value="l.id">{{ l.name }} ({{ l.phone }})</option>
                                </select>
                                <p v-if="form.errors.lead_id" class="text-xs text-rose-500 mt-1">{{ form.errors.lead_id }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Pilih Unit <span class="text-rose-500">*</span></label>
                                <select v-model="form.unit_id" @change="handleUnitChange" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                    <option value="">Pilih Unit...</option>
                                    <option v-for="u in availableUnits" :key="u.id" :value="u.id">
                                        {{ u.project?.code }} - {{ u.block }}{{ u.number }} ({{ formatCurrency(u.final_price) }})
                                    </option>
                                </select>
                                <p v-if="form.errors.unit_id" class="text-xs text-rose-500 mt-1">{{ form.errors.unit_id }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Booking Fee (UTJ) <span class="text-rose-500">*</span></label>
                                    <input v-model="form.booking_fee" type="number" placeholder="5000000" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20" />
                                    <p v-if="form.errors.booking_fee" class="text-xs text-rose-500 mt-1">{{ form.errors.booking_fee }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Skema Pembayaran <span class="text-rose-500">*</span></label>
                                    <select v-model="form.payment_scheme" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                        <option value="kpr">KPR</option>
                                        <option value="cash">Cash Keras</option>
                                        <option value="cash_installment">In-House / Cicilan</option>
                                    </select>
                                </div>
                            </div>

                            <div class="pt-6 border-t border-slate-50">
                                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Rincian Biaya & Pajak</h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Harga Dasar Unit <span class="text-rose-500">*</span></label>
                                        <input v-model="form.base_price" @input="calculateTaxes" type="number" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm font-black focus:ring-2 focus:ring-blue-500/20" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1.5">PPN (11%)</label>
                                        <input v-model="form.ppn_amount" @input="updateTotal" type="number" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1.5">BPHTB (Est)</label>
                                        <input v-model="form.bphtb_amount" @input="updateTotal" type="number" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Biaya AJB/BBN</label>
                                        <input v-model="form.ajb_bbn_amount" @input="updateTotal" type="number" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20" />
                                    </div>
                                </div>
                            </div>

                            <div class="bg-blue-50 p-5 rounded-2xl border border-blue-100 flex items-center justify-between">
                                <div>
                                    <p class="text-[10px] font-black text-blue-400 uppercase tracking-widest">Total Harga Kesepakatan (All-in)</p>
                                    <p class="text-xl font-black text-blue-900">{{ formatCurrency(form.final_price) }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Satu Juta / DP Minimal</p>
                                    <p class="text-sm font-bold text-slate-600">{{ formatCurrency(form.final_price * 0.1) }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">Catatan Khusus</label>
                                <textarea v-model="form.notes" rows="2" placeholder="Tambahkan catatan khusus..." class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 resize-none"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SIDEBAR INFO -->
                <div class="space-y-6">
                    <div class="bg-slate-900 rounded-2xl p-6 text-white shadow-xl">
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-blue-400 mb-4">Ringkasan Unit</h3>
                        <div v-if="selectedUnit" class="space-y-4">
                            <div>
                                <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Proyek</p>
                                <p class="text-sm font-bold">{{ selectedUnit.project?.name }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Blok</p>
                                    <p class="text-sm font-bold">{{ selectedUnit.block }}{{ selectedUnit.number }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Tipe</p>
                                    <p class="text-sm font-bold">{{ selectedUnit.unit_type?.name }}</p>
                                </div>
                            </div>
                            <div class="pt-4 border-t border-white/10">
                                <p class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Harga Unit</p>
                                <p class="text-xl font-black text-blue-400">{{ formatCurrency(form.total_price || selectedUnit.final_price) }}</p>
                            </div>
                        </div>
                        <div v-else class="text-center py-10 opacity-50">
                            <p class="text-xs">Silakan pilih unit terlebih dahulu</p>
                        </div>
                    </div>

                    <button type="submit" :disabled="form.processing"
                        class="w-full py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-black rounded-2xl shadow-xl shadow-blue-500/30 hover:-translate-y-1 transition-all active:scale-95 disabled:opacity-50">
                        {{ form.processing ? 'Memproses...' : 'BUAT BOOKING' }}
                    </button>
                    
                    <Link href="/bookings" class="block w-full py-3 text-center text-xs font-bold text-slate-400 hover:text-slate-600 transition-colors">Batal</Link>
                </div>
            </form>
        </div>
    </CrmLayout>
</template>
