<script setup>
import { ref, watch, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { splitCoordinatorsByRole, resolveInitialSignerKey, applySignerSelection } from '@/utils/developerSigners';

const props = defineProps({
    units: Array,
    leads: Array,
    coordinators: Array,
    developerSignatories: {
        type: Array,
        default: () => [],
    },
    defaultDeveloperSigner: {
        type: Object,
        default: null,
    },
    preselectedUnitId: [String, Number],
    preselectedLeadId: [String, Number],
    preselectedNego: Object,
    defaultCompanyName: String,
    standardBookingFee: {
        type: Number,
        default: 17000000,
    },
});

const groupedCoordinators = computed(() => splitCoordinatorsByRole(props.coordinators));

const form = useForm({
    unit_id: props.preselectedUnitId || props.preselectedNego?.unit_id || '',
    lead_id: props.preselectedLeadId || props.preselectedNego?.lead_id || '',
    negotiation_id: props.preselectedNego?.id || '',
    client_name: props.preselectedNego?.client_name || '',
    client_phone: props.preselectedNego?.client_phone || '',
    client_email: props.preselectedNego?.client_email || '',
    client_nik: props.preselectedNego?.lead?.identity_number || '',
    amount: 10000000, // Default 10 Juta (dapat diubah-ubah)
    payment_method: 'transfer',
    payment_proof: null,
    company_name: props.defaultCompanyName || '',
    signer_slot: props.defaultDeveloperSigner?.name ? (props.defaultDeveloperSigner?.slot || 'sig1') : 'user',
    sales_pic_name: props.preselectedNego?.lead?.assigned_to?.name || props.preselectedNego?.lead?.inhouse_pic?.name || '',
    agent_coordinator_id: '',
    agent_coordinator_name: props.defaultDeveloperSigner?.name || '',
    agent_coordinator_title: props.defaultDeveloperSigner?.title || 'Sales Manager',
    expires_days: 7,
    notes: props.preselectedNego ? `Dibuat dari Pengajuan Negosiasi #${props.preselectedNego.negotiation_number || props.preselectedNego.token}` : '',
});

const selectedSignerKey = ref(resolveInitialSignerKey({ defaultDeveloperSigner: props.defaultDeveloperSigner }));
const activePresetImage = ref(props.defaultDeveloperSigner?.image || null);

const selectableUnits = computed(() => {
    const preId = props.preselectedUnitId ? Number(props.preselectedUnitId) : null;
    return (props.units || []).filter((u) => {
        const st = String(u.status || '').toLowerCase().trim();
        if (st === 'sold' || st === 'booked' || st === 'cancelled') return false;
        if (preId && Number(u.id) === preId) return true;
        return st === 'available' || st === '';
    });
});

const selectedUnit = computed(() => {
    return (props.units || []).find(u => u.id == form.unit_id);
});

const remainingUtj = computed(() => {
    return Math.max(0, Number(props.standardBookingFee || 0) - Number(form.amount || 0));
});

watch(() => form.unit_id, () => {
    if (!form.company_name) {
        if (props.defaultCompanyName) {
            form.company_name = props.defaultCompanyName;
        } else if (selectedUnit.value?.project?.name) {
            form.company_name = `PT ${selectedUnit.value.project.name} Development`;
        }
    }
}, { immediate: true });

watch(selectedSignerKey, (key) => {
    const res = applySignerSelection(key, {
        form,
        developerSignatories: props.developerSignatories,
        coordinators: props.coordinators,
    });
    activePresetImage.value = res.image;
}, { immediate: true });

watch(() => form.lead_id, (leadId, oldLeadId) => {
    if (!leadId) return;
    const lead = props.leads.find(l => l.id == leadId);
    if (lead) {
        const isSwitchingLead = Boolean(oldLeadId && oldLeadId != leadId);
        if (isSwitchingLead || !form.client_name) form.client_name = lead.name || '';
        if (isSwitchingLead || !form.client_phone) form.client_phone = lead.phone || '';
        if (isSwitchingLead || !form.client_email) form.client_email = lead.email || '';
        if (isSwitchingLead || !form.client_nik) form.client_nik = lead.identity_number || '';
        if ((isSwitchingLead || !form.unit_id) && !props.preselectedUnitId && lead.suggested_unit_id) {
            const matchingUnit = selectableUnits.value.find(u => Number(u.id) === Number(lead.suggested_unit_id));
            if (matchingUnit) {
                form.unit_id = matchingUnit.id;
            }
        }
        const leadPicName = lead.assigned_to?.name || lead.inhouse_pic?.name || '';
        if (leadPicName) {
            form.sales_pic_name = leadPicName;
        }
        const leadAgentId = (typeof lead.assigned_to === 'object' ? lead.assigned_to?.id : lead.assigned_to)
            || lead.inhouse_pic?.id
            || lead.inhouse_pic_id;
        if (leadAgentId && !selectedSignerKey.value) {
            selectedSignerKey.value = `user:${leadAgentId}`;
        }
    }
}, { immediate: true });

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
                                <option v-for="u in selectableUnits" :key="u.id" :value="u.id">
                                    {{ u.project?.name }} · {{ u.label }} (Tipe {{ u.unit_type?.name }}) - {{ formatCurrency(u.final_price || u.unit_type?.current_price) }} [{{ u.status }}]
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
                            <p class="font-black">🏠 {{ selectedUnit.project?.name }} — {{ selectedUnit.label || (`Unit ${selectedUnit.number || '-'}`) }}</p>
                            <p class="text-[10px] text-blue-700 font-semibold">
                                Tipe {{ selectedUnit.unit_type?.name }}
                                <span v-if="selectedUnit.unit_type?.building_area || selectedUnit.unit_type?.land_area">
                                    (LB {{ selectedUnit.unit_type?.building_area || '-' }} m² / LT {{ selectedUnit.unit_type?.land_area || '-' }} m²)
                                </span>
                                · Harga Listing: {{ formatCurrency(selectedUnit.final_price || selectedUnit.unit_type?.current_price) }}
                            </p>
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
                            <p v-if="form.errors.client_email" class="text-[10px] text-rose-500 font-bold mt-1">{{ form.errors.client_email }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">NIK KTP Pemohon (Opsional)</label>
                            <input v-model="form.client_nik" type="text" placeholder="16 Digit NIK KTP..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500" />
                            <p v-if="form.errors.client_nik" class="text-[10px] text-rose-500 font-bold mt-1">{{ form.errors.client_nik }}</p>
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
                            <p class="text-[10px] text-slate-400 font-bold mt-1">Input manual: {{ formatCurrency(form.amount) }}</p>
                            <div class="mt-2 p-2.5 bg-emerald-50/70 border border-emerald-200 rounded-xl text-[11px] space-y-1">
                                <div class="flex justify-between text-slate-600">
                                    <span>Booking Fee (UTJ) Standar (Settings):</span>
                                    <span class="font-bold text-slate-900 font-mono">{{ formatCurrency(standardBookingFee) }}</span>
                                </div>
                                <div class="flex justify-between text-emerald-700">
                                    <span>Potongan dari Reservasi Ini:</span>
                                    <span class="font-bold font-mono">- {{ formatCurrency(form.amount) }}</span>
                                </div>
                                <div class="flex justify-between border-t border-emerald-200/80 pt-1 font-black text-slate-900">
                                    <span>Sisa UTJ Saat Naik Booking:</span>
                                    <span :class="remainingUtj === 0 ? 'text-emerald-700' : 'text-amber-700'" class="font-mono">
                                        {{ remainingUtj === 0 ? 'Rp 0 (Lunas UTJ)' : formatCurrency(remainingUtj) }}
                                    </span>
                                </div>
                            </div>
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
                            <p v-if="form.errors.payment_proof" class="text-[10px] text-rose-500 font-bold mt-1">{{ form.errors.payment_proof }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Penandatangan Resmi Pihak Developer (Kwitansi)</label>
                            <select v-model="selectedSignerKey" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500">
                                <optgroup v-if="developerSignatories.length" label="🏛️ Pejabat Resmi Developer (Master Settings)">
                                    <option v-for="s in developerSignatories" :key="s.key" :value="`preset:${s.key}`">
                                        {{ s.name ? `${s.name} — ${s.title}` : `${s.label} (${s.title})` }}{{ s.image ? ' ✓ TTD Digital' : '' }}
                                    </option>
                                </optgroup>
                                <optgroup v-if="groupedCoordinators.devStaff.length" label="👔 Tim Internal Developer (User)">
                                    <option v-for="c in groupedCoordinators.devStaff" :key="c.id" :value="`user:${c.id}`">
                                        {{ c.name }} ({{ c.signer_title }})
                                    </option>
                                </optgroup>
                                <optgroup v-if="groupedCoordinators.salesAgents.length" label="🤝 Sales / Agent Koordinator">
                                    <option v-for="c in groupedCoordinators.salesAgents" :key="c.id" :value="`user:${c.id}`">
                                        {{ c.name }} ({{ c.signer_title }})
                                    </option>
                                </optgroup>
                                <option value="custom">✏️ Isi Manual Nama & Jabatan Penandatangan</option>
                            </select>
                            <p v-if="activePresetImage" class="text-[10px] text-emerald-700 font-bold mt-1">✓ TTD Digital dari Settings akan otomatis tercetak pada PDF Kwitansi.</p>
                            <p v-else class="text-[10px] text-slate-400 mt-1">Default mengikuti pengaturan Penandatangan Developer di Settings.</p>
                        </div>
                    </div>
                </div>

                <!-- SEKSI 4: OTENTIKASI KWITANSI & PT DEVELOPER (DINAMIS) -->
                <div>
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2 mb-4">4. Otentikasi Kwitansi & PT Developer (Dinamis)</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama PT / Perusahaan Developer</label>
                            <input v-model="form.company_name" type="text" placeholder="Contoh: PT Serangkai Roden Development" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500" />
                            <p class="text-[10px] text-slate-400 mt-1">Dapat diubah bebas per kwitansi.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Pejabat Penandatangan</label>
                            <input v-model="form.agent_coordinator_name" type="text" placeholder="Nama Penandatangan..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500" />
                            <p class="text-[10px] text-slate-400 mt-1">Terisi otomatis sesuai pejabat yang dipilih.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Jabatan Penandatangan</label>
                            <input v-model="form.agent_coordinator_title" type="text" placeholder="Sales Manager / Finance / Direktur" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Sales / Agent PIC (Opsional)</label>
                            <input v-model="form.sales_pic_name" type="text" placeholder="Nama Sales yang Closing..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:ring-blue-500" />
                            <p class="text-[10px] text-slate-400 mt-1">Catatan referensi sales pendamping pembeli.</p>
                        </div>
                    </div>
                </div>

                <!-- SEKSI 5: MASA BERLAKU & CATATAN -->
                <div>
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2 mb-4">5. Masa Berlaku & Catatan</h3>
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
