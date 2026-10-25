<script lang="ts">
	import { enhance, type SubmitFunction } from '$app/forms';
	import * as Card from '#lib/components/ui/card/index.ts';
	import * as Table from '#lib/components/ui/table/index.ts';
	import { Badge } from '#lib/components/ui/badge/index.ts';
	import { Button } from '#lib/components/ui/button/index.ts';
	import { Input } from '#lib/components/ui/input/index.ts';
	import Notice from '#lib/components/Notice.svelte';
	import BanForm from '#lib/components/admin/BanForm.svelte';
	import Pager from '#lib/components/admin/Pager.svelte';
	import Time from '#lib/components/admin/Time.svelte';
	import { duration, full } from '#lib/charts.ts';
	import SearchIcon from '@lucide/svelte/icons/search';
	import PlusIcon from '@lucide/svelte/icons/plus';
	import type { PageProps } from './$types';

	let { data, form }: PageProps = $props();

	let pending = $state<string | null>(null);
	let adding = $state(false);
	const submit =
		(name: string): SubmitFunction =>
		() => {
			pending = name;
			return async ({ result, update }) => {
				if (result.type === 'success' && name === 'ban') adding = false;
				await update();
				pending = null;
			};
		};

	const banLength = (minutes: number) => (minutes === 0 ? 'Permanent' : duration(minutes * 60));
	const now = Date.now() / 1000;
</script>

{#if form && 'error' in form && form.error}
	<Notice type="error" class="mb-4">{form.error}</Notice>
{:else if form && 'message' in form && form.message}
	<Notice type="success" class="mb-4">{form.message}</Notice>
{/if}

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
	<form method="GET" class="flex flex-wrap items-center gap-2" role="search">
		<div class="inline-flex rounded-lg border bg-card p-0.5" role="group" aria-label="Which bans">
			{#each [{ id: 'active', label: 'Unexpired' }, { id: 'all', label: 'All' }] as option (option.id)}
				<a
					href="?scope={option.id}{data.q ? `&q=${encodeURIComponent(data.q)}` : ''}"
					aria-current={option.id === data.scope ? 'true' : undefined}
					class="rounded-md px-3 py-1 text-sm transition-colors {option.id === data.scope
						? 'bg-accent font-medium text-accent-foreground'
						: 'text-muted-foreground hover:text-foreground'}">{option.label}</a
				>
			{/each}
		</div>
		<input type="hidden" name="scope" value={data.scope} />
		<Input name="q" value={data.q} placeholder="Name, IP address or client id" class="w-64" aria-label="Search bans" />
		<Button type="submit" variant="secondary"><SearchIcon /> Search</Button>
	</form>
	<Button onclick={() => (adding = !adding)} variant={adding ? 'ghost' : 'default'}><PlusIcon /> New ban</Button>
</div>

{#if adding}
	<Card.Root class="mb-4">
		<Card.Header>
			<Card.Title>New ban</Card.Title>
			<Card.Description>For a name that may not be an account, an address or a client id. To ban a player, their page has their details to hand.</Card.Description>
		</Card.Header>
		<Card.Content><BanForm submit={submit('ban')} pending={pending === 'ban'} /></Card.Content>
	</Card.Root>
{/if}

<Card.Root>
	<Card.Header>
		<Card.Title>{data.scope === 'active' ? 'Unexpired bans' : 'Every ban'}</Card.Title>
		<Card.Description>
			{full(data.total)} found. “In effect” ones are what Servatrice goes by: for each name, address and client id, only the newest ban counts.
		</Card.Description>
	</Card.Header>
	<Card.Content class="space-y-4">
		{#if data.bans.length}
			<Table.Root>
				<Table.Header>
					<Table.Row>
						<Table.Head>Banned</Table.Head>
						<Table.Head>Placed</Table.Head>
						<Table.Head>Length</Table.Head>
						<Table.Head>By</Table.Head>
						<Table.Head>Reason</Table.Head>
						<Table.Head></Table.Head>
					</Table.Row>
				</Table.Header>
				<Table.Body>
					{#each data.bans as ban (`${ban.userName}:${ban.at}`)}
						<Table.Row>
							<Table.Cell class="align-top">
								{#if ban.accountId !== null}
									<a href="/admin/players/{encodeURIComponent(ban.userName)}" class="font-medium underline-offset-4 hover:underline">{ban.userName}</a>
								{:else if ban.userName}
									<span class="font-medium" title="No account has this name">{ban.userName}</span>
								{/if}
								{#if ban.ip}<div class="font-mono text-xs">{ban.ip}</div>{/if}
								{#if ban.clientId}<div class="font-mono text-xs text-muted-foreground">{ban.clientId}</div>{/if}
							</Table.Cell>
							<Table.Cell class="align-top"><Time at={ban.at} /></Table.Cell>
							<Table.Cell class="align-top">
								<div>{banLength(ban.minutes)}</div>
								{#if ban.effective}
									<Badge variant="destructive">In effect</Badge>
								{:else if ban.minutes === 0 || (ban.expiresAt ?? 0) > now}
									<Badge variant="outline" title="A later ban on the same name, address or client id overrides it">Overridden</Badge>
								{/if}
								{#if ban.expiresAt && ban.expiresAt > now}<div class="text-xs text-muted-foreground">until <Time at={ban.expiresAt} /></div>{/if}
							</Table.Cell>
							<Table.Cell class="align-top">{ban.adminName ?? `#${ban.adminId}`}</Table.Cell>
							<Table.Cell class="max-w-md align-top text-sm whitespace-pre-line">
								{ban.reason}
								{#if ban.visibleReason}<div class="text-xs text-muted-foreground">Shown: {ban.visibleReason}</div>{/if}
							</Table.Cell>
							<Table.Cell class="align-top">
								{#if ban.minutes === 0 || (ban.expiresAt ?? 0) > now}
									<form method="POST" action="?/liftBan" use:enhance={submit('liftBan')}>
										<input type="hidden" name="userName" value={ban.userName} />
										<input type="hidden" name="at" value={ban.at} />
										<Button type="submit" size="xs" variant="outline" disabled={pending === 'liftBan'}>Lift</Button>
									</form>
								{/if}
							</Table.Cell>
						</Table.Row>
					{/each}
				</Table.Body>
			</Table.Root>
			<Pager total={data.total} offset={data.offset} limit={data.limit} />
		{:else}
			<p class="text-sm text-muted-foreground">No bans.</p>
		{/if}
	</Card.Content>
</Card.Root>
