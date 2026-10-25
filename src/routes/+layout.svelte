<script lang="ts">
	import './layout.css';
	import { page } from '$app/state';
	import { Button } from '#lib/components/ui/button/index.js';
	import * as Avatar from '#lib/components/ui/avatar/index.js';
	import * as DropdownMenu from '#lib/components/ui/dropdown-menu/index.js';
	import Notice from '#lib/components/Notice.svelte';
	import ActivityIcon from '@lucide/svelte/icons/activity';
	import HouseIcon from '@lucide/svelte/icons/house';
	import UserCogIcon from '@lucide/svelte/icons/user-cog';
	import LogOutIcon from '@lucide/svelte/icons/log-out';
	import LogInIcon from '@lucide/svelte/icons/log-in';
	import type { LayoutProps } from './$types';

	let { data, children }: LayoutProps = $props();

	const nav = [
		{ href: '/', label: 'Home', icon: HouseIcon },
		{ href: '/stats', label: 'Statistics', icon: ActivityIcon }
	];

	const isActive = (href: string) => (href === '/' ? page.url.pathname === '/' : page.url.pathname.startsWith(href));
</script>

<svelte:head>
	<link rel="icon" href="/favicon.ico" />
	<title>Chickatrice</title>
</svelte:head>

<header class="sticky top-0 z-40 bg-primary text-primary-foreground shadow-sm">
	<nav class="mx-auto flex h-13 max-w-6xl items-stretch gap-1 px-4">
		<a href="/" class="mr-2 flex items-center gap-2 px-1 font-semibold sm:mr-4" aria-label="Chickatrice home">
			<img src="/logo.png" alt="" class="size-7" />
		</a>

		{#each nav as item (item.href)}
			<a
				href={item.href}
				class="flex items-center gap-1.5 border-b-3 px-3 pt-0.75 text-sm transition-colors hover:bg-black/5 [&_svg]:size-4 {isActive(item.href)
					? 'border-white'
					: 'border-transparent text-white/85 hover:text-white'}"
				aria-current={isActive(item.href) ? 'page' : undefined}
			>
				<item.icon />
				<span class="hidden sm:inline">{item.label}</span>
			</a>
		{/each}

		<div class="flex-1"></div>

		{#if data.account}
			<div class="flex items-center">
				<DropdownMenu.Root>
					<DropdownMenu.Trigger class="flex cursor-pointer items-center gap-2 rounded-full outline-none focus-visible:ring-3 focus-visible:ring-white/60">
						<span class="hidden text-sm sm:inline">{data.account.name}</span>
						<Avatar.Root class="size-8 ring-2 ring-white/70">
							{#if data.account.hasAvatar}
								<Avatar.Image src="/avatar/{encodeURIComponent(data.account.name)}" alt="" />
							{/if}
							<Avatar.Fallback class="text-xs">{data.account.name.slice(0, 2).toUpperCase()}</Avatar.Fallback>
						</Avatar.Root>
					</DropdownMenu.Trigger>
					<DropdownMenu.Content align="end" class="w-56">
						<DropdownMenu.Label class="truncate">{data.account.name}</DropdownMenu.Label>
						<DropdownMenu.Separator />
						<DropdownMenu.Item>
							{#snippet child({ props })}
								<a href="/account" {...props}><UserCogIcon /> Account settings</a>
							{/snippet}
						</DropdownMenu.Item>
						<DropdownMenu.Separator />
						<form method="POST" action="/auth/signout">
							<input type="hidden" name="callbackUrl" value="/" />
							<DropdownMenu.Item>
								{#snippet child({ props })}
									<button type="submit" {...props} class="{props.class ?? ''} w-full"><LogOutIcon /> Sign out</button>
								{/snippet}
							</DropdownMenu.Item>
						</form>
					</DropdownMenu.Content>
				</DropdownMenu.Root>
			</div>
		{:else}
			<div class="flex items-center">
				<Button href="/login" variant="outline" class="border-input bg-white text-heading hover:bg-white/90">
					<LogInIcon /> Sign in
				</Button>
			</div>
		{/if}
	</nav>
</header>

<!-- A column so a page's last section can `flex-1` down to the footer (the home hero does). -->
<main class="mx-auto flex w-full max-w-6xl flex-1 flex-col px-4 py-8">
	{#if data.flash}
		<Notice type={data.flash.type} class="mb-6">{data.flash.message}</Notice>
	{/if}
	{#if data.account && !data.account.hasPassword && page.url.pathname !== '/account'}
		<Notice type="info" class="mb-6">
			You haven't set an in-game password yet, so you can't log in from Cockatrice.
			<a href="/account" class="font-medium text-primary underline-offset-4 hover:underline">Set one now</a>.
		</Notice>
	{/if}
	{@render children()}
</main>

<footer class="border-t bg-card">
	<div class="mx-auto max-w-6xl px-4 py-5 text-center text-xs text-muted-foreground">
		Chickatrice is a community server for <a href="https://cockatrice.github.io/" class="underline-offset-4 hover:underline">Cockatrice</a>.
	</div>
</footer>
