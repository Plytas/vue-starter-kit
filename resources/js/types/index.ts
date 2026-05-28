export * from './navigation';
export * from './ui';

export type { RoleOption, Team, TeamInvitation, TeamMember, TeamPermissions } from '@/types/generated';

export interface PaginatedResult<T> {
	data: T[];
	links: {
		first: string;
		last: string;
		next?: string;
		prev?: string;
	};
	meta: {
		current_page: number;
		from?: number;
		last_page: number;
		links: {
			active: boolean;
			label: string;
			url?: string;
		}[];
		path: string;
		per_page: number;
		to?: number;
		total: number;
	};
}
