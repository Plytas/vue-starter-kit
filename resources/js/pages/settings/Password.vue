<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { update } from '@/routes/user-password';
import type { BreadcrumbItem } from '@/types';
import type { PasswordUpdateRequest } from '@/types/generated';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const breadcrumbItems: BreadcrumbItem[] = [
	{
		title: 'Password settings',
		href: '/settings/password',
	},
];

const passwordInput = ref<{ focus: () => void } | null>(null);
const currentPasswordInput = ref<{ focus: () => void } | null>(null);

const form = useForm<PasswordUpdateRequest>({
	current_password: '',
	password: '',
	password_confirmation: '',
});

const updatePassword = () => {
	form.submit(update(), {
		preserveScroll: true,
		onSuccess: () => form.reset(),
		onError: (errors: any) => {
			if (errors.password) {
				form.reset('password', 'password_confirmation');
				passwordInput.value?.focus();
			}

			if (errors.current_password) {
				form.reset('current_password');
				currentPasswordInput.value?.focus();
			}
		},
	});
};
</script>

<template>
	<AppLayout :breadcrumbs="breadcrumbItems">
		<Head title="Password settings" />

		<SettingsLayout>
			<div class="space-y-6">
				<HeadingSmall title="Update password" description="Ensure your account is using a long, random password to stay secure" />

				<form @submit.prevent="updatePassword" class="space-y-6">
					<div class="grid gap-2">
						<Label for="current_password">Current password</Label>
						<PasswordInput
							id="current_password"
							ref="currentPasswordInput"
							v-model="form.current_password"
							class="mt-1 block w-full"
							autocomplete="current-password"
							placeholder="Current password"
						/>
						<InputError :message="form.errors.current_password" />
					</div>

					<div class="grid gap-2">
						<Label for="password">New password</Label>
						<PasswordInput
							id="password"
							ref="passwordInput"
							v-model="form.password"
							class="mt-1 block w-full"
							autocomplete="new-password"
							placeholder="New password"
						/>
						<InputError :message="form.errors.password" />
					</div>

					<div class="grid gap-2">
						<Label for="password_confirmation">Confirm password</Label>
						<PasswordInput
							id="password_confirmation"
							v-model="form.password_confirmation"
							class="mt-1 block w-full"
							autocomplete="new-password"
							placeholder="Confirm password"
						/>
						<InputError :message="form.errors.password_confirmation" />
					</div>

					<div class="flex items-center gap-4">
						<Button :disabled="form.processing">Save password</Button>
					</div>
				</form>
			</div>
		</SettingsLayout>
	</AppLayout>
</template>
