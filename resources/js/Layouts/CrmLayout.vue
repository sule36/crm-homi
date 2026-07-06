<script setup>
import { ref, computed, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth.user);
const reminders = computed(() => page.props.auth.reminders || []);

const showNotificationsDropdown = ref(false);

function completeReminder(id) {
    router.post(`/reminders/${id}/complete`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showNotificationsDropdown.value = false;
        }
    });
}

const sidebarOpen = ref(true);
const mobileMenuOpen = ref(false);
const financeOpen = ref(route().current('finance.*'));

// Toast Notification System
const toasts = ref([]);
let toastId = 0;

function addToast(title, message, icon = 'ℹ️', type = 'info') {
    const id = toastId++;
    toasts.value.push({ id, title, message, icon, type });
    setTimeout(() => {
        removeToast(id);
    }, 5000);
}

function removeToast(id) {
    toasts.value = toasts.value.filter(t => t.id !== id);
}

const shownToasts = new Set();

watch(() => page.props.flash, (flash) => {
    if (flash?.success && !shownToasts.has(flash.success)) {
        addToast('Sukses', flash.success, '✅', 'success');
        shownToasts.add(flash.success);
        setTimeout(() => shownToasts.delete(flash.success), 6000);
    }
    if (flash?.error && !shownToasts.has(flash.error)) {
        addToast('Error', flash.error, '❌', 'error');
        shownToasts.add(flash.error);
        setTimeout(() => shownToasts.delete(flash.error), 6000);
    }
}, { deep: true, immediate: true });

watch(() => page.props.errors, (errs) => {
    if (errs && Object.keys(errs).length > 0) {
        const msg = Object.values(errs).join(', ');
        if (!shownToasts.has(msg)) {
            addToast('Validasi Gagal', msg, '⚠️', 'error');
            shownToasts.add(msg);
            setTimeout(() => shownToasts.delete(msg), 6000);
        }
    }
}, { deep: true, immediate: true });


const navigationGroups = [
    {
        title: 'Utama',
        items: [
            { name: 'Dashboard', href: '/dashboard', icon: '📊', active: route().current('dashboard') },
        ]
    },
    {
        title: 'Marketing & Sales',
        items: [
            { name: 'Leads', href: '/leads', icon: '👥', active: route().current('leads.index') },
            { name: 'Pipeline', href: '/pipeline', icon: '📋', active: route().current('leads.pipeline') },
            { name: 'Campaign', href: '/campaigns', icon: '📢', active: route().current('campaigns.*') },
            { name: 'Target & Performa', href: '/kpi', icon: '🎯', active: route().current('kpi.*') },
            { name: 'Laporan Sales', href: '/reports', icon: '📈', active: route().current('reports.*') },
        ]
    },
    {
        title: 'Operasional & Unit',
        items: [
            { name: 'Proyek', href: '/projects', icon: '🏗️', active: route().current('projects.*') },
            { name: 'Inventory / Unit', href: '/units', icon: '🏠', active: route().current('units.*') },
        ]
    },
    {
        title: 'Transaksi & Tim',
        items: [
            { name: 'Booking & KPR', href: '/bookings', icon: '📝', active: route().current('bookings.*') },
            { name: 'Staff / Pengguna', href: '/users', icon: '👔', active: route().current('users.*') },
            { name: 'Monitor Agen', href: '/agent-monitoring', icon: '👀', active: route().current('agent-monitoring.*') },
            { name: 'Komisi Agen', href: '/commissions', icon: '💸', active: route().current('commissions.*') },
        ]
    },
    {
        title: 'Keuangan & RAB',
        items: [
            { name: 'Keuangan', icon: '💰', isGroup: true, active: route().current('finance.*'), children: [
                { name: 'Kas & Pembayaran', href: '/finance', icon: '💳', active: route().current('finance.index') },
                { name: 'Pengeluaran', href: '/finance/expenses', icon: '💸', active: route().current('finance.expenses.*') },
                { name: 'Penggajian', href: '/finance/payroll', icon: '💼', active: route().current('finance.payroll.*') },
                { name: 'RAB Proyek', href: '/finance/rab', icon: '🏗️', active: route().current('finance.rab.*') },
                { name: 'Kontrak Subkon', href: '/finance/contracts', icon: '🤝', active: route().current('finance.contracts.*') },
                { name: 'Laporan Keuangan', href: '/finance/reports', icon: '📊', active: route().current('finance.reports.*') },
            ]},
        ]
    }
];

const bottomNav = [
    { name: 'Audit Log', href: '/settings/audit-logs', icon: '🕵️', active: route().current('settings.auditLogs') },
    { name: 'Pengaturan', href: '/settings', icon: '⚙️', active: route().current('settings.index') },
];

</script>

<template>
    <div class="min-h-screen bg-[#f0f2f5] font-sans">
        <!-- MOBILE OVERLAY -->
        <div v-if="mobileMenuOpen" class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden" @click="mobileMenuOpen = false"></div>

        <!-- SIDEBAR -->
        <aside :class="[
            sidebarOpen ? 'w-[260px]' : 'w-[72px]',
            mobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        ]" class="fixed top-0 left-0 z-50 h-screen bg-[#0f172a] transition-all duration-300 flex flex-col overflow-hidden shadow-2xl">
            
            <!-- LOGO -->
            <div class="flex items-center gap-3 px-5 h-[72px] border-b border-white/5 shrink-0">
                <div class="w-9 h-9 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center text-white font-black text-sm shadow-lg shadow-blue-500/30 shrink-0">
                    H
                </div>
                <transition enter-active-class="transition duration-200" enter-from-class="opacity-0 -translate-x-2" enter-to-class="opacity-100 translate-x-0" leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
                    <div v-if="sidebarOpen" class="overflow-hidden">
                        <p class="text-white font-black text-sm tracking-tight leading-none">homi</p>
                        <p class="text-blue-400 text-[9px] font-bold uppercase tracking-[0.2em]">Developer CRM</p>
                    </div>
                </transition>
            </div>

            <!-- NAV ITEMS -->
            <nav class="flex-1 px-3 py-4 space-y-4 overflow-y-auto scrollbar-thin">
                <div v-for="group in navigationGroups" :key="group.title" class="space-y-1">
                    <!-- Group Header -->
                    <div v-if="sidebarOpen" class="px-3 pt-3 pb-1 text-[8px] font-black text-slate-500 uppercase tracking-[0.2em]">{{ group.title }}</div>
                    <div v-else class="h-px bg-white/5 my-2"></div>

                    <!-- Group Items -->
                    <template v-for="item in group.items" :key="item.name">
                        <!-- Group item (submenu) -->
                        <template v-if="item.isGroup">
                            <button @click="financeOpen = !financeOpen"
                                :class="[
                                    item.active
                                        ? 'bg-blue-600/20 text-blue-400 border-blue-500/30'
                                        : 'text-slate-400 hover:text-white hover:bg-white/5 border-transparent',
                                ]"
                                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 border group"
                            >
                                <span class="text-lg shrink-0 w-7 text-center group-hover:scale-110 transition-transform">{{ item.icon }}</span>
                                <span v-if="sidebarOpen" class="truncate flex-1 text-left">{{ item.name }}</span>
                                <svg v-if="sidebarOpen" :class="financeOpen ? 'rotate-90' : ''" class="w-3 h-3 transition-transform duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                            </button>
                            <transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 max-h-0" enter-to-class="opacity-100 max-h-96"
                                leave-active-class="transition-all duration-150 ease-in" leave-from-class="opacity-100 max-h-96" leave-to-class="opacity-0 max-h-0">
                                <div v-if="financeOpen && sidebarOpen" class="ml-4 pl-3 border-l border-white/10 space-y-0.5 mt-1 overflow-hidden">
                                    <Link v-for="child in item.children" :key="child.name" :href="child.href"
                                        :class="[
                                            child.active
                                                ? 'bg-blue-600/15 text-blue-400'
                                                : 'text-slate-500 hover:text-white hover:bg-white/5',
                                        ]"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-semibold transition-all"
                                    >
                                        <span class="text-sm shrink-0 w-5 text-center">{{ child.icon }}</span>
                                        <span class="truncate">{{ child.name }}</span>
                                    </Link>
                                </div>
                            </transition>
                        </template>
                        <!-- Regular item -->
                        <template v-else>
                            <Link :href="item.href"
                                :class="[
                                    item.active
                                        ? 'bg-blue-600/20 text-blue-400 border-blue-500/30'
                                        : 'text-slate-400 hover:text-white hover:bg-white/5 border-transparent',
                                ]"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200 border group"
                            >
                                <span class="text-lg shrink-0 w-7 text-center group-hover:scale-110 transition-transform">{{ item.icon }}</span>
                                <transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100">
                                    <span v-if="sidebarOpen" class="truncate">{{ item.name }}</span>
                                </transition>
                            </Link>
                        </template>
                    </template>
                </div>
            </nav>

            <!-- BOTTOM -->
            <div class="border-t border-white/5 px-3 py-3 shrink-0">
                <Link v-for="item in bottomNav" :key="item.name" :href="item.href"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-500 hover:text-white hover:bg-white/5 transition-all"
                >
                    <span class="text-lg shrink-0 w-7 text-center">{{ item.icon }}</span>
                    <span v-if="sidebarOpen" class="truncate">{{ item.name }}</span>
                </Link>

                <!-- User Avatar -->
                <div class="flex items-center gap-3 px-3 py-3 mt-2 rounded-xl bg-white/5">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-400 to-cyan-500 flex items-center justify-center text-white text-xs font-black shrink-0 shadow-lg">
                        {{ user?.name?.charAt(0) || 'U' }}
                    </div>
                    <div v-if="sidebarOpen" class="min-w-0">
                        <p class="text-white text-xs font-bold truncate">{{ user?.name }}</p>
                        <p class="text-slate-500 text-[10px] font-medium truncate">{{ user?.email }}</p>
                    </div>
                </div>
            </div>

            <!-- COLLAPSE BUTTON -->
            <button @click="sidebarOpen = !sidebarOpen" class="hidden lg:flex items-center justify-center h-10 border-t border-white/5 text-slate-500 hover:text-white transition-colors">
                <svg :class="sidebarOpen ? '' : 'rotate-180'" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                </svg>
            </button>
        </aside>

        <!-- MAIN CONTENT -->
        <div :class="sidebarOpen ? 'lg:pl-[260px]' : 'lg:pl-[72px]'" class="transition-all duration-300">
            <!-- TOP BAR -->
            <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-xl border-b border-gray-200/50 h-[64px] flex items-center justify-between px-6 shadow-sm">
                <div class="flex items-center gap-4">
                    <!-- Mobile Menu -->
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="lg:hidden w-9 h-9 flex items-center justify-center rounded-xl hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <!-- Breadcrumb -->
                    <div class="hidden md:flex items-center gap-2 text-xs text-gray-400 font-medium">
                        <span>homi-developer</span>
                        <span>/</span>
                        <span class="text-gray-800 font-bold">
                            <slot name="breadcrumb">Dashboard</slot>
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Search -->
                    <div class="hidden md:flex items-center bg-gray-100/80 rounded-xl px-3 py-2 gap-2 w-64 hover:bg-gray-100 transition-colors">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" placeholder="Cari lead, unit, proyek..." class="bg-transparent border-none text-xs focus:ring-0 p-0 w-full placeholder:text-gray-400">
                    </div>

                    <!-- Notifications -->
                    <div class="relative">
                        <button @click="showNotificationsDropdown = !showNotificationsDropdown" 
                            class="relative w-9 h-9 flex items-center justify-center rounded-xl hover:bg-gray-100 transition-colors">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            <span v-if="reminders.length" class="absolute top-1.5 right-1.5 w-2 h-2 bg-rose-500 rounded-full animate-pulse"></span>
                        </button>

                        <!-- Notification Dropdown -->
                        <div v-if="showNotificationsDropdown" 
                            class="absolute right-0 mt-2 w-80 bg-white rounded-2xl shadow-xl border border-slate-100 py-3 z-[200] overflow-hidden">
                            <div class="px-4 py-2 border-b border-slate-50 flex justify-between items-center">
                                <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider">Follow-Up Reminders</h4>
                                <span class="text-[9px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-bold">{{ reminders.length }} Pending</span>
                            </div>
                            <div class="max-h-64 overflow-y-auto divide-y divide-slate-50 scrollbar-thin">
                                <div v-for="rem in reminders" :key="rem.id" class="p-3 hover:bg-slate-50/50 transition-colors flex gap-2.5 items-start">
                                    <div class="w-6 h-6 bg-amber-50 text-amber-500 rounded-md flex items-center justify-center text-xs shrink-0 mt-0.5">⏰</div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-slate-800 truncate">{{ rem.message || 'Follow up prospek' }}</p>
                                        <p class="text-[10px] text-slate-400 mt-0.5">Lead: <Link :href="`/leads/${rem.lead_id}`" class="text-blue-500 hover:underline" @click="showNotificationsDropdown = false">{{ rem.lead?.name }}</Link></p>
                                        <p class="text-[9px] font-medium text-slate-400 mt-1">{{ new Date(rem.remind_at).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' }) }}</p>
                                    </div>
                                    <button @click="completeReminder(rem.id)" title="Tandai Selesai" 
                                        class="w-6 h-6 rounded-md bg-emerald-50 text-emerald-600 hover:bg-emerald-100 flex items-center justify-center text-xs shrink-0 transition-colors">
                                        ✓
                                    </button>
                                </div>
                                <div v-if="!reminders.length" class="p-8 text-center text-slate-400">
                                    <span class="text-2xl mb-1 block">🎉</span>
                                    <p class="text-[10px] font-black uppercase tracking-wider">Tidak ada pengingat</p>
                                    <p class="text-[9px] mt-0.5">Semua tugas follow-up Anda telah diselesaikan!</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Logout -->
                    <Link href="/logout" method="post" as="button" class="w-9 h-9 flex items-center justify-center rounded-xl hover:bg-red-50 transition-colors text-gray-400 hover:text-red-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </Link>
                </div>
            </header>

            <!-- PAGE CONTENT -->
            <main class="p-6">
                <slot />
            </main>
        </div>

        <!-- TOAST NOTIFICATIONS -->
        <div class="fixed top-4 right-4 z-[9999] space-y-2 w-full max-w-sm pointer-events-none">
            <transition-group
                enter-active-class="transform ease-out duration-300 transition"
                enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-for="toast in toasts" :key="toast.id" class="pointer-events-auto bg-white rounded-2xl shadow-xl border border-slate-100 p-4 flex items-start gap-3 w-full">
                    <span class="text-xl shrink-0">{{ toast.icon }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold text-slate-800">{{ toast.title }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ toast.message }}</p>
                    </div>
                    <button @click="removeToast(toast.id)" class="text-slate-400 hover:text-slate-600 font-bold text-lg leading-none shrink-0">&times;</button>
                </div>
            </transition-group>
        </div>
    </div>
</template>

<style scoped>
.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}
</style>
