<script lang="ts">
	import { enhance, type SubmitFunction } from '$app/forms';
	import { Button } from '#lib/components/ui/button/index.ts';
	import { Input } from '#lib/components/ui/input/index.ts';
	import { Label } from '#lib/components/ui/label/index.ts';
	import Select from '#lib/components/Select.svelte';
	import Textarea from '#lib/components/Textarea.svelte';
	import BanIcon from '@lucide/svelte/icons/ban';

	let {
		submit,
		pending = false,
		userName = '',
		/** Offered with a click, not filled in: an address or client id ban reaches further than a name. */
		suggestedIp = null,
		suggestedClientId = null,
		/** On a player's page the name is fixed. */
		fixedName = false
	}: {
		submit: SubmitFunction;
		pending?: boolean;
		userName?: string;
		suggestedIp?: string | null;
		suggestedClientId?: string | null;
		fixedName?: boolean;
	} = $props();

	// svelte-ignore state_referenced_locally
	let name = $state(userName);
	let ip = $state('');
	let clientId = $state('');
	let minutes = $state('1440');

	const durations = [
		{ value: '60', label: '1 hour' },
		{ value: '1440', label: '1 day' },
		{ value: '10080', label: '1 week' },
		{ value: '43200', label: '30 days' },
		{ value: '0', label: 'Permanent' },
		{ value: 'custom', label: 'Custom…' }
	];
</script>

<form method="POST" action="?/ban" class="space-y-4" use:enhance={submit}>
	<div class="grid gap-3 sm:grid-cols-3">
		<div class="space-y-1.5">
			<Label for="ban-name">Name</Label>
			<Input id="ban-name" name="userName" bind:value={name} readonly={fixedName} maxlength={35} autocomplete="off" />
		</div>
		<div class="space-y-1.5">
			<Label for="ban-ip">IP address</Label>
			<Input id="ban-ip" name="ip" bind:value={ip} placeholder="Not banned" autocomplete="off" class="font-mono" />
			{#if suggestedIp && ip !== suggestedIp}
				<button type="button" class="text-xs text-primary underline-offset-4 hover:underline" onclick={() => (ip = suggestedIp)}>
					Use their last, {suggestedIp}
				</button>
			{/if}
		</div>
		<div class="space-y-1.5">
			<Label for="ban-client">Client id</Label>
			<Input id="ban-client" name="clientId" bind:value={clientId} placeholder="Not banned" maxlength={15} autocomplete="off" class="font-mono" />
			{#if suggestedClientId && clientId !== suggestedClientId}
				<button type="button" class="text-xs text-primary underline-offset-4 hover:underline" onclick={() => (clientId = suggestedClientId)}>
					Use theirs, {suggestedClientId}
				</button>
			{/if}
		</div>
	</div>
	<p class="text-xs text-muted-foreground">
		Servatrice turns away a login matching any of these. An address can be shared by a whole household or network.
	</p>

	<div class="flex flex-wrap items-end gap-3">
		<div class="space-y-1.5">
			<Label for="ban-length">Length</Label>
			<Select id="ban-length" name="minutes" bind:value={minutes}>
				{#each durations as option (option.value)}<option value={option.value}>{option.label}</option>{/each}
			</Select>
		</div>
		{#if minutes === 'custom'}
			<div class="space-y-1.5">
				<Label for="ban-custom">Minutes</Label>
				<Input id="ban-custom" name="customMinutes" type="number" min={1} max={999999} required class="w-32" />
			</div>
		{/if}
	</div>

	<div class="grid gap-3 sm:grid-cols-2">
		<div class="space-y-1.5">
			<Label for="ban-reason">Reason for staff</Label>
			<Textarea id="ban-reason" name="reason" required rows={2} />
		</div>
		<div class="space-y-1.5">
			<Label for="ban-visible">Reason shown to them</Label>
			<Textarea id="ban-visible" name="visibleReason" rows={2} placeholder="Optional" />
		</div>
	</div>

	<Button type="submit" variant="destructive" disabled={pending}><BanIcon /> Ban</Button>
</form>
