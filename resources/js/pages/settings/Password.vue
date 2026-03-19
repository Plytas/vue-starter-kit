<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { update } from '@/routes/password';
import type { BreadcrumbItem } from '@/types';
import { Head, Form } from '@inertiajs/vue3';
import { ref } from 'vue';

const breadcrumbItems: BreadcrumbItem[] = [
	{
		title: 'Password settings',
		href: '/settings/password',
	},
];

const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);
const recentlySuccessful = ref(false);

const handleError = (errors: any) => {
	if (errors.password) {
		if (passwordInput.value instanceof HTMLInputElement) {
			passwordInput.value.focus();
		}
	}

	if (errors.current_password) {
		if (currentPasswordInput.value instanceof HTMLInputElement) {
			currentPasswordInput.value.focus();
		}
	}
};
</script>

<template>
	<AppLayout :breadcrumbs="breadcrumbItems">
		<Head title="Password settings" />

		<SettingsLayout>
			<div class="space-y-6">
				<HeadingSmall title="Update password" description="Ensure your account is using a long, random password to stay secure" />

				<Form
					method="put"
					:action="update()"
					:reset-on-error="['password', 'password_confirmation', 'current_password']"
					:options="{ preserveScroll: true }"
					@success="recentlySuccessful = true"
					@error="handleError"
					v-slot="{ errors, processing }"
				>
					<div class="space-y-6">
						<div class="grid gap-2">
							<Label for="current_password">Current password</Label>
							<Input
								id="current_password"
								ref="currentPasswordInput"
								type="password"
								name="current_password"
								class="mt-1 block w-full"
								autocomplete="current-password"
								placeholder="Current password"
							/>
							<InputError :message="errors.current_password" />
						</div>

						<div class="grid gap-2">
							<Label for="password">New password</Label>
							<Input
								id="password"
								ref="passwordInput"
								type="password"
								name="password"
								class="mt-1 block w-full"
								autocomplete="new-password"
								placeholder="New password"
							/>
							<InputError :message="errors.password" />
						</div>

						<div class="grid gap-2">
							<Label for="password_confirmation">Confirm password</Label>
							<Input
								id="password_confirmation"
								type="password"
								name="password_confirmation"
								class="mt-1 block w-full"
								autocomplete="new-password"
								placeholder="Confirm password"
							/>
							<InputError :message="errors.password_confirmation" />
						</div>

						<div class="flex items-center gap-4">
							<Button type="submit" :disabled="processing">Save password</Button>

							<Transition
								enter-active-class="transition ease-in-out"
								enter-from-class="opacity-0"
								leave-active-class="transition ease-in-out"
								leave-to-class="opacity-0"
							>
								<p v-show="recentlySuccessful" class="text-sm text-neutral-600">Saved.</p>
							</Transition>
						</div>
					</div>
				</Form>
			</div>
		</SettingsLayout>
	</AppLayout>
</template>
