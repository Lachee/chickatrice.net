<script lang="ts">
	import { enhance } from '$app/forms';
	import { Button } from '#lib/components/ui/button/index.js';
	import { Input } from '#lib/components/ui/input/index.js';
	import { Label } from '#lib/components/ui/label/index.js';
	import * as Card from '#lib/components/ui/card/index.js';
	import Notice from '#lib/components/Notice.svelte';
	import LogInIcon from '@lucide/svelte/icons/log-in';
	import ProviderIcon from '#lib/components/ProviderIcon.svelte';
	import type { PageProps } from './$types';

	let { data, form }: PageProps = $props();
	let submitting = $state(false);
</script>

<div class="mx-auto max-w-sm pt-6">
	<Card.Root>
		<Card.Header>
			<Card.Title class="text-xl">Sign in</Card.Title>
			<Card.Description>
				{#if data.allowRegistration && data.providers.length}
					New here? Signing in with a provider creates your Cockatrice account.
				{:else}
					Manage your Cockatrice account.
				{/if}
			</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-6">
			{#if data.error && !form?.error}
				<Notice type="error">{data.error}</Notice>
			{/if}

			{#if data.providers.length}
				<div class="space-y-2">
					{#each data.providers as provider (provider.id)}
						<!-- Native POST straight to Auth.js, which sends the browser on to the provider. -->
						<form method="POST" action="/auth/signin/{provider.id}">
							<input type="hidden" name="callbackUrl" value={data.next} />
							<Button type="submit" variant="outline" size="lg" class="w-full">
								<ProviderIcon src={provider.icon}>
									{#snippet fallback()}<LogInIcon />{/snippet}
								</ProviderIcon>
								Continue with {provider.name}
							</Button>
						</form>
					{/each}
				</div>
			{/if}

			{#if data.allowPasswordLogin}
				{#if data.providers.length}
					<div class="flex items-center gap-3 text-xs text-muted-foreground">
						<span class="h-px flex-1 bg-border"></span>
						or with your in-game password
						<span class="h-px flex-1 bg-border"></span>
					</div>
				{/if}

				<form
					method="POST"
					class="space-y-4"
					use:enhance={() => {
						submitting = true;
						return async ({ update }) => {
							await update({ reset: false });
							submitting = false;
						};
					}}
				>
					{#if form?.error}
						<Notice type="error">{form.error}</Notice>
					{/if}
					<div class="space-y-1.5">
						<Label for="username">Cockatrice name</Label>
						<Input id="username" name="username" autocomplete="username" required value={form?.username ?? ''} />
					</div>
					<div class="space-y-1.5">
						<Label for="password">Password</Label>
						<Input id="password" name="password" type="password" autocomplete="current-password" required />
					</div>
					<Button type="submit" size="lg" class="w-full" disabled={submitting}>Sign in</Button>
					<p class="text-xs text-muted-foreground">
						For accounts registered from the Cockatrice client. Once a provider is linked to an account, it signs in with that
						instead.
					</p>
				</form>
			{/if}
		</Card.Content>
	</Card.Root>
</div>
