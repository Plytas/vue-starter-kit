import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

export type FlashToast = {
	type: 'success' | 'info' | 'warning' | 'error';
	message: string;
};

export function initializeFlashToast(): void {
	router.on('flash', (event) => {
		const data = event.detail?.flash?.toast as FlashToast | undefined;

		if (!data) {
			return;
		}

		toast[data.type](data.message);
	});
}
