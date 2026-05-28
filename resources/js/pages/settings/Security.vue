<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldBan, ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';

import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit, update } from '@/routes/security';
import { disable, enable } from '@/routes/two-factor';
import type { BreadcrumbItem } from '@/types';
import type { PasswordUpdateRequest } from '@/types/generated';

type Props = {
	canManageTwoFactor?: boolean;
	requiresConfirmation?: boolean;
	twoFactorEnabled?: boolean;
};

withDefaults(defineProps<Props>(), {
	canManageTwoFactor: false,
	requiresConfirmation: false,
	twoFactorEnabled: false,
});

const breadcrumbs: BreadcrumbItem[] = [
	{
		title: 'Security settings',
		href: edit(),
	},
];

const passwordInput = ref<{ focus: () => void } | null>(null);
const currentPasswordInput = ref<{ focus: () => void } | null>(null);

const passwordForm = useForm<PasswordUpdateRequest>({
	current_password: '',
	password: '',
	password_confirmation: '',
});

const updatePassword = () => {
	passwordForm.submit(update(), {
		preserveScroll: true,
		onSuccess: () => passwordForm.reset(),
		onError: (errors: Record<string, string>) => {
			if (errors.password) {
				passwordForm.reset('password', 'password_confirmation');
				passwordInput.value?.focus();
			}

			if (errors.current_password) {
				passwordForm.reset('current_password');
				currentPasswordInput.value?.focus();
			}
		},
	});
};

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

const enableForm = useForm({});
const disableForm = useForm({});

const enable2fa = () => {
	enableForm.submit(enable(), {
		preserveScroll: true,
		onSuccess: () => {
			showSetupModal.value = true;
		},
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
		<Head title="Security settings" />

		<h1 class="sr-only">Security settings</h1>

		<SettingsLayout>
			<div class="space-y-6">
				<Heading variant="small" title="Update password" description="Ensure your account is using a long, random password to stay secure" />

				<form @submit.prevent="updatePassword" class="space-y-6">
					<div class="grid gap-2">
						<Label for="current_password">Current password</Label>
						<PasswordInput
							id="current_password"
							ref="currentPasswordInput"
							v-model="passwordForm.current_password"
							class="mt-1 block w-full"
							autocomplete="current-password"
							placeholder="Current password"
						/>
						<InputError :message="passwordForm.errors.current_password" />
					</div>

					<div class="grid gap-2">
						<Label for="password">New password</Label>
						<PasswordInput
							id="password"
							ref="passwordInput"
							v-model="passwordForm.password"
							class="mt-1 block w-full"
							autocomplete="new-password"
							placeholder="New password"
						/>
						<InputError :message="passwordForm.errors.password" />
					</div>

					<div class="grid gap-2">
						<Label for="password_confirmation">Confirm password</Label>
						<PasswordInput
							id="password_confirmation"
							v-model="passwordForm.password_confirmation"
							class="mt-1 block w-full"
							autocomplete="new-password"
							placeholder="Confirm password"
						/>
						<InputError :message="passwordForm.errors.password_confirmation" />
					</div>

					<div class="flex items-center gap-4">
						<Button :disabled="passwordForm.processing">Save password</Button>
					</div>
				</form>
			</div>

			<div v-if="canManageTwoFactor" class="space-y-6">
				<Heading variant="small" title="Two-factor authentication" description="Manage your two-factor authentication settings" />

				<div v-if="!twoFactorEnabled" class="flex flex-col items-start justify-start space-y-4">
					<Badge variant="destructive">Disabled</Badge>

					<p class="text-muted-foreground">
						When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from
						a TOTP-supported application on your phone.
					</p>

					<div>
						<Button v-if="hasSetupData" @click="showSetupModal = true"> <ShieldCheck />Continue setup </Button>
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
