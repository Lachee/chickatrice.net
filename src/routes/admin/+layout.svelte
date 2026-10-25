<script lang="ts">
	import { page } from '$app/state';
	import { Badge } from '#lib/components/ui/badge/index.ts';
	import ShieldIcon from '@lucide/svelte/icons/shield';
	import type { LayoutProps } from './$types';

	let { data, children }: LayoutProps = $props();

	const tabs = $derived(
		[
			{ href: '/admin', label: 'Dashboard', show: true },
			{ href: '/admin/online', label: 'Online', show: true },
			{ href: '/admin/players', label: 'Players', show: data.staff.moderate },
			{ href: '/admin/bans', label: 'Bans', show: data.staff.moderate },
			{ href: '/admin/reports', label: 'Reports', show: data.staff.moderate && data.caps.reports, count: data.openReports },
			{ href: '/admin/logs', label: 'Logs', show: true },
			{ href: '/admin/staff', label: 'Staff', show: data.staff.moderate },
			{ href: '/admin/settings', label: 'Settings', show: data.staff.moderate }
		].filter((tab) => tab.show)
	);

	const isActive = (href: string) => (href === '/admin' ? page.url.pathname === '/admin' : page.url.pathname.startsWith(href));
</script>

<svelte:head>
	<title>Admin · Chickatrice</title>
	<meta name="robots" content="noindex" />
</svelte:head>

<div class="mb-6">
	<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
		<h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight"><ShieldIcon class="size-5 text-primary" /> Admin</h1>
		<span class="text-sm text-muted-foreground">Signed in as {data.staff.name} · {data.staff.roles.join(', ')}</span>
	</div>
	<nav class="-mx-4 overflow-x-auto px-4" aria-label="Admin sections">
		<div class="flex min-w-max gap-1 border-b">
			{#each tabs as tab (tab.href)}
				<a
					href={tab.href}
					aria-current={isActive(tab.href) ? 'page' : undefined}
					class="-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm transition-colors {isActive(tab.href)
						? 'border-primary font-medium text-foreground'
						: 'border-transparent text-muted-foreground hover:text-foreground'}"
				>
					{tab.label}
					{#if tab.count}<Badge class="h-4 min-w-4 px-1 text-[10px]">{tab.count}</Badge>{/if}
				</a>
			{/each}
		</div>
	</nav>
</div>

{@render children()}
