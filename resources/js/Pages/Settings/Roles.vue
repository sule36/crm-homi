<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    roles: Array,
    permissionsGrouped: Object,
    allPermissions: Array,
});

const selectedRole = ref(props.roles[0] || null);
const showAddRoleModal = ref(false);

const permissionForm = useForm({
    permissions: selectedRole.value ? [...selectedRole.value.permissions] : [],
});

function selectRole(role) {
    selectedRole.value = role;
    permissionForm.permissions = [...role.permissions];
}

function togglePermission(permName) {
    const idx = permissionForm.permissions.indexOf(permName);
    if (idx > -1) {
        permissionForm.permissions.splice(idx, 1);
    } else {
        permissionForm.permissions.push(permName);
    }
}

function toggleGroup(groupPermissions) {
    const groupNames = groupPermissions.map(p => p.name);
    const allSelected = groupNames.every(name => permissionForm.permissions.includes(name));
    
    if (allSelected) {
        permissionForm.permissions = permissionForm.permissions.filter(p => !groupNames.includes(p));
    } else {
        groupNames.forEach(name => {
            if (!permissionForm.permissions.includes(name)) {
                permissionForm.permissions.push(name);
            }
        });
    }
}

function savePermissions() {
    if (!selectedRole.value) return;
    permissionForm.put(`/settings/roles/${selectedRole.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            // Update local selected role permissions
            const updated = props.roles.find(r => r.id === selectedRole.value.id);
            if (updated) updated.permissions = [...permissionForm.permissions];
        }
    });
}

const addRoleForm = useForm({
    name: '',
    permissions: [],
});

function submitAddRole() {
    addRoleForm.post('/settings/roles', {
        onSuccess: () => {
            showAddRoleModal.value = false;
            addRoleForm.reset();
        }
    });
}

function deleteRole(role) {
    if (confirm(`Apakah Anda yakin ingin menghapus role ${role.name}?`)) {
        router.delete(`/settings/roles/${role.id}`);
    }
}

const moduleTitles = {
    users: '👤 Pengguna & Pengadaan Staff',
    projects: '🏢 Proyek Properti',
    inventory: '🏡 Inventoris & Unit',
    leads: '🎯 Leads & Konsumen',
    bookings: '📋 Booking & Transaksi',
    payments: '💰 Keuangan & Pembayaran',
    reports: '📊 Laporan & Analytics',
    documents: '📄 Dokumen & SPR',
    settings: '⚙️ Pengaturan Sistem',
};
</script>

<template>
    <Head title="Manajemen Peran & Hak Akses (RBAC)" />
    <CrmLayout>
        <template #breadcrumb>Pengaturan / Hak Akses (RBAC)</template>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Manajemen Peran & Hak Akses (RBAC)</h1>
                <p class="text-xs text-slate-400 mt-1">Atur izin dan tingkat akses granular untuk setiap peranan pengguna di aplikasi</p>
            </div>
            <button @click="showAddRoleModal = true" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-lg shadow-blue-500/20 hover:-translate-y-0.5 transition-all flex items-center gap-2">
                <span>+</span> Tambah Peran/Role Baru
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- LEFT COLUMN: ROLES LIST -->
            <div class="lg:col-span-1 space-y-3">
                <div class="bg-white rounded-2xl border border-slate-100 p-4 shadow-sm">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-wider mb-3 px-2">Daftar Peran (Roles)</h3>
                    <div class="space-y-1.5">
                        <button v-for="role in roles" :key="role.id" @click="selectRole(role)"
                            :class="selectedRole?.id === role.id ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'bg-slate-50 text-slate-700 hover:bg-slate-100'"
                            class="w-full text-left px-4 py-3 rounded-xl font-bold text-xs transition-all flex items-center justify-between group">
                            <div>
                                <span class="capitalize block">{{ role.name.replace('_', ' ') }}</span>
                                <span class="text-[10px] opacity-75 font-medium">{{ role.users_count }} pengguna</span>
                            </div>
                            <span v-if="!['super_admin', 'project_manager', 'sales_manager', 'sales_agent', 'finance'].includes(role.name)" 
                                @click.stop="deleteRole(role)" class="opacity-0 group-hover:opacity-100 text-rose-300 hover:text-white p-1">
                                🗑️
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: PERMISSION MATRIX -->
            <div class="lg:col-span-3">
                <div v-if="selectedRole" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
                        <div>
                            <h2 class="text-base font-black text-slate-900 capitalize flex items-center gap-2">
                                <span>🔑</span> <span>Hak Akses Peran: {{ selectedRole.name.replace('_', ' ') }}</span>
                            </h2>
                            <p class="text-xs text-slate-400">Pilih izin modul yang dapat diakses oleh role ini.</p>
                        </div>
                        <button @click="savePermissions" :disabled="permissionForm.processing" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black rounded-xl shadow-lg shadow-emerald-500/20 uppercase transition-all">
                            Simpan Perubahan Hak Akses
                        </button>
                    </div>

                    <!-- PERMISSIONS BY MODULE -->
                    <div class="space-y-6">
                        <div v-for="(perms, moduleGroup) in permissionsGrouped" :key="moduleGroup" class="p-4 bg-slate-50/70 rounded-2xl border border-slate-100">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-xs font-black text-slate-800 uppercase tracking-wide">
                                    {{ moduleTitles[moduleGroup] || moduleGroup }}
                                </h4>
                                <button type="button" @click="toggleGroup(perms)" class="text-[10px] font-bold text-blue-600 hover:underline">
                                    Pilih Semua Modul Ini
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                <label v-for="p in perms" :key="p.id" 
                                    class="flex items-center gap-2.5 p-2.5 bg-white rounded-xl border border-slate-200/80 hover:border-blue-300 cursor-pointer transition-all select-none">
                                    <input type="checkbox" :checked="permissionForm.permissions.includes(p.name)" @change="togglePermission(p.name)" class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500" />
                                    <span class="text-xs font-bold text-slate-700 capitalize">{{ p.name.replace('.', ' → ').replace('_', ' ') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 flex justify-end">
                        <button @click="savePermissions" :disabled="permissionForm.processing" class="px-8 py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black rounded-xl shadow-lg shadow-emerald-500/20 uppercase transition-all">
                            Simpan Perubahan Hak Akses
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ADD ROLE MODAL -->
        <div v-if="showAddRoleModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showAddRoleModal = false"></div>
            <div class="relative bg-white rounded-3xl w-full max-w-md overflow-hidden shadow-2xl p-8 animate-in zoom-in duration-150">
                <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-4">Tambah Role / Peran Baru</h3>
                <form @submit.prevent="submitAddRole" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Nama Peran (Role Name)</label>
                        <input v-model="addRoleForm.name" type="text" placeholder="misal: legal_officer / supervisor" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-xs font-bold text-slate-800" required />
                    </div>
                    <div class="pt-3 flex justify-end gap-2">
                        <button type="button" @click="showAddRoleModal = false" class="px-4 py-2.5 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl">Batal</button>
                        <button type="submit" :disabled="addRoleForm.processing" class="px-6 py-2.5 bg-blue-600 text-white text-xs font-black rounded-xl shadow-lg">Simpan Role</button>
                    </div>
                </form>
            </div>
        </div>
    </CrmLayout>
</template>
