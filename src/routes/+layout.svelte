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

<header class="sticky top-0 z-40 border-b bg-background/85 backdrop-blur-xl">
	<nav class="mx-auto flex h-14 max-w-6xl items-center gap-1 px-4 sm:gap-2">
		<a href="/" class="mr-2 flex items-center gap-2 font-semibold tracking-tight sm:mr-4">
			<img src="/logo.png" alt="" class="size-6" />
			<span>Chickatrice</span>
		</a>

		{#each nav as item (item.href)}
			<Button
				href={item.href}
				variant="ghost"
				size="sm"
				class={isActive(item.href) ? 'bg-accent text-accent-foreground' : 'text-muted-foreground'}
				aria-current={isActive(item.href) ? 'page' : undefined}
			>
				<item.icon />
				<span class="hidden sm:inline">{item.label}</span>
			</Button>
		{/each}

		<div class="flex-1"></div>

		{#if data.account}
			<DropdownMenu.Root>
				<DropdownMenu.Trigger class="flex items-center gap-2 rounded-full outline-none focus-visible:ring-3 focus-visible:ring-ring/50">
					<span class="hidden text-sm sm:inline">{data.account.name}</span>
					<Avatar.Root class="size-8">
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
		{:else}
			<Button href="/login" size="sm">Sign in</Button>
		{/if}
	</nav>
</header>

<main class="mx-auto max-w-6xl px-4 py-8">
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

<footer class="mx-auto max-w-6xl px-4 pb-10 text-xs text-muted-foreground">
	Chickatrice is a community server for <a href="https://cockatrice.github.io/" class="underline-offset-4 hover:underline">Cockatrice</a>.
</footer>
