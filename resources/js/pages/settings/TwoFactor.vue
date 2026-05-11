<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { disable, enable } from '@/routes/two-factor';
import type { BreadcrumbItem } from '@/types';
import type { TwoFactorProps } from '@/types/generated';
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldBan, ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';

defineProps<TwoFactorProps>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Two-Factor Authentication',
        href: '/settings/two-factor',
    },
];

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

const enableForm = useForm({});
const disableForm = useForm({});

const enable2fa = () => {
    enableForm.submit(enable(), {
        preserveScroll: true,
        onSuccess: () => { showSetupModal.value = true; },
    });
};

const disable2fa = () => {
    disableForm.submit(disable(), { preserveScroll: true });
};

onUnmounted(() => {
    clearTwoFactorAuthData();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Two-Factor Authentication" />
        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Two-Factor Authentication" description="Manage your two-factor authentication settings" />

                <div v-if="!twoFactorEnabled" class="flex flex-col items-start justify-start space-y-4">
                    <Badge variant="destructive">Disabled</Badge>

                    <p class="text-muted-foreground">
                        When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from
                        a TOTP-supported application on your phone.
                    </p>

                    <div>
                        <Button v-if="hasSetupData" @click="showSetupModal = true"> <ShieldCheck />Continue Setup </Button>
                        <form v-else @submit.prevent="enable2fa">
                            <Button type="submit" :disabled="enableForm.processing"> <ShieldCheck />Enable 2FA</Button>
                        </form>
                    </div>
                </div>

                <div v-else class="flex flex-col items-start justify-start space-y-4">
                    <Badge variant="default">Enabled</Badge>

                    <p class="text-muted-foreground">
                        With two-factor authentication enabled, you will be prompted for a secure, random pin during login, which you can retrieve
                        from the TOTP-supported application on your phone.
                    </p>

                    <TwoFactorRecoveryCodes />

                    <div class="relative inline">
                        <form @submit.prevent="disable2fa">
                            <Button variant="destructive" type="submit" :disabled="disableForm.processing">
                                <ShieldBan />
                                Disable 2FA
                            </Button>
                        </form>
                    </div>
                </div>

                <TwoFactorSetupModal
                    v-model:isOpen="showSetupModal"
                    :requiresConfirmation="requiresConfirmation"
                    :twoFactorEnabled="twoFactorEnabled"
                />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
