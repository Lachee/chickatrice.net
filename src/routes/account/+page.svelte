<script lang="ts">
	import { enhance, type SubmitFunction } from '$app/forms';
	import { Button } from '#lib/components/ui/button/index.js';
	import { Input } from '#lib/components/ui/input/index.js';
	import { Label } from '#lib/components/ui/label/index.js';
	import { Badge } from '#lib/components/ui/badge/index.js';
	import * as Card from '#lib/components/ui/card/index.js';
	import * as Avatar from '#lib/components/ui/avatar/index.js';
	import Notice from '#lib/components/Notice.svelte';
	import { flag } from '#lib/countries.js';
	import KeyRoundIcon from '@lucide/svelte/icons/key-round';
	import ImageIcon from '@lucide/svelte/icons/image';
	import LinkIcon from '@lucide/svelte/icons/link';
	import Trash2Icon from '@lucide/svelte/icons/trash-2';
	import TriangleAlertIcon from '@lucide/svelte/icons/triangle-alert';
	import UploadIcon from '@lucide/svelte/icons/upload';
	import type { PageProps } from './$types';

	let { data, form }: PageProps = $props();

	const profile = $derived(data.profile);
	// Bumped after an avatar change so the browser fetches the new picture.
	let avatarVersion = $state(0);
	let pending = $state<string | null>(null);

	const submit =
		(name: string, reset = true): SubmitFunction =>
		() => {
			pending = name;
			return async ({ result, update }) => {
				if (result.type === 'success' && name.toLowerCase().includes('avatar')) avatarVersion++;
				await update({ reset });
				pending = null;
			};
		};

	// Deleting takes two steps: open the confirmation, then type the name.
	let confirmingDelete = $state(false);
	let confirmName = $state('');

	const dateFormat = new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeZone: 'UTC' });
	const feedback = (action: string) => (form?.action === action ? form : null);
</script>

<svelte:head>
	<title>Account · {profile.name}</title>
</svelte:head>

{#snippet result(action: string)}
	{@const f = feedback(action)}
	{#if f && 'error' in f && f.error}
		<Notice type="error">{f.error}</Notice>
	{:else if f && 'message' in f && f.message}
		<Notice type="success">{f.message}</Notice>
	{/if}
{/snippet}

<div class="mb-8 flex flex-wrap items-center gap-4">
	<Avatar.Root class="size-16 rounded-xl after:rounded-xl">
		{#if profile.hasAvatar}
			<Avatar.Image src="/avatar/{encodeURIComponent(profile.name)}?v={avatarVersion}" alt="" class="rounded-xl" />
		{/if}
		<Avatar.Fallback class="rounded-xl text-lg">{profile.name.slice(0, 2).toUpperCase()}</Avatar.Fallback>
	</Avatar.Root>
	<div class="min-w-0">
		<h1 class="flex flex-wrap items-center gap-2 text-2xl font-semibold tracking-tight">
			{profile.name}
			{#if profile.country}<span class="text-xl" aria-hidden="true">{flag(profile.country)}</span>{/if}
			{#if !profile.active}<Badge variant="destructive">Not activated</Badge>{/if}
		</h1>
		<p class="text-sm text-muted-foreground">
			{#if profile.email}{profile.email} · {/if}Joined {dateFormat.format(new Date(profile.registeredAt))}
		</p>
	</div>
</div>

<div class="grid gap-4 lg:grid-cols-2">
	<Card.Root id="password">
		<Card.Header>
			<Card.Title class="flex items-center gap-2"><KeyRoundIcon class="size-4" /> In-game password</Card.Title>
			<Card.Description>
				{#if profile.hasPassword}
					What you type into Cockatrice to log in as <strong class="text-foreground">{profile.name}</strong>.
				{:else}
					Set this to log in to Cockatrice as <strong class="text-foreground">{profile.name}</strong>.
				{/if}
			</Card.Description>
		</Card.Header>
		<Card.Content>
			<form method="POST" action="?/password" class="space-y-4" use:enhance={submit('password')}>
				{#if !profile.hasPassword && !form}
					<Notice type="info">You haven't set a password yet, so this account can't log in from the client.</Notice>
				{/if}
				{@render result('password')}
				<!-- Lets password managers file the new password under the right name. -->
				<input type="text" name="username" value={profile.name} autocomplete="username" hidden readonly />
				{#if data.needsCurrentPassword}
					<div class="space-y-1.5">
						<Label for="current">Current password</Label>
						<Input id="current" name="current" type="password" autocomplete="current-password" required />
					</div>
				{/if}
				<div class="space-y-1.5">
					<Label for="new-password">New password</Label>
					<Input id="new-password" name="password" type="password" autocomplete="new-password" minlength={data.minPasswordLength} maxlength={255} required />
					<p class="text-xs text-muted-foreground">At least {data.minPasswordLength} characters.</p>
				</div>
				<div class="space-y-1.5">
					<Label for="confirm">Confirm new password</Label>
					<Input id="confirm" name="confirm" type="password" autocomplete="new-password" required />
				</div>
				<Button type="submit" disabled={pending === 'password'}>
					{profile.hasPassword ? 'Change password' : 'Set password'}
				</Button>
				{#if profile.hasPassword}
					<p class="text-xs text-muted-foreground">Changing it signs you out of this site everywhere else.</p>
				{/if}
			</form>
		</Card.Content>
	</Card.Root>

	<Card.Root>
		<Card.Header>
			<Card.Title class="flex items-center gap-2"><ImageIcon class="size-4" /> Profile</Card.Title>
			<Card.Description>What other players see when they look you up in Cockatrice.</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-6">
			<form method="POST" action="?/country" class="space-y-3" use:enhance={submit('country', false)}>
				{@render result('country')}
				<div class="space-y-1.5">
					<Label for="country">Country</Label>
					<div class="flex gap-2">
						<select
							id="country"
							name="country"
							required
							class="h-8 min-w-0 flex-1 rounded-lg border border-input bg-transparent px-2.5 py-0 text-sm dark:bg-input/30 focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 outline-none"
						>
							{#if !profile.country}<option value="" disabled selected>Choose a country…</option>{/if}
							{#each data.countries as country (country.code)}
								<option value={country.code} selected={country.code === profile.country}>{flag(country.code)} {country.name}</option>
							{/each}
						</select>
						<Button type="submit" variant="secondary" disabled={pending === 'country'}>Save</Button>
					</div>
				</div>
			</form>

			<div class="space-y-3">
				<Label for="avatar">Avatar</Label>
				{@render result('avatar')}
				<div class="flex items-start gap-4">
					<Avatar.Root class="size-20 shrink-0 rounded-xl after:rounded-xl">
						{#if profile.hasAvatar}
							<Avatar.Image src="/avatar/{encodeURIComponent(profile.name)}?v={avatarVersion}" alt="Your avatar" class="rounded-xl" />
						{/if}
						<Avatar.Fallback class="rounded-xl text-xs text-muted-foreground">None</Avatar.Fallback>
					</Avatar.Root>
					<div class="min-w-0 flex-1 space-y-2">
						<form method="POST" action="?/avatar" enctype="multipart/form-data" class="flex flex-wrap gap-2" use:enhance={submit('avatar')}>
							<input
								id="avatar"
								name="avatar"
								type="file"
								accept="image/png,image/jpeg,image/gif,image/webp,image/bmp"
								required
								class="min-w-0 flex-1 text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-2.5 file:py-1 file:text-sm file:text-secondary-foreground hover:file:bg-accent"
							/>
							<Button type="submit" size="sm" variant="secondary" disabled={pending === 'avatar'}><UploadIcon /> Upload</Button>
						</form>
						<p class="text-xs text-muted-foreground">PNG, JPEG, GIF, WebP or BMP up to {data.maxAvatarMb} MB. It's cropped square.</p>
						<div class="flex flex-wrap gap-2">
							{#each data.identities.filter((i) => i.hasImage) as identity (identity.provider)}
								<form method="POST" action="?/importAvatar" use:enhance={submit('importAvatar')}>
									<input type="hidden" name="provider" value={identity.provider} />
									<Button type="submit" size="sm" variant="outline" disabled={pending === 'importAvatar'}>Use {identity.providerName} picture</Button>
								</form>
							{/each}
							{#if profile.hasAvatar}
								<form method="POST" action="?/removeAvatar" use:enhance={submit('removeAvatar')}>
									<Button type="submit" size="sm" variant="ghost" class="text-muted-foreground" disabled={pending === 'removeAvatar'}>
										<Trash2Icon /> Remove
									</Button>
								</form>
							{/if}
						</div>
					</div>
				</div>
			</div>
		</Card.Content>
	</Card.Root>

	<Card.Root class="lg:col-span-2">
		<Card.Header>
			<Card.Title class="flex items-center gap-2"><LinkIcon class="size-4" /> Sign-in methods</Card.Title>
			<Card.Description>
				{#if data.identities.length}
					How you sign in to this site. Your Cockatrice name stays the same whichever you use.
				{:else}
					You sign in here with your in-game password. Link a provider to sign in with it instead.
				{/if}
			</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-3">
			{@render result('providers')}
			{#if data.identities.length}
				<ul class="divide-y rounded-lg border">
					{#each data.identities as identity (identity.provider)}
						<li class="flex flex-wrap items-center justify-between gap-3 px-3.5 py-2.5">
							<div class="min-w-0">
								<div class="text-sm font-medium">{identity.providerName}</div>
								<div class="truncate text-xs text-muted-foreground">
									{identity.username ?? identity.email ?? 'Linked'} · since {dateFormat.format(new Date(identity.linkedAt))}
								</div>
							</div>
							<form method="POST" action="?/unlink" use:enhance={submit('unlink')}>
								<input type="hidden" name="provider" value={identity.provider} />
								<Button type="submit" size="sm" variant="ghost" class="text-muted-foreground" disabled={pending === 'unlink'}>Unlink</Button>
							</form>
						</li>
					{/each}
				</ul>
			{/if}
			{#if data.linkable.length}
				<div class="flex flex-wrap gap-2">
					{#each data.linkable as provider (provider.id)}
						<!-- Signed in, Auth.js links the provider instead of signing in with it. -->
						<form method="POST" action="/auth/signin/{provider.id}">
							<input type="hidden" name="callbackUrl" value="/account?linked={provider.id}" />
							<Button type="submit" variant="outline"><LinkIcon /> Link {provider.name}</Button>
						</form>
					{/each}
				</div>
			{/if}
		</Card.Content>
	</Card.Root>

	<Card.Root id="delete" class="ring-1 ring-destructive/25 lg:col-span-2">
		<Card.Header>
			<Card.Title class="flex items-center gap-2 text-destructive"><TriangleAlertIcon class="size-4" /> Delete account</Card.Title>
			<Card.Description>
				Permanently delete <strong class="text-foreground">{profile.name}</strong> and its data, from this site and the Cockatrice server.
				This can't be undone.
			</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			{@render result('deleteAccount')}
			{#if profile.isStaff}
				<Notice type="info">Staff accounts can't be deleted here, since the bans they issued would go with them. Ask the server's admin.</Notice>
			{:else if !confirmingDelete}
				<Button variant="destructive" onclick={() => (confirmingDelete = true)}><Trash2Icon /> Delete account…</Button>
			{:else}
				<div class="grid gap-4 text-sm sm:grid-cols-2">
					<div>
						<h3 class="mb-1.5 font-medium">Deleted</h3>
						<ul class="list-disc space-y-1 pl-5 text-muted-foreground">
							<li>Your Cockatrice account, password and avatar</li>
							<li>Saved decks, deck folders and shared deck links</li>
							<li>Buddy and ignore lists, and access to your replays</li>
							<li>Chat messages the server logged from you</li>
							<li>Connection and login history, including IP addresses</li>
							<li>Linked sign-ins, and your sessions on this site</li>
						</ul>
					</div>
					<div>
						<h3 class="mb-1.5 font-medium">Kept</h3>
						<ul class="list-disc space-y-1 pl-5 text-muted-foreground">
							<li>Bans, warnings and reports: the server's moderation record</li>
							<li>Games you played, which are other players' history too</li>
						</ul>
						<p class="mt-3 text-muted-foreground">Afterwards, anyone can register the name {profile.name}.</p>
					</div>
				</div>
				<form method="POST" action="?/deleteAccount" class="max-w-md space-y-4" use:enhance={submit('deleteAccount', false)}>
					<div class="space-y-1.5">
						<Label for="confirm-name">Type <strong>{profile.name}</strong> to confirm</Label>
						<Input id="confirm-name" name="confirmName" autocomplete="off" spellcheck="false" bind:value={confirmName} required />
					</div>
					{#if data.needsCurrentPassword}
						<div class="space-y-1.5">
							<Label for="delete-password">Your password</Label>
							<Input id="delete-password" name="current" type="password" autocomplete="current-password" required />
						</div>
					{/if}
					<div class="flex flex-wrap gap-2">
						<Button
							type="submit"
							class="bg-destructive text-white hover:bg-destructive/90"
							disabled={confirmName !== profile.name || pending === 'deleteAccount'}
						>
							<Trash2Icon /> Delete my account forever
						</Button>
						<Button
							variant="ghost"
							onclick={() => {
								confirmingDelete = false;
								confirmName = '';
							}}>Cancel</Button
						>
					</div>
				</form>
			{/if}
		</Card.Content>
	</Card.Root>
</div>
