<script lang="ts">
	import * as Card from '#lib/components/ui/card/index.ts';
	import * as Table from '#lib/components/ui/table/index.ts';
	import { Badge } from '#lib/components/ui/badge/index.ts';
	import { Button } from '#lib/components/ui/button/index.ts';
	import { Input } from '#lib/components/ui/input/index.ts';
	import { Label } from '#lib/components/ui/label/index.ts';
	import Select from '#lib/components/Select.svelte';
	import Time from '#lib/components/admin/Time.svelte';
	import SearchIcon from '@lucide/svelte/icons/search';
	import type { PageProps } from './$types';

	let { data }: PageProps = $props();

	const tabs = $derived([
		{ id: 'chat', label: 'Chat' },
		...(data.staff.moderate
			? [
					{ id: 'audit', label: 'Servatrice audit' },
					{ id: 'site', label: 'Website actions' }
				]
			: [])
	]);
	const hoursLabel = (hours: number) => (hours === 0 ? 'All time' : hours === 24 ? 'Last day' : hours === 168 ? 'Last week' : 'Last 30 days');
	const typeLabel: Record<string, string> = { room: 'Rooms', game: 'Games', chat: 'Private messages' };
	const playerHref = (name: string) => `/admin/players/${encodeURIComponent(name)}`;
</script>

<div class="mb-4 inline-flex rounded-lg border bg-card p-0.5" role="group" aria-label="Log">
	{#each tabs as option (option.id)}
		<a
			href="?tab={option.id}"
			aria-current={option.id === data.tab ? 'true' : undefined}
			class="rounded-md px-3 py-1 text-sm transition-colors {option.id === data.tab
				? 'bg-accent font-medium text-accent-foreground'
				: 'text-muted-foreground hover:text-foreground'}">{option.label}</a
		>
	{/each}
</div>

{#if data.tab === 'chat' && data.chat}
	{@const search = data.chat.search}
	<Card.Root>
		<Card.Header>
			<Card.Title>Chat log</Card.Title>
			<Card.Description>
				What Servatrice logged, newest first, when servatrice.ini's [logging] has it on.
				{#if search.restricted}Private messages and addresses are left out for developers.{/if}
			</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<form method="GET" class="space-y-3">
				<input type="hidden" name="tab" value="chat" />
				<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
					<div class="space-y-1.5"><Label for="log-user">Player</Label><Input id="log-user" name="user" value={search.user} /></div>
					{#if !search.restricted}
						<div class="space-y-1.5"><Label for="log-ip">IP address</Label><Input id="log-ip" name="ip" value={search.ip} class="font-mono" /></div>
					{/if}
					<div class="space-y-1.5"><Label for="log-game">Game id</Label><Input id="log-game" name="gameId" type="number" min={1} value={search.gameId ?? ''} /></div>
					<div class="space-y-1.5"><Label for="log-target">Room or game name</Label><Input id="log-target" name="target" value={search.target} /></div>
					<div class="space-y-1.5"><Label for="log-text">Message contains</Label><Input id="log-text" name="text" value={search.text} /></div>
				</div>
				<div class="flex flex-wrap items-center gap-4">
					{#each data.chat.types as type (type)}
						{#if !(search.restricted && type === 'chat')}
							<label class="flex items-center gap-2 text-sm">
								<input type="checkbox" name={type} checked={search.types.includes(type)} class="rounded" />
								{typeLabel[type]}
							</label>
						{/if}
					{/each}
					<Select name="hours" value={String(search.hours)} aria-label="Time range">
						{#each data.chat.hoursOptions as hours (hours)}<option value={String(hours)}>{hoursLabel(hours)}</option>{/each}
					</Select>
					<Button type="submit" variant="secondary"><SearchIcon /> Search</Button>
				</div>
			</form>

			{#if data.chat.messages.length}
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head>When</Table.Head>
							<Table.Head>From</Table.Head>
							<Table.Head>Where</Table.Head>
							<Table.Head>Message</Table.Head>
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each data.chat.messages as message, i (i)}
							<Table.Row>
								<Table.Cell class="align-top"><Time at={message.at} /></Table.Cell>
								<Table.Cell class="align-top">
									{#if data.staff.moderate && message.senderId !== null}
										<a href={playerHref(message.senderName)} class="font-medium underline-offset-4 hover:underline">{message.senderName}</a>
									{:else}
										<span class="font-medium">{message.senderName}</span>
									{/if}
									{#if message.senderIp}<div class="font-mono text-xs text-muted-foreground">{message.senderIp}</div>{/if}
								</Table.Cell>
								<Table.Cell class="align-top">
									<Badge variant={message.targetType === 'chat' ? 'outline' : 'secondary'}>{message.targetType === 'chat' ? 'to' : message.targetType}</Badge>
									{#if message.targetType === 'game' && message.targetId}
										<a href="?tab=chat&gameId={message.targetId}" class="underline-offset-4 hover:underline">{message.targetName || `#${message.targetId}`}</a>
									{:else}
										{message.targetName}
									{/if}
								</Table.Cell>
								<Table.Cell class="max-w-xl align-top break-words whitespace-pre-line">{message.message}</Table.Cell>
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>
				{#if data.chat.messages.length === search.limit}<p class="text-xs text-muted-foreground">The newest {search.limit}; narrow the search for older ones.</p>{/if}
			{:else}
				<p class="text-sm text-muted-foreground">Nothing logged matches.</p>
			{/if}
		</Card.Content>
	</Card.Root>
{:else if data.tab === 'audit' && data.audit}
	<Card.Root>
		<Card.Header>
			<Card.Title>Servatrice audit</Card.Title>
			<Card.Description>Registrations, activations and password resets, as servatrice.ini's [audit] records them. The newest 100.</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<form method="GET" class="flex flex-wrap items-end gap-3">
				<input type="hidden" name="tab" value="audit" />
				<div class="space-y-1.5"><Label for="audit-name">Name</Label><Input id="audit-name" name="name" value={data.audit.name} /></div>
				<Select name="action" value={data.audit.action} aria-label="Action">
					<option value="">Any action</option>
					{#each data.audit.actions as action (action)}<option value={action}>{action}</option>{/each}
				</Select>
				<Button type="submit" variant="secondary"><SearchIcon /> Search</Button>
			</form>
			{#if data.audit.entries.length}
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head>When</Table.Head>
							<Table.Head>Name</Table.Head>
							<Table.Head>Action</Table.Head>
							<Table.Head>Details</Table.Head>
							<Table.Head>Address</Table.Head>
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each data.audit.entries as entry (entry.id)}
							<Table.Row>
								<Table.Cell><Time at={entry.at} /></Table.Cell>
								<Table.Cell><a href="?tab=audit&name={encodeURIComponent(entry.name)}" class="underline-offset-4 hover:underline">{entry.name}</a></Table.Cell>
								<Table.Cell>
									<code class="text-xs">{entry.action}</code>
									<Badge variant={entry.success ? 'secondary' : 'destructive'}>{entry.success ? 'ok' : 'failed'}</Badge>
								</Table.Cell>
								<Table.Cell class="max-w-md text-muted-foreground">{entry.details}</Table.Cell>
								<Table.Cell class="font-mono text-xs">{entry.ip}</Table.Cell>
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>
			{:else}
				<p class="text-sm text-muted-foreground">Nothing recorded matches.</p>
			{/if}
		</Card.Content>
	</Card.Root>
{:else if data.tab === 'site' && data.site}
	<Card.Root>
		<Card.Header>
			<Card.Title>Website actions</Card.Title>
			<Card.Description>Everything staff have done from these pages. The newest 200.</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			<form method="GET" class="flex flex-wrap items-end gap-3">
				<input type="hidden" name="tab" value="site" />
				<div class="space-y-1.5">
					<Label for="site-target">Done to</Label>
					<Input id="site-target" name="target" value={data.site.target} placeholder="A name, room:3, report:12" />
				</div>
				<Button type="submit" variant="secondary"><SearchIcon /> Search</Button>
			</form>
			{#if data.site.entries.length}
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head>When</Table.Head>
							<Table.Head>Staff</Table.Head>
							<Table.Head>Action</Table.Head>
							<Table.Head>To</Table.Head>
							<Table.Head>Details</Table.Head>
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each data.site.entries as entry (entry.id)}
							<Table.Row>
								<Table.Cell><Time at={entry.at} /></Table.Cell>
								<Table.Cell>{entry.actorName}</Table.Cell>
								<Table.Cell><code class="text-xs">{entry.action}</code></Table.Cell>
								<Table.Cell>
									{#if entry.target?.startsWith('report:')}
										<a href="/admin/reports/{entry.target.slice(7)}" class="underline-offset-4 hover:underline">{entry.target}</a>
									{:else if entry.target && !entry.target.includes(':')}
										<a href={playerHref(entry.target)} class="underline-offset-4 hover:underline">{entry.target}</a>
									{:else}
										{entry.target ?? '—'}
									{/if}
								</Table.Cell>
								<Table.Cell class="max-w-md whitespace-pre-line text-muted-foreground">{entry.details ?? ''}</Table.Cell>
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>
			{:else}
				<p class="text-sm text-muted-foreground">Nothing yet.</p>
			{/if}
		</Card.Content>
	</Card.Root>
{/if}
