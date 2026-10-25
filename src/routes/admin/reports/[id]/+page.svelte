<script lang="ts">
	import { enhance, type SubmitFunction } from '$app/forms';
	import * as Card from '#lib/components/ui/card/index.ts';
	import { Badge } from '#lib/components/ui/badge/index.ts';
	import { Button } from '#lib/components/ui/button/index.ts';
	import { Label } from '#lib/components/ui/label/index.ts';
	import Notice from '#lib/components/Notice.svelte';
	import Textarea from '#lib/components/Textarea.svelte';
	import Time from '#lib/components/admin/Time.svelte';
	import { duration } from '#lib/charts.ts';
	import type { PageProps } from './$types';

	let { data, form }: PageProps = $props();

	const report = $derived(data.report);
	const open = $derived(report.status === 'open' || report.status === 'assigned');

	let pending = $state<string | null>(null);
	const submit =
		(name: string): SubmitFunction =>
		() => {
			pending = name;
			return async ({ update }) => {
				await update();
				pending = null;
			};
		};

	const statusLabel: Record<string, string> = { open: 'Open', assigned: 'Assigned', resolved: 'Resolved', dismissed: 'Dismissed' };
</script>

<a href="/admin/reports" class="mb-4 inline-block text-sm text-muted-foreground underline-offset-4 hover:underline">← Reports</a>

{#if form && 'error' in form && form.error}
	<Notice type="error" class="mb-4">{form.error}</Notice>
{:else if form && 'message' in form && form.message}
	<Notice type="success" class="mb-4">{form.message}</Notice>
{/if}

<div class="mb-6">
	<h2 class="flex flex-wrap items-center gap-2 text-2xl font-semibold tracking-tight">
		#{report.id} {data.category}
		<Badge variant={open ? 'default' : 'secondary'}>{statusLabel[report.status]}</Badge>
	</h2>
	<p class="text-sm text-muted-foreground">
		<a href="/admin/players/{encodeURIComponent(report.reporterName)}" class="underline-offset-4 hover:underline">{report.reporterName}</a>
		reported
		<a href="/admin/players/{encodeURIComponent(report.reportedUserName)}" class="font-medium text-foreground underline-offset-4 hover:underline">{report.reportedUserName}</a>
		<Time at={report.createdAt} relative />
		{#if report.roomName}in {report.roomName}{/if}
		{#if report.gameId}· game #{report.gameId}{/if}
		{#if report.replayId}· replay #{report.replayId} kept{/if}
	</p>
</div>

<div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
	<div class="space-y-4">
		<Card.Root>
			<Card.Header><Card.Title>What they said</Card.Title></Card.Header>
			<Card.Content><p class="text-sm whitespace-pre-line">{report.description}</p></Card.Content>
		</Card.Root>

		{#if report.chatLog}
			<Card.Root>
				<Card.Header>
					<Card.Title>Chat attached</Card.Title>
					<Card.Description>As the reporter's client saw it.</Card.Description>
				</Card.Header>
				<Card.Content>
					<pre class="max-h-96 overflow-auto rounded-md border bg-muted/50 p-3 font-mono text-xs whitespace-pre-wrap">{report.chatLog}</pre>
				</Card.Content>
			</Card.Root>
		{/if}

		<Card.Root>
			<Card.Header>
				<Card.Title>Comments</Card.Title>
				<Card.Description>Between the reporter and staff. Each side sees new ones at their next login.</Card.Description>
			</Card.Header>
			<Card.Content class="space-y-4">
				{#if report.comments.length}
					<ul class="space-y-3">
						{#each report.comments as comment (comment.id)}
							<li class="rounded-lg border px-3 py-2 {comment.isModerator ? 'bg-accent/40' : ''}">
								<div class="mb-1 flex items-center gap-2 text-xs text-muted-foreground">
									<span class="font-medium text-foreground">{comment.authorName}</span>
									{#if comment.isModerator}<Badge variant="secondary">Staff</Badge>{/if}
									<Time at={comment.createdAt} relative />
								</div>
								<p class="text-sm whitespace-pre-line">{comment.text}</p>
							</li>
						{/each}
					</ul>
				{:else}
					<p class="text-sm text-muted-foreground">No comments.</p>
				{/if}
				{#if open}
					<form method="POST" action="?/comment" class="space-y-2" use:enhance={submit('comment')}>
						<Textarea name="comment" rows={3} required placeholder="Write to the reporter" aria-label="Comment" />
						<Button type="submit" size="sm" variant="secondary" disabled={pending === 'comment'}>Comment</Button>
					</form>
				{/if}
			</Card.Content>
		</Card.Root>
	</div>

	<div class="space-y-4">
		<Card.Root>
			<Card.Header><Card.Title>Handling</Card.Title></Card.Header>
			<Card.Content class="space-y-4 text-sm">
				{#if report.status === 'open'}
					<form method="POST" action="?/assign" use:enhance={submit('assign')}>
						<Button type="submit" disabled={pending === 'assign'}>Take it</Button>
					</form>
				{:else if report.status === 'assigned'}
					<p>Assigned to <span class="font-medium">{report.assignedName ?? 'a deleted account'}</span>.</p>
				{/if}

				{#if open}
					<form method="POST" action="?/resolve" class="space-y-3" use:enhance={submit('resolve')}>
						<div class="space-y-1.5">
							<Label for="note">Note to the reporter</Label>
							<Textarea id="note" name="note" rows={3} placeholder="Optional" />
						</div>
						<div class="flex flex-wrap gap-2">
							<Button type="submit" name="outcome" value="resolve" disabled={pending === 'resolve'}>Resolve</Button>
							<Button type="submit" name="outcome" value="dismiss" variant="outline" disabled={pending === 'resolve'}>Dismiss</Button>
						</div>
					</form>
				{:else}
					<p>
						{statusLabel[report.status]} by <span class="font-medium">{report.resolvedName ?? 'unknown'}</span>
						<Time at={report.resolvedAt} relative />{#if report.resolvedAt}, {duration(Math.max(0, report.resolvedAt - report.createdAt))} after it came in{/if}.
					</p>
					{#if report.resolutionNote}<p class="whitespace-pre-line text-muted-foreground">“{report.resolutionNote}”</p>{/if}
				{/if}
			</Card.Content>
		</Card.Root>

		{#if report.game}
			<Card.Root>
				<Card.Header>
					<Card.Title>Game #{report.game.id}</Card.Title>
					<Card.Description>{report.game.description || 'No description'}</Card.Description>
				</Card.Header>
				<Card.Content class="space-y-1 text-sm">
					{#if report.game.roomName}<p>In {report.game.roomName}{#if report.game.gameTypes}, {report.game.gameTypes}{/if}</p>{/if}
					{#if report.game.creatorName}<p>Created by {report.game.creatorName}</p>{/if}
					<p class="text-muted-foreground">
						Started <Time at={report.game.started} />{#if report.game.finished}, ended <Time at={report.game.finished} />{:else}; still going, or not recorded{/if}
					</p>
					{#if report.game.players.length}
						<p>
							Players:
							{#each report.game.players as name, i (name)}
								<a href="/admin/players/{encodeURIComponent(name)}" class="underline-offset-4 hover:underline">{name}</a>{i < report.game.players.length - 1 ? ', ' : ''}
							{/each}
						</p>
					{/if}
					<a href="/admin/logs?gameId={report.game.id}" class="inline-block text-primary underline-offset-4 hover:underline">Its chat log</a>
				</Card.Content>
			</Card.Root>
		{/if}
	</div>
</div>
