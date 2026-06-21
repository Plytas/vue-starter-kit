<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

/* @chisel-passkeys */
import { index as confirmOptions, store as confirmStore } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
/* @end-chisel-passkeys */
import InputError from '@/components/InputError.vue';
/* @chisel-passkeys */
import PasskeyVerify from '@/components/PasskeyVerify.vue';
/* @end-chisel-passkeys */
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/password/confirm';
import type { ConfirmPasswordRequest } from '@/types/generated';

const form = useForm<ConfirmPasswordRequest>({
	password: '',
});

const submit = () => {
	form.submit(store(), {
		onFinish: () => {
			form.reset();
		},
	});
};
</script>

<template>
	<AuthLayout title="Confirm password" description="This is a secure area of the application. Please confirm your password before continuing.">
		<Head title="Confirm password" />

		<!-- @chisel-passkeys -->
		<PasskeyVerify
			:routes="{ options: confirmOptions(), submit: confirmStore() }"
			label="Confirm with passkey"
			loading-label="Confirming..."
			separator="Or confirm with password"
		/>
		<!-- @end-chisel-passkeys -->

		<form @submit.prevent="submit">
			<div class="space-y-6">
				<div class="grid gap-2">
					<Label htmlFor="password">Password</Label>
					<PasswordInput
						id="password"
						class="mt-1 block w-full"
						v-model="form.password"
						required
						autocomplete="current-password"
						autofocus
					/>

					<InputError :message="form.errors.password" />
				</div>

				<div class="flex items-center">
					<Button class="w-full" :disabled="form.processing">
						<Spinner v-if="form.processing" />
						Confirm password
					</Button>
				</div>
			</div>
		</form>
	</AuthLayout>
</template>
