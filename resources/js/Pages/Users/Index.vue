<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    users: Object,
    roles: Array,
    projects: Array,
    broker_companies: Array,
});

const showModal = ref(false);
const editMode = ref(false);
const selectedUser = ref(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: 'sales_agent',
    project_id: '',
    broker_company_id: '',
    phone: '',
    commission_rate: 1.00,
    bank_name: '',
    bank_account_number: '',
    bank_account_name: '',
});

const openCreateModal = () => {
    editMode.value = false;
    form.reset();
    showModal.value = true;
};

const openEditModal = (user) => {
    editMode.value = true;
    selectedUser.value = user;
    form.name = user.name;
    form.email = user.email;
    form.role = user.roles[0]?.name || '';
    form.project_id = user.project_id || '';
    form.broker_company_id = user.broker_company_id || '';
    form.phone = user.phone || '';
    form.commission_rate = user.commission_rate || 1.00;
    form.bank_name = user.bank_name || '';
    form.bank_account_number = user.bank_account_number || '';
    form.bank_account_name = user.bank_account_name || '';
    showModal.value = true;
};

const submit = () => {
    if (editMode.value) {
        form.put(`/users/${selectedUser.value.id}`, {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post('/users', {
            onSuccess: () => closeModal(),
        });
    }
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
};

const deleteUser = (id) => {
    if (confirm('Yakin ingin menghapus user ini?')) {
        form.delete(`/users/${id}`);
    }
};
</script>

<template>
    <Head title="Manajemen Staff" />
    <CrmLayout>
        <template #breadcrumb>Pengaturan / Staff</template>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Manajemen Staff</h1>
                <p class="text-sm text-slate-500 mt-1">Kelola akses tim sales, finance, dan manajer proyek.</p>
            </div>
            <button @click="openCreateModal"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Tambah Staff
            </button>
        </div>

        <!-- USERS TABLE -->
        <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider">Nama & Email</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider">Proyek</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider">Afiliasi Broker</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider">Telepon</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center text-white text-xs font-black shadow-lg">
                                    {{ user.name.charAt(0) }}
                                </div>
                                <div>
                                    <p class="text-sm font-black text-slate-900">{{ user.name }}</p>
                                    <p class="text-[10px] text-slate-400 font-medium">{{ user.email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span v-for="role in user.roles" :key="role.id" class="px-2.5 py-1 bg-blue-50 text-blue-600 rounded-lg text-[10px] font-black uppercase tracking-wider">
                                {{ role.name.replace('_', ' ') }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-xs font-bold text-slate-650">{{ user.project?.name || 'All Projects' }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span v-if="user.broker_company" class="px-2.5 py-1 bg-purple-50 text-purple-600 rounded-lg text-[10px] font-black uppercase tracking-wider">
                                🏢 {{ user.broker_company.name }}
                            </span>
                            <span v-else class="px-2.5 py-1 bg-slate-50 text-slate-500 rounded-lg text-[10px] font-bold uppercase tracking-wider">
                                Independen
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-xs text-slate-500">{{ user.phone || '-' }}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button @click="openEditModal(user)" class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 hover:bg-blue-600 hover:text-white transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                                <button @click="deleteUser(user.id)" class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 hover:bg-rose-600 hover:text-white transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- MODAL -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeModal"></div>
            <div class="relative bg-white rounded-3xl w-full max-w-md overflow-hidden shadow-2xl animate-in zoom-in duration-200">
                <div class="p-8">
                    <h3 class="text-xl font-black text-slate-900 mb-2">{{ editMode ? 'Edit Staff' : 'Tambah Staff Baru' }}</h3>
                    <p class="text-xs text-slate-400 mb-6">Pastikan email yang dimasukkan valid untuk login.</p>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Nama Lengkap</label>
                            <input v-model="form.name" type="text" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" placeholder="Contoh: Budi Santoso" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Email Address</label>
                            <input v-model="form.email" type="email" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" placeholder="budi@homi.id" />
                        </div>
                        <div v-if="!editMode">
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Password</label>
                            <input v-model="form.password" type="password" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Role Access</label>
                                <select v-model="form.role" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20 cursor-pointer">
                                    <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name.replace('_', ' ') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Assignment Proyek</label>
                                <select v-model="form.project_id" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20 cursor-pointer">
                                    <option value="">All Projects</option>
                                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="form.role === 'sales_agent'" class="space-y-1.5">
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Afiliasi Kantor Agen / Broker</label>
                            <select v-model="form.broker_company_id" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20 cursor-pointer">
                                <option value="">Independent Agent (Tidak Berafiliasi)</option>
                                <option v-for="broker in broker_companies" :key="broker.id" :value="broker.id">🏢 {{ broker.name }}</option>
                            </select>
                            <p class="text-[10px] text-slate-450 font-bold italic">Jika agen independen, biarkan kosong. Jika agen berbendera, pilih nama kantor agen di atas.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Telepon</label>
                                <input v-model="form.phone" type="text" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" placeholder="08xxxx" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Komisi (%)</label>
                                <input v-model="form.commission_rate" type="number" step="0.01" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" />
                            </div>
                        </div>

                        <div class="p-4 bg-blue-50/50 rounded-2xl border border-blue-100/50 space-y-4">
                            <p class="text-[10px] font-black text-blue-600 uppercase tracking-widest">Informasi Bank (Untuk Komisi)</p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Bank</label>
                                    <input v-model="form.bank_name" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-blue-600/20" placeholder="BCA/Mandiri" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">No. Rekening</label>
                                    <input v-model="form.bank_account_number" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-blue-600/20" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nama Di Rekening</label>
                                <input v-model="form.bank_account_name" type="text" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-xs focus:ring-2 focus:ring-blue-600/20" />
                            </div>
                        </div>

                        <div class="pt-4 flex gap-3">
                            <button type="submit" :disabled="form.processing"
                                class="flex-1 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-black rounded-2xl shadow-xl shadow-blue-600/30 hover:-translate-y-1 transition-all">
                                {{ form.processing ? 'Saving...' : 'SAVE STAFF' }}
                            </button>
                            <button type="button" @click="closeModal" class="px-6 py-4 text-xs font-bold text-slate-400 hover:text-slate-600 transition-colors">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </CrmLayout>
</template>
