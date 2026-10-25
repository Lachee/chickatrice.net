<script lang="ts">
	import './layout.css';
	import { page } from '$app/state';
	import { Button } from '#lib/components/ui/button/index.ts';
	import * as Avatar from '#lib/components/ui/avatar/index.ts';
	import * as DropdownMenu from '#lib/components/ui/dropdown-menu/index.ts';
	import Notice from '#lib/components/Notice.svelte';
	import Umami from '#lib/components/Umami.svelte';
	import ActivityIcon from '@lucide/svelte/icons/activity';
	import HouseIcon from '@lucide/svelte/icons/house';
	import UserCogIcon from '@lucide/svelte/icons/user-cog';
	import LogOutIcon from '@lucide/svelte/icons/log-out';
	import LogInIcon from '@lucide/svelte/icons/log-in';
	import ShieldIcon from '@lucide/svelte/icons/shield';
	import type { LayoutProps } from './$types';

	let { data, children }: LayoutProps = $props();

	const nav = $derived([
		{ href: '/', label: 'Home', icon: HouseIcon },
		{ href: '/stats', label: 'Statistics', icon: ActivityIcon },
		...(data.account?.staff ? [{ href: '/admin', label: 'Admin', icon: ShieldIcon }] : [])
	]);

	const isActive = (href: string) => (href === '/' ? page.url.pathname === '/' : page.url.pathname.startsWith(href));
</script>

{#if data.umami}
	<Umami {...data.umami} />
{/if}

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
					<DropdownMenu.Trigger class="flex items-center gap-2 rounded-full outline-none focus-visible:ring-3 focus-visible:ring-white/60">
						<span class="hidden text-sm sm:inline">{data.account.name}</span>
						<Avatar.Root class="size-8 ring-2 ring-white/70">
							{#if data.account.hasAvatar}
								<Avatar.Image src="/api/user/{encodeURIComponent(data.account.name)}/avatar" alt="" />
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
						{#if data.account.staff}
							<DropdownMenu.Item>
								{#snippet child({ props })}
									<a href="/admin" {...props}><ShieldIcon /> Admin tools</a>
								{/snippet}
							</DropdownMenu.Item>
						{/if}
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
	<div class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-x-4 gap-y-2 px-4 py-5 text-xs text-muted-foreground">
		<span>Chickatrice is a community server for <a href="https://cockatrice.github.io/" class="underline-offset-4 hover:underline">Cockatrice</a> and is operated by 🏳️‍⚧️ Lachee.</span>
		<a
			href="https://github.com/lachee/chickatrice.net"
			target="_blank"
			rel="noopener"
			class="inline-flex items-center gap-1.5 underline-offset-4 hover:text-foreground hover:underline"
		>
			<!-- GitHub's mark; Lucide no longer ships brand icons. -->
			<svg viewBox="0 0 16 16" class="size-3.5 fill-current" aria-hidden="true">
				<path
					d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"
				/>
			</svg>
			Open source on GitHub.
		</a>
		<a href="/policy/privacy">Privacy Policy</a>
		<a href="/policy/terms">Terms of Service</a>
	</div>
</footer>
