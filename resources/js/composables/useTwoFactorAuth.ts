import { useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';

import { qrCode, recoveryCodes, secretKey } from '@/routes/two-factor';

export type UseTwoFactorAuthReturn = {
	qrCodeSvg: Ref<string | null>;
	manualSetupKey: Ref<string | null>;
	recoveryCodesList: Ref<string[]>;
	errors: Ref<string[]>;
	hasSetupData: ComputedRef<boolean>;
	clearSetupData: () => void;
	clearErrors: () => void;
	clearTwoFactorAuthData: () => void;
	fetchQrCode: () => Promise<void>;
	fetchSetupKey: () => Promise<void>;
	fetchSetupData: () => Promise<void>;
	fetchRecoveryCodes: () => Promise<void>;
};

const qrCodeSvg = ref<string | null>(null);
const manualSetupKey = ref<string | null>(null);
const recoveryCodesList = ref<string[]>([]);
const errors = ref<string[]>([]);

const hasSetupData = computed<boolean>(() => qrCodeSvg.value !== null && manualSetupKey.value !== null);

export const useTwoFactorAuth = (): UseTwoFactorAuthReturn => {
	const clearErrors = (): void => {
		errors.value = [];
	};

	const fetchQrCode = async (): Promise<void> => {
		try {
			const { svg } = await useHttp<Record<string, never>, { svg: string; url: string }>().submit(qrCode());

			qrCodeSvg.value = svg;
		} catch {
			errors.value.push('Failed to fetch QR code');
		}
	};

	const fetchSetupKey = async (): Promise<void> => {
		try {
			const { secretKey: key } = await useHttp<Record<string, never>, { secretKey: string }>().submit(secretKey());

			manualSetupKey.value = key;
		} catch {
			errors.value.push('Failed to fetch a setup key');
		}
	};

	const clearSetupData = (): void => {
		manualSetupKey.value = null;
		qrCodeSvg.value = null;
		clearErrors();
	};

	const clearTwoFactorAuthData = (): void => {
		clearSetupData();

		recoveryCodesList.value = [];
	};

	const fetchRecoveryCodes = async (): Promise<void> => {
		clearErrors();

		try {
			recoveryCodesList.value = await useHttp<Record<string, never>, string[]>().submit(recoveryCodes());
		} catch {
			errors.value.push('Failed to fetch recovery codes');

			recoveryCodesList.value = [];
		}
	};

	const fetchSetupData = async (): Promise<void> => {
		clearErrors();

		await Promise.all([fetchQrCode(), fetchSetupKey()]);
	};

	return {
		qrCodeSvg,
		manualSetupKey,
		recoveryCodesList,
		errors,
		hasSetupData,
		clearSetupData,
		clearErrors,
		clearTwoFactorAuthData,
		fetchQrCode,
		fetchSetupKey,
		fetchSetupData,
		fetchRecoveryCodes,
	};
};
