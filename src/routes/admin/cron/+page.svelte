<script lang="ts">
	import { enhance } from '$app/forms';
	import * as Card from '#lib/components/ui/card/index.ts';
	import * as Table from '#lib/components/ui/table/index.ts';
	import { Badge } from '#lib/components/ui/badge/index.ts';
	import { Button } from '#lib/components/ui/button/index.ts';
	import Notice from '#lib/components/Notice.svelte';
	import Time from '#lib/components/admin/Time.svelte';
	import { duration } from '#lib/charts.ts';
	import PlayIcon from '@lucide/svelte/icons/play';
	import type { PageProps } from './$types';

	let { data, form }: PageProps = $props();

	/** The job whose history is open under its row. */
	let expanded = $state<string | null>(null);
	let pending = $state<string | null>(null);

	const every = (ms: number) => {
		const units: [number, string][] = [[86400_000, 'day'], [3600_000, 'hour'], [60_000, 'minute']];
		const unit = units.find(([size]) => ms % size === 0);
		if (!unit) return duration(ms / 1000);
		const n = ms / unit[0];
		return n === 1 ? unit[1] : `${n} ${unit[1]}s`;
	};
	const took = (ms: number) => (ms < 1000 ? `${ms} ms` : `${(ms / 1000).toFixed(1)} s`);
</script>

<Card.Root>
	<Card.Header>
		<Card.Title>Scheduled jobs</Card.Title>
		<Card.Description>
			The site's own housekeeping. Each instance times them from when it started, and only one runs a job at a time, so
			"next run" isn't known; the last run and how often is. Runs are kept for 30 days.
		</Card.Description>
	</Card.Header>
	<Card.Content class="space-y-4">
		{#if form && 'error' in form && form.error}
			<Notice type="error">{form.error}</Notice>
		{:else if form && 'message' in form && form.message}
			<Notice type="success">{form.message}</Notice>
		{/if}

		<Table.Root>
			<Table.Header>
				<Table.Row>
					<Table.Head>Job</Table.Head>
					<Table.Head>Every</Table.Head>
					<Table.Head>Last run</Table.Head>
					<Table.Head>Result</Table.Head>
					<Table.Head>Last did</Table.Head>
					{#if data.staff.administer}<Table.Head></Table.Head>{/if}
				</Table.Row>
			</Table.Header>
			<Table.Body>
				{#each data.jobs as job (job.name)}
					{@const last = job.lastRun}
					<Table.Row>
						<Table.Cell class="font-mono text-xs">{job.name}</Table.Cell>
						<Table.Cell class="text-muted-foreground">{every(job.interval)}</Table.Cell>
						<Table.Cell>
							<Time at={last?.at ?? null} relative empty="never" />
							{#if last}<span class="text-xs text-muted-foreground">· {took(last.durationMs)}</span>{/if}
						</Table.Cell>
						<Table.Cell>
							{#if job.running}
								<Badge variant="outline">running…</Badge>
							{:else if !last}
								<span class="text-muted-foreground">—</span>
							{:else if last.ok}
								<Badge variant="secondary">ok{last.manual ? ' (manual)' : ''}</Badge>
							{:else}
								<Badge variant="destructive" title={last.error}>failed{last.manual ? ' (manual)' : ''}</Badge>
							{/if}
						</Table.Cell>
						<Table.Cell class="max-w-md text-muted-foreground">
							{#if job.history.length}
								{@const said = job.history[0]}
								<button
									type="button"
									class="block w-full truncate text-left hover:text-foreground"
									title={said.error ?? said.summary}
									aria-expanded={expanded === job.name}
									onclick={() => (expanded = expanded === job.name ? null : job.name)}
								>
									<Time at={said.at} relative /> · {said.error ? `failed: ${said.error}` : said.summary}
								</button>
							{:else}
								—
							{/if}
						</Table.Cell>
						{#if data.staff.administer}
							<Table.Cell class="text-right">
								<form
									method="POST"
									action="?/run"
									use:enhance={() => {
										pending = job.name;
										return async ({ update }) => {
											await update();
											pending = null;
										};
									}}
								>
									<input type="hidden" name="name" value={job.name} />
									<Button
										type="submit"
										variant="ghost"
										size="sm"
										disabled={pending !== null || job.running}
										title="Runs it now, under the same lock the timer uses"
									>
										<PlayIcon />
										{pending === job.name ? 'Running…' : 'Run now'}
									</Button>
								</form>
							</Table.Cell>
						{/if}
					</Table.Row>
					{#if expanded === job.name}
						<!-- What it said it did, newest first; runs with nothing to do say nothing. -->
						<Table.Row class="hover:bg-transparent">
							<Table.Cell colspan={data.staff.administer ? 6 : 5} class="pt-0">
								<ol class="space-y-0.5 rounded-md bg-muted/40 p-2 font-mono text-xs">
									{#each job.history as run, i (i)}
										<li class="break-words whitespace-normal">
											<span class="text-muted-foreground"><Time at={run.at} /></span>
											{#if run.error}<span class="text-destructive">failed: {run.error}</span>{:else}{run.summary}{/if}
											{#if run.manual}<span class="text-muted-foreground">(manual)</span>{/if}
										</li>
									{/each}
								</ol>
							</Table.Cell>
						</Table.Row>
					{/if}
				{/each}
			</Table.Body>
		</Table.Root>
	</Card.Content>
</Card.Root>
