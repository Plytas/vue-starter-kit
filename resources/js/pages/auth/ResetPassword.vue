<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/password';
import { ResetPasswordProps } from '@/types/generated';
import { Head, Form } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<ResetPasswordProps>();
</script>

<template>
	<AuthLayout title="Reset password" description="Please enter your new password below">
		<Head title="Reset password" />

		<Form
			method="post"
			:action="store()"
			:transform="(data) => ({ ...data, token: props.token, email: props.email })"
			:reset-on-success="['password', 'password_confirmation']"
			v-slot="{ errors, processing }"
		>
			<div class="grid gap-6">
				<div class="grid gap-2">
					<Label for="email">Email</Label>
					<Input id="email" type="email" name="email" autocomplete="email" :default-value="props.email" class="mt-1 block w-full" readonly />
					<InputError :message="errors.email" class="mt-2" />
				</div>

				<div class="grid gap-2">
					<Label for="password">Password</Label>
					<Input
						id="password"
						type="password"
						name="password"
						autocomplete="new-password"
						class="mt-1 block w-full"
						autofocus
						placeholder="Password"
					/>
					<InputError :message="errors.password" />
				</div>

				<div class="grid gap-2">
					<Label for="password_confirmation"> Confirm Password </Label>
					<Input
						id="password_confirmation"
						type="password"
						name="password_confirmation"
						autocomplete="new-password"
						class="mt-1 block w-full"
						placeholder="Confirm password"
					/>
					<InputError :message="errors.password_confirmation" />
				</div>

				<Button type="submit" class="mt-4 w-full" :disabled="processing">
					<LoaderCircle v-if="processing" class="h-4 w-4 animate-spin" />
					Reset password
				</Button>
			</div>
		</Form>
	</AuthLayout>
</template>
