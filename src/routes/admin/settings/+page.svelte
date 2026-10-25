<script lang="ts">
	import { enhance, type SubmitFunction } from '$app/forms';
	import * as Card from '#lib/components/ui/card/index.ts';
	import * as Table from '#lib/components/ui/table/index.ts';
	import { Badge } from '#lib/components/ui/badge/index.ts';
	import { Button } from '#lib/components/ui/button/index.ts';
	import { Input } from '#lib/components/ui/input/index.ts';
	import { Label } from '#lib/components/ui/label/index.ts';
	import Notice from '#lib/components/Notice.svelte';
	import Select from '#lib/components/Select.svelte';
	import Textarea from '#lib/components/Textarea.svelte';
	import Time from '#lib/components/admin/Time.svelte';
	import PlusIcon from '@lucide/svelte/icons/plus';
	import Trash2Icon from '@lucide/svelte/icons/trash-2';
	import type { PageProps } from './$types';

	let { data, form }: PageProps = $props();

	const canEdit = $derived(data.staff.administer);

	let pending = $state<string | null>(null);
	let adding = $state(false);
	let deleting = $state<number | null>(null);
	const submit =
		(name: string, reset = false): SubmitFunction =>
		() => {
			pending = name;
			return async ({ result, update }) => {
				if (result.type === 'success' && name === 'newRoom') adding = false;
				if (result.type === 'success' && name === 'deleteRoom') deleting = null;
				await update({ reset });
				pending = null;
			};
		};

	const feedback = (action: string, id?: number | null) =>
		form?.action === action && (id === undefined || ('id' in form && form.id === id)) ? form : null;

	const permissionLabel: Record<string, string> = { NONE: 'Anyone', REGISTERED: 'Registered players', MODERATOR: 'Moderators', ADMINISTRATOR: 'Admins' };
	const privilegeLabel: Record<string, string> = { NONE: 'No privilege needed', PRIVILEGED: 'Privileged', VIP: 'VIP', DONATOR: 'Donator' };

	type RoomFields = (typeof data.rooms)[number];
	const blank: Omit<RoomFields, 'id'> = {
		name: '',
		description: '',
		permissionLevel: 'NONE',
		privilegeLevel: 'NONE',
		autoJoin: false,
		joinMessage: '',
		chatHistorySize: 100,
		gameTypes: []
	};
</script>

{#snippet result(action: string, id?: number | null)}
	{@const f = feedback(action, id)}
	{#if f && 'error' in f && f.error}
		<Notice type="error">{f.error}</Notice>
	{:else if f && 'message' in f && f.message}
		<Notice type="success">{f.message}</Notice>
	{/if}
{/snippet}

{#snippet roomForm(room: Omit<RoomFields, 'id'> & { id?: number }, pendingName: string)}
	<form method="POST" action="?/saveRoom" class="space-y-4" use:enhance={submit(pendingName, room.id === undefined)}>
		{#if room.id !== undefined}<input type="hidden" name="id" value={room.id} />{/if}
		<fieldset disabled={!canEdit} class="space-y-4">
			<div class="grid gap-3 sm:grid-cols-2">
				<div class="space-y-1.5">
					<Label for="room-name-{room.id ?? 'new'}">Name</Label>
					<Input id="room-name-{room.id ?? 'new'}" name="name" value={room.name} maxlength={data.limits.name} required />
				</div>
				<div class="space-y-1.5">
					<Label for="room-history-{room.id ?? 'new'}">Chat history kept</Label>
					<Input id="room-history-{room.id ?? 'new'}" name="chatHistorySize" type="number" min={0} max={data.limits.chatHistorySize} value={room.chatHistorySize} required />
				</div>
			</div>
			<div class="space-y-1.5">
				<Label for="room-descr-{room.id ?? 'new'}">Description</Label>
				<Input id="room-descr-{room.id ?? 'new'}" name="description" value={room.description} maxlength={data.limits.description} />
			</div>
			<div class="space-y-1.5">
				<Label for="room-join-{room.id ?? 'new'}">Join message</Label>
				<Textarea id="room-join-{room.id ?? 'new'}" name="joinMessage" rows={2} value={room.joinMessage} maxlength={data.limits.joinMessage} />
			</div>
			<div class="flex flex-wrap items-end gap-3">
				<div class="space-y-1.5">
					<Label for="room-perm-{room.id ?? 'new'}">Who may join</Label>
					<Select id="room-perm-{room.id ?? 'new'}" name="permissionLevel" value={room.permissionLevel}>
						{#each data.permissions as level (level)}<option value={level}>{permissionLabel[level]}</option>{/each}
					</Select>
				</div>
				<div class="space-y-1.5">
					<Label for="room-priv-{room.id ?? 'new'}">Privilege</Label>
					<Select id="room-priv-{room.id ?? 'new'}" name="privilegeLevel" value={room.privilegeLevel}>
						{#each data.privileges as level (level)}<option value={level}>{privilegeLabel[level]}</option>{/each}
					</Select>
				</div>
				<label class="flex h-8 items-center gap-2 text-sm">
					<input type="checkbox" name="autoJoin" checked={room.autoJoin} class="rounded" />
					Join automatically on connecting
				</label>
			</div>
			<div class="space-y-1.5">
				<Label for="room-types-{room.id ?? 'new'}">Game types, one per line</Label>
				<Textarea id="room-types-{room.id ?? 'new'}" name="gameTypes" rows={Math.max(3, room.gameTypes.length + 1)} value={room.gameTypes.join('\n')} />
				<p class="text-xs text-muted-foreground">In the order the client lists them.</p>
			</div>
		</fieldset>
		{#if canEdit}
			<Button type="submit" disabled={pending === pendingName}>{room.id === undefined ? 'Create room' : 'Save room'}</Button>
		{/if}
	</form>
{/snippet}

<div class="space-y-4">
	<Card.Root id="rooms">
		<Card.Header>
			<Card.Title>Rooms and game types</Card.Title>
			<Card.Description>
				Server {data.serverId}'s rooms, from the database. Servatrice only uses them with <code class="text-xs">[rooms] method=sql</code> in
				servatrice.ini, and only reads them when it starts: restart it to apply changes.
			</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-3">
			{#if !canEdit}<Notice type="info">Only admins can change rooms.</Notice>{/if}
			{@render result('room', null)}
			{#each data.rooms as room (room.id)}
				<details class="group rounded-lg border" open={feedback('room', room.id) !== null}>
					<summary class="flex cursor-pointer list-none flex-wrap items-center gap-2 px-3.5 py-2.5 select-none">
						<span class="font-medium">{room.name}</span>
						<span class="text-xs text-muted-foreground">#{room.id}</span>
						{#if room.autoJoin}<Badge variant="secondary">Auto-join</Badge>{/if}
						{#if room.permissionLevel !== 'NONE'}<Badge variant="outline">{permissionLabel[room.permissionLevel]}</Badge>{/if}
						{#if room.privilegeLevel !== 'NONE'}<Badge variant="outline">{privilegeLabel[room.privilegeLevel]}</Badge>{/if}
						<span class="ml-auto truncate text-xs text-muted-foreground">{room.gameTypes.join(', ') || 'No game types'}</span>
					</summary>
					<div class="space-y-4 border-t px-3.5 py-4">
						{@render result('room', room.id)}
						{@render roomForm(room, `room-${room.id}`)}
						{#if canEdit}
							<div class="border-t pt-4">
								{#if deleting === room.id}
									<form method="POST" action="?/deleteRoom" class="flex flex-wrap items-end gap-2" use:enhance={submit('deleteRoom')}>
										<input type="hidden" name="id" value={room.id} />
										<div class="space-y-1.5">
											<Label for="confirm-{room.id}">Type {room.name} to delete it</Label>
											<Input id="confirm-{room.id}" name="confirm" autocomplete="off" required />
										</div>
										<Button type="submit" variant="destructive" disabled={pending === 'deleteRoom'}><Trash2Icon /> Delete</Button>
										<Button variant="ghost" onclick={() => (deleting = null)}>Cancel</Button>
									</form>
								{:else}
									<Button variant="ghost" size="sm" class="text-muted-foreground" onclick={() => (deleting = room.id)}><Trash2Icon /> Delete room…</Button>
								{/if}
							</div>
						{/if}
					</div>
				</details>
			{:else}
				<p class="text-sm text-muted-foreground">No rooms in the database for this server.</p>
			{/each}

			{#if canEdit}
				{#if adding}
					<div class="rounded-lg border px-3.5 py-4">
						<h3 class="mb-3 font-medium">New room</h3>
						{@render roomForm(blank, 'newRoom')}
					</div>
				{:else}
					<Button variant="outline" onclick={() => (adding = true)}><PlusIcon /> New room</Button>
				{/if}
			{/if}
		</Card.Content>
	</Card.Root>

	<Card.Root id="message">
		<Card.Header>
			<Card.Title>Server message</Card.Title>
			<Card.Description>
				What players see when they log in. Servatrice reads it when it starts, or when an admin chooses Server → Update server message in
				the client.
			</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			{@render result('message')}
			{#if canEdit}
				<form method="POST" action="?/postMessage" class="space-y-3" use:enhance={submit('message', true)}>
					<Textarea name="message" rows={5} value={data.messages[0]?.message ?? ''} required aria-label="Server message" />
					<p class="text-xs text-muted-foreground">
						Cockatrice shows it as HTML, so line breaks need &lt;br&gt; or &lt;p&gt;; &lt;b&gt; and &lt;a href&gt; work too.
					</p>
					<Button type="submit" disabled={pending === 'message'}>Post message</Button>
				</form>
			{/if}
			{#if data.messages.length}
				<details>
					<summary class="cursor-pointer text-sm text-muted-foreground select-none hover:text-foreground">Earlier messages</summary>
					<ul class="mt-3 space-y-3">
						{#each data.messages as message (message.at)}
							<li class="rounded-lg border px-3 py-2">
								<div class="mb-1 text-xs text-muted-foreground"><Time at={message.at} /></div>
								<p class="text-sm whitespace-pre-line">{message.message}</p>
							</li>
						{/each}
					</ul>
				</details>
			{/if}
		</Card.Content>
	</Card.Root>

	{#if data.caps.cardArtRules}
		<Card.Root id="card-art">
			<Card.Header>
				<Card.Title>Card art rules</Card.Title>
				<Card.Description>
					Which cards players may use as their profile art. Anything without a rule is allowed. Servatrice checks these whenever someone picks one.
				</Card.Description>
			</Card.Header>
			<Card.Content class="space-y-4">
				{@render result('cardArt')}
				<form method="POST" action="?/setCardArt" class="flex flex-wrap items-end gap-3" use:enhance={submit('cardArt', true)}>
					<div class="space-y-1.5">
						<Label for="card-name">Card name</Label>
						<Input id="card-name" name="cardName" required class="w-56" />
					</div>
					<div class="space-y-1.5">
						<Label for="card-provider">Printing id</Label>
						<Input id="card-provider" name="providerId" placeholder="Every printing" class="w-48 font-mono" />
					</div>
					<Select name="mode" aria-label="Rule">
						<option value="DENY">Deny</option>
						<option value="ALLOW">Allow</option>
					</Select>
					<div class="min-w-48 flex-1 space-y-1.5">
						<Label for="card-reason">Reason</Label>
						<Input id="card-reason" name="reason" placeholder="Optional" />
					</div>
					<Button type="submit" disabled={pending === 'cardArt'}>Save rule</Button>
				</form>
				{#if data.cardArt.length}
					<Table.Root>
						<Table.Header>
							<Table.Row>
								<Table.Head>Card</Table.Head>
								<Table.Head>Printing</Table.Head>
								<Table.Head>Rule</Table.Head>
								<Table.Head>Reason</Table.Head>
								<Table.Head>Added</Table.Head>
								<Table.Head></Table.Head>
							</Table.Row>
						</Table.Header>
						<Table.Body>
							{#each data.cardArt as rule (rule.id)}
								<Table.Row>
									<Table.Cell class="font-medium">{rule.cardName}</Table.Cell>
									<Table.Cell class="font-mono text-xs text-muted-foreground">{rule.providerId || 'any'}</Table.Cell>
									<Table.Cell><Badge variant={rule.mode === 'DENY' ? 'destructive' : 'secondary'}>{rule.mode === 'DENY' ? 'Denied' : 'Allowed'}</Badge></Table.Cell>
									<Table.Cell class="text-muted-foreground">{rule.reason ?? ''}</Table.Cell>
									<Table.Cell class="text-xs text-muted-foreground">
										<Time at={rule.createdAt} />{#if rule.createdBy}&nbsp;by {rule.createdBy}{/if}
									</Table.Cell>
									<Table.Cell>
										<form method="POST" action="?/removeCardArt" use:enhance={submit('removeCardArt')}>
											<input type="hidden" name="id" value={rule.id} />
											<Button type="submit" size="icon-xs" variant="ghost" aria-label="Remove rule for {rule.cardName}" disabled={pending === 'removeCardArt'}>
												<Trash2Icon />
											</Button>
										</form>
									</Table.Cell>
								</Table.Row>
							{/each}
						</Table.Body>
					</Table.Root>
				{/if}
			</Card.Content>
		</Card.Root>
	{/if}
</div>
