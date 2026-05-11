<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/two-factor/login';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

const toggleRecoveryMode = (): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    form.clearErrors();
    form.reset();
};

const submit = () => {
    form.submit(store(), {
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
                        <Input
                            v-model="form.code"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            placeholder="000000"
                            maxlength="6"
                            autofocus
                            :disabled="form.processing"
                            class="text-center tracking-widest"
                        />
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
