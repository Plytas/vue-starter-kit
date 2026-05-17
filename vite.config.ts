import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import { transformer } from './resources/js/lib/vite-plugin-transformer';
import vueDevTools from 'vite-plugin-vue-devtools';

export default defineConfig({
	plugins: [
		vueDevTools({
			appendTo: 'resources/js/app.ts',
			launchEditor: 'phpstorm',
		}),
		laravel({
			input: ['resources/css/app.css', 'resources/js/app.ts'],
			refresh: true,
		}),
		inertia(),
		wayfinder(),
		transformer(),
		tailwindcss(),
		vue({
			template: {
				transformAssetUrls: {
					base: null,
					includeAbsolute: false,
				},
			},
		}),
	],
});
