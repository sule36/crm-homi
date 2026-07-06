<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    logs: Object
});
</script>

<template>
    <Head title="Audit Logs" />
    <CrmLayout>
        <template #breadcrumb>Settings / Audit Logs</template>

        <div class="mb-8">
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Audit Logs</h1>
            <p class="text-sm text-slate-500 mt-1">Lacak semua aktivitas dan perubahan data dalam sistem.</p>
        </div>

        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Waktu</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">User</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Aksi</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4">
                                <p class="text-xs font-bold text-slate-900">{{ new Date(log.created_at).toLocaleString('id-ID') }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 bg-slate-200 rounded-full flex items-center justify-center text-[10px] font-bold">
                                        {{ log.user?.name.charAt(0) }}
                                    </div>
                                    <p class="text-xs font-black text-slate-700">{{ log.user?.name || 'System' }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-black uppercase tracking-wider">
                                    {{ log.action }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-xs text-slate-600 italic">{{ log.description }}</p>
                                <p class="text-[9px] text-slate-400 font-mono mt-1">IP: {{ log.ip_address }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-center gap-2">
                <Link v-for="link in logs.links" :key="link.label" :href="link.url || '#'"
                    v-html="link.label"
                    :class="[link.active ? 'bg-slate-900 text-white' : 'bg-white text-slate-500 hover:bg-slate-100', !link.url ? 'opacity-50' : '']"
                    class="px-3 py-1.5 text-[10px] font-black rounded-lg border border-slate-200 transition-all" />
            </div>
        </div>
    </CrmLayout>
</template>
