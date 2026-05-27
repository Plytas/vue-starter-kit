import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
	return twMerge(clsx(inputs));
}

export function urlIsActive(url: string, currentUrl: string) {
	return url === currentUrl;
}

export function resolveTitle(title: string | null): string {
	const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

	return title ? `${title} - ${appName}` : appName;
}
