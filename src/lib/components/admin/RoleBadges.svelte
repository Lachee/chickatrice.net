<script lang="ts">
	import { Badge } from '#lib/components/ui/badge/index.ts';
	import { staffLabels } from '#lib/staff.ts';

	let { admin, privlevel = 'NONE' }: { admin: number; privlevel?: string } = $props();
</script>

{#each staffLabels(admin) as label (label)}
	<Badge variant={label === 'Admin' || label === 'Moderator' ? 'default' : 'secondary'}>{label}</Badge>
{/each}
{#if privlevel && privlevel !== 'NONE'}
	<Badge variant="outline">{privlevel === 'VIP' ? 'VIP' : 'Donator'}</Badge>
{/if}
