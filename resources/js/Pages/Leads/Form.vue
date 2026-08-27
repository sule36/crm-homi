<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const props = defineProps({
    lead: { type: Object, default: null },
    projects: { type: Array, default: () => [] },
    agents: { type: Array, default: () => [] },
    broker_companies: { type: Array, default: () => [] },
});

const isEdit = computed(() => !!props.lead);

const form = useForm({
    name: props.lead?.name || '',
    phone: props.lead?.phone || '',
    email: props.lead?.email || '',
    source: props.lead?.source || 'walk_in',
    status: props.lead?.status || 'new',
    budget: props.lead?.budget || '',
    preferred_type: props.lead?.preferred_type || '',
    notes: props.lead?.notes || '',
    project_id: props.lead?.project_id || '',
    assigned_to: props.lead?.assigned_to || '',
    broker_company_id: props.lead?.broker_company_id || '',
    nik: props.lead?.nik || '',
    npwp: props.lead?.npwp || '',
    address: props.lead?.address || '',
    job: props.lead?.job || '',
});

function submit() {
    if (isEdit.value) {
        form.put(route('leads.update', props.lead.id));
    } else {
        form.post(route('leads.store'));
    }
}

const inhouseAgents = computed(() => {
    return (props.agents || []).filter(a => !a.broker_company_id || a.agent_type === 'inhouse');
});

const brokerAgents = computed(() => {
    return (props.agents || []).filter(a => a.broker_company_id || a.agent_type === 'agency_agent' || a.agent_type === 'independent');
});
</script>

<template>
    <Head :title="isEdit ? 'Edit Lead Prospek' : 'Tambah Lead Prospek Baru'" />
    <CrmLayout>
        <template #breadcrumb>
            <Link href="/leads" class="hover:underline">Leads</Link>
            <span class="mx-1">/</span>
            <span>{{ isEdit ? 'Edit Lead' : 'Tambah Lead Baru' }}</span>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">
                        {{ isEdit ? 'Edit Data Lead Prospek' : 'Tambah Lead Prospek Baru' }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Isi kelengkapan data calon konsumen / pembeli unit properti Homi Developer.
                    </p>
                </div>
                <Link href="/leads" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-all shadow-sm">
                    ← Kembali ke Daftar Leads
                </Link>
            </div>

            <form @submit.prevent="submit" class="bg-white rounded-3xl border border-slate-100 p-8 shadow-sm space-y-6">
                <!-- Informas Utama -->
                <div class="space-y-4">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Informasi Kontak & Diri</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nama Lengkap Konsumen *</label>
                            <input v-model="form.name" type="text" required placeholder="Contoh: Budi Santoso" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-blue-500/20" />
                            <div v-if="form.errors.name" class="text-rose-500 text-[10px] mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">No. WhatsApp / Telepon *</label>
                            <input v-model="form.phone" type="text" required placeholder="Contoh: 081234567890" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-blue-500/20" />
                            <div v-if="form.errors.phone" class="text-rose-500 text-[10px] mt-1">{{ form.errors.phone }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Alamat Email</label>
                            <input v-model="form.email" type="email" placeholder="budi@example.com" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-blue-500/20" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Sumber Prospek (Source)</label>
                            <select v-model="form.source" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                <option value="walk_in">🚶 Walk In / Pameran</option>
                                <option value="facebook">📘 Meta / Facebook Ads</option>
                                <option value="instagram">📸 Instagram Ads</option>
                                <option value="google">🔍 Google Ads</option>
                                <option value="agent">👔 Agen / Broker</option>
                                <option value="referral">🤝 Referensi</option>
                                <option value="website">🌐 Website</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Kelengkapan Identitas -->
                <div class="space-y-4 pt-4 border-t border-slate-100">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Kelengkapan Identitas (NIK & NPWP)</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">NIK (Nomor KTP)</label>
                            <input v-model="form.nik" type="text" placeholder="16 Digit NIK KTP" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-blue-500/20" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">NPWP</label>
                            <input v-model="form.npwp" type="text" placeholder="Nomor NPWP Wajib Pajak" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-blue-500/20" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Pekerjaan</label>
                            <input v-model="form.job" type="text" placeholder="Contoh: Karyawan Swasta / Pengusaha" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-blue-500/20" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Alamat Lengkap</label>
                            <input v-model="form.address" type="text" placeholder="Alamat Sesuai KTP" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-blue-500/20" />
                        </div>
                    </div>
                </div>

                <!-- Proyek & Alokasi Agen -->
                <div class="space-y-4 pt-4 border-t border-slate-100">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Alokasi Proyek & Sales / Kantor Agen</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Minat Proyek</label>
                            <select v-model="form.project_id" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                <option value="">-- Pilih Proyek --</option>
                                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Sales PIC Assigned / Agen</label>
                            <select v-model="form.assigned_to" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                <option value="">-- Pilih Sales PIC / Agen --</option>
                                <optgroup v-if="brokerAgents.length" label="🏢 AGEN KANTOR BROKER / INDEPENDEN">
                                    <option v-for="a in brokerAgents" :key="a.id" :value="a.id">🏢 {{ a.name }} ({{ a.broker_company ? a.broker_company.name : 'Agency' }})</option>
                                </optgroup>
                                <optgroup label="🏠 TIM SALES IN-HOUSE">
                                    <option v-for="a in inhouseAgents" :key="a.id" :value="a.id">🏠 {{ a.name }}</option>
                                </optgroup>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Kantor Agency (Refferal)</label>
                            <select v-model="form.broker_company_id" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                <option value="">-- Tanpa Kantor Agency --</option>
                                <option v-for="b in broker_companies" :key="b.id" :value="b.id">{{ b.name }} ({{ b.code }})</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Estimasi Budget (IDR)</label>
                            <input v-model="form.budget" type="number" step="10000000" placeholder="Contoh: 500000000" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-blue-500/20" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Tipe Unit Yang Diminati</label>
                            <input v-model="form.preferred_type" type="text" placeholder="Contoh: Tipe 36/72, 2 Lantai" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:ring-2 focus:ring-blue-500/20" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Catatan Tambahan</label>
                        <textarea v-model="form.notes" rows="3" placeholder="Catatan kebutuhan khusus konsumen..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:ring-2 focus:ring-blue-500/20"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <Link href="/leads" class="px-5 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-200 transition-all">
                        Batal
                    </Link>
                    <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-black rounded-xl transition-all shadow-md shadow-blue-500/20 disabled:opacity-50">
                        {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Tambah Lead') }}
                    </button>
                </div>
            </form>
        </div>
    </CrmLayout>
</template>
