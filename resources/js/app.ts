import { initializeFlashToast } from '@/lib/flashToast';
import { resolveTitle } from '@/lib/utils';
import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { initializeTheme } from './composables/useAppearance';

const pinia = createPinia();

createInertiaApp({
	title: resolveTitle,
	progress: {
		color: '#4B5563',
	},
	withApp(app, { ssr: _ssr }) {
		app.use(pinia);
	},
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
