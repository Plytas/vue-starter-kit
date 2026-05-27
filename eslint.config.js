import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';
import prettier from 'eslint-config-prettier/flat';
import importPlugin from 'eslint-plugin-import';
import oxlint from 'eslint-plugin-oxlint';
import vue from 'eslint-plugin-vue';


export default defineConfigWithVueTs(
	vue.configs['flat/essential'],
	vueTsConfigs.recommended,
	{
		plugins: { import: importPlugin },
		settings: {
			'import/resolver': {
				typescript: { alwaysTryTypes: true, project: './tsconfig.json' },
				node: true,
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
					pathGroups: [
						{ pattern: '@/actions', group: 'internal' },
						{ pattern: '@/actions/**', group: 'internal' },
						{ pattern: '@/routes', group: 'internal' },
						{ pattern: '@/routes/**', group: 'internal' },
					],
					'newlines-between': 'always',
					alphabetize: { order: 'asc', caseInsensitive: true },
				},
			],
			'import/consistent-type-specifier-style': ['error', 'prefer-top-level'],
		},
	},
	{
		ignores: [
			'vendor',
			'node_modules',
			'public',
			'bootstrap/ssr',
			'tailwind.config.js',
			'resources/js/components/ui/*',
			'resources/js/types/generated.d.ts',
			'resources/js/actions/**',
			'resources/js/routes/**',
			'vite.config.ts',
		],
	},
	prettier,
	...oxlint.configs['flat/recommended'],
);
