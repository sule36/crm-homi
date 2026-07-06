<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ project: { type: Object, default: null } });
const isEdit = computed(() => !!props.project);

const form = useForm({
    name: props.project?.name || '',
    code: props.project?.code || '',
    description: props.project?.description || '',
    location: props.project?.location || '',
    address: props.project?.address || '',
    status: props.project?.status || 'upcoming',
    amenities: props.project?.amenities || [],
    logo: null,
    master_plan_image: null,
    brochure_file: null,
});

const amenityInput = ref('');

function addAmenity() {
    if (amenityInput.value.trim() && !form.amenities.includes(amenityInput.value.trim())) {
        form.amenities.push(amenityInput.value.trim());
        amenityInput.value = '';
    }
}

function removeAmenity(index) {
    form.amenities.splice(index, 1);
}

function submit() {
    if (isEdit.value) {
        form.transform((data) => ({
            ...data,
            _method: 'PUT',
        })).post(`/projects/${props.project.id}`, {
            forceFormData: true,
        });
    } else {
        form.post('/projects');
    }
}

function handleFile(e, field) {
    form[field] = e.target.files[0];
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Proyek' : 'Tambah Proyek'" />
    <CrmLayout>
        <template #breadcrumb>
            <span class="text-gray-400">Proyek</span> / {{ isEdit ? 'Edit' : 'Tambah' }}
        </template>

        <div class="max-w-4xl mx-auto">
            <!-- HEADER -->
            <div class="mb-8">
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ isEdit ? 'Edit Proyek' : 'Tambah Proyek Baru' }}</h1>
                <p class="text-sm text-slate-500 mt-1">{{ isEdit ? 'Perbarui informasi proyek' : 'Isi detail proyek properti baru' }}</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- BASIC INFO -->
                <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-5 flex items-center gap-2">
                        <span class="w-7 h-7 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 text-xs">📋</span>
                        Informasi Dasar
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Nama Proyek <span class="text-rose-500">*</span></label>
                            <input v-model="form.name" type="text" placeholder="Citraland Grand View" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                            <p v-if="form.errors.name" class="text-xs text-rose-500 mt-1">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Kode Proyek <span class="text-rose-500">*</span></label>
                            <input v-model="form.code" type="text" placeholder="CGV" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 uppercase" />
                            <p v-if="form.errors.code" class="text-xs text-rose-500 mt-1">{{ form.errors.code }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Lokasi <span class="text-rose-500">*</span></label>
                            <input v-model="form.location" type="text" placeholder="Surabaya Barat" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                            <p v-if="form.errors.location" class="text-xs text-rose-500 mt-1">{{ form.errors.location }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Status <span class="text-rose-500">*</span></label>
                            <select v-model="form.status" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                                <option value="upcoming">Upcoming</option>
                                <option value="active">Aktif</option>
                                <option value="completed">Selesai</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Alamat Lengkap</label>
                            <input v-model="form.address" type="text" placeholder="Jl. Citraland No. 1, Surabaya" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Deskripsi</label>
                            <textarea v-model="form.description" rows="4" placeholder="Deskripsi singkat proyek..." class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 resize-none"></textarea>
                        </div>
                    </div>
                </div>

                <!-- AMENITIES -->
                <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-5 flex items-center gap-2">
                        <span class="w-7 h-7 bg-emerald-100 rounded-lg flex items-center justify-center text-emerald-600 text-xs">✨</span>
                        Fasilitas
                    </h2>
                    <div class="flex gap-2 mb-3">
                        <input v-model="amenityInput" @keydown.enter.prevent="addAmenity" type="text" placeholder="Kolam renang, taman, dll..." class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                        <button type="button" @click="addAmenity" class="px-4 py-2.5 bg-emerald-600 text-white text-sm font-bold rounded-xl hover:bg-emerald-700 transition-colors">Tambah</button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="(a, i) in form.amenities" :key="i" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-full">
                            {{ a }}
                            <button type="button" @click="removeAmenity(i)" class="w-4 h-4 bg-emerald-200 hover:bg-rose-200 hover:text-rose-600 rounded-full flex items-center justify-center transition-colors">&times;</button>
                        </span>
                    </div>
                </div>

                <!-- FILE UPLOADS -->
                <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-5 flex items-center gap-2">
                        <span class="w-7 h-7 bg-purple-100 rounded-lg flex items-center justify-center text-purple-600 text-xs">📁</span>
                        Media & Dokumen
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Logo Proyek</label>
                            <div v-if="project?.logo" class="mb-2 relative w-16 h-16 rounded-xl border border-slate-100 overflow-hidden bg-slate-50 flex items-center justify-center">
                                <img :src="`/storage/${project.logo}`" class="w-full h-full object-cover" />
                            </div>
                            <input type="file" @change="e => handleFile(e, 'logo')" accept="image/*" class="w-full text-xs file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-600 file:font-bold file:cursor-pointer hover:file:bg-blue-100" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Master Plan</label>
                            <div v-if="project?.master_plan_image" class="mb-2 relative w-full h-20 rounded-xl border border-slate-100 overflow-hidden bg-slate-50 flex items-center justify-center">
                                <img :src="`/storage/${project.master_plan_image}`" class="w-full h-full object-cover" />
                            </div>
                            <input type="file" @change="e => handleFile(e, 'master_plan_image')" accept="image/*" class="w-full text-xs file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-600 file:font-bold file:cursor-pointer hover:file:bg-blue-100" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Brosur (PDF)</label>
                            <div v-if="project?.brochure_file" class="mb-2 flex items-center gap-1.5 text-xs text-blue-600 font-bold bg-blue-50 px-3 py-2 rounded-xl w-fit">
                                <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <a :href="`/storage/${project.brochure_file}`" target="_blank" class="hover:underline truncate max-w-[150px]">Unduh Brosur Proyek</a>
                            </div>
                            <input type="file" @change="e => handleFile(e, 'brochure_file')" accept=".pdf" class="w-full text-xs file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-600 file:font-bold file:cursor-pointer hover:file:bg-blue-100" />
                        </div>
                    </div>
                </div>

                <!-- SUBMIT -->
                <div class="flex items-center justify-end gap-3">
                    <button type="button" @click="$inertia.visit('/projects')" class="px-6 py-2.5 text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">Batal</button>
                    <button type="submit" :disabled="form.processing"
                        class="px-8 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white text-sm font-bold rounded-xl shadow-lg shadow-blue-500/25 hover:shadow-blue-500/40 hover:-translate-y-0.5 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                        <span v-if="form.processing">Menyimpan...</span>
                        <span v-else>{{ isEdit ? 'Perbarui Proyek' : 'Buat Proyek' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </CrmLayout>
</template>
