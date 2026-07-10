<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, useForm, usePage, router, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    settings: Object,
    tokens: Array,
    partner_banks_all: Array,
    broker_companies_all: Array,
    bank_accounts_all: Array,
});

const page = usePage();
const apiToken = computed(() => page.props.flash?.api_token);
const activeTab = ref('spk');

// Bank CRUD States
const showBankModal = ref(false);
const editingBank = ref(null);
const bankForm = useForm({
    name: '',
    interest_rate_fixed: 5.0,
    interest_rate_floating: 11.0,
    fixed_duration: 3,
    is_active: true,
    is_syariah: false,
    syariah_margin_rate: 6.5,
    is_tiered: false,
    tiered_rates: [
        { rate: 3.85, years: 3 },
        { rate: 6.85, years: 3 },
        { rate: 8.85, years: 4 }
    ]
});

const openAddBank = () => {
    editingBank.value = null;
    bankForm.reset();
    bankForm.name = '';
    bankForm.interest_rate_fixed = 3.85;
    bankForm.interest_rate_floating = 11.0;
    bankForm.fixed_duration = 3;
    bankForm.is_active = true;
    bankForm.is_syariah = false;
    bankForm.syariah_margin_rate = 6.5;
    bankForm.is_tiered = false;
    bankForm.tiered_rates = [
        { rate: 3.85, years: 3 },
        { rate: 6.85, years: 3 },
        { rate: 8.85, years: 4 }
    ];
    showBankModal.value = true;
};

const openEditBank = (bank) => {
    editingBank.value = bank;
    bankForm.name = bank.name;
    bankForm.interest_rate_fixed = Number(bank.interest_rate_fixed);
    bankForm.interest_rate_floating = Number(bank.interest_rate_floating);
    bankForm.fixed_duration = Number(bank.fixed_duration);
    bankForm.is_active = bank.is_active ? true : false;
    bankForm.is_syariah = bank.is_syariah ? true : false;
    bankForm.syariah_margin_rate = Number(bank.syariah_margin_rate);
    bankForm.is_tiered = bank.is_tiered ? true : false;
    if (bank.tiered_rates && Array.isArray(bank.tiered_rates)) {
        bankForm.tiered_rates = bank.tiered_rates.map(t => ({ rate: Number(t.rate), years: Number(t.years) }));
    } else {
        bankForm.tiered_rates = [
            { rate: 3.85, years: 3 },
            { rate: 6.85, years: 3 },
            { rate: 8.85, years: 4 }
        ];
    }
    showBankModal.value = true;
};

const saveBank = () => {
    if (editingBank.value) {
        bankForm.put(`/settings/partner-banks/${editingBank.value.id}`, {
            onSuccess: () => {
                showBankModal.value = false;
            }
        });
    } else {
        bankForm.post('/settings/partner-banks', {
            onSuccess: () => {
                showBankModal.value = false;
            }
        });
    }
};

const deleteBank = (id) => {
    if (confirm('Yakin ingin menghapus bank partner ini?')) {
        router.delete(`/settings/partner-banks/${id}`);
    }
};

const removeTier = () => {
    if (bankForm.tiered_rates.length > 1) {
        bankForm.tiered_rates.pop();
    }
};

const addTier = () => {
    bankForm.tiered_rates.push({ rate: 8.95, years: 3 });
};

// Broker CRUD States
const showBrokerModal = ref(false);
const editingBroker = ref(null);
const brokerForm = useForm({
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    commission_rate: 2.50,
    status: 'active'
});

const openAddBroker = () => {
    editingBroker.value = null;
    brokerForm.reset();
    showBrokerModal.value = true;
};

const openEditBroker = (broker) => {
    editingBroker.value = broker;
    brokerForm.name = broker.name;
    brokerForm.contact_person = broker.contact_person || '';
    brokerForm.phone = broker.phone || '';
    brokerForm.email = broker.email || '';
    brokerForm.address = broker.address || '';
    brokerForm.commission_rate = broker.commission_rate || 2.50;
    brokerForm.status = broker.status || 'active';
    showBrokerModal.value = true;
};

const saveBroker = () => {
    if (editingBroker.value) {
        brokerForm.put(`/settings/brokers/${editingBroker.value.id}`, {
            onSuccess: () => {
                showBrokerModal.value = false;
            }
        });
    } else {
        brokerForm.post('/settings/brokers', {
            onSuccess: () => {
                showBrokerModal.value = false;
            }
        });
    }
};

const deleteBroker = (id) => {
    if (confirm('Yakin ingin menghapus kantor agen/broker ini?')) {
        router.delete(`/settings/brokers/${id}`);
    }
};

// Bank Account CRUD States
const showBankAccountModal = ref(false);
const editingBankAccount = ref(null);
const bankAccountForm = useForm({
    name: '',
    bank_name: '',
    account_number: '',
    account_holder: '',
    initial_balance: 0
});

const openAddBankAccount = () => {
    editingBankAccount.value = null;
    bankAccountForm.reset();
    showBankAccountModal.value = true;
};

const openEditBankAccount = (acc) => {
    editingBankAccount.value = acc;
    bankAccountForm.name = acc.name;
    bankAccountForm.bank_name = acc.bank_name || '';
    bankAccountForm.account_number = acc.account_number || '';
    bankAccountForm.account_holder = acc.account_holder || '';
    bankAccountForm.initial_balance = acc.initial_balance || 0;
    showBankAccountModal.value = true;
};

const saveBankAccount = () => {
    if (editingBankAccount.value) {
        bankAccountForm.put(`/settings/bank-accounts/${editingBankAccount.value.id}`, {
            onSuccess: () => {
                showBankAccountModal.value = false;
            }
        });
    } else {
        bankAccountForm.post('/settings/bank-accounts', {
            onSuccess: () => {
                showBankAccountModal.value = false;
            }
        });
    }
};

const deleteBankAccount = (id) => {
    if (confirm('Yakin ingin menghapus rekening bank/kas ini?')) {
        router.delete(`/settings/bank-accounts/${id}`);
    }
};


const form = useForm({
    company_name: props.settings.company_name || '',
    company_address: props.settings.company_address || '',
    company_email: props.settings.company_email || '',
    company_website: props.settings.company_website || '',
    spk_terms: props.settings.spk_terms || '',
    company_logo: null,
    wa_verify_token: props.settings.wa_verify_token || '',
    wa_access_token: props.settings.wa_access_token || '',
    wa_phone_number_id: props.settings.wa_phone_number_id || '',
    wa_auto_reply_message: props.settings.wa_auto_reply_message || 'Halo! Terima kasih telah menghubungi Homi. Tim konsultan kami, {agent_name}, akan segera membalas pesan Anda.',
    google_ads_webhook_key: props.settings.google_ads_webhook_key || '',
    meta_leadads_verify_token: props.settings.meta_leadads_verify_token || '',
    meta_leadads_access_token: props.settings.meta_leadads_access_token || '',
    gemini_api_key: props.settings.gemini_api_key || '',
    meta_page_access_token: props.settings.meta_page_access_token || '',
    messenger_verify_token: props.settings.messenger_verify_token || '',
    instagram_verify_token: props.settings.instagram_verify_token || '',
    ai_autopilot_whatsapp: props.settings.ai_autopilot_whatsapp === '1' || props.settings.ai_autopilot_whatsapp === true || props.settings.ai_autopilot_whatsapp === 'true',
    ai_autopilot_messenger: props.settings.ai_autopilot_messenger === '1' || props.settings.ai_autopilot_messenger === true || props.settings.ai_autopilot_messenger === 'true',
    ai_autopilot_instagram: props.settings.ai_autopilot_instagram === '1' || props.settings.ai_autopilot_instagram === true || props.settings.ai_autopilot_instagram === 'true',
});

const submit = () => {
    form.post('/settings', {
        forceFormData: true,
        onSuccess: () => alert('Pengaturan berhasil diperbarui!')
    });
};

const generateToken = () => {
    router.post('/settings/token', {}, {
        preserveScroll: true,
    });
};

const copyToken = () => {
    navigator.clipboard.writeText(apiToken.value);
    alert('Token berhasil disalin!');
};

const handleLogoUpload = (e) => {
    form.company_logo = e.target.files[0];
};

const tabs = [
    { id: 'spk', name: 'Template SPK', icon: '📄' },
    { id: 'api', name: 'Integrasi API', icon: '🔌' },
    { id: 'whatsapp', name: 'WhatsApp Meta', icon: '💬' },
    { id: 'google_ads', name: 'Google Ads', icon: '🔍' },
    { id: 'meta_leads', name: 'Meta Lead Ads', icon: '🎯' },
    { id: 'omnichannel', name: 'Omnichannel & AI', icon: '🤖' },
    { id: 'banks', name: 'Bank Partner', icon: '🏦' },
    { id: 'brokers', name: 'Kantor Agen', icon: '🏢' },
    { id: 'bank_accounts', name: 'Rekening Kas', icon: '💳' },
];
</script>

<template>
    <Head title="System Settings" />
    <CrmLayout>
        <template #breadcrumb>Pengaturan Sistem</template>

        <div class="mb-8">
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Control Room</h1>
            <p class="text-sm text-slate-500 mt-1">Konfigurasi parameter sistem Homi Developer.</p>
        </div>

        <!-- TABS NAVIGATION -->
        <!-- Mobile Selector Dropdown -->
        <div class="block md:hidden mb-6">
            <label class="block text-[10px] font-black text-slate-450 uppercase mb-2">Pilih Kategori Pengaturan:</label>
            <div class="relative">
                <select v-model="activeTab" class="w-full px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl text-xs font-black uppercase tracking-wider focus:ring-2 focus:ring-blue-500/20 cursor-pointer appearance-none pr-10">
                    <option v-for="tab in tabs" :key="tab.id" :value="tab.id">
                        {{ tab.icon }} &nbsp;&nbsp; {{ tab.name }}
                    </option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500">
                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                </div>
            </div>
        </div>

        <!-- Desktop Selector Row -->
        <div class="hidden md:flex overflow-x-auto whitespace-nowrap bg-slate-100 p-1.5 rounded-2xl mb-8 w-full max-w-full scrollbar-none gap-1 select-none">
            <button v-for="tab in tabs" :key="tab.id"
                @click="activeTab = tab.id"
                :class="activeTab === tab.id ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                class="px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all flex items-center space-x-2 shrink-0">
                <span>{{ tab.icon }}</span>
                <span>{{ tab.name }}</span>
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <!-- TAB: TEMPLATE SPK (Unified) -->
                <div v-if="activeTab === 'spk'" class="space-y-6">
                    <!-- Section: Identity -->
                    <div class="bg-white rounded-3xl border border-slate-100 p-4 sm:p-8 shadow-sm space-y-6">
                        <h3 class="text-xs font-black uppercase tracking-widest text-blue-600 mb-6">1. Identitas & Alamat (Header SPK)</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Nama Perusahaan</label>
                                <input v-model="form.company_name" type="text" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Alamat Kantor</label>
                                <textarea v-model="form.company_address" rows="3" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20"></textarea>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Official Email</label>
                                    <input v-model="form.company_email" type="email" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Website</label>
                                    <input v-model="form.company_website" type="text" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Logo -->
                    <div class="bg-white rounded-3xl border border-slate-100 p-4 sm:p-8 shadow-sm space-y-6">
                        <h3 class="text-xs font-black uppercase tracking-widest text-pink-600 mb-6">2. Logo Perusahaan</h3>
                        <div class="flex flex-col sm:flex-row items-center sm:space-x-8 gap-6 sm:gap-0">
                            <div class="aspect-video w-48 bg-slate-50 rounded-2xl border-2 border-dashed border-slate-200 flex items-center justify-center overflow-hidden p-4 shrink-0">
                                <img v-if="settings.company_logo" :src="`/storage/${settings.company_logo}`" class="max-h-16" />
                                <span v-else class="text-[9px] text-slate-400 font-bold uppercase tracking-widest text-center">Belum ada logo</span>
                            </div>
                            <div class="flex-1 text-center sm:text-left">
                                <input type="file" @change="handleLogoUpload" class="hidden" id="logo-input" accept="image/*" />
                                <label for="logo-input" class="inline-block px-6 py-3 bg-pink-50 text-pink-600 font-black rounded-xl text-[10px] cursor-pointer hover:bg-pink-100 transition-all uppercase tracking-widest">
                                    UPLOAD LOGO BARU
                                </label>
                                <p v-if="form.errors.company_logo" class="text-[10px] text-rose-500 mt-2 font-bold">{{ form.errors.company_logo }}</p>
                                <p class="text-[9px] text-slate-400 mt-2 italic leading-relaxed">Logo ini akan muncul di pojok kiri atas dokumen SPK.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Terms -->
                    <div class="bg-white rounded-3xl border border-slate-100 p-4 sm:p-8 shadow-sm space-y-6">
                        <h3 class="text-xs font-black uppercase tracking-widest text-emerald-600 mb-6">3. Syarat & Ketentuan (T&C)</h3>
                        <div>
                            <textarea v-model="form.spk_terms" rows="8" class="w-full px-6 py-4 bg-slate-50 border-none rounded-2xl text-sm focus:ring-2 focus:ring-emerald-600/20 font-mono leading-relaxed" placeholder="Gunakan baris baru untuk setiap poin..."></textarea>
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button @click="submit" class="px-10 py-4 bg-slate-900 text-white font-black rounded-2xl shadow-xl hover:-translate-y-1 transition-all uppercase text-xs tracking-widest">
                            SIMPAN SELURUH TEMPLATE SPK
                        </button>
                    </div>
                </div>

                <!-- TAB: API -->
                <div v-if="activeTab === 'api'" class="bg-white rounded-3xl border border-slate-100 p-4 sm:p-8 shadow-sm space-y-6">
                    <h3 class="text-xs font-black uppercase tracking-widest text-indigo-600 mb-6">Integrasi API Website</h3>
                    <div class="space-y-6">
                        <div v-if="apiToken" class="p-4 sm:p-8 bg-indigo-50 rounded-3xl border border-indigo-100">
                            <p class="text-[10px] text-indigo-400 uppercase font-black mb-4 tracking-widest">API TOKEN RAHASIA (BARU):</p>
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:space-x-4">
                                <code class="flex-1 bg-white p-4 rounded-xl border border-indigo-100 text-indigo-600 font-mono text-xs break-all shadow-inner">{{ apiToken }}</code>
                                <button @click="copyToken" class="px-6 py-4 bg-indigo-600 text-white font-black rounded-xl text-xs uppercase tracking-widest hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all">
                                    COPY
                                </button>
                            </div>
                            <p class="text-[10px] text-indigo-400 mt-4 italic font-bold">PENTING: Token ini hanya muncul sekali. Harap segera salin dan simpan.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-6 bg-slate-50 rounded-2xl border border-slate-100">
                                <p class="text-[10px] font-black text-slate-400 uppercase mb-2">Endpoint URL</p>
                                <code class="text-xs text-slate-600 font-mono break-all">https://crm.homi.id/api/leads</code>
                            </div>
                            <div class="p-6 bg-slate-50 rounded-2xl border border-slate-100">
                                <p class="text-[10px] font-black text-slate-400 uppercase mb-2">Status Token</p>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-600 uppercase">
                                    {{ tokens.length > 0 ? 'Active' : 'No Token' }}
                                </span>
                            </div>
                        </div>

                        <div class="pt-4 flex justify-center">
                            <button @click="generateToken" class="px-10 py-4 bg-indigo-600 text-white font-black rounded-2xl shadow-xl hover:-translate-y-1 transition-all uppercase text-xs tracking-widest">
                                {{ tokens.length > 0 ? 'RE-GENERATE KUNCI API' : 'GENERATE KUNCI API' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TAB: WHATSAPP META -->
                <div v-if="activeTab === 'whatsapp'" class="bg-white rounded-3xl border border-slate-100 p-4 sm:p-8 shadow-sm space-y-6">
                    <h3 class="text-xs font-black uppercase tracking-widest text-emerald-600 mb-6">Konfigurasi WhatsApp Cloud API</h3>
                    
                    <div class="p-6 bg-emerald-50 border border-emerald-100 rounded-2xl mb-6">
                        <p class="text-xs font-bold text-emerald-800 mb-2">Webhook URL Anda:</p>
                        <code class="block bg-white p-3 rounded-xl border border-emerald-200 text-emerald-600 font-mono text-xs shadow-inner">
                            https://[domain-anda.com]/api/webhooks/whatsapp
                        </code>
                        <p class="text-[10px] text-emerald-600 mt-2">Masukkan URL ini ke dashboard Meta for Developers Anda.</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Verify Token (Untuk Webhook Meta)</label>
                            <input v-model="form.wa_verify_token" type="text" placeholder="Masukkan token rahasia acak..." class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-emerald-600/20" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Phone Number ID</label>
                            <input v-model="form.wa_phone_number_id" type="text" placeholder="Contoh: 10456123456789" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-emerald-600/20" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Permanent Access Token</label>
                            <textarea v-model="form.wa_access_token" rows="3" placeholder="EAAL..." class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-emerald-600/20 font-mono text-xs"></textarea>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Pesan Balasan Otomatis (Auto-Reply)</label>
                            <textarea v-model="form.wa_auto_reply_message" rows="3" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-emerald-600/20 leading-relaxed"></textarea>
                            <p class="text-[10px] text-slate-400 mt-1 italic">Gunakan <b>{agent_name}</b> untuk memanggil nama agen yang ditugaskan secara otomatis.</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Gemini API Key (Homi AI Copilot WhatsApp)</label>
                            <input v-model="form.gemini_api_key" type="password" placeholder="Masukkan Gemini API Key (e.g. AIzaSy...)" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-emerald-600/20" />
                            <p class="text-[10px] text-slate-400 mt-1 italic">Jika kosong, sistem akan menggunakan API Key dari file .env (GEMINI_API_KEY).</p>
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button @click="submit" class="px-10 py-4 bg-emerald-600 text-white font-black rounded-2xl shadow-xl hover:-translate-y-1 transition-all uppercase text-xs tracking-widest hover:bg-emerald-700">
                            SIMPAN KONFIGURASI WHATSAPP
                        </button>
                    </div>
                </div>

                <!-- TAB: GOOGLE ADS -->
                <div v-if="activeTab === 'google_ads'" class="bg-white rounded-3xl border border-slate-100 p-4 sm:p-8 shadow-sm space-y-6">
                    <h3 class="text-xs font-black uppercase tracking-widest text-red-600 mb-6">Konfigurasi Google Ads Webhook</h3>
                    
                    <div class="p-6 bg-red-50 border border-red-100 rounded-2xl mb-6">
                        <p class="text-xs font-bold text-red-800 mb-2">Webhook URL Google Ads Anda:</p>
                        <code class="block bg-white p-3 rounded-xl border border-red-200 text-red-600 font-mono text-xs shadow-inner">
                            https://[domain-anda.com]/api/webhooks/google-ads
                        </code>
                        <p class="text-[10px] text-red-600 mt-2">Masukkan URL ini ke bagian Webhook pada Lead Form Extension di akun Google Ads Anda.</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Google Webhook Key</label>
                            <input v-model="form.google_ads_webhook_key" type="text" placeholder="Masukkan kunci rahasia webhook..." class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-red-600/20" />
                            <p class="text-[10px] text-slate-400 mt-1 italic">Kunci ini digunakan untuk memverifikasi kecocokan data pengirim dari Google Ads (X-Webhook-Key).</p>
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button @click="submit" class="px-10 py-4 bg-red-600 text-white font-black rounded-2xl shadow-xl hover:-translate-y-1 transition-all uppercase text-xs tracking-widest hover:bg-red-700">
                            SIMPAN KONFIGURASI GOOGLE ADS
                        </button>
                    </div>
                </div>

                <!-- TAB: META LEAD ADS -->
                <div v-if="activeTab === 'meta_leads'" class="bg-white rounded-3xl border border-slate-100 p-4 sm:p-8 shadow-sm space-y-6">
                    <h3 class="text-xs font-black uppercase tracking-widest text-blue-600 mb-6">Konfigurasi Meta Lead Ads</h3>
                    
                    <div class="p-6 bg-blue-50 border border-blue-100 rounded-2xl mb-6">
                        <p class="text-xs font-bold text-blue-800 mb-2">Webhook URL Meta Lead Ads Anda:</p>
                        <code class="block bg-white p-3 rounded-xl border border-blue-200 text-blue-600 font-mono text-xs shadow-inner">
                            https://[domain-anda.com]/api/webhooks/meta-leads
                        </code>
                        <p class="text-[10px] text-blue-600 mt-2">Masukkan URL ini ke dashboard Meta Developer App Anda (Produk Webhooks -> Leadgen).</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Meta Verify Token</label>
                            <input v-model="form.meta_leadads_verify_token" type="text" placeholder="Masukkan token verifikasi acak..." class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Meta Permanent Access Token</label>
                            <textarea v-model="form.meta_leadads_access_token" rows="3" placeholder="EAAL..." class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20 font-mono text-xs"></textarea>
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button @click="submit" class="px-10 py-4 bg-blue-600 text-white font-black rounded-2xl shadow-xl hover:-translate-y-1 transition-all uppercase text-xs tracking-widest hover:bg-blue-700">
                            SIMPAN KONFIGURASI META LEADS
                        </button>
                    </div>
                </div>

                <!-- TAB: OMNICHANNEL & AI AUTOPILOT -->
                <div v-if="activeTab === 'omnichannel'" class="bg-white rounded-3xl border border-slate-100 p-4 sm:p-8 shadow-sm space-y-6">
                    <h3 class="text-xs font-black uppercase tracking-widest text-indigo-600 mb-6">Konfigurasi Omnichannel & AI Autopilot</h3>
                    
                    <div class="p-6 bg-indigo-50 border border-indigo-100 rounded-2xl mb-6">
                        <p class="text-xs font-bold text-indigo-800 mb-2">Webhook URL Meta Messaging (FB & IG):</p>
                        <code class="block bg-white p-3 rounded-xl border border-indigo-200 text-indigo-600 font-mono text-xs shadow-inner">
                            https://[domain-anda.com]/api/webhooks/meta-messaging
                        </code>
                        <p class="text-[10px] text-indigo-600 mt-2">Masukkan URL ini ke bagian Webhooks pada produk Messenger / Instagram di dashboard Meta Developers Anda.</p>
                    </div>

                    <div class="space-y-6">
                        <!-- AI Autopilot Switches -->
                        <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 space-y-4">
                            <h4 class="font-black text-slate-800 uppercase tracking-widest text-[10px] border-b border-slate-200 pb-2">Pengaturan AI Autopilot (Balasan Otomatis)</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <label class="flex items-center gap-3 p-3 bg-white rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors">
                                    <input v-model="form.ai_autopilot_whatsapp" type="checkbox" class="rounded text-indigo-600 border-slate-350 w-4 h-4" />
                                    <div class="text-left">
                                        <p class="text-[11px] font-black text-slate-700 leading-none">WhatsApp AI</p>
                                        <p class="text-[9px] text-slate-450 mt-1">Autopilot WhatsApp</p>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 p-3 bg-white rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors">
                                    <input v-model="form.ai_autopilot_messenger" type="checkbox" class="rounded text-indigo-600 border-slate-350 w-4 h-4" />
                                    <div class="text-left">
                                        <p class="text-[11px] font-black text-slate-700 leading-none">Messenger AI</p>
                                        <p class="text-[9px] text-slate-450 mt-1">Autopilot FB Messenger</p>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 p-3 bg-white rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors">
                                    <input v-model="form.ai_autopilot_instagram" type="checkbox" class="rounded text-indigo-600 border-slate-350 w-4 h-4" />
                                    <div class="text-left">
                                        <p class="text-[11px] font-black text-slate-700 leading-none">Instagram AI</p>
                                        <p class="text-[9px] text-slate-450 mt-1">Autopilot IG Direct</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Access Tokens & Verification Tokens -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Meta Page Access Token (FB & IG Messaging)</label>
                                <textarea v-model="form.meta_page_access_token" rows="3" placeholder="Masukkan Token Akses Halaman Meta..." class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-indigo-600/20 font-mono text-xs"></textarea>
                                <p class="text-[9px] text-slate-400 mt-1 italic">Token akses halaman Facebook dengan izin pages_messaging dan instagram_manage_messages.</p>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Facebook Verify Token (Webhook)</label>
                                    <input v-model="form.messenger_verify_token" type="text" placeholder="Verify Token Messenger..." class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-indigo-600/20" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Instagram Verify Token (Webhook)</label>
                                    <input v-model="form.instagram_verify_token" type="text" placeholder="Verify Token Instagram..." class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-indigo-600/20" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button @click="submit" class="px-10 py-4 bg-indigo-600 text-white font-black rounded-2xl shadow-xl hover:-translate-y-1 transition-all uppercase text-xs tracking-widest hover:bg-indigo-700">
                            SIMPAN KONFIGURASI OMNICHANNEL
                        </button>
                    </div>
                </div>

                <!-- TAB: BANKS PARTNER CRUD -->
                <div v-if="activeTab === 'banks'" class="bg-white rounded-3xl border border-slate-100 p-4 sm:p-8 shadow-sm space-y-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-widest text-blue-600">Daftar Bank Partner KPR</h3>
                            <p class="text-xs text-slate-500 mt-1">Daftar bank resmi yang tampil pada simulasi KPR marketing.</p>
                        </div>
                        <button @click="openAddBank" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-black rounded-xl shadow-lg shadow-blue-500/20 transition-all uppercase tracking-widest">
                            ➕ Tambah Bank
                        </button>
                    </div>

                    <div v-if="partner_banks_all && partner_banks_all.length" class="overflow-x-auto rounded-2xl border border-slate-100">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-100">
                                    <th class="px-5 py-3 font-black text-slate-400 uppercase tracking-wider">Nama Bank</th>
                                    <th class="px-5 py-3 font-black text-slate-400 uppercase tracking-wider">Tipe Skema</th>
                                    <th class="px-5 py-3 font-black text-slate-400 uppercase tracking-wider">Detail Suku Bunga</th>
                                    <th class="px-5 py-3 font-black text-slate-400 uppercase tracking-wider">Status</th>
                                    <th class="px-5 py-3 font-black text-slate-400 uppercase tracking-wider text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="bank in partner_banks_all" :key="bank.id" class="hover:bg-slate-50 transition-colors">
                                    <td class="px-5 py-4 font-bold text-slate-900">{{ bank.name }}</td>
                                    <td class="px-5 py-4">
                                        <span v-if="bank.is_syariah" class="px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded-md text-[9px] font-black uppercase">Syariah</span>
                                        <span v-else-if="bank.is_tiered" class="px-2 py-0.5 bg-blue-50 text-blue-700 rounded-md text-[9px] font-black uppercase">Berjenjang</span>
                                        <span v-else class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded-md text-[9px] font-black uppercase">Konvensional</span>
                                    </td>
                                    <td class="px-5 py-4 text-slate-600 font-medium">
                                        <span v-if="bank.is_syariah">Margin Flat: <strong>{{ bank.syariah_margin_rate }}%</strong></span>
                                        <span v-else-if="bank.is_tiered">
                                            Rates: <strong v-if="bank.tiered_rates && bank.tiered_rates.length">
                                                {{ bank.tiered_rates.map(t => t.rate + '%').join(' / ') }}
                                            </strong>
                                        </span>
                                        <span v-else>Fixed: <strong>{{ bank.interest_rate_fixed }}%</strong> ({{ bank.fixed_duration }} thn) / Float: <strong>{{ bank.interest_rate_floating }}%</strong></span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span :class="bank.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'" class="px-2 py-0.5 rounded text-[9px] font-black uppercase">
                                            {{ bank.is_active ? 'Aktif' : 'Non-aktif' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-right space-x-1.5 whitespace-nowrap">
                                        <button @click="openEditBank(bank)" class="text-blue-600 hover:text-blue-800 font-bold text-xs">Edit</button>
                                        <button @click="deleteBank(bank.id)" class="text-rose-650 hover:text-rose-800 font-bold text-xs">Hapus</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center py-12 text-slate-450 font-bold italic border border-dashed border-slate-200 rounded-2xl">
                        Belum ada bank partner yang ditambahkan.
                    </div>

                    <!-- BANK ADD/EDIT MODAL -->
                    <teleport to="body">
                        <div v-if="showBankModal" class="fixed inset-0 z-[150] flex items-center justify-center p-4">
                            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showBankModal = false"></div>
                            <div class="relative bg-white rounded-3xl w-full max-w-lg p-8 shadow-2xl overflow-y-auto max-h-[85vh]">
                                <h3 class="text-base font-black text-slate-900 mb-6 uppercase tracking-wider">
                                    {{ editingBank ? '✏️ Edit Bank Partner' : '🏦 Tambah Bank Partner' }}
                                </h3>

                                <form @submit.prevent="saveBank" class="space-y-4 text-xs">
                                    <div>
                                        <label class="block font-bold text-slate-700 mb-1.5">Nama Bank <span class="text-rose-500">*</span></label>
                                        <input v-model="bankForm.name" type="text" placeholder="Misal: Bank Mandiri" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20" required />
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <label class="flex items-center gap-2 p-3 bg-slate-50 rounded-xl border border-slate-100 cursor-pointer">
                                            <input v-model="bankForm.is_syariah" type="checkbox" class="rounded border-slate-350 text-blue-600" />
                                            <span class="font-bold text-slate-700">Skema Syariah</span>
                                        </label>

                                        <label class="flex items-center gap-2 p-3 bg-slate-50 rounded-xl border border-slate-100 cursor-pointer">
                                            <input v-model="bankForm.is_tiered" type="checkbox" class="rounded border-slate-350 text-blue-600" />
                                            <span class="font-bold text-slate-700">Bunga Berjenjang</span>
                                        </label>
                                    </div>

                                    <!-- Conventional Form Section -->
                                    <div v-if="!bankForm.is_syariah && !bankForm.is_tiered" class="space-y-4 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                        <h4 class="font-black text-slate-700 uppercase tracking-widest text-[9px] mb-1">Skema Konvensional</h4>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">Bunga Fixed (%)</label>
                                                <input v-model.number="bankForm.interest_rate_fixed" type="number" step="0.01" class="w-full px-3 py-2 border border-slate-200 rounded-lg" />
                                            </div>
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1">Tenor Fixed (Tahun)</label>
                                                <input v-model.number="bankForm.fixed_duration" type="number" min="1" class="w-full px-3 py-2 border border-slate-200 rounded-lg" />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block font-bold text-slate-700 mb-1">Asumsi Bunga Floating (%)</label>
                                            <input v-model.number="bankForm.interest_rate_floating" type="number" step="0.01" class="w-full px-3 py-2 border border-slate-200 rounded-lg" />
                                        </div>
                                    </div>

                                    <!-- Syariah Form Section -->
                                    <div v-if="bankForm.is_syariah" class="space-y-4 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                        <h4 class="font-black text-slate-700 uppercase tracking-widest text-[9px] mb-1">Skema Syariah</h4>
                                        <div>
                                            <label class="block font-bold text-slate-700 mb-1">Margin Keuntungan Flat (% / Tahun)</label>
                                            <input v-model.number="bankForm.syariah_margin_rate" type="number" step="0.01" class="w-full px-3 py-2 border border-slate-200 rounded-lg" />
                                        </div>
                                    </div>

                                    <!-- Tiered Form Section -->
                                    <div v-if="bankForm.is_tiered" class="space-y-4 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                        <div class="flex justify-between items-center mb-1">
                                            <h4 class="font-black text-slate-700 uppercase tracking-widest text-[9px]">Skema Berjenjang</h4>
                                            <div class="flex gap-1.5">
                                                <button type="button" @click="removeTier" class="w-5 h-5 bg-white border border-slate-200 rounded flex items-center justify-center text-xs font-bold shadow-sm">-</button>
                                                <span class="font-bold">{{ bankForm.tiered_rates.length }} Tahap</span>
                                                <button type="button" @click="addTier" class="w-5 h-5 bg-white border border-slate-200 rounded flex items-center justify-center text-xs font-bold shadow-sm">+</button>
                                            </div>
                                        </div>
                                        <div class="space-y-2">
                                            <div v-for="(t, idx) in bankForm.tiered_rates" :key="idx" class="flex gap-2 items-center">
                                                <span class="font-bold text-slate-500 w-12">Tahap {{ idx + 1 }}</span>
                                                <input v-model.number="t.rate" type="number" step="0.01" placeholder="Rate" class="w-20 px-2 py-1 border border-slate-250 rounded text-center" />
                                                <span class="text-slate-400">%</span>
                                                <input v-model.number="t.years" type="number" min="1" placeholder="Years" class="w-20 px-2 py-1 border border-slate-250 rounded text-center" />
                                                <span class="text-slate-400">Tahun</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 pt-2">
                                        <input v-model="bankForm.is_active" type="checkbox" id="bank_is_active" class="rounded border-slate-350 text-blue-600" />
                                        <label for="bank_is_active" class="font-bold text-slate-700 cursor-pointer">Bank Aktif (Tampilkan dalam kalkulator sales)</label>
                                    </div>

                                    <div class="flex justify-end gap-3 pt-6 border-t border-slate-100">
                                        <button type="button" @click="showBankModal = false" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-650 font-bold rounded-xl">Batal</button>
                                        <button type="submit" :disabled="bankForm.processing" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg shadow-blue-500/20">
                                            {{ editingBank ? 'Simpan Perubahan' : 'Tambah Bank' }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </teleport>
                </div>

                <!-- TAB: BROKERS / KANTOR AGEN -->
                <div v-if="activeTab === 'brokers'" class="space-y-6">
                    <div class="bg-white rounded-3xl border border-slate-100 p-4 sm:p-8 shadow-sm">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-xs font-black uppercase tracking-widest text-purple-600">🏢 Daftar Kantor Agen / Broker Partner</h3>
                                <p class="text-xs text-slate-500 mt-1">Kelola daftar perusahaan broker berbendera yang bekerja sama dengan Homi.</p>
                            </div>
                            <button @click="openAddBroker" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-purple-500/20">
                                ➕ Tambah Kantor Agen
                            </button>
                        </div>

                        <div v-if="broker_companies_all?.length" class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-wider">
                                        <th class="px-5 py-3">Nama Perusahaan</th>
                                        <th class="px-5 py-3">Contact Person</th>
                                        <th class="px-5 py-3">Telepon / Email</th>
                                        <th class="px-5 py-3 text-right">Default Komisi</th>
                                        <th class="px-5 py-3">Status</th>
                                        <th class="px-5 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="broker in broker_companies_all" :key="broker.id" class="hover:bg-slate-50 transition-colors">
                                        <td class="px-5 py-4 font-bold text-slate-900">{{ broker.name }}</td>
                                        <td class="px-5 py-4 text-slate-700">{{ broker.contact_person || '-' }}</td>
                                        <td class="px-5 py-4">
                                            <p class="font-medium text-slate-800">{{ broker.phone || '-' }}</p>
                                            <p class="text-[10px] text-slate-400">{{ broker.email || '-' }}</p>
                                        </td>
                                        <td class="px-5 py-4 text-right font-black text-purple-600">
                                            {{ broker.commission_rate }}%
                                        </td>
                                        <td class="px-5 py-4">
                                            <span :class="broker.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'" class="px-2 py-0.5 rounded text-[9px] font-black uppercase">
                                                {{ broker.status === 'active' ? 'Aktif' : 'Non-aktif' }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-right space-x-1.5 whitespace-nowrap">
                                            <button @click="openEditBroker(broker)" class="text-blue-600 hover:text-blue-800 font-bold text-xs">Edit</button>
                                            <button @click="deleteBroker(broker.id)" class="text-rose-600 hover:text-rose-800 font-bold text-xs">Hapus</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-center py-12 text-slate-450 font-bold italic border border-dashed border-slate-200 rounded-2xl">
                            Belum ada kantor agen / broker yang ditambahkan.
                        </div>

                        <!-- BROKER ADD/EDIT MODAL -->
                        <teleport to="body">
                            <div v-if="showBrokerModal" class="fixed inset-0 z-[150] flex items-center justify-center p-4">
                                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showBrokerModal = false"></div>
                                <div class="relative bg-white rounded-3xl w-full max-w-lg p-8 shadow-2xl overflow-y-auto max-h-[85vh]">
                                    <h3 class="text-base font-black text-slate-900 mb-6 uppercase tracking-wider">
                                        {{ editingBroker ? '✏️ Edit Kantor Agen' : '🏢 Tambah Kantor Agen' }}
                                    </h3>

                                    <form @submit.prevent="saveBroker" class="space-y-4 text-xs">
                                        <div>
                                            <label class="block font-bold text-slate-700 mb-1.5">Nama Kantor Agen / Broker <span class="text-rose-500">*</span></label>
                                            <input v-model="brokerForm.name" type="text" placeholder="Misal: Ray White Central" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500/20" required />
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1.5">Contact Person (PIC)</label>
                                                <input v-model="brokerForm.contact_person" type="text" placeholder="Nama PIC" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500/20" />
                                            </div>
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1.5">No. Telepon / HP</label>
                                                <input v-model="brokerForm.phone" type="text" placeholder="Nomor Telepon" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500/20" />
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1.5">Email Kantor</label>
                                                <input v-model="brokerForm.email" type="email" placeholder="broker@gmail.com" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500/20" />
                                            </div>
                                            <div>
                                                <label class="block font-bold text-slate-700 mb-1.5">Komisi Default (%)</label>
                                                <input v-model.number="brokerForm.commission_rate" type="number" step="0.01" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500/20" required />
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block font-bold text-slate-700 mb-1.5">Alamat Kantor</label>
                                            <textarea v-model="brokerForm.address" rows="2" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500/20" placeholder="Alamat lengkap kantor agen..."></textarea>
                                        </div>

                                        <div>
                                            <label class="block font-bold text-slate-700 mb-1.5">Status Aktif</label>
                                            <select v-model="brokerForm.status" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500/20 cursor-pointer">
                                                <option value="active">Aktif (Bisa dipilih agen)</option>
                                                <option value="inactive">Non-aktif</option>
                                            </select>
                                        </div>

                                        <div class="flex justify-end gap-3 pt-6 border-t border-slate-100">
                                            <button type="button" @click="showBrokerModal = false" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-650 font-bold rounded-xl">Batal</button>
                                            <button type="submit" :disabled="brokerForm.processing" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl shadow-lg shadow-purple-500/20">
                                                {{ editingBroker ? 'Simpan Perubahan' : 'Tambah Kantor Agen' }}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </teleport>
                    </div>
                </div>

                <!-- TAB: REKENING KAS / BANK -->
                <div v-if="activeTab === 'bank_accounts'" class="space-y-6">
                    <div class="bg-white rounded-3xl border border-slate-100 p-4 sm:p-8 shadow-sm">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-sm font-black text-slate-800 uppercase tracking-wider">Rekening Kas & Bank</h3>
                                <p class="text-[10px] text-slate-400 font-bold mt-0.5">Kelola rekening bank operasional developer dan Petty Cash.</p>
                            </div>
                            <button @click="openAddBankAccount" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-[10px] font-black uppercase tracking-widest rounded-xl transition-all shadow-sm">
                                + Rekening Baru
                            </button>
                        </div>

                        <!-- Bank Accounts Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                        <th class="px-6 py-4">Nama Rekening / Kas</th>
                                        <th class="px-6 py-4">Nama Bank</th>
                                        <th class="px-6 py-4">No. Rekening</th>
                                        <th class="px-6 py-4">Pemilik</th>
                                        <th class="px-6 py-4 text-right">Saldo Saat Ini</th>
                                        <th class="px-6 py-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="acc in bank_accounts_all" :key="acc.id" class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <p class="font-black text-slate-900">{{ acc.name }}</p>
                                        </td>
                                        <td class="px-6 py-4 font-bold text-slate-650">{{ acc.bank_name || '-' }}</td>
                                        <td class="px-6 py-4 font-mono font-bold text-slate-700">{{ acc.account_number || '-' }}</td>
                                        <td class="px-6 py-4 font-bold text-slate-600">{{ acc.account_holder || '-' }}</td>
                                        <td class="px-6 py-4 text-right font-black text-blue-600">{{ new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(acc.current_balance) }}</td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button @click="openEditBankAccount(acc)" class="px-2.5 py-1.5 bg-slate-100 text-slate-600 text-[10px] font-black rounded-lg hover:bg-slate-200 transition-all">Edit</button>
                                                <button @click="deleteBankAccount(acc.id)" class="px-2.5 py-1.5 bg-rose-50 text-rose-600 text-[10px] font-black rounded-lg hover:bg-rose-100 transition-all">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!bank_accounts_all?.length">
                                        <td colspan="6" class="px-6 py-12 text-center text-slate-400 italic font-bold">Belum ada kas/rekening bank terdaftar.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Bank Account Modal -->
                        <teleport to="body">
                            <div v-if="showBankAccountModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4">
                                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showBankAccountModal = false"></div>
                                <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md">
                                    <div class="px-8 py-6 border-b border-slate-100">
                                        <h3 class="text-sm font-black text-slate-800 uppercase">{{ editingBankAccount ? '✏️ Edit Rekening' : '💳 Tambah Rekening Baru' }}</h3>
                                    </div>
                                    <form @submit.prevent="saveBankAccount" class="p-8 space-y-4">
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Nama Rekening / Kas *</label>
                                            <input v-model="bankAccountForm.name" type="text" required class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-slate-500/20" placeholder="Contoh: BCA Operasional, Kas Kecil Kantor" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Nama Bank</label>
                                            <input v-model="bankAccountForm.bank_name" type="text" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-slate-500/20" placeholder="Contoh: Bank Central Asia (kosongkan jika Kas Kecil)" />
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Nomor Rekening</label>
                                                <input v-model="bankAccountForm.account_number" type="text" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-slate-500/20" placeholder="Opsional" />
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Nama Pemilik</label>
                                                <input v-model="bankAccountForm.account_holder" type="text" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-slate-500/20" placeholder="Opsional" />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Saldo Awal (Rp) *</label>
                                            <input v-model="bankAccountForm.initial_balance" type="number" required min="0" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-slate-500/20" />
                                        </div>
                                        <div class="flex justify-end gap-3 pt-6 border-t border-slate-100">
                                            <button type="button" @click="showBankAccountModal = false" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-650 font-bold rounded-xl">Batal</button>
                                            <button type="submit" :disabled="bankAccountForm.processing" class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl shadow-lg">Simpan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </teleport>
                    </div>
                </div>
            </div>

            <!-- SIDE INFO -->
            <div class="space-y-6">
                <div class="bg-slate-900 rounded-3xl p-4 sm:p-8 text-white shadow-xl shadow-slate-200">
                    <div class="w-12 h-12 bg-white/10 rounded-2xl flex items-center justify-center text-2xl mb-6">💡</div>
                    <h4 class="font-black mb-2 uppercase tracking-widest text-xs">Informasi</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Halaman ini mengatur parameter global CRM Homi. Pastikan data yang dimasukkan sudah benar karena akan berdampak langsung pada operasional tim sales dan branding perusahaan.
                    </p>
                </div>

                <div class="bg-white rounded-3xl p-4 sm:p-8 border border-slate-100 shadow-sm">
                    <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center text-2xl mb-6">🛡️</div>
                    <h4 class="font-black mb-2 uppercase tracking-widest text-xs text-slate-800">Keamanan & Audit</h4>
                    <p class="text-xs text-slate-500 leading-relaxed mb-4">
                        Pantau semua aktivitas transaksi, perubahan status unit, booking, dan akses pengguna secara detail.
                    </p>
                    <Link href="/settings/audit-logs" class="inline-block w-full py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-[10px] font-black uppercase tracking-widest rounded-xl text-center transition-all">
                        Buka Audit Logs →
                    </Link>
                </div>
                
                <div v-if="activeTab === 'api'" class="bg-blue-600 rounded-3xl p-4 sm:p-8 text-white shadow-xl shadow-blue-200">
                    <h4 class="font-black mb-2 uppercase tracking-widest text-xs">Petunjuk IT</h4>
                    <p class="text-[11px] text-blue-100 leading-relaxed mb-4">Gunakan header Authorization berikut saat mengirim data dari website:</p>
                    <code class="block bg-blue-700 p-3 rounded-xl text-[10px] font-mono mb-4 border border-blue-500">Authorization: Bearer [TOKEN]</code>
                    <p class="text-[11px] text-blue-100 leading-relaxed italic">Data dikirim dengan format JSON melalui method POST.</p>
                </div>
            </div>
        </div>
    </CrmLayout>
</template>

<style scoped>
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
.scrollbar-none {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
