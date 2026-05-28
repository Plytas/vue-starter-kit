import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
	return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>): string {
	return typeof href === 'string' ? href : href.url;
}

export function resolveTitle(title: string | null): string {
	const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

	return title ? `${title} - ${appName}` : appName;
}
