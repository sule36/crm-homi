<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    companies: Object,
    filters: Object,
    stats: Object,
});

const showAddModal = ref(false);
const editingCompany = ref(null);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    subscription_plan: 'starter',
    status: 'active',
    max_users: 10,
    max_projects: 5,
    admin_name: '',
    admin_email: '',
    admin_password: '',
});

function openAddModal() {
    editingCompany.value = null;
    form.reset();
    form.clearErrors();
    showAddModal.value = true;
}

function openEditModal(company) {
    editingCompany.value = company;
    form.name = company.name;
    form.email = company.email || '';
    form.phone = company.phone || '';
    form.address = company.address || '';
    form.subscription_plan = company.subscription_plan;
    form.status = company.status;
    form.max_users = company.max_users;
    form.max_projects = company.max_projects;
    showAddModal.value = true;
}

function submitForm() {
    if (editingCompany.value) {
        form.put(`/super-admin/companies/${editingCompany.value.id}`, {
            onSuccess: () => {
                showAddModal.value = false;
            }
        });
    } else {
        form.post('/super-admin/companies', {
            onSuccess: () => {
                showAddModal.value = false;
                form.reset();
            }
        });
    }
}

function deleteCompany(company) {
    if (confirm(`Apakah Anda yakin ingin menghapus/menangguhkan developer ${company.name}?`)) {
        router.delete(`/super-admin/companies/${company.id}`);
    }
}

const statusColors = {
    active: 'bg-emerald-100 text-emerald-700',
    trial: 'bg-amber-100 text-amber-700',
    suspended: 'bg-rose-100 text-rose-700',
};

const planBadges = {
    starter: 'bg-blue-50 text-blue-700 border-blue-200',
    pro: 'bg-purple-50 text-purple-700 border-purple-200',
    enterprise: 'bg-amber-50 text-amber-700 border-amber-200',
};
</script>

<template>
    <Head title="Manajemen SaaS Developer (Super Admin)" />
    <CrmLayout>
        <template #breadcrumb>SaaS Platform / Kontrol Developer</template>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">🏢 Kontrol Perusahaan Developer (SaaS Tenants)</h1>
                <p class="text-xs text-slate-400 mt-1">Kelola seluruh pengembang properti berlangganan platform CRM Homi</p>
            </div>
            <button @click="openAddModal" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-blue-500/20 hover:-translate-y-0.5 transition-all flex items-center gap-2">
                <span>+</span> Mendaftarkan Developer Baru
            </button>
        </div>

        <!-- SAAS STATS CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-xl">🏢</div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase">Total Perusahaan</p>
                    <p class="text-xl font-black text-slate-900">{{ stats.total_companies }}</p>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center text-xl">✅</div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase">Perusahaan Aktif</p>
                    <p class="text-xl font-black text-emerald-600">{{ stats.active_companies }}</p>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center text-xl">⏳</div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase">Masa Trial (30 Hari)</p>
                    <p class="text-xl font-black text-amber-600">{{ stats.trial_companies }}</p>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center text-xl">👥</div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase">Total Pengguna Terdaftar</p>
                    <p class="text-xl font-black text-purple-600">{{ stats.total_users }}</p>
                </div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-x-auto max-w-full touch-pan-x">
            <table class="w-full text-left border-collapse min-w-[750px]">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-100">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider">Perusahaan / Developer</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider">Paket Langganan</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider text-center">Pengguna / Kuota</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider text-center">Proyek / Kuota</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider text-center">Status</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-wider text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr v-for="company in companies.data" :key="company.id" class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <span class="text-sm font-black text-slate-900">{{ company.name }}</span>
                            <p class="text-[10px] text-slate-400 mt-0.5">{{ company.email || 'Email belum diisi' }} • {{ company.phone || '-' }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <span :class="planBadges[company.subscription_plan]" class="inline-block px-3 py-1 rounded-lg border text-xs font-black uppercase">
                                {{ company.subscription_plan }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-xs font-bold text-slate-700">{{ company.users_count }} / {{ company.max_users }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-xs font-bold text-slate-700">{{ company.projects_count }} / {{ company.max_projects }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span :class="statusColors[company.status]" class="inline-block px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                {{ company.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button @click="openEditModal(company)" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-lg transition-all">
                                    Edit Paket
                                </button>
                                <button @click="deleteCompany(company)" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-bold rounded-lg transition-all">
                                    Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!companies.data.length">
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400 text-xs">Belum ada perusahaan developer terdaftar.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ADD/EDIT MODAL -->
        <div v-if="showAddModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showAddModal = false"></div>
            <div class="relative bg-white rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl p-8 animate-in zoom-in duration-150">
                <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-4">
                    {{ editingCompany ? 'Edit Paket & Batas Developer' : 'Mendaftarkan Perusahaan Developer Baru' }}
                </h3>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Nama Perusahaan Developer</label>
                        <input v-model="form.name" type="text" placeholder="PT. Serangkai Roden Development" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" required />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Email Perusahaan</label>
                            <input v-model="form.email" type="email" placeholder="info@developer.com" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">No. Telp / WA</label>
                            <input v-model="form.phone" type="text" placeholder="08123456789" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Paket SaaS</label>
                            <select v-model="form.subscription_plan" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold">
                                <option value="starter">Starter Plan</option>
                                <option value="pro">Pro Plan</option>
                                <option value="enterprise">Enterprise Plan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Status Langganan</label>
                            <select v-model="form.status" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold">
                                <option value="active">Active</option>
                                <option value="trial">Trial (30 Hari)</option>
                                <option value="suspended">Suspended (Ditangguhkan)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Batas Maksimal Pengguna</label>
                            <input v-model.number="form.max_users" type="number" min="1" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" required />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Batas Maksimal Proyek</label>
                            <input v-model.number="form.max_projects" type="number" min="1" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold" required />
                        </div>
                    </div>

                    <!-- ADMIN REGISTRATION (NEW TENANT ONLY) -->
                    <div v-if="!editingCompany" class="p-4 bg-blue-50/50 border border-blue-100 rounded-2xl space-y-3">
                        <span class="text-xs font-black text-blue-900 uppercase">Akun Project Manager / Admin Developer Pertama</span>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Nama Admin</label>
                            <input v-model="form.admin_name" type="text" placeholder="Luhur Wira" class="w-full px-3 py-2 bg-white border border-blue-200 rounded-xl text-xs font-bold" required />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Email Admin Login</label>
                                <input v-model="form.admin_email" type="email" placeholder="admin@developer.com" class="w-full px-3 py-2 bg-white border border-blue-200 rounded-xl text-xs font-bold" required />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Password</label>
                                <input v-model="form.admin_password" type="password" placeholder="••••••••" class="w-full px-3 py-2 bg-white border border-blue-200 rounded-xl text-xs font-bold" required />
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 flex justify-end gap-2">
                        <button type="button" @click="showAddModal = false" class="px-4 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                        <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-blue-600 text-white text-xs font-black rounded-xl shadow-lg">
                            {{ editingCompany ? 'Simpan Perubahan' : 'Daftarkan Developer' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </CrmLayout>
</template>
