import { defineConfig } from 'drizzle-kit';

if (!process.env.DATABASE_URL) throw new Error('DATABASE_URL is not set');

export default defineConfig({
	// Only this site's tables. Servatrice's live in `db/cockatrice.ts`, outside
	// this glob, so drizzle-kit never generates SQL that touches them.
	schema: './src/lib/server/db/*.schema.ts',
	dialect: 'mysql',
	dbCredentials: { url: process.env.DATABASE_URL },
	// Belt and braces for `push`/`studio`: never look at Servatrice's tables.
	tablesFilter: ['chickatrice_*'],
	// Must match `migrationsTable` in src/lib/server/db/migrate.ts.
	migrations: { table: 'chickatrice_migrations' },
	verbose: true,
	strict: true
});
