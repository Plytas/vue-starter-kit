<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { update } from '@/routes/password';
import { NewPasswordRequest, ResetPasswordProps } from '@/types/generated';

const props = defineProps<ResetPasswordProps>();

const form = useForm<NewPasswordRequest>({
	token: props.token,
	email: props.email,
	password: '',
	password_confirmation: '',
});

const submit = () => {
	form.submit(update(), {
		onFinish: () => {
			form.reset('password', 'password_confirmation');
		},
	});
};
</script>

<template>
	<AuthLayout title="Reset password" description="Please enter your new password below">
		<Head title="Reset password" />

		<form @submit.prevent="submit">
			<div class="grid gap-6">
				<div class="grid gap-2">
					<Label for="email">Email</Label>
					<Input id="email" type="email" name="email" autocomplete="email" v-model="form.email" class="mt-1 block w-full" readonly />
					<InputError :message="form.errors.email" class="mt-2" />
				</div>

				<div class="grid gap-2">
					<Label for="password">Password</Label>
					<PasswordInput
						id="password"
						name="password"
						autocomplete="new-password"
						v-model="form.password"
						class="mt-1 block w-full"
						autofocus
						:passwordrules="passwordRules"
						placeholder="Password"
					/>
					<InputError :message="form.errors.password" />
				</div>

				<div class="grid gap-2">
					<Label for="password_confirmation"> Confirm password </Label>
					<PasswordInput
						id="password_confirmation"
						name="password_confirmation"
						autocomplete="new-password"
						v-model="form.password_confirmation"
						class="mt-1 block w-full"
						:passwordrules="passwordRules"
						placeholder="Confirm password"
					/>
					<InputError :message="form.errors.password_confirmation" />
				</div>

				<Button type="submit" class="mt-4 w-full" :disabled="form.processing">
					<Spinner v-if="form.processing" />
					Reset password
				</Button>
			</div>
		</form>
	</AuthLayout>
</template>
