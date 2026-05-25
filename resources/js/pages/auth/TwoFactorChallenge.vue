<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/two-factor/login';

interface AuthConfigContent {
    title: string;
    description: string;
    toggleText: string;
}

const authConfigContent = computed<AuthConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Recovery Code',
            description: 'Please confirm access to your account by entering one of your emergency recovery codes.',
            toggleText: 'login using an authentication code',
        };
    }

    return {
        title: 'Authentication Code',
        description: 'Enter the authentication code provided by your authenticator application.',
        toggleText: 'login using a recovery code',
    };
});

const showRecoveryInput = ref<boolean>(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const code = ref<string>('');

const toggleRecoveryMode = (): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    code.value = '';
    form.clearErrors();
    form.reset();
};

const submit = () => {
    form.code = code.value;
    form.submit(store(), {
        onError: () => { code.value = ''; },
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <AuthLayout :title="authConfigContent.title" :description="authConfigContent.description">
        <Head title="Two-Factor Authentication" />

        <div class="space-y-6">
            <form @submit.prevent="submit" class="space-y-4">
                <template v-if="!showRecoveryInput">
                    <div class="flex flex-col items-center justify-center space-y-3 text-center">
                        <div class="flex w-full items-center justify-center">
                            <InputOTP id="otp" v-model="code" :maxlength="6" :disabled="form.processing" autofocus>
                                <InputOTPGroup>
                                    <InputOTPSlot v-for="index in 6" :key="index" :index="index - 1" />
                                </InputOTPGroup>
                            </InputOTP>
                        </div>
                        <InputError :message="form.errors.code" />
                    </div>
                </template>

                <template v-else>
                    <Input
                        v-model="form.recovery_code"
                        type="text"
                        placeholder="Enter recovery code"
                        :autofocus="showRecoveryInput"
                        required
                    />
                    <InputError :message="form.errors.recovery_code" />
                </template>

                <Button type="submit" class="w-full" :disabled="form.processing">Continue</Button>

                <div class="text-center text-sm text-muted-foreground">
                    <span>or you can </span>
                    <button
                        type="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                        @click="toggleRecoveryMode"
                    >
                        {{ authConfigContent.toggleText }}
                    </button>
                </div>
            </form>
        </div>
    </AuthLayout>
</template>
