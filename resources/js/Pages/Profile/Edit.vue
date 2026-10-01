<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profil & Ganti Password" />

    <CrmLayout>
        <template #breadcrumb>Profil & Ganti Password</template>

        <div class="max-w-4xl mx-auto space-y-6">
            <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-slate-100">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-xl"
                />
            </div>

            <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-slate-100">
                <UpdatePasswordForm class="max-w-xl" />
            </div>

            <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-slate-100">
                <DeleteUserForm class="max-w-xl" />
            </div>
        </div>
    </CrmLayout>
</template>
