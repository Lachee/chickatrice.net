import { defineEnvVars } from '@sveltejs/kit/env';

export const variables = defineEnvVars({
	CONFIG_FILE: {
		description: 'Path to the YAML config file. Defaults to `config.yaml` in the working directory.',
		schema: (value) => value || undefined
	}
});
