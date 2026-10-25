<script lang="ts">
	import { Badge } from '#lib/components/ui/badge/index.ts';
	import { VISIBILITIES, type DeckVisibility } from '#lib/decks.ts';
	import GlobeIcon from '@lucide/svelte/icons/globe';
	import LinkIcon from '@lucide/svelte/icons/link';
	import LockIcon from '@lucide/svelte/icons/lock';

	let { visibility }: { visibility: DeckVisibility } = $props();

	const Icon = $derived({ private: LockIcon, unlisted: LinkIcon, public: GlobeIcon }[visibility]);
	const info = $derived(VISIBILITIES.find((v) => v.id === visibility)!);
</script>

<Badge variant={visibility === 'public' ? 'secondary' : 'outline'} title={info.description}><Icon /> {info.label}</Badge>
