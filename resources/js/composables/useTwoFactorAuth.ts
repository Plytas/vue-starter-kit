import { qrCode, recoveryCodes, secretKey } from '@/routes/two-factor';
import { useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const qrCodeSvg = ref<string | null>(null);
const manualSetupKey = ref<string | null>(null);
const recoveryCodesList = ref<string[]>([]);

const hasSetupData = computed<boolean>(() => qrCodeSvg.value !== null && manualSetupKey.value !== null);

export const useTwoFactorAuth = () => {
    const fetchQrCode = async (): Promise<void> => {
        const { svg } = await useHttp<Record<string, never>, { svg: string; url: string }>().submit(qrCode());

        qrCodeSvg.value = svg;
    };

    const fetchSetupKey = async (): Promise<void> => {
        const { secretKey: key } = await useHttp<Record<string, never>, { secretKey: string }>().submit(secretKey());

        manualSetupKey.value = key;
    };

    const clearSetupData = (): void => {
        manualSetupKey.value = null;
        qrCodeSvg.value = null;
    };

    const clearTwoFactorAuthData = (): void => {
        clearSetupData();

        recoveryCodesList.value = [];
    };

    const fetchRecoveryCodes = async (): Promise<void> => {
        try {
            recoveryCodesList.value = await useHttp<Record<string, never>, string[]>().submit(recoveryCodes());
        } catch (error) {
            console.error('Failed to fetch recovery codes:', error);

            recoveryCodesList.value = [];
        }
    };

    const fetchSetupData = async (): Promise<void> => {
        try {
            await Promise.all([fetchQrCode(), fetchSetupKey()]);
        } catch (error) {
            console.error('Failed to fetch setup data:', error);

            qrCodeSvg.value = null;
            manualSetupKey.value = null;
        }
    };

    return {
        qrCodeSvg,
        manualSetupKey,
        recoveryCodesList,
        hasSetupData,
        clearSetupData,
        clearTwoFactorAuthData,
        fetchQrCode,
        fetchSetupKey,
        fetchSetupData,
        fetchRecoveryCodes,
    };
};
