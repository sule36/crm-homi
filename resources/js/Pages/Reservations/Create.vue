<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const props = defineProps({
    units: Array,
    leads: Array,
    coordinators: Array,
    preselectedUnitId: [String, Number],
    preselectedLeadId: [String, Number],
    preselectedNego: Object,
});

const form = useForm({
    unit_id: props.preselectedUnitId || props.preselectedNego?.unit_id || '',
    lead_id: props.preselectedLeadId || props.preselectedNego?.lead_id || '',
    negotiation_id: props.preselectedNego?.id || '',
    client_name: props.preselectedNego?.client_name || '',
    client_phone: props.preselectedNego?.client_phone || '',
    client_email: props.preselectedNego?.client_email || '',
    client_nik: '',
    amount: 10000000, // Default 10 Juta (dapat diubah-ubah)
    payment_method: 'transfer',
    payment_proof: null,
    agent_coordinator_id: '',
    expires_days: 7,
    notes: props.preselectedNego ? `Dibuat dari Pengajuan Negosiasi #${props.preselectedNego.negotiation_number || props.preselectedNego.token}` : '',
});

const selectedUnit = computed(() => {
    return props.units.find(u => u.id == form.unit_id);
});

watch(() => form.lead_id, (leadId) => {
    if (!leadId) return;
    const lead = props.leads.find(l => l.id == leadId);
    if (lead) {
        if (!form.client_name) form.client_name = lead.name || '';
        if (!form.client_phone) form.client_phone = lead.phone || '';
        if (!form.client_email) form.client_email = lead.email || '';
        if (!form.client_nik) form.client_nik = lead.identity_number || '';
        if (lead.assigned_to && !form.agent_coordinator_id) {
            form.agent_coordinator_id = lead.assigned_to;
        }
    }
});

const handleProofChange = (e) => {
    form.payment_proof = e.target.files[0];
};

const submit = () => {
    form.post('/reservations');
};

const formatCurrency = (val) => {
    if (!val) return 'Rp 0';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
};
</script>

<template>
    <CrmLayout title="Buat Reservasi Unit Baru">
        <Head title="Buat Reservasi Unit Baru (100% Refundable)" />

        <div class="max-w-4xl mx-auto">
            <!-- BREADCRUMB & HEADER -->
            <div class="mb-6">
                <Link href="/reservations" class="text-xs font-bold text-blue-600 hover:underline flex items-center gap-1 mb-2">
                    ← Kembali ke Daftar Reservasi
                </Link>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">Form Reservasi Unit (Hold Unit)</h1>
                    <span class="px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-700 border border-emerald-300 rounded-md">🛡️ 100% Refundable</span>
                </div>
                <p class="text-xs text-slate-500 mt-1">Input pembayaran biaya reservasi untuk me-lock unit dengan klausa garansi refund 100%.</p>
            </div>

            <!-- CALLOUT NOTICE -->
            <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 mb-6 text-xs text-emerald-800 space-y-1">
                <div class="font-black text-sm text-emerald-900 flex items-center gap-1.5">
                    <span>💡</span> Ketentuan & Garansi Reservasi
                </div>
                <p>• <strong>Nilai Reservasi Dinamis</strong>: Nominal biaya reservasi dapat diisi dan diubah secara bebas sesuai kesepakatan.</p>
                <p>• <strong>Pemotongan Sisa UTJ</strong>: Saat dikonversi ke Booking (SPR), nominal reservasi ini memotong total Booking Fee (UTJ) secara otomatis.</p>
                <p>• <strong>Garansi 100% Refundable</strong>: Dana dikembalikan 100% utuh tanpa potongan apabila pengajuan tidak disetujui atau pembeli membatalkan.</p>
            </div>

            <!-- FORM CARD -->
            <form @submit.prevent="submit" class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
                
                <!-- SEKSI 1: PILIH UNIT & LEAD -->
                <div>
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2 mb-4">1. Pilih Unit Properti & Lead Client</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Unit Properti <span class="text-rose-500">*</span></label>
                            <select v-model="form.unit_id" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500">
                                <option value="" disabled>-- Pilih Unit (Available) --</option>
                                <option v-for="u in units" :key="u.id" :value="u.id">
                                    {{ u.project?.name }} · {{ u.label }} (Tipe {{ u.unit_type?.name }}) - {{ formatCurrency(u.final_price || u.unit_type?.current_price) }}
                                </option>
                            </select>
                            <p v-if="form.errors.unit_id" class="text-[10px] text-rose-500 font-bold mt-1">{{ form.errors.unit_id }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Hubungkan Data Lead (Opsional)</label>
                            <select v-model="form.lead_id" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500">
                                <option value="">-- Tanpa Lead (Input Manual) --</option>
                                <option v-for="l in leads" :key="l.id" :value="l.id">
                                    {{ l.name }} ({{ l.phone }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- PREVIEW UNIT DIPILIH -->
                    <div v-if="selectedUnit" class="mt-3 p-3.5 bg-blue-50/70 border border-blue-200 rounded-2xl flex items-center justify-between text-xs text-blue-900">
                        <div>
                            <p class="font-black">🏠 {{ selectedUnit.project?.name }} — Unit {{ selectedUnit.code || selectedUnit.number }}</p>
                            <p class="text-[10px] text-blue-700 font-semibold">Tipe {{ selectedUnit.unit_type?.name }} · Harga Listing: {{ formatCurrency(selectedUnit.final_price || selectedUnit.unit_type?.current_price) }}</p>
                        </div>
                        <span class="px-2 py-1 bg-emerald-100 text-emerald-800 text-[10px] font-black rounded-lg">Siap Di-Hold</span>
                    </div>
                </div>

                <!-- SEKSI 2: IDENTITAS CLIENT -->
                <div>
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2 mb-4">2. Data Identitas Pemohon</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Lengkap Pemohon <span class="text-rose-500">*</span></label>
                            <input v-model="form.client_name" type="text" placeholder="Masukkan nama lengkap pemohon..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500" />
                            <p v-if="form.errors.client_name" class="text-[10px] text-rose-500 font-bold mt-1">{{ form.errors.client_name }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">No. WhatsApp / HP <span class="text-rose-500">*</span></label>
                            <input v-model="form.client_phone" type="text" placeholder="08xxxxxxxxxx" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500" />
                            <p v-if="form.errors.client_phone" class="text-[10px] text-rose-500 font-bold mt-1">{{ form.errors.client_phone }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Email (Opsional)</label>
                            <input v-model="form.client_email" type="email" placeholder="email@domain.com" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">NIK KTP Pemohon (Opsional)</label>
                            <input v-model="form.client_nik" type="text" placeholder="16 Digit NIK KTP..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500" />
                        </div>
                    </div>
                </div>

                <!-- SEKSI 3: NOMINAL RESERVASI DINAMIS & PEMBAYARAN -->
                <div>
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2 mb-4">3. Nominal Biaya Reservasi Dinamis & Pembayaran</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Nominal Biaya Reservasi (Rp) <span class="text-rose-500">*</span></label>
                            <input v-model="form.amount" type="number" step="500000" min="0" placeholder="10000000" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-black text-emerald-700 font-mono focus:ring-2 focus:ring-blue-500" />
                            <p class="text-[10px] text-slate-400 font-bold mt-1">Terbilang: {{ formatCurrency(form.amount) }} (Bisa diubah bebas)</p>
                            <p v-if="form.errors.amount" class="text-[10px] text-rose-500 font-bold mt-1">{{ form.errors.amount }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Metode Pembayaran <span class="text-rose-500">*</span></label>
                            <select v-model="form.payment_method" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500">
                                <option value="transfer">Bank Transfer (Rekening Resmi Developer)</option>
                                <option value="cash">Cash / Tunai di Office</option>
                                <option value="qris">QRIS / EDC Merchant</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Upload Bukti Transfer / Pembayaran</label>
                            <input @change="handleProofChange" type="file" accept="image/*,.pdf" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:ring-2 focus:ring-blue-500" />
                            <p class="text-[10px] text-slate-400 mt-1">Format JPG, PNG, PDF max 5MB.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Agent Koordinator (Penandatangan Kwitansi)</label>
                            <select v-model="form.agent_coordinator_id" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500">
                                <option value="">-- Gunakan User Login Saat Ini --</option>
                                <option v-for="c in coordinators" :key="c.id" :value="c.id">
                                    {{ c.name }} ({{ c.agent_type === 'master_lead' ? 'Master Lead / Agent Coord' : 'Sales Representative' }})
                                </option>
                            </select>
                            <p class="text-[10px] text-slate-400 mt-1">Nama ini akan tercetak sebagai penandatangan pada PDF Kwitansi Reservasi.</p>
                        </div>
                    </div>
                </div>

                <!-- SEKSI 4: MASA BERLAKU & CATATAN -->
                <div>
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2 mb-4">4. Masa Berlaku & Catatan</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Masa Berlaku Reservasi (Hari)</label>
                            <input v-model="form.expires_days" type="number" min="1" max="30" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Catatan Tambahan</label>
                            <input v-model="form.notes" type="text" placeholder="Catatan tambahan reservasi..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500" />
                        </div>
                    </div>
                </div>

                <!-- SUBMIT BUTTONS -->
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-6">
                    <Link href="/reservations" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all">
                        Batal
                    </Link>
                    <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-blue-600/20 flex items-center gap-2">
                        <span>🔖</span>
                        <span>{{ form.processing ? 'Menyimpan...' : 'Simpan & Terbitkan Kwitansi' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </CrmLayout>
</template>
