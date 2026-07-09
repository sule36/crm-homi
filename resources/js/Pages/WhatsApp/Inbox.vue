<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';

const props = defineProps({
    chats: Array,
    activeLeads: Array,
    partnerBanks: Array,
});

const activeChat = ref(null);
const messages = ref([]);
const messageInput = ref('');
const searchQuery = ref('');
const loadingMessages = ref(false);
const pollingInterval = ref(null);
const sendingMessage = ref(false);

// New Chat Modal State
const showNewChatModal = ref(false);
const selectedLeadIdForNewChat = ref('');

// KPR Simulation Assistant States
const showKprAssistant = ref(false);
const kprPrice = ref(500000000);
const kprTenor = ref(15);
const kprDpPercent = ref(10);
const kprSelectedBankId = ref('');

// Filter chats based on search query
const filteredChats = computed(() => {
    if (!searchQuery.value) return props.chats;
    const query = searchQuery.value.toLowerCase();
    return props.chats.filter(c => 
        c.name.toLowerCase().includes(query) || 
        c.phone.includes(query) || 
        c.project.toLowerCase().includes(query)
    );
});

// Format Currency Utility
const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val);
};

// Select a chat to view messages
async function selectChat(chat) {
    activeChat.value = chat;
    messages.value = [];
    loadingMessages.value = true;
    
    await fetchMessages(chat.phone);
    loadingMessages.value = false;
    scrollToBottom();
}

// Fetch messages from backend
async function fetchMessages(phone) {
    try {
        const response = await axios.get(`/whatsapp/chat/${phone}`);
        messages.value = response.data;
    } catch (error) {
        console.error('Failed to load chat history:', error);
    }
}

// Scroll chat window to bottom
function scrollToBottom() {
    nextTick(() => {
        const chatWindow = document.getElementById('chat-messages-container');
        if (chatWindow) {
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }
    });
}

// Send Message Action
async function handleSendMessage() {
    if (!messageInput.value.trim() || !activeChat.value || sendingMessage.value) return;

    sendingMessage.value = true;
    const phone = activeChat.value.phone;
    const textToSend = messageInput.value;

    // optimistic UI add
    messages.value.push({
        id: Date.now(),
        direction: 'outgoing',
        message: textToSend,
        created_at: new Date().toISOString(),
        status: 'sending'
    });
    messageInput.value = '';
    scrollToBottom();

    try {
        const response = await axios.post('/whatsapp/send', {
            phone: phone,
            message: textToSend
        });
        
        // Refresh messages after successful send
        await fetchMessages(phone);
        scrollToBottom();
    } catch (error) {
        alert('Gagal mengirim pesan: ' + (error.response?.data?.message || error.message));
        // Remove optimistic message or mark as failed
        await fetchMessages(phone);
    } finally {
        sendingMessage.value = false;
    }
}

// Send Manual (via wa.me link redirection)
async function handleSendManual() {
    if (!messageInput.value.trim() || !activeChat.value) return;

    const phone = activeChat.value.phone;
    const textToSend = messageInput.value;
    const formattedPhone = phone.replace(/^0/, '62');

    // 1. Save to CRM database locally (so it's recorded in the history)
    try {
        await axios.post('/whatsapp/log-manual-send', {
            phone: phone,
            message: textToSend
        });
        
        // Refresh messages locally
        await fetchMessages(phone);
        scrollToBottom();
    } catch (error) {
        console.error('Failed to log manual message:', error);
    }

    // Clear input
    messageInput.value = '';

    // 2. Open WhatsApp Web or Mobile App with pre-filled message
    const url = `https://wa.me/${formattedPhone}?text=${encodeURIComponent(textToSend)}`;
    window.open(url, '_blank');
}

// Quick Replies & Smart Templates
function insertTemplate(type) {
    if (!activeChat.value) return;

    let text = '';
    const customerName = activeChat.value.name;
    const projectName = activeChat.value.project || 'Proyek Homi';

    if (type === 'welcome') {
        text = `Halo Bapak/Ibu *${customerName}*,\n\nPerkenalkan saya konsultan marketing Homi Developer. Terima kasih telah menghubungi kami mengenai proyek perumahan *${projectName}*.\n\nApakah ada waktu luang hari ini untuk mengobrol singkat mengenai tipe rumah atau denah yang sedang Bapak/Ibu cari? 😊`;
    } else if (type === 'visit') {
        text = `Halo Bapak/Ibu *${customerName}*,\n\nMenyambung rencana kepemilikan hunian di *${projectName}*, kami mengundang Bapak/Ibu untuk berkunjung langsung (*Site Visit*) melihat unit contoh dan lokasi proyek kami pada akhir pekan ini.\n\nApakah hari Sabtu atau Minggu besok ada waktu luang? Kami siap menyambut kunjungan Anda.`;
    } else if (type === 'booking') {
        text = `Halo Bapak/Ibu *${customerName}*,\n\nUntuk mengamankan nomor kavling unit pilihan Anda di *${projectName}* agar tidak terjual ke konsumen lain, Anda dapat melakukan pembayaran Booking Fee / Tanda Jadi (UTJ) sebesar *${formatCurrency(5000000)}*.\n\nPembayaran dapat ditransfer langsung ke rekening resmi developer. Jika ingin dikirimkan invoice resminya, silakan kabari saya. Terima kasih.`;
    } else if (type === 'kpr_docs') {
        text = `Halo Bapak/Ibu *${customerName}*,\n\nBerikut adalah daftar berkas persyaratan yang diperlukan untuk pengajuan KPR unit di *${projectName}*:\n\n📄 *Kategori Identitas & Keluarga:*\n- Foto KTP Suami & Istri\n- Foto Kartu Keluarga (KK)\n- Foto NPWP Pribadi\n- Foto Akta Nikah / Cerai (jika ada)\n\n💼 *Kategori Pekerjaan & Penghasilan:*\n- Slip Gaji 3 Bulan Terakhir\n- Surat Keterangan Kerja (SKK) Asli\n- Rekening Koran Tabungan 3 Bulan Terakhir\n\nMohon siapkan berkas di atas dalam format PDF atau foto yang jelas, lalu kirimkan ke kami agar bisa segera dibantu proses pengajuannya ke bank partner. Terima kasih.`;
    } else if (type === 'promo') {
        text = `📢 *PROMO SPESIAL BULAN INI di ${projectName.toUpperCase()}* 🎉\n\nDapatkan berbagai keuntungan eksklusif untuk pembelian unit pilihan Anda:\n✅ *Diskon DP* atau Subsidi Uang Muka\n✅ *Free Biaya Surat-Surat* (BPHTB, AJB, Balik Nama)\n✅ *Free AC & Canopy Carport*\n✅ *Voucher Belanja Jutaan Rupiah* (S&K Berlaku)\n\nPromo ini terbatas hanya untuk 3 unit pertama bulan ini! Segera amankan unit impian Anda sekarang sebelum kehabisan.`;
    } else if (type === 'share_loc') {
        text = `📍 *LOKASI KANTOR PEMASARAN & PROYEK*\n\nHalo Bapak/Ibu *${customerName}*,\n\nBerikut adalah link peta petunjuk arah Google Maps untuk menuju ke lokasi proyek *${projectName}* kami:\n🔗 https://maps.google.com/?q=${encodeURIComponent(projectName)}\n\nKami buka setiap hari pukul 09.00 - 17.00 WIB. Kabari saya jika Anda sudah di perjalanan agar bisa saya sambut di lokasi gallery pemasaran.`;
    }

    messageInput.value = text;
    showKprAssistant.value = false;
}

// Calculator Mode: 'kpr' or 'inhouse'
const calculatorMode = ref('kpr');
const inhouseTenorMonths = ref(12);

// Calculate and Insert KPR / In-house Simulation into input
function insertKprSimulation() {
    if (!activeChat.value) return;

    const customerName = activeChat.value.name;
    const projectName = activeChat.value.project || 'Proyek Homi';
    const dpAmount = Math.round((kprPrice.value * kprDpPercent.value) / 100);
    const remainingAmount = Math.max(0, kprPrice.value - dpAmount);
    
    let simulationText = '';

    if (calculatorMode.value === 'inhouse') {
        const monthly = Math.round(remainingAmount / inhouseTenorMonths.value);
        simulationText = `*SIMULASI CICILAN IN-HOUSE DEVELOPER* 🏡\n` +
                         `-----------------------------------\n` +
                         `Proyek: *${projectName}*\n` +
                         `Harga Properti: *${formatCurrency(kprPrice.value)}*\n` +
                         `Uang Muka (DP ${kprDpPercent.value}%): *${formatCurrency(dpAmount)}*\n` +
                         `Sisa Piutang Developer: *${formatCurrency(remainingAmount)}*\n` +
                         `-----------------------------------\n` +
                         `Skema: *Cicilan Langsung ke Developer*\n` +
                         `Tenor Cicilan: *${inhouseTenorMonths.value} Bulan*\n` +
                         `Cicilan Bulanan: *${formatCurrency(monthly)}/bulan*\n` +
                         `-----------------------------------\n` +
                         `Tanpa BI Checking, Tanpa Bunga Bank! Balas pesan ini untuk info prosedur booking unitnya.`;
    } else {
        // KPR Bank Simulation
        let bankName = 'Bunga Kustom';
        let interestRate = 5.0; // Default custom rate
        let type = 'conventional';

        const selectedBank = props.partnerBanks.find(b => b.id === Number(kprSelectedBankId.value));
        if (selectedBank) {
            bankName = selectedBank.name;
            if (selectedBank.is_syariah) {
                type = 'syariah';
                interestRate = Number(selectedBank.syariah_margin_rate);
            } else {
                interestRate = Number(selectedBank.interest_rate_fixed);
            }
        }

        simulationText = `*SIMULASI CICILAN KPR BANK PINTAR* 🏠\n` +
                         `-----------------------------------\n` +
                         `Proyek: *${projectName}*\n` +
                         `Harga Properti: *${formatCurrency(kprPrice.value)}*\n` +
                         `Uang Muka (DP ${kprDpPercent.value}%): *${formatCurrency(dpAmount)}*\n` +
                         `Jumlah Pinjaman KPR: *${formatCurrency(remainingAmount)}*\n` +
                         `-----------------------------------\n` +
                         `Bank Rekomendasi: *${bankName}*\n` +
                         `Tenor KPR: *${kprTenor.value} Tahun*\n`;

        if (type === 'syariah') {
            const totalMargin = remainingAmount * (interestRate / 100) * kprTenor.value;
            const totalPayable = remainingAmount + totalMargin;
            const monthly = Math.round(totalPayable / (kprTenor.value * 12));
            
            simulationText += `Skema: *Flat Syariah (Margin ${interestRate}%/thn)*\n` +
                              `Cicilan Bulanan: *${formatCurrency(monthly)}/bulan*\n`;
        } else {
            // Simple conventional fixed calculations (Annuity formula)
            const monthlyRate = (interestRate / 100) / 12;
            const totalMonths = kprTenor.value * 12;
            let monthly = 0;
            
            if (monthlyRate === 0) {
                monthly = remainingAmount / totalMonths;
            } else {
                const factor = Math.pow(1 + monthlyRate, totalMonths);
                monthly = (remainingAmount * monthlyRate * factor) / (factor - 1);
            }
            monthly = Math.round(monthly);

            simulationText += `Skema: *Fix & Floating (Asumsi fixed ${interestRate}%/thn)*\n` +
                              `Cicilan Bulanan (Masa Fixed): *${formatCurrency(monthly)}/bulan*\n`;
        }

        simulationText += `-----------------------------------\n` +
                          `Tertarik untuk mengajukan KPR ini? Balas pesan ini untuk kami bantu siapkan berkas pengajuannya.`;
    }

    messageInput.value = simulationText;
    showKprAssistant.value = false;
}

// Start New Chat Action
function startNewChat() {
    const lead = props.activeLeads.find(l => l.id === Number(selectedLeadIdForNewChat.value));
    if (!lead) return;

    showNewChatModal.value = false;
    selectedLeadIdForNewChat.value = '';

    // Check if chat already exists in list
    const existingChat = props.chats.find(c => c.phone === lead.phone);
    if (existingChat) {
        selectChat(existingChat);
    } else {
        // Create dynamic temporary chat item
        const tempChat = {
            id: lead.id,
            name: lead.name,
            phone: lead.phone,
            project: 'Proyek Baru',
            last_message: 'Mulai obrolan baru...',
            last_message_time: 'Baru saja'
        };
        props.chats.unshift(tempChat);
        selectChat(tempChat);
    }
}

// Short Polling to fetch new messages automatically every 5 seconds
onMounted(() => {
    pollingInterval.value = setInterval(() => {
        if (activeChat.value) {
            fetchMessages(activeChat.value.phone).then(() => {
                // Only auto-scroll down if user is near the bottom
                const chatWindow = document.getElementById('chat-messages-container');
                if (chatWindow) {
                    const isNearBottom = chatWindow.scrollHeight - chatWindow.clientHeight - chatWindow.scrollTop < 150;
                    if (isNearBottom) {
                        scrollToBottom();
                    }
                }
            });
        }
    }, 5000);
});

// Update Lead Status via Axios API
async function updateStatus(leadId, newStatus) {
    try {
        await axios.post(`/whatsapp/leads/${leadId}/status`, {
            status: newStatus
        });
        
        // Update local chat list item status as well
        const chat = props.chats.find(c => c.id === leadId);
        if (chat) {
            chat.status = newStatus;
        }
        
        console.log('Lead status updated successfully to ' + newStatus);
    } catch (error) {
        alert('Gagal memperbarui status: ' + (error.response?.data?.message || error.message));
    }
}

// Status Styling utility
const statusColorClass = (status) => {
    const classes = {
        'new': 'bg-blue-50 text-blue-700',
        'contacted': 'bg-amber-50 text-amber-700',
        'visited': 'bg-purple-50 text-purple-700',
        'negotiation': 'bg-indigo-50 text-indigo-700',
        'booking': 'bg-rose-50 text-rose-700',
        'won': 'bg-emerald-50 text-emerald-700',
        'lost': 'bg-slate-50 text-slate-500',
    };
    return classes[status] || 'bg-slate-50 text-slate-700';
};
</script>

<template>
    <Head title="WhatsApp Shared Inbox" />
    <CrmLayout>
        <template #breadcrumb>
            <span class="text-gray-400">Marketing & Sales</span> / WhatsApp Chat
        </template>

        <div class="h-[calc(100dvh-125px)] md:h-[calc(100vh-140px)] flex bg-white rounded-3xl border border-slate-100 overflow-hidden shadow-xl">
            <!-- LEFT PANEL: CHAT LIST -->
            <div :class="activeChat ? 'hidden md:flex' : 'w-full md:w-[360px] flex'" class="border-r border-slate-100 flex flex-col shrink-0 min-w-0">
                <!-- Search & New Chat Button -->
                <div class="p-5 border-b border-slate-50 space-y-3 shrink-0">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-black text-slate-900 tracking-tight">WhatsApp Chats</h2>
                        <button @click="showNewChatModal = true" class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors flex items-center justify-center font-bold text-lg" title="Mulai Chat Baru">
                            +
                        </button>
                    </div>
                    <div class="relative">
                        <input v-model="searchQuery" type="text" placeholder="Cari nama, proyek, atau nomor..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border-none rounded-xl text-xs font-semibold focus:ring-1 focus:ring-blue-500" />
                        <span class="absolute left-3.5 top-2.5 text-xs text-slate-400">🔍</span>
                    </div>
                </div>

                <!-- Chat Items Scrollable -->
                <div class="flex-1 overflow-y-auto divide-y divide-slate-50">
                    <div v-for="chat in filteredChats" :key="chat.phone" 
                        @click="selectChat(chat)"
                        :class="activeChat?.phone === chat.phone ? 'bg-blue-50/50 border-l-4 border-blue-600' : 'hover:bg-slate-50/40 border-l-4 border-transparent'"
                        class="p-4 flex gap-3 cursor-pointer transition-all border-b border-slate-50">
                        
                        <!-- Contact Avatar Initial -->
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white font-black text-xs flex items-center justify-center uppercase shadow-sm shrink-0">
                            {{ chat.name.substring(0, 2) }}
                        </div>
                        
                        <!-- Chat Text Details -->
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <h4 class="text-xs font-black text-slate-800 truncate">{{ chat.name }}</h4>
                                <span class="text-[9px] text-slate-400 font-bold whitespace-nowrap">{{ chat.last_message_time }}</span>
                            </div>
                            <p class="text-[9px] text-blue-600 font-bold uppercase tracking-wider mt-0.5">{{ chat.project }}</p>
                            <p class="text-[10px] text-slate-400 truncate mt-1 leading-snug">{{ chat.last_message }}</p>
                        </div>
                    </div>
                    
                    <div v-if="!filteredChats.length" class="text-center py-16 text-slate-400 italic text-xs font-bold">
                        Tidak ada percakapan ditemukan.
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL: CHAT WINDOW -->
            <div :class="activeChat ? 'flex-1 flex' : 'hidden md:flex flex-1'" class="flex-col bg-slate-50 relative min-w-0">
                <!-- If Active Chat Selected -->
                <template v-if="activeChat">
                    <!-- Chat Header (Lebih Compact untuk Mobile) -->
                    <div class="px-3 md:px-6 py-2 md:py-3.5 bg-white border-b border-slate-100 flex justify-between items-center z-10 shrink-0 min-w-0">
                        <div class="flex items-center gap-2 md:gap-3 min-w-0 flex-1">
                            <!-- Mobile Back Button -->
                            <button @click="activeChat = null" class="md:hidden p-1.5 hover:bg-slate-100 rounded-lg text-slate-500 font-black text-xs shrink-0" title="Kembali ke Daftar Chat">
                                ◀
                            </button>
                            <div class="w-8 h-8 md:w-9 md:h-9 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white font-black text-[10px] flex items-center justify-center uppercase shrink-0 shadow-sm">
                                {{ activeChat.name.substring(0, 2) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-xs font-black text-slate-900 leading-none truncate">{{ activeChat.name }}</h3>
                                <p class="text-[9px] text-slate-500 font-semibold mt-1 truncate">
                                    {{ activeChat.phone }} • Proyek: {{ activeChat.project }} • 👤 Agent: <span class="text-blue-600 font-black">{{ activeChat.agent_name || 'Belum Ditugaskan' }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 md:gap-3 shrink-0">
                            <!-- Status Dropdown -->
                            <div class="flex items-center gap-1 bg-slate-50 px-1.5 md:px-2.5 py-0.5 md:py-1 rounded-lg border border-slate-100/50 select-none">
                                <span class="text-[7px] md:text-[8px] font-black text-slate-400 uppercase tracking-wider hidden sm:inline">Status:</span>
                                <select v-model="activeChat.status" @change="updateStatus(activeChat.id, activeChat.status)" :class="statusColorClass(activeChat.status)" class="text-[8px] md:text-[9px] font-black py-0.5 px-1 md:px-2 border-none rounded focus:ring-0 focus:outline-none cursor-pointer">
                                    <option value="new">Baru</option>
                                    <option value="contacted">Dihubungi</option>
                                    <option value="visited">Kunjungan</option>
                                    <option value="negotiation">Negosiasi</option>
                                    <option value="booking">Booking UTJ</option>
                                    <option value="won">Closing (Won)</option>
                                    <option value="lost">Lost</option>
                                </select>
                            </div>

                            <a :href="`https://wa.me/${activeChat.phone.replace(/^0/, '62')}`" target="_blank" class="px-2 md:px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 text-[8px] md:text-[10px] font-black rounded-lg transition-colors border border-emerald-100/50 flex items-center gap-1 shrink-0">
                                📱 <span class="hidden sm:inline">Buka di WA</span><span class="sm:hidden">WA</span>
                            </a>
                        </div>
                    </div>

                    <!-- Chat Messages Area -->
                    <div id="chat-messages-container" class="flex-1 overflow-y-auto p-6 space-y-4">
                        <div v-if="loadingMessages" class="text-center py-10">
                            <span class="inline-block animate-spin text-xl">⏳</span>
                            <p class="text-xs text-slate-400 font-semibold mt-2">Memuat riwayat chat...</p>
                        </div>
                        <template v-else>
                            <div v-for="msg in messages" :key="msg.id" 
                                :class="msg.direction === 'outgoing' ? 'justify-end' : 'justify-start'" 
                                class="flex w-full">
                                
                                <div :class="msg.direction === 'outgoing' ? 'bg-blue-600 text-white rounded-2xl rounded-tr-none shadow-sm' : 'bg-white text-slate-800 rounded-2xl rounded-tl-none border border-slate-100 shadow-sm'"
                                    class="max-w-[80%] md:max-w-[70%] px-3.5 py-2.5 text-xs leading-relaxed relative whitespace-pre-wrap">
                                    
                                    {{ msg.message }}
                                    
                                    <!-- Timestamp & Status -->
                                    <div :class="msg.direction === 'outgoing' ? 'text-blue-100' : 'text-slate-400'" class="text-[8px] text-right mt-1.5 font-bold flex items-center justify-end gap-1 select-none">
                                        <span>{{ new Date(msg.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}</span>
                                        <span v-if="msg.direction === 'outgoing'">
                                            <span v-if="msg.status === 'sending'">⏳</span>
                                            <span v-else-if="msg.status === 'sent'">✓</span>
                                            <span v-else>✓✓</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Input Bar & Smart Templates Panel (Lebih Ringkas & Dropdown Ke Bawah) -->
                    <div class="p-2 md:p-3 bg-white border-t border-slate-100 shrink-0 space-y-1.5 z-10 relative">
                        <!-- Smart Templates Selector - Dropdown List Vertikal -->
                        <div class="flex items-center gap-2 bg-slate-50 p-1.5 rounded-xl border border-slate-100">
                            <span class="text-[8px] md:text-[9px] font-black text-slate-500 uppercase tracking-wider shrink-0 select-none">Balas Cepat:</span>
                            <select @change="(e) => { if (e.target.value) { insertTemplate(e.target.value); e.target.value = ''; } }" class="flex-1 py-0.5 px-2 bg-white border border-slate-200 rounded-lg text-[9px] md:text-[10px] font-bold focus:ring-1 focus:ring-blue-500 cursor-pointer">
                                <option value="">📋 Pilih Template Pesan...</option>
                                <option value="welcome">👋 Sapaan Awal</option>
                                <option value="visit">🏠 Jadwal Visit</option>
                                <option value="booking">🧾 Booking UTJ</option>
                                <option value="kpr_docs">📄 Berkas KPR</option>
                                <option value="promo">🎁 Promo Spesial</option>
                                <option value="share_loc">📍 Lokasi Proyek (Google Maps)</option>
                            </select>
                            
                            <button type="button" @click="showKprAssistant = !showKprAssistant" :class="showKprAssistant ? 'bg-blue-600 text-white shadow-md shadow-blue-500/10' : 'bg-blue-50 text-blue-600 hover:bg-blue-100'" class="px-2 py-1 text-[9px] font-black rounded-lg transition-all flex items-center gap-1 shrink-0">
                                🧮 KPR
                            </button>
                        </div>

                        <!-- KPR Assistant Dialog (Embedded/Interactive Floating) -->
                        <div v-if="showKprAssistant" class="absolute left-4 bottom-[72px] bg-white border border-slate-200 rounded-3xl p-5 shadow-2xl w-[calc(100vw-32px)] md:w-80 space-y-3.5 z-[20] animate-in slide-in-from-bottom-3 duration-250">
                            <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                                <span class="text-[10px] font-black text-slate-900 uppercase">Kalkulator Simulasi Cicilan</span>
                                <button @click="showKprAssistant = false" class="text-slate-400 font-bold">&times;</button>
                            </div>
                            
                            <!-- Mode Tabs: KPR Bank vs In-house -->
                            <div class="flex bg-slate-100 p-0.5 rounded-xl text-[9px] font-black uppercase text-center shrink-0">
                                <button type="button" @click="calculatorMode = 'kpr'" :class="calculatorMode === 'kpr' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'" class="flex-1 py-1.5 rounded-lg transition-all">KPR Bank</button>
                                <button type="button" @click="calculatorMode = 'inhouse'" :class="calculatorMode === 'inhouse' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'" class="flex-1 py-1.5 rounded-lg transition-all">In-House</button>
                            </div>

                            <div class="space-y-2 text-xs">
                                <div>
                                    <label class="block text-[8px] font-bold text-slate-400 uppercase mb-1">Harga Unit Rumah</label>
                                    <input v-model.number="kprPrice" type="number" step="1000000" class="w-full px-2.5 py-1.5 bg-slate-50 border-none rounded-lg text-xs font-bold" />
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[8px] font-bold text-slate-400 uppercase mb-1">DP (%)</label>
                                        <input v-model.number="kprDpPercent" type="number" min="0" max="90" step="5" class="w-full px-2.5 py-1.5 bg-slate-50 border-none rounded-lg text-xs font-bold" />
                                    </div>
                                    
                                    <!-- Dynamic Tenor based on mode -->
                                    <div v-if="calculatorMode === 'kpr'">
                                        <label class="block text-[8px] font-bold text-slate-400 uppercase mb-1">Tenor (Thn)</label>
                                        <input v-model.number="kprTenor" type="number" min="1" max="30" class="w-full px-2.5 py-1.5 bg-slate-50 border-none rounded-lg text-xs font-bold" />
                                    </div>
                                    <div v-else>
                                        <label class="block text-[8px] font-bold text-slate-400 uppercase mb-1">Tenor (Bln)</label>
                                        <input v-model.number="inhouseTenorMonths" type="number" min="1" max="120" step="6" class="w-full px-2.5 py-1.5 bg-slate-50 border-none rounded-lg text-xs font-bold" />
                                    </div>
                                </div>
                                
                                <!-- Bank Selection only for KPR Bank mode -->
                                <div v-if="calculatorMode === 'kpr'">
                                    <label class="block text-[8px] font-bold text-slate-400 uppercase mb-1">Pilih Bank Partner</label>
                                    <select v-model="kprSelectedBankId" class="w-full px-2.5 py-1.5 bg-slate-50 border-none rounded-lg text-xs font-bold">
                                        <option value="">-- Bunga Kustom 5% --</option>
                                        <option v-for="bank in partnerBanks" :key="bank.id" :value="bank.id">
                                            {{ bank.name }} {{ bank.is_syariah ? '(Syariah)' : '(Promo)' }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <button @click="insertKprSimulation" class="w-full py-2 bg-blue-600 text-white text-[9px] font-black uppercase tracking-widest rounded-xl shadow-md">
                                Sisipkan Simulasi Ke Input Chat
                            </button>
                        </div>

                        <!-- Typing Area and Send Button (Sleek Capsule Design - No Overflow) -->
                        <form @submit.prevent="handleSendMessage" class="flex gap-2 items-center bg-slate-50 p-1 rounded-full border border-slate-200/60 pl-4 pr-1">
                            <input v-model="messageInput" @keydown.enter.prevent="handleSendMessage" placeholder="Tulis pesan..." class="flex-1 bg-transparent border-none text-xs font-semibold focus:ring-0 p-0 focus:outline-none placeholder:text-slate-400 font-sans" />
                            
                            <!-- WA Manual Send Button -->
                            <button type="button" @click="handleSendManual" :disabled="!messageInput.trim()" class="h-8 px-3 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 text-[9px] font-black rounded-full flex items-center justify-center gap-0.5 transition-all shrink-0 select-none disabled:opacity-40" title="Kirim via WhatsApp HP/Web Manual">
                                📱 Manual
                            </button>

                            <button type="submit" :disabled="sendingMessage || !messageInput.trim()" class="h-8 w-8 shrink-0 bg-blue-600 hover:bg-blue-700 text-white rounded-full flex items-center justify-center shadow-md disabled:opacity-40 transition-all select-none" title="Kirim via API Meta Resmi">
                                🚀
                            </button>
                        </form>
                    </div>
                </template>

                <!-- If No Chat Active -->
                <div v-else class="flex-1 flex flex-col items-center justify-center text-slate-400 p-8 text-center bg-slate-50/20">
                    <div class="text-5xl mb-4">💬</div>
                    <h3 class="text-xs font-black uppercase text-slate-600 tracking-wider">WhatsApp Shared Inbox</h3>
                    <p class="text-[10px] text-slate-400 max-w-xs mt-1 leading-relaxed">Pilih percakapan di kolom kiri untuk mulai membaca dan membalas pesan WhatsApp prospek secara langsung dari CRM Homi.</p>
                </div>
            </div>
        </div>

        <!-- NEW CHAT MODAL -->
        <teleport to="body">
            <div v-if="showNewChatModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showNewChatModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 space-y-4">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-tight">Mulai Chat WhatsApp Baru</h2>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Pilih Prospek (Leads)</label>
                        <select v-model="selectedLeadIdForNewChat" class="w-full px-4 py-2.5 bg-slate-50 border-none rounded-xl text-xs font-bold text-slate-700 focus:ring-1 focus:ring-blue-500 cursor-pointer">
                            <option value="">Pilih Prospek</option>
                            <option v-for="lead in activeLeads" :key="lead.id" :value="lead.id">
                                {{ lead.name }} ({{ lead.phone }})
                            </option>
                        </select>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showNewChatModal = false" class="px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200">Batal</button>
                        <button type="button" @click="startNewChat" :disabled="!selectedLeadIdForNewChat" class="px-5 py-2 bg-blue-600 text-white text-xs font-bold rounded-xl shadow-lg hover:bg-blue-700 disabled:opacity-50">
                            Buka Chat
                        </button>
                    </div>
                </div>
            </div>
        </teleport>
    </CrmLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap');
.font-sans { font-family: 'Outfit', sans-serif; }
</style>
