import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';
import prettier from 'eslint-config-prettier';
import importPlugin from 'eslint-plugin-import';
import oxlint from 'eslint-plugin-oxlint';
import vue from 'eslint-plugin-vue';


export default defineConfigWithVueTs(
	vue.configs['flat/essential'],
	vueTsConfigs.recommended,
	{
		ignores: [
			'vendor',
			'node_modules',
			'public',
			'bootstrap/ssr',
			'tailwind.config.js',
			'resources/js/components/ui/*',
			'resources/js/types/generated.d.ts',
		],
	},
	{
		plugins: { import: importPlugin },
		settings: {
			'import/resolver': {
				typescript: { alwaysTryTypes: true, project: './tsconfig.json' },
			},
		},
		rules: {
			'vue/multi-word-component-names': 'off',
			'@typescript-eslint/no-explicit-any': 'off',
			'@typescript-eslint/no-unused-vars': [
				'error',
				{
					caughtErrors: 'none',
				},
			],
			'import/order': [
				'error',
				{
					groups: ['builtin', 'external', 'internal', 'parent', 'sibling', 'index'],
					'newlines-between': 'always',
					alphabetize: { order: 'asc', caseInsensitive: true },
				},
			],
		},
	},
	prettier,
	...oxlint.configs['flat/recommended'],
);
