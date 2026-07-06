<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ project: Object });

const showModal = ref(false);
const editingType = ref(null);

const form = useForm({
    project_id: props.project.id,
    name: '',
    code: '',
    building_area: '',
    land_area: '',
    bedrooms: '',
    bathrooms: '',
    current_price: '',
});

function openModal(type = null) {
    editingType.value = type;
    if (type) {
        form.name = type.name;
        form.code = type.code;
        form.building_area = type.building_area;
        form.land_area = type.land_area;
        form.bedrooms = type.bedrooms;
        form.bathrooms = type.bathrooms;
        form.current_price = type.current_price;
    } else {
        form.reset();
        form.project_id = props.project.id;
    }
    showModal.value = true;
}

function submit() {
    if (editingType.value) {
        form.put(`/unit-types/${editingType.value.id}`, {
            onSuccess: () => { showModal.value = false; form.reset(); }
        });
    } else {
        form.post('/unit-types', {
            onSuccess: () => { showModal.value = false; form.reset(); }
        });
    }
}

function deleteUnitType(type) {
    if (confirm(`Yakin ingin menghapus tipe unit "${type.name}"?`)) {
        router.delete(`/unit-types/${type.id}`, {
            preserveScroll: true,
        });
    }
}

const statusColors = { upcoming: 'bg-amber-100 text-amber-700', active: 'bg-emerald-100 text-emerald-700', completed: 'bg-blue-100 text-blue-700' };
const statusLabels = { upcoming: 'Upcoming', active: 'Aktif', completed: 'Selesai' };

const occupancy = computed(() => {
    if (!props.project.units_count) return 0;
    const sold = props.project.unit_types?.reduce((sum, t) => sum + (t.units?.filter(u => u.status === 'sold').length || 0), 0) || 0;
    return Math.round((sold / props.project.units_count) * 100);
});

const unitStats = computed(() => {
    const units = props.project.unit_types?.flatMap(t => t.units) || [];
    return {
        available: units.filter(u => u.status === 'available').length,
        hold: units.filter(u => u.status === 'hold').length,
        booked: units.filter(u => u.status === 'booked').length,
        sold: units.filter(u => u.status === 'sold').length,
    };
});
</script>

<template>
    <Head :title="project.name" />
    <CrmLayout>
        <template #breadcrumb>
            <span class="text-gray-400">Proyek</span> / {{ project.name }}
        </template>

        <!-- HEADER -->
        <div class="flex flex-col lg:flex-row gap-6 mb-8">
            <div class="flex-1">
                <div class="flex items-center gap-3 mb-2">
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ project.name }}</h1>
                    <span :class="statusColors[project.status]" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider">{{ statusLabels[project.status] }}</span>
                </div>
                <p class="text-sm text-slate-500 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                    {{ project.location }} — {{ project.address }}
                </p>
            </div>
            <div class="flex gap-2 shrink-0">
                <Link :href="`/projects/${project.id}/edit`" class="px-5 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition-colors">Edit Proyek</Link>
            </div>
        </div>

        <!-- STATS ROW -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-2xl border border-slate-100 p-4 text-center shadow-sm">
                <p class="text-2xl font-black text-slate-900">{{ project.units_count || 0 }}</p>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5">Total Unit</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-100 p-4 text-center shadow-sm">
                <p class="text-2xl font-black text-emerald-600">{{ unitStats.available }}</p>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5">Tersedia</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-100 p-4 text-center shadow-sm">
                <p class="text-2xl font-black text-amber-500">{{ unitStats.hold }}</p>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5">Hold (Pending)</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-100 p-4 text-center shadow-sm">
                <p class="text-2xl font-black text-indigo-600">{{ unitStats.booked }}</p>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5">Booked</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-100 p-4 text-center shadow-sm">
                <p class="text-2xl font-black text-blue-600">{{ unitStats.sold }}</p>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5">Terjual</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-100 p-4 text-center shadow-sm">
                <p class="text-2xl font-black text-indigo-600">{{ occupancy }}%</p>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5">Okupansi</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- LEFT: Description + Amenities -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3">Deskripsi</h2>
                    <p class="text-sm text-slate-600 leading-relaxed whitespace-pre-line">{{ project.description || 'Belum ada deskripsi.' }}</p>
                </div>

                <!-- UNIT TYPES -->
                <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Tipe Unit</h2>
                        <button @click="openModal()" class="px-3 py-1.5 bg-blue-50 text-blue-600 text-[10px] font-black uppercase tracking-widest rounded-lg hover:bg-blue-100 transition-colors">+ Tambah Tipe</button>
                    </div>
                    
                    <div v-if="project.unit_types?.length" class="space-y-3">
                        <div v-for="type in project.unit_types" :key="type.id" class="group flex items-center justify-between p-4 bg-slate-50 hover:bg-white hover:shadow-md hover:ring-1 hover:ring-slate-100 rounded-2xl transition-all">
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-bold text-slate-900">{{ type.name }}</p>
                                    <span class="px-1.5 py-0.5 bg-slate-200 text-[9px] font-bold rounded text-slate-600">{{ type.code }}</span>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-0.5">LB {{ type.building_area }}m² / LT {{ type.land_area }}m² • {{ type.bedrooms }} KT {{ type.bathrooms }} KM</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p class="text-sm font-black text-blue-600">Rp {{ Number(type.current_price).toLocaleString('id-ID') }}</p>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter">{{ type.units?.length || 0 }} Unit</p>
                                </div>
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-all">
                                    <button @click="openModal(type)" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button @click="deleteUnitType(type)" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-8 bg-slate-50 rounded-2xl border-2 border-dashed border-slate-100">
                        <p class="text-sm text-slate-400">Belum ada tipe unit yang terdaftar.</p>
                    </div>
                </div>

        <!-- UNIT TYPE MODAL -->
        <div v-if="showModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl w-full max-w-xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-lg font-black text-slate-900 uppercase tracking-tight">{{ editingType ? 'Edit Tipe Unit' : 'Tambah Tipe Unit' }}</h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form @submit.prevent="submit" class="p-8 space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Nama Tipe (Contoh: Tipe 36 Emerald)</label>
                            <input v-model="form.name" type="text" required class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Kode Tipe (Contoh: T36)</label>
                            <input v-model="form.code" type="text" required class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20 uppercase" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Harga Jual (Rp)</label>
                            <input v-model="form.current_price" type="number" required class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Luas Bangunan (m²)</label>
                            <input v-model="form.building_area" type="number" required class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Luas Tanah (m²)</label>
                            <input v-model="form.land_area" type="number" required class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Kamar Tidur</label>
                            <input v-model="form.bedrooms" type="number" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1.5">Kamar Mandi</label>
                            <input v-model="form.bathrooms" type="number" class="w-full px-4 py-3 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-600/20" />
                        </div>
                    </div>
                    <div class="pt-4 flex gap-3">
                        <button type="button" @click="showModal = false" class="flex-1 py-3 bg-slate-100 text-slate-600 font-black rounded-xl text-[10px] uppercase tracking-widest hover:bg-slate-200">Batal</button>
                        <button type="submit" :disabled="form.processing" class="flex-1 py-3 bg-blue-600 text-white font-black rounded-xl text-[10px] uppercase tracking-widest shadow-lg shadow-blue-500/20 hover:bg-blue-700">
                            {{ editingType ? 'Update Tipe' : 'Simpan Tipe' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

                <!-- RECENT LEADS -->
                <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-4">Lead Terbaru</h2>
                    <div v-if="project.leads?.length" class="space-y-2">
                        <div v-for="lead in project.leads" :key="lead.id" class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                            <div>
                                <p class="text-sm font-bold text-slate-900">{{ lead.name }}</p>
                                <p class="text-xs text-slate-500">{{ lead.phone }}</p>
                            </div>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full"
                                :class="{
                                    'bg-blue-100 text-blue-600': lead.status === 'new',
                                    'bg-amber-100 text-amber-600': lead.status === 'contacted',
                                    'bg-emerald-100 text-emerald-600': lead.status === 'won',
                                    'bg-rose-100 text-rose-600': lead.status === 'lost',
                                    'bg-purple-100 text-purple-600': ['visited', 'negotiation'].includes(lead.status),
                                }">
                                {{ lead.status }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-400">Belum ada lead.</p>
                </div>
            </div>

            <!-- RIGHT: Amenities + Master Plan -->
            <div class="space-y-6">
                <div v-if="project.amenities?.length" class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3">Fasilitas</h2>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="a in project.amenities" :key="a" class="px-3 py-1.5 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-full">{{ a }}</span>
                    </div>
                </div>

                <div v-if="project.master_plan_image" class="bg-white rounded-2xl border border-slate-100 p-4 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3">Master Plan</h2>
                    <img :src="`/storage/${project.master_plan_image}`" class="w-full rounded-xl" />
                </div>

                <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3">Info Proyek</h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between"><span class="text-slate-500">Kode</span><span class="font-bold">{{ project.code }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Leads</span><span class="font-bold">{{ project.leads_count }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Bookings</span><span class="font-bold">{{ project.bookings_count }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Dibuat</span><span class="font-bold">{{ new Date(project.created_at).toLocaleDateString('id-ID') }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    </CrmLayout>
</template>
