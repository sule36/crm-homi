<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    maxWidth: {
        type: String,
        default: 'sm:max-w-md',
    },
    hideLogo: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-slate-950 px-4 py-10 relative overflow-hidden font-sans selection:bg-blue-500 selection:text-white"
    >
        <!-- Ambient lighting elements -->
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-blue-600/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-indigo-600/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-purple-600/5 rounded-full blur-3xl pointer-events-none"></div>

        <div v-if="!hideLogo" class="mb-6 relative z-10">
            <Link href="/" class="flex items-center gap-3 group">
                <div class="w-11 h-11 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center text-white font-black text-xl shadow-xl shadow-blue-500/25 border border-white/10 group-hover:scale-105 transition-transform duration-200">
                    H
                </div>
                <div>
                    <p class="text-white font-black text-lg tracking-tight leading-none">homi</p>
                    <p class="text-blue-400 text-[10px] font-bold uppercase tracking-[0.25em] mt-0.5">Developer CRM</p>
                </div>
            </Link>
        </div>

        <div
            class="w-full relative z-10 overflow-hidden bg-white/95 backdrop-blur-xl px-6 py-7 shadow-2xl rounded-2xl border border-white/20 transition-all duration-300"
            :class="maxWidth"
        >
            <slot />
        </div>
        
        <!-- Footer copyright info -->
        <div class="mt-8 text-center text-xs text-slate-500 relative z-10">
            &copy; {{ new Date().getFullYear() }} Homi Developer CRM. All rights reserved.
        </div>
    </div>
</template>
