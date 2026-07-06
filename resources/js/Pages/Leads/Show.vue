<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ lead: Object, agents: Array });

const statusSteps = [
    { key: 'new', label: 'Baru', color: 'blue' },
    { key: 'contacted', label: 'Dihubungi', color: 'cyan' },
    { key: 'visited', label: 'Kunjungan', color: 'purple' },
    { key: 'negotiation', label: 'Negosiasi', color: 'amber' },
    { key: 'booking', label: 'Booking', color: 'emerald' },
    { key: 'won', label: 'Won', color: 'green' },
];

const currentStepIndex = computed(() => statusSteps.findIndex(s => s.key === props.lead.status));

// Activity Form
const activityForm = useForm({ type: 'note', description: '' });
function submitActivity() {
    activityForm.post(`/leads/${props.lead.id}/activity`, { preserveScroll: true, onSuccess: () => activityForm.reset('description') });
}

// Reminder Form
import KprCalculatorModal from '@/Components/Crm/KprCalculatorModal.vue';

const reminderForm = useForm({ remind_at: '', message: '' });
const showReminderModal = ref(false);
const showKprModal = ref(false);

function submitReminder() {
    reminderForm.post(`/leads/${props.lead.id}/reminder`, { preserveScroll: true, onSuccess: () => { showReminderModal.value = false; reminderForm.reset(); } });
}

const generatedMessage = ref('');
function generateWaMessage(type) {
    const agentName = props.lead.assigned_to_user?.name || 'Konsultan Homi';
    const projectName = props.lead.project?.name || 'Proyek Homi';
    
    if (type === 'perkenalan') {
        generatedMessage.value = `Halo Bapak/Ibu *${props.lead.name}*,\n\nPerkenalkan saya *${agentName}* dari Homi Developer. Terima kasih telah menyatakan minat pada proyek *${projectName}*.\n\nApakah ada waktu luang untuk berdiskusi sebentar mengenai tipe rumah atau denah unit yang sedang dicari?\n\nSalam,\n*${agentName}* - Homi Developer`;
    } else if (type === 'visit') {
        generatedMessage.value = `Halo Bapak/Ibu *${props.lead.name}*,\n\nMenyambung percakapan kita, kami mengundang Bapak/Ibu untuk berkunjung langsung (*Site Visit*) melihat rumah contoh dan lokasi proyek *${projectName}* pada akhir pekan ini.\n\nApakah hari Sabtu atau Minggu besok ada waktu luang?\n\nTerima kasih,\n*${agentName}*`;
    } else if (type === 'booking_reminder') {
        generatedMessage.value = `Halo Bapak/Ibu *${props.lead.name}*,\n\nKami mengonfirmasi ketertarikan Anda pada unit di *${projectName}*. Untuk mengamankan nomor kavling unit pilihan Anda agar tidak terjual ke konsumen lain, Anda dapat melakukan pembayaran Booking Fee (UTJ).\n\nJika ingin melakukan pembayaran atau konsultasi KPR terlebih dahulu, silakan hubungi saya kembali.\n\nTerima kasih,\n*${agentName}*`;
    } else {
        generatedMessage.value = `Halo Bapak/Ibu *${props.lead.name}*,\n\nBagaimana kabar rencana kepemilikan hunian impian Anda di *${projectName}*? Jika ada pertanyaan mengenai promo diskon, suku bunga KPR bank partner, atau ingin berkunjung kembali ke lokasi, saya siap membantu.\n\nSalam,\n*${agentName}*`;
    }
}

// Quick update
const editForm = useForm({
    status: props.lead.status,
    assigned_to: props.lead.assigned_to,
    notes: props.lead.notes || '',
});

function updateLead() {
    editForm.put(`/leads/${props.lead.id}`, { preserveScroll: true });
}

const activityIcons = {
    call: '📞', whatsapp: '💬', email: '📧', visit: '🏠', meeting: '🤝', note: '📝', status_change: '🔄',
};

function timeAgo(date) {
    const diff = Date.now() - new Date(date).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 60) return `${mins}m lalu`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}j lalu`;
    const days = Math.floor(hrs / 24);
    return `${days}h lalu`;
}

function scoreColor(s) {
    if (s >= 8) return 'from-emerald-500 to-green-500';
    if (s >= 5) return 'from-amber-500 to-orange-500';
    return 'from-rose-500 to-red-500';
}
</script>

<template>
    <Head :title="`Lead: ${lead.name}`" />
    <CrmLayout>
        <template #breadcrumb>
            <span class="text-gray-400">Leads</span> / {{ lead.name }}
        </template>

        <!-- HEADER -->
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-4">
                <!-- Score -->
                <div :class="`bg-gradient-to-br ${scoreColor(lead.score)}`" class="w-14 h-14 rounded-2xl flex items-center justify-center text-white font-black text-xl shadow-lg">
                    {{ lead.score }}
                </div>
                <div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ lead.name }}</h1>
                    <p class="text-sm text-slate-500">{{ lead.phone }} {{ lead.email ? `• ${lead.email}` : '' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button @click="showKprModal = true" class="px-4 py-2 bg-blue-50 text-blue-700 text-xs font-bold rounded-xl hover:bg-blue-100 transition-colors">🧮 Kalkulator KPR</button>
                <button @click="showReminderModal = true" class="px-4 py-2 bg-amber-50 text-amber-700 text-xs font-bold rounded-xl hover:bg-amber-100 transition-colors">⏰ Set Reminder</button>
                <a :href="`https://wa.me/${lead.phone?.replace(/^0/, '62')}`" target="_blank" class="px-4 py-2 bg-emerald-600 text-white text-xs font-bold rounded-xl hover:bg-emerald-700 transition-colors">💬 WhatsApp</a>
            </div>
        </div>

        <!-- STATUS PIPELINE -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 mb-6 shadow-sm">
            <div class="flex items-center justify-between overflow-x-auto">
                <div v-for="(step, i) in statusSteps" :key="step.key" class="flex items-center flex-1 min-w-0">
                    <button @click="editForm.status = step.key; updateLead()"
                        :class="i <= currentStepIndex ? `bg-${step.color}-500 text-white shadow-lg` : 'bg-slate-100 text-slate-400'"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-black shrink-0 transition-all hover:scale-110">
                        {{ i + 1 }}
                    </button>
                    <div v-if="i < statusSteps.length - 1"
                        :class="i < currentStepIndex ? `bg-${step.color}-300` : 'bg-slate-200'"
                        class="h-0.5 flex-1 mx-1 transition-colors"></div>
                </div>
            </div>
            <div class="flex justify-between mt-2 text-[9px] font-bold uppercase tracking-wider text-slate-500">
                <span v-for="step in statusSteps" :key="step.key" class="text-center flex-1">{{ step.label }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- LEFT: Activity Timeline -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Add Activity -->
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-4">Tambah Aktivitas</h2>
                    <form @submit.prevent="submitActivity">
                        <div class="flex flex-wrap gap-2 mb-3">
                            <button v-for="t in ['call','whatsapp','email','visit','meeting','note']" :key="t" type="button"
                                @click="activityForm.type = t"
                                :class="activityForm.type === t ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3 py-1.5 text-xs font-bold rounded-lg transition-all capitalize">
                                {{ activityIcons[t] }} {{ t }}
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <input v-model="activityForm.description" type="text" placeholder="Deskripsi aktivitas..." class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />
                            <button type="submit" :disabled="activityForm.processing" class="px-5 py-2.5 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition-colors disabled:opacity-50">Kirim</button>
                        </div>
                    </form>
                </div>

                <!-- Timeline -->
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-4">Riwayat Aktivitas</h2>
                    <div v-if="lead.activities?.length" class="space-y-4">
                        <div v-for="act in lead.activities" :key="act.id" class="flex gap-3">
                            <div class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center text-sm shrink-0">{{ activityIcons[act.type] || '📝' }}</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-slate-900">{{ act.description }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] text-slate-400 font-medium">{{ act.user?.name }}</span>
                                    <span class="text-[10px] text-slate-300">•</span>
                                    <span class="text-[10px] text-slate-400">{{ timeAgo(act.created_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-400">Belum ada aktivitas tercatat.</p>
                </div>
            </div>

            <!-- RIGHT: Details -->
            <div class="space-y-6">
                <!-- Lead Info -->
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-4">Detail Lead</h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between"><span class="text-slate-500">Proyek</span><span class="font-bold">{{ lead.project?.name || '-' }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Sumber</span><span class="font-bold capitalize">{{ lead.source?.replace('_', ' ') }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Broker</span><span class="font-bold">{{ lead.broker_company?.name || '-' }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Terakhir Kontak</span><span class="font-bold">{{ lead.last_contacted_at ? timeAgo(lead.last_contacted_at) : '-' }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Masuk</span><span class="font-bold">{{ new Date(lead.created_at).toLocaleDateString('id-ID') }}</span></div>
                    </div>
                </div>

                <!-- Assign Agent -->
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3">Agen</h2>
                    <select v-model="editForm.assigned_to" @change="updateLead()" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 cursor-pointer">
                        <option value="">Belum di-assign</option>
                        <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                </div>

                <!-- Notes -->
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3">Catatan</h2>
                    <textarea v-model="editForm.notes" @blur="updateLead()" rows="4" placeholder="Catatan internal..." class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 resize-none"></textarea>
                </div>

                <!-- Smart WA Follow-up -->
                <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                        <span>💬</span> Smart WA Follow-up
                    </h2>
                    <div class="space-y-3">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase">Pilih Template Pesan</label>
                        <div class="grid grid-cols-2 gap-2 text-[10px]">
                            <button type="button" @click="generateWaMessage('perkenalan')" class="p-2 border border-slate-200 rounded-xl font-bold bg-slate-50 hover:bg-slate-100 text-slate-700 text-left">👋 Perkenalan</button>
                            <button type="button" @click="generateWaMessage('visit')" class="p-2 border border-slate-200 rounded-xl font-bold bg-slate-50 hover:bg-slate-100 text-slate-700 text-left">🏠 Site Visit</button>
                            <button type="button" @click="generateWaMessage('booking_reminder')" class="p-2 border border-slate-200 rounded-xl font-bold bg-slate-50 hover:bg-slate-100 text-slate-700 text-left">💳 Tagihan UTJ</button>
                            <button type="button" @click="generateWaMessage('custom')" class="p-2 border border-slate-200 rounded-xl font-bold bg-slate-50 hover:bg-slate-100 text-slate-700 text-left">✏️ Custom Follow-up</button>
                        </div>
                        <div v-if="generatedMessage" class="mt-4 pt-4 border-t border-slate-100 space-y-3">
                            <textarea v-model="generatedMessage" rows="5" class="w-full p-3 bg-slate-50 border-none rounded-xl text-xs leading-relaxed focus:ring-1 focus:ring-blue-500 font-sans"></textarea>
                            <a :href="`https://wa.me/${lead.phone.replace(/^0/, '62').replace(/[^0-9]/g, '')}?text=${encodeURIComponent(generatedMessage)}`" target="_blank"
                                class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black rounded-xl transition-all shadow-lg shadow-emerald-500/20 text-center block uppercase tracking-widest">
                                Kirim WhatsApp →
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Reminders -->
                <div v-if="lead.reminders?.length" class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider mb-3">Reminders</h2>
                    <div class="space-y-2">
                        <div v-for="r in lead.reminders" :key="r.id" class="flex items-center gap-3 p-2.5 rounded-xl"
                            :class="r.status === 'pending' ? 'bg-amber-50' : 'bg-slate-50'">
                            <span class="text-sm">{{ r.status === 'pending' ? '⏰' : '✅' }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-slate-700 truncate">{{ r.message || 'Follow up' }}</p>
                                <p class="text-[10px] text-slate-400">{{ new Date(r.remind_at).toLocaleString('id-ID') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- REMINDER MODAL -->
        <teleport to="body">
            <div v-if="showReminderModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showReminderModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h2 class="text-lg font-black text-slate-900 mb-4">Set Follow-Up Reminder</h2>
                    <form @submit.prevent="submitReminder" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Waktu Reminder <span class="text-rose-500">*</span></label>
                            <input v-model="reminderForm.remind_at" type="datetime-local" class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1.5">Pesan</label>
                            <textarea v-model="reminderForm.message" rows="3" placeholder="Follow up soal tipe unit..." class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 resize-none"></textarea>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="showReminderModal = false" class="px-5 py-2.5 text-sm font-bold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200">Batal</button>
                            <button type="submit" :disabled="reminderForm.processing" class="px-6 py-2.5 bg-amber-500 text-white text-sm font-bold rounded-xl hover:bg-amber-600 shadow-lg transition-all">Set Reminder</button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>

        <KprCalculatorModal :show="showKprModal" @close="showKprModal = false" />
    </CrmLayout>
</template>
