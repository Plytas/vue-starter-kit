<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { request } from '@/routes/password';
import type { LoginProps, LoginRequest } from '@/types/generated';

defineProps<LoginProps>();

const loginForm = useForm<LoginRequest>('login', {
	email: '',
	password: '',
	remember: false,
});

const submit = () => {
	loginForm.submit(AuthenticatedSessionController.store(), {
		onFinish: () => loginForm.reset('password'),
	});
};
</script>

<template>
	<AuthBase title="Log in to your account">
		<Head title="Log in" />

		<div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
			{{ status }}
		</div>

		<PasskeyVerify />

		<form @submit.prevent="submit" class="flex flex-col gap-6">
			<div class="grid gap-6">
				<div class="grid gap-2">
					<Label for="email">Email address</Label>
					<Input
						id="email"
						type="email"
						required
						autofocus
						:tabindex="1"
						autocomplete="email webauthn"
						v-model="loginForm.email"
						placeholder="email@example.com"
					/>
					<InputError :message="loginForm.errors.email" />
				</div>

				<div class="grid gap-2">
					<div class="flex items-center justify-between">
						<Label for="password">Password</Label>
						<TextLink v-if="canResetPassword" :href="request()" class="text-sm" :tabindex="5"> Forgot password? </TextLink>
					</div>
					<PasswordInput
						id="password"
						required
						:tabindex="2"
						autocomplete="current-password"
						v-model="loginForm.password"
						placeholder="Password"
					/>
					<InputError :message="loginForm.errors.password" />
				</div>

				<div class="flex items-center justify-between">
					<Label for="remember" class="flex items-center space-x-3">
						<Checkbox id="remember" v-model="loginForm.remember" :tabindex="3" />
						<span>Remember me</span>
					</Label>
				</div>

				<Button type="submit" class="mt-4 w-full" :tabindex="4" :disabled="loginForm.processing">
					<Spinner v-if="loginForm.processing" />
					Log in
				</Button>
			</div>

			<div v-if="canRegister" class="text-center text-sm text-muted-foreground">
				Don't have an account?
				<TextLink :href="register()" :tabindex="5">Sign up</TextLink>
			</div>
		</form>
	</AuthBase>
</template>
