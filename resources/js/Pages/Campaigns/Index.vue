<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    campaigns: Array,
    projects: Array,
});

const showModal = ref(false);
const form = useForm({
    name: '',
    platform: 'facebook',
    budget: '',
    project_id: '',
    utm_campaign: '',
    start_date: '',
    end_date: '',
});

const submit = () => {
    form.post('/campaigns', {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        }
    });
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
};

const platformColors = {
    facebook: 'bg-blue-100 text-blue-600',
    instagram: 'bg-pink-100 text-pink-600',
    google: 'bg-red-100 text-red-600',
    tiktok: 'bg-slate-900 text-white',
    offline: 'bg-amber-100 text-amber-600',
};
</script>

<template>
    <Head title="Marketing Campaigns" />
    <CrmLayout>
        <template #breadcrumb>Campaign Marketing</template>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Campaign Tracker</h1>
                <p class="text-sm text-slate-500 mt-1">Lacak performa iklan dan biaya per lead (CPL).</p>
            </div>
            <button @click="showModal = true"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Tambah Campaign
            </button>
        </div>

        <div v-if="campaigns.length === 0" class="bg-white rounded-3xl border border-slate-100 p-16 text-center shadow-sm">
            <p class="text-5xl mb-4">📢</p>
            <h3 class="text-lg font-black text-slate-800">Belum Ada Campaign</h3>
            <p class="text-sm text-slate-500 mt-2 max-w-sm mx-auto">Silakan tambahkan campaign iklan baru untuk mulai melacak performa leads dan biaya pemasaran properti Anda.</p>
            <button @click="showModal = true" class="mt-6 px-6 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white text-xs font-black uppercase tracking-widest rounded-xl shadow-lg shadow-emerald-500/20 hover:shadow-emerald-500/30 transition-all">
                Buat Campaign Pertama
            </button>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="c in campaigns" :key="c.id" class="bg-white rounded-3xl border border-slate-100 p-6 shadow-sm hover:shadow-xl transition-all">
                <div class="flex justify-between items-start mb-4">
                    <span :class="platformColors[c.platform]" class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest">
                        {{ c.platform }}
                    </span>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ c.status }}</span>
                </div>
                
                <h3 class="text-lg font-black text-slate-900 mb-1">{{ c.name }}</h3>
                <p class="text-[10px] text-slate-400 font-bold uppercase mb-6">{{ c.project?.name || 'All Projects' }}</p>

                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase mb-1">Budget</p>
                        <p class="text-sm font-black text-slate-900">{{ formatCurrency(c.budget) }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase mb-1">Total Leads</p>
                        <p class="text-sm font-black text-blue-600">{{ c.leads_count }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase mb-1">Closing</p>
                        <p class="text-sm font-black text-emerald-600">{{ c.conversions_count }} Unit</p>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-50 space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Cost Per Lead (CPL)</span>
                        <span class="text-xs font-black text-slate-700">{{ formatCurrency(c.cost_per_lead) }}</span>
                    </div>
                    <div class="flex justify-between items-center bg-indigo-50/50 p-2 rounded-lg border border-indigo-100/50">
                        <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">Biaya Akuisisi (CAC)</span>
                        <span class="text-sm font-black text-indigo-700">{{ formatCurrency(c.cost_per_acquisition) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL ADD CAMPAIGN -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showModal = false"></div>
            <div class="relative bg-white rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-8">
                <h3 class="text-xl font-black text-slate-900 mb-6 uppercase tracking-tight">Tambah Campaign Iklan</h3>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Nama Campaign <span class="text-rose-500">*</span></label>
                        <input v-model="form.name" type="text" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" placeholder="Contoh: Promo Ramadhan FB" />
                        <p v-if="form.errors.name" class="text-xs text-rose-500 mt-1 font-bold">{{ form.errors.name }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Platform <span class="text-rose-500">*</span></label>
                            <select v-model="form.platform" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20">
                                <option value="facebook">Facebook</option>
                                <option value="instagram">Instagram</option>
                                <option value="google">Google Ads</option>
                                <option value="tiktok">TikTok</option>
                                <option value="offline">Offline / Event</option>
                            </select>
                            <p v-if="form.errors.platform" class="text-xs text-rose-500 mt-1 font-bold">{{ form.errors.platform }}</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Budget Iklan <span class="text-rose-500">*</span></label>
                            <input v-model="form.budget" type="number" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" />
                            <p v-if="form.errors.budget" class="text-xs text-rose-500 mt-1 font-bold">{{ form.errors.budget }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Proyek</label>
                        <select v-model="form.project_id" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20">
                            <option value="">Semua Proyek (Global)</option>
                            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <p v-if="form.errors.project_id" class="text-xs text-rose-500 mt-1 font-bold">{{ form.errors.project_id }}</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">UTM Campaign (ID Lacak)</label>
                        <input v-model="form.utm_campaign" type="text" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" placeholder="ID yang dipasang di iklan" />
                        <p v-if="form.errors.utm_campaign" class="text-xs text-rose-500 mt-1 font-bold">{{ form.errors.utm_campaign }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tanggal Mulai</label>
                            <input v-model="form.start_date" type="date" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" />
                            <p v-if="form.errors.start_date" class="text-xs text-rose-500 mt-1 font-bold">{{ form.errors.start_date }}</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Tanggal Selesai</label>
                            <input v-model="form.end_date" type="date" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" />
                            <p v-if="form.errors.end_date" class="text-xs text-rose-500 mt-1 font-bold">{{ form.errors.end_date }}</p>
                        </div>
                    </div>
                    <div class="pt-4 flex gap-3">
                        <button type="submit" :disabled="form.processing"
                            class="flex-1 py-4 bg-emerald-600 text-white font-black rounded-2xl shadow-xl hover:-translate-y-1 transition-all">
                            SIMPAN CAMPAIGN
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </CrmLayout>
</template>
