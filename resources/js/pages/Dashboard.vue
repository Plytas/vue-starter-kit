<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import type { Team } from '@/types/generated';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team | null);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
	{
		title: 'Dashboard',
		href: currentTeam.value ? dashboard(currentTeam.value.slug).url : '/',
	},
]);
</script>

<template>
	<Head title="Dashboard" />

	<AppLayout :breadcrumbs="breadcrumbs">
		<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
			<div class="grid auto-rows-min gap-4 md:grid-cols-3">
				<div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
					<PlaceholderPattern />
				</div>
				<div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
					<PlaceholderPattern />
				</div>
				<div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
					<PlaceholderPattern />
				</div>
			</div>
			<div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
				<PlaceholderPattern />
			</div>
		</div>
	</AppLayout>
</template>
