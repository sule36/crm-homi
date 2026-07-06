<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    projects: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');

let timeout;
watch(search, (val) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get('/projects', { search: val, status: statusFilter.value }, { preserveState: true, replace: true });
    }, 400);
});

watch(statusFilter, (val) => {
    router.get('/projects', { search: search.value, status: val }, { preserveState: true, replace: true });
});

function deleteProject(id) {
    if (confirm('Yakin ingin menghapus proyek ini?')) {
        router.delete(`/projects/${id}`);
    }
}

const statusColors = {
    upcoming: 'bg-amber-100 text-amber-700',
    active: 'bg-emerald-100 text-emerald-700',
    completed: 'bg-blue-100 text-blue-700',
};

const statusLabels = { upcoming: 'Upcoming', active: 'Aktif', completed: 'Selesai' };
</script>

<template>
    <Head title="Proyek" />
    <CrmLayout>
        <template #breadcrumb>Proyek</template>

        <!-- HEADER -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Proyek</h1>
                <p class="text-sm text-slate-500 mt-1">Kelola semua proyek properti developer</p>
            </div>
            <Link href="/projects/create"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Tambah Proyek
            </Link>
        </div>

        <!-- FILTERS -->
        <div class="flex flex-col sm:flex-row gap-3 mb-6">
            <div class="relative flex-1 max-w-md">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input v-model="search" type="text" placeholder="Cari proyek..." class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" />
            </div>
            <select v-model="statusFilter" class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                <option value="">Semua Status</option>
                <option value="upcoming">Upcoming</option>
                <option value="active">Aktif</option>
                <option value="completed">Selesai</option>
            </select>
        </div>

        <!-- PROJECTS GRID -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            <div v-for="project in projects.data" :key="project.id"
                class="bg-white rounded-2xl border border-slate-100 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden group">
                
                <!-- Card Header with Gradient -->
                <div class="relative h-32 bg-gradient-to-br from-slate-800 to-slate-900 overflow-hidden">
                    <img v-if="project.logo" :src="`/storage/${project.logo}`" class="absolute inset-0 w-full h-full object-cover opacity-40 group-hover:opacity-50 transition-opacity" />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                    <div class="absolute bottom-3 left-4 right-4">
                        <span :class="statusColors[project.status]" class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider mb-1.5">
                            {{ statusLabels[project.status] }}
                        </span>
                        <h3 class="text-white font-bold text-lg leading-tight truncate">{{ project.name }}</h3>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="p-4">
                    <div class="flex items-center gap-1.5 text-xs text-slate-500 mb-4">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ project.location }}
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <div class="text-center p-2 bg-slate-50 rounded-lg">
                            <p class="text-lg font-black text-slate-900">{{ project.units_count || 0 }}</p>
                            <p class="text-[10px] text-slate-500 font-medium">Unit</p>
                        </div>
                        <div class="text-center p-2 bg-slate-50 rounded-lg">
                            <p class="text-lg font-black text-blue-600">{{ project.leads_count || 0 }}</p>
                            <p class="text-[10px] text-slate-500 font-medium">Leads</p>
                        </div>
                        <div class="text-center p-2 bg-slate-50 rounded-lg">
                            <p class="text-lg font-black text-emerald-600">{{ project.bookings_count || 0 }}</p>
                            <p class="text-[10px] text-slate-500 font-medium">Booking</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <Link :href="`/projects/${project.id}`" class="flex-1 text-center py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition-colors">Detail</Link>
                        <Link :href="`/projects/${project.id}/edit`" class="flex-1 text-center py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 text-xs font-bold rounded-lg transition-colors">Edit</Link>
                        <button @click="deleteProject(project.id)" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-500 rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- EMPTY STATE -->
        <div v-if="!projects.data.length" class="text-center py-20">
            <div class="w-20 h-20 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4 text-3xl">🏗️</div>
            <h3 class="text-lg font-bold text-slate-700">Belum ada proyek</h3>
            <p class="text-sm text-slate-500 mt-1">Mulai dengan menambahkan proyek pertama Anda</p>
            <Link href="/projects/create" class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition-colors">
                Tambah Proyek
            </Link>
        </div>

        <!-- PAGINATION -->
        <div v-if="projects.links && projects.links.length > 3" class="flex justify-center gap-1 mt-8">
            <Link v-for="link in projects.links" :key="link.label" :href="link.url || '#'"
                :class="[link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-slate-600 hover:bg-slate-50', !link.url ? 'opacity-40 pointer-events-none' : '']"
                class="px-3.5 py-2 text-xs font-bold rounded-lg border border-slate-200 transition-all"
                v-html="link.label" />
        </div>
    </CrmLayout>
</template>
