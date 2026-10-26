<script lang="ts">
	import { enhance, type SubmitFunction } from '$app/forms';
	import * as Card from '#lib/components/ui/card/index.ts';
	import * as Table from '#lib/components/ui/table/index.ts';
	import * as Avatar from '#lib/components/ui/avatar/index.ts';
	import { Badge } from '#lib/components/ui/badge/index.ts';
	import { Button } from '#lib/components/ui/button/index.ts';
	import { Input } from '#lib/components/ui/input/index.ts';
	import { Label } from '#lib/components/ui/label/index.ts';
	import Notice from '#lib/components/Notice.svelte';
	import CopyField from '#lib/components/CopyField.svelte';
	import Select from '#lib/components/Select.svelte';
	import Textarea from '#lib/components/Textarea.svelte';
	import BanForm from '#lib/components/admin/BanForm.svelte';
	import RoleBadges from '#lib/components/admin/RoleBadges.svelte';
	import Time from '#lib/components/admin/Time.svelte';
	import { countryName, flag } from '#lib/countries.ts';
	import { duration } from '#lib/charts.ts';
	import TriangleAlertIcon from '@lucide/svelte/icons/triangle-alert';
	import type { PageProps } from './$types';

	let { data, form }: PageProps = $props();

	const player = $derived(data.player);

	let pending = $state<string | null>(null);
	const submit =
		(name: string, reset = true): SubmitFunction =>
		() => {
			pending = name;
			return async ({ update }) => {
				await update({ reset });
				pending = null;
			};
		};

	const feedback = (action: string) => (form?.action === action ? form : null);
	const banLength = (minutes: number) => (minutes === 0 ? 'Permanent' : duration(minutes * 60));
	const statusLabel: Record<string, string> = { open: 'Open', assigned: 'Assigned', resolved: 'Resolved', dismissed: 'Dismissed' };
</script>

{#snippet result(action: string)}
	{@const f = feedback(action)}
	{#if f && 'error' in f && f.error}
		<Notice type="error">{f.error}</Notice>
	{:else if f && 'message' in f && f.message}
		<Notice type="success">{f.message}</Notice>
	{/if}
{/snippet}

<a href="/admin/players" class="mb-4 inline-block text-sm text-muted-foreground underline-offset-4 hover:underline">← Players</a>

<div class="mb-6 flex flex-wrap items-center gap-4">
	<Avatar.Root class="size-16 rounded-xl after:rounded-xl">
		{#if player.hasAvatar}
			<Avatar.Image src="/api/user/{encodeURIComponent(player.name)}/avatar" alt="" class="rounded-xl" />
		{/if}
		<Avatar.Fallback class="rounded-xl text-lg">{player.name.slice(0, 2).toUpperCase()}</Avatar.Fallback>
	</Avatar.Root>
	<div class="min-w-0">
		<h2 class="flex flex-wrap items-center gap-2 text-2xl font-semibold tracking-tight">
			{player.name}
			{#if player.country}<span class="text-xl" title={countryName(player.country)}>{flag(player.country)}</span>{/if}
			<RoleBadges admin={player.admin} privlevel={player.privlevel} />
			{#if !player.active}<Badge variant="destructive">Not activated</Badge>{/if}
			{#if data.nameBan}<Badge variant="destructive">Banned</Badge>{/if}
			{#if !player.hasPassword}<Badge variant="outline">No password</Badge>{/if}
			{#if player.forcePasswordChange}<Badge variant="outline">Must change password</Badge>{/if}
		</h2>
		<p class="text-sm text-muted-foreground">
			#{player.id} · {player.email || 'no email'} · registered <Time at={player.registeredAt} /> · last login
			<Time at={player.lastLogin} relative empty="never" />{#if player.clientVersion}&nbsp;on {player.clientVersion}{/if} ·
			<a href="/decks/by/{encodeURIComponent(player.name)}" class="underline-offset-4 hover:underline">decks</a>
		</p>
	</div>
</div>

{#if data.nameBan}
	<Notice type="error" class="mb-4">
		Banned {data.nameBan.minutes === 0 ? 'permanently' : 'until'}
		{#if data.nameBan.expiresAt}<Time at={data.nameBan.expiresAt} />{/if} by {data.nameBan.adminName ?? `#${data.nameBan.adminId}`}:
		{data.nameBan.reason}
	</Notice>
{/if}

<div class="grid gap-4 lg:grid-cols-2">
	<Card.Root class="lg:col-span-2">
		<Card.Header>
			<Card.Title>Ban</Card.Title>
			<Card.Description>
				Takes effect at their next login. Servatrice isn't told, so if they're connected they stay connected until they leave.
			</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-4">
			{@render result('ban')}
			{#if data.refusal}
				<Notice type="info">{data.refusal}</Notice>
			{:else}
				<BanForm
					submit={submit('ban')}
					pending={pending === 'ban'}
					userName={player.name}
					fixedName
					suggestedIp={player.lastIp}
					suggestedClientId={player.clientId || null}
				/>
			{/if}
		</Card.Content>
	</Card.Root>

	<Card.Root>
		<Card.Header>
			<Card.Title>Warn</Card.Title>
			<Card.Description>Adds to their warning history, which staff see in the client. They aren't shown it.</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-3">
			{@render result('warn')}
			{#if data.refusal}
				<Notice type="info">{data.refusal}</Notice>
			{:else}
				<form method="POST" action="?/warn" class="flex gap-2" use:enhance={submit('warn')}>
					<Input name="reason" list="official-warnings" placeholder="Reason" required aria-label="Reason" />
					<datalist id="official-warnings">
						{#each data.officialWarnings as reason (reason)}<option value={reason}></option>{/each}
					</datalist>
					<Button type="submit" variant="secondary" disabled={pending === 'warn'}>Warn</Button>
				</form>
			{/if}
		</Card.Content>
	</Card.Root>

	<Card.Root>
		<Card.Header>
			<Card.Title>Account</Card.Title>
			<Card.Description>Client id <code class="text-xs">{player.clientId || 'none yet'}</code>{#if player.realname} · real name {player.realname}{/if}</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-3">
			{@render result('account')}
			{@render result('activate')}
			{#if form?.action === 'account' && 'password' in form && form.password}
				<CopyField label="Temporary password" value={form.password} />
			{/if}
			<div class="flex flex-wrap gap-2">
				{#if !player.active}
					<form method="POST" action="?/activate" use:enhance={submit('activate')}>
						<Button type="submit" size="sm" disabled={pending === 'activate'}>Activate</Button>
					</form>
				{/if}
				{#if !data.refusal}
					<form method="POST" action="?/resetPassword" use:enhance={submit('resetPassword')}>
						<Button type="submit" size="sm" variant="outline" disabled={pending === 'resetPassword'}>Reset password</Button>
					</form>
					{#if player.hasAvatar}
						<form method="POST" action="?/removeAvatar" use:enhance={submit('removeAvatar')}>
							<Button type="submit" size="sm" variant="outline" disabled={pending === 'removeAvatar'}>Remove avatar</Button>
						</form>
					{/if}
					<form method="POST" action="?/signOut" use:enhance={submit('signOut')}>
						<Button type="submit" size="sm" variant="ghost" disabled={pending === 'signOut'}>Sign out of the website</Button>
					</form>
				{/if}
			</div>
		</Card.Content>
	</Card.Root>

	{#if player.adminNotes !== null}
		<Card.Root>
			<Card.Header>
				<Card.Title>Staff notes</Card.Title>
				<Card.Description>The same notes staff see in the client.</Card.Description>
			</Card.Header>
			<Card.Content>
				<form method="POST" action="?/notes" class="space-y-3" use:enhance={submit('notes', false)}>
					{@render result('notes')}
					<Textarea name="notes" rows={5} value={player.adminNotes} aria-label="Staff notes" />
					<Button type="submit" size="sm" variant="secondary" disabled={pending === 'notes'}>Save notes</Button>
				</form>
			</Card.Content>
		</Card.Root>
	{/if}

	{#if data.staff.administer}
		<Card.Root>
			<Card.Header>
				<Card.Title>Roles and privileges</Card.Title>
				<Card.Description>Admin itself is only set in the database, by hand.</Card.Description>
			</Card.Header>
			<Card.Content class="space-y-5">
				<form method="POST" action="?/staff" class="space-y-3" use:enhance={submit('staff', false)}>
					{@render result('staff')}
					<div class="flex flex-wrap gap-4">
						{#each data.adjustableFlags as role (role.id)}
							<label class="flex items-center gap-2 text-sm">
								<input type="checkbox" name={role.id} checked={(player.admin & role.bit) !== 0} class="rounded" />
								{role.label}
							</label>
						{/each}
					</div>
					<Button type="submit" size="sm" variant="secondary" disabled={pending === 'staff'}>Save roles</Button>
				</form>

				<form method="POST" action="?/privilege" class="space-y-3" use:enhance={submit('privilege', false)}>
					{@render result('privilege')}
					<div class="flex flex-wrap items-end gap-3">
						<div class="space-y-1.5">
							<Label for="privilege">Privilege</Label>
							<Select id="privilege" name="level" value={player.privlevel}>
								{#each data.privileges as level (level)}<option value={level}>{level === 'NONE' ? 'None' : level === 'VIP' ? 'VIP' : 'Donator'}</option>{/each}
							</Select>
						</div>
						<div class="space-y-1.5">
							<Label for="privilege-until">Until (for your records)</Label>
							<Input
								id="privilege-until"
								name="until"
								type="date"
								value={player.privlevelEnd && player.privlevel !== 'NONE' ? new Date(player.privlevelEnd * 1000).toISOString().slice(0, 10) : ''}
							/>
						</div>
						<Button type="submit" size="sm" variant="secondary" disabled={pending === 'privilege'}>Save</Button>
					</div>
					<p class="text-xs text-muted-foreground">Rooms can require a privilege. Servatrice never ends one by itself.</p>
				</form>
			</Card.Content>
		</Card.Root>
	{/if}

	<Card.Root class="lg:col-span-2">
		<Card.Header>
			<Card.Title>Bans</Card.Title>
			<Card.Description>Every ban placed on this name.</Card.Description>
		</Card.Header>
		<Card.Content class="space-y-3">
			{@render result('liftBan')}
			{#if data.bans.length}
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head>Placed</Table.Head>
							<Table.Head>By</Table.Head>
							<Table.Head>Length</Table.Head>
							<Table.Head>Also banned</Table.Head>
							<Table.Head>Reason</Table.Head>
							<Table.Head></Table.Head>
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each data.bans as ban (ban.at)}
							<Table.Row>
								<Table.Cell><Time at={ban.at} /></Table.Cell>
								<Table.Cell>{ban.adminName ?? `#${ban.adminId}`}</Table.Cell>
								<Table.Cell>
									{banLength(ban.minutes)}
									{#if ban.effective}<Badge variant="destructive">In effect</Badge>{/if}
								</Table.Cell>
								<Table.Cell class="font-mono text-xs">{[ban.ip, ban.clientId].filter(Boolean).join(', ') || '—'}</Table.Cell>
								<Table.Cell class="max-w-md text-sm whitespace-pre-line">
									{ban.reason}
									{#if ban.visibleReason}<div class="text-xs text-muted-foreground">Shown: {ban.visibleReason}</div>{/if}
								</Table.Cell>
								<Table.Cell>
									{#if ban.minutes === 0 || (ban.expiresAt ?? 0) > Date.now() / 1000}
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
			{:else}
				<p class="text-sm text-muted-foreground">Never banned.</p>
			{/if}
		</Card.Content>
	</Card.Root>

	<Card.Root>
		<Card.Header>
			<Card.Title>Warnings</Card.Title>
		</Card.Header>
		<Card.Content>
			{#if data.warnings.length}
				<ul class="divide-y text-sm">
					{#each data.warnings as warning (warning.at)}
						<li class="flex flex-wrap justify-between gap-2 py-2">
							<span>{warning.reason} <span class="text-muted-foreground">— {warning.modName}</span></span>
							<span class="text-xs text-muted-foreground"><Time at={warning.at} /></span>
						</li>
					{/each}
				</ul>
			{:else}
				<p class="text-sm text-muted-foreground">Never warned.</p>
			{/if}
		</Card.Content>
	</Card.Root>

	{#if data.reports}
		<Card.Root>
			<Card.Header>
				<Card.Title>Reports about them</Card.Title>
				<Card.Description>{data.reports.total} in all</Card.Description>
			</Card.Header>
			<Card.Content>
				{#if data.reports.reports.length}
					<ul class="divide-y text-sm">
						{#each data.reports.reports as report (report.id)}
							<li class="flex flex-wrap items-center justify-between gap-2 py-2">
								<a href="/admin/reports/{report.id}" class="min-w-0 truncate underline-offset-4 hover:underline">
									#{report.id} {report.category} — from {report.reporterName}
								</a>
								<span class="flex items-center gap-2 text-xs text-muted-foreground">
									<Badge variant={report.status === 'open' || report.status === 'assigned' ? 'default' : 'secondary'}>{statusLabel[report.status]}</Badge>
									<Time at={report.createdAt} relative />
								</span>
							</li>
						{/each}
					</ul>
				{:else}
					<p class="text-sm text-muted-foreground">Nobody has reported them.</p>
				{/if}
			</Card.Content>
		</Card.Root>
	{/if}

	<Card.Root class="lg:col-span-2">
		<Card.Header>
			<Card.Title>Connections</Card.Title>
			<Card.Description>The latest {data.sessions.length}.</Card.Description>
		</Card.Header>
		<Card.Content>
			{#if data.sessions.length}
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head>Started</Table.Head>
							<Table.Head>Length</Table.Head>
							<Table.Head>Address</Table.Head>
							<Table.Head>Client id</Table.Head>
							<Table.Head>Type</Table.Head>
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each data.sessions as session (session.id)}
							<Table.Row>
								<Table.Cell><Time at={session.start} /></Table.Cell>
								<Table.Cell>
									{#if session.end === null}<Badge>Open</Badge>{:else}{duration(Math.max(0, session.end - session.start))}{/if}
								</Table.Cell>
								<Table.Cell>
									<a href="/admin/players?q={encodeURIComponent(session.ip)}" class="font-mono text-xs underline-offset-4 hover:underline">{session.ip}</a>
								</Table.Cell>
								<Table.Cell class="font-mono text-xs text-muted-foreground">{session.clientId || '—'}</Table.Cell>
								<Table.Cell class="text-muted-foreground">{session.connectionType ?? '—'}</Table.Cell>
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>
			{:else}
				<p class="text-sm text-muted-foreground">Never connected.</p>
			{/if}
		</Card.Content>
	</Card.Root>

	<Card.Root class="lg:col-span-2">
		<Card.Header>
			<Card.Title class="flex items-center gap-2">
				Possible alts
				{#if data.alts.some((alt) => alt.bans > 0)}<TriangleAlertIcon class="size-4 text-destructive" />{/if}
			</Card.Title>
			<Card.Description>Accounts with the same email or client id, or that used one of their addresses in the last six months.</Card.Description>
		</Card.Header>
		<Card.Content>
			{#if data.alts.length}
				<Table.Root>
					<Table.Header>
						<Table.Row>
							<Table.Head>Name</Table.Head>
							<Table.Head>Shares</Table.Head>
							<Table.Head>Registered</Table.Head>
							<Table.Head>Last login</Table.Head>
							<Table.Head class="text-right">Warnings</Table.Head>
							<Table.Head class="text-right">Bans</Table.Head>
						</Table.Row>
					</Table.Header>
					<Table.Body>
						{#each data.alts as alt (alt.id)}
							<Table.Row>
								<Table.Cell>
									<a href="/admin/players/{encodeURIComponent(alt.name)}" class="font-medium underline-offset-4 hover:underline">{alt.name}</a>
									{#if !alt.active}<Badge variant="outline">Inactive</Badge>{/if}
								</Table.Cell>
								<Table.Cell class="text-muted-foreground">
									{[alt.sharesEmail && 'email', alt.sharesClientId && 'client id'].filter(Boolean).join(', ') || 'an address'}
								</Table.Cell>
								<Table.Cell><Time at={alt.registeredAt} /></Table.Cell>
								<Table.Cell><Time at={alt.lastLogin} relative empty="never" /></Table.Cell>
								<Table.Cell class="text-right tabular-nums">{alt.warnings}</Table.Cell>
								<Table.Cell class="text-right tabular-nums {alt.bans ? 'font-medium text-destructive' : ''}">{alt.bans}</Table.Cell>
							</Table.Row>
						{/each}
					</Table.Body>
				</Table.Root>
			{:else}
				<p class="text-sm text-muted-foreground">None found.</p>
			{/if}
		</Card.Content>
	</Card.Root>

	<Card.Root>
		<Card.Header>
			<Card.Title>Website sign-ins</Card.Title>
		</Card.Header>
		<Card.Content>
			{#if data.identities.length}
				<ul class="divide-y text-sm">
					{#each data.identities as identity (identity.provider)}
						<li class="py-2">
							<span class="font-medium">{identity.provider}</span>
							<span class="text-muted-foreground">{identity.username ?? ''} {identity.email ? `· ${identity.email}` : ''}</span>
							<div class="text-xs text-muted-foreground">Linked <Time at={identity.linkedAt} /> · last used <Time at={identity.lastLoginAt} relative /></div>
						</li>
					{/each}
				</ul>
			{:else}
				<p class="text-sm text-muted-foreground">No provider linked{player.hasPassword ? '; signs in with their password' : ''}.</p>
			{/if}
		</Card.Content>
	</Card.Root>

	<Card.Root>
		<Card.Header>
			<Card.Title>Staff actions here</Card.Title>
			<Card.Description>What staff have done to this account from the website.</Card.Description>
		</Card.Header>
		<Card.Content>
			{#if data.actions.length}
				<ul class="divide-y text-sm">
					{#each data.actions as entry (entry.id)}
						<li class="py-2">
							<span class="font-medium">{entry.actorName}</span>
							<code class="mx-1 rounded bg-muted px-1 py-0.5 text-xs">{entry.action}</code>
							{#if entry.details}<span class="text-muted-foreground">{entry.details}</span>{/if}
							<div class="text-xs text-muted-foreground"><Time at={entry.at} /></div>
						</li>
					{/each}
				</ul>
			{:else}
				<p class="text-sm text-muted-foreground">Nothing yet.</p>
			{/if}
		</Card.Content>
	</Card.Root>
</div>
