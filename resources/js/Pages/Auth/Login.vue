<script setup>
import { ref, onMounted } from 'vue';
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    initialPortal: {
        type: String,
        default: 'developer',
    },
});

const activePortal = ref(props.initialPortal === 'owner' ? 'owner' : 'developer');
const showPassword = ref(false);

function switchPortal(portal) {
    activePortal.value = portal;
    const newUrl = portal === 'owner' ? '/super-admin/login' : '/login';
    if (window.history && window.history.replaceState) {
        window.history.replaceState({}, '', newUrl);
    }
}

onMounted(() => {
    // If URL has query ?portal=owner or path contains super-admin, match it
    if (window.location.pathname.includes('super-admin') || window.location.search.includes('owner') || window.location.search.includes('saas')) {
        activePortal.value = 'owner';
    }
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
    portal: activePortal,
});

const submit = () => {
    form.portal = activePortal.value;
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout maxWidth="sm:max-w-lg">
        <Head :title="activePortal === 'owner' ? 'Login Pemilik SaaS - Control Tower' : 'Login Developer Properti - CRM Homi'" />

        <!-- Status Notification -->
        <div v-if="status" class="mb-5 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-800 flex items-center gap-2">
            <span>✅</span>
            <span>{{ status }}</span>
        </div>

        <!-- PORTAL SELECTOR TABS -->
        <div class="mb-6">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2 px-1">
                Pilih Portal Masuk
            </p>
            <div class="grid grid-cols-2 gap-1.5 p-1.5 bg-slate-100 rounded-xl border border-slate-200/80">
                <!-- Tab Developer -->
                <button
                    type="button"
                    @click="switchPortal('developer')"
                    class="flex flex-col items-center justify-center py-2.5 px-3 rounded-lg text-xs font-bold transition-all duration-200"
                    :class="activePortal === 'developer'
                        ? 'bg-white text-blue-700 shadow-sm border border-slate-200/60 ring-2 ring-blue-500/20'
                        : 'text-slate-500 hover:text-slate-800 hover:bg-white/50'"
                >
                    <div class="flex items-center gap-1.5 mb-0.5">
                        <span class="text-sm">🏢</span>
                        <span class="font-extrabold tracking-tight">Developer Properti</span>
                    </div>
                    <span class="text-[10px] font-normal" :class="activePortal === 'developer' ? 'text-blue-600' : 'text-slate-400'">
                        Workspace CRM & Penjualan
                    </span>
                </button>

                <!-- Tab Pemilik SaaS -->
                <button
                    type="button"
                    @click="switchPortal('owner')"
                    class="flex flex-col items-center justify-center py-2.5 px-3 rounded-lg text-xs font-bold transition-all duration-200"
                    :class="activePortal === 'owner'
                        ? 'bg-gradient-to-r from-slate-900 to-indigo-950 text-amber-300 shadow-md border border-indigo-900/50 ring-2 ring-indigo-500/30'
                        : 'text-slate-500 hover:text-slate-800 hover:bg-white/50'"
                >
                    <div class="flex items-center gap-1.5 mb-0.5">
                        <span class="text-sm">👑</span>
                        <span class="font-extrabold tracking-tight">Pemilik SaaS</span>
                    </div>
                    <span class="text-[10px] font-normal" :class="activePortal === 'owner' ? 'text-indigo-200' : 'text-slate-400'">
                        SaaS Control Tower
                    </span>
                </button>
            </div>
        </div>

        <!-- PORTAL HEADER BRANDING -->
        <div class="mb-6 pb-5 border-b border-slate-100">
            <!-- DEVELOPER PORTAL HEADER -->
            <div v-if="activePortal === 'developer'" class="space-y-1">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-50 border border-blue-200 text-blue-700 text-[10px] font-black uppercase tracking-wider mb-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                    Workspace Developer Properti
                </div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight">
                    Masuk ke CRM Developer
                </h1>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Sistem operasional kawasan proyek, leads penjualan, booking kavling, site plan interaktif, kasir & legalitas SPR.
                </p>
            </div>

            <!-- OWNER SAAS PORTAL HEADER -->
            <div v-else class="space-y-1">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 border border-indigo-200 text-indigo-800 text-[10px] font-black uppercase tracking-wider mb-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-purple-600 animate-pulse"></span>
                    SaaS Master Control Tower
                </div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    Portal Pemilik Platform SaaS
                    <span class="text-sm font-semibold px-2 py-0.5 bg-amber-100 text-amber-800 rounded-md border border-amber-300">Super Admin</span>
                </h1>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Pusat kendali master untuk mengelola semua perusahaan developer klien, aktivasi paket langganan, dan billing global.
                </p>
            </div>
        </div>

        <!-- LOGIN FORM -->
        <form @submit.prevent="submit" class="space-y-4">
            <!-- Email -->
            <div>
                <InputLabel
                    for="email"
                    :value="activePortal === 'owner' ? 'Email Super Administrator' : 'Email Akun Developer'"
                    class="text-xs font-bold text-slate-700"
                />

                <div class="relative mt-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-sm">
                        {{ activePortal === 'owner' ? '👑' : '✉️' }}
                    </div>
                    <TextInput
                        id="email"
                        type="email"
                        class="block w-full pl-9 pr-3 text-sm rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                        :class="activePortal === 'owner' ? 'focus:border-indigo-600 focus:ring-indigo-600' : ''"
                        v-model="form.email"
                        :placeholder="activePortal === 'owner' ? 'admin@homi.id' : 'nama@developer.com atau marketing@properti.id'"
                        required
                        autofocus
                        autocomplete="username"
                    />
                </div>

                <InputError class="mt-1 text-xs" :message="form.errors.email" />
            </div>

            <!-- Password -->
            <div>
                <div class="flex items-center justify-between">
                    <InputLabel for="password" value="Kata Sandi (Password)" class="text-xs font-bold text-slate-700" />
                    <Link
                        v-if="canResetPassword && activePortal === 'developer'"
                        :href="route('password.request')"
                        class="text-[11px] font-semibold text-blue-600 hover:text-blue-800 hover:underline"
                    >
                        Lupa kata sandi?
                    </Link>
                </div>

                <div class="relative mt-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-sm">
                        🔒
                    </div>
                    <TextInput
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        class="block w-full pl-9 pr-10 text-sm rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 shadow-sm"
                        :class="activePortal === 'owner' ? 'focus:border-indigo-600 focus:ring-indigo-600' : ''"
                        v-model="form.password"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    />
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 text-xs font-medium"
                    >
                        {{ showPassword ? 'Sembunyikan' : 'Lihat' }}
                    </button>
                </div>

                <InputError class="mt-1 text-xs" :message="form.errors.password" />
            </div>

            <!-- Remember me -->
            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <Checkbox name="remember" v-model:checked="form.remember" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
                    <span class="text-xs text-slate-600 font-medium">Ingat perangkat ini</span>
                </label>
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <PrimaryButton
                    v-if="activePortal === 'developer'"
                    class="w-full justify-center py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-black text-sm shadow-lg shadow-blue-500/25 border-0 transition-all duration-200"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Memverifikasi...
                    </span>
                    <span v-else class="flex items-center gap-2">
                        Masuk ke Workspace Developer →
                    </span>
                </PrimaryButton>

                <PrimaryButton
                    v-else
                    class="w-full justify-center py-3 rounded-xl bg-gradient-to-r from-slate-900 via-indigo-950 to-purple-950 hover:from-black hover:to-indigo-900 text-amber-300 font-black text-sm shadow-xl shadow-indigo-950/30 border border-amber-500/30 transition-all duration-200"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-amber-300" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Mengakses Control Tower...
                    </span>
                    <span v-else class="flex items-center gap-2">
                        Masuk ke SaaS Control Tower 👑 →
                    </span>
                </PrimaryButton>
            </div>
        </form>

        <!-- PORTAL GUIDANCE FOOTNOTE -->
        <div class="mt-6 pt-4 border-t border-slate-100">
            <!-- Note for Developer -->
            <div v-if="activePortal === 'developer'" class="rounded-xl bg-slate-50 border border-slate-200/80 p-3 space-y-1.5">
                <div class="flex items-start gap-2">
                    <span class="text-xs">💡</span>
                    <p class="text-[11px] text-slate-600 leading-relaxed">
                        <strong class="font-bold text-slate-800">Akses Karyawan & Direktur:</strong> Gunakan kredensial yang didaftarkan oleh admin kantor developer Anda.
                    </p>
                </div>
                <div class="text-[11px] text-slate-500 pt-1 border-t border-slate-200/50 flex items-center justify-between">
                    <span>Pemilik Platform Homi?</span>
                    <button
                        type="button"
                        @click="switchPortal('owner')"
                        class="font-bold text-indigo-600 hover:text-indigo-800 hover:underline"
                    >
                        Buka Portal Master SaaS 👑
                    </button>
                </div>
            </div>

            <!-- Note for Owner -->
            <div v-else class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-3 space-y-1.5">
                <div class="flex items-start gap-2">
                    <span class="text-xs">🔒</span>
                    <p class="text-[11px] text-indigo-900 leading-relaxed">
                        <strong class="font-bold text-indigo-950">Akses Terbatas:</strong> Hanya untuk Super Administrator SaaS Homi guna mengelola lisensi, konfigurasi tenant, dan tagihan klien.
                    </p>
                </div>
                <div class="text-[11px] text-indigo-700 pt-1 border-t border-indigo-200/50 flex items-center justify-between">
                    <span>Bukan Pemilik Platform SaaS?</span>
                    <button
                        type="button"
                        @click="switchPortal('developer')"
                        class="font-bold text-blue-600 hover:text-blue-800 hover:underline"
                    >
                        Kembali ke Portal Developer 🏢
                    </button>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
