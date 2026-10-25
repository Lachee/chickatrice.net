-- Carries a database over from the old PHP site (`master` / `v1.0.0`).
--
-- That site kept its own `chickatrice_users` (Discord snowflake -> Cockatrice
-- account, plus deck privacy, deck/replay limits and API keys this site has no
-- use for), a `chickatrice_users_backup` of every account's original email, and
-- a `chickatrice_cards` cache from Scryfall. It also hung triggers and a foreign
-- key off Servatrice's `cockatrice_users`.
--
-- Here, the Discord links become `discord` identities (the provider id used in
-- config.example.yaml, whose subject is Discord's user id, the old snowflake),
-- and the old tables are renamed to `legacy_*`, out of the `chickatrice_*`
-- namespace drizzle-kit manages, for an admin to drop once they're sure.
--
-- On a fresh database none of that exists: every step checks first, so this is
-- a no-op there. Steps that may not apply are built as strings and run with
-- PREPARE, since MySQL has no IF outside a stored program; the statements of a
-- migration share one connection, so the session variables carry between them.

-- The old site's triggers. `User Always Inactive` forced every new account to
-- active = 0, overriding the accounts this site creates as active; the other two
-- copied emails into `chickatrice_users_backup`, and would fail every insert and
-- update on `cockatrice_users` once that table is renamed. Who may register in
-- the client is Servatrice's business now ([registration] in servatrice.ini).
DROP TRIGGER IF EXISTS `User Always Inactive`;--> statement-breakpoint
DROP TRIGGER IF EXISTS `User Insert`;--> statement-breakpoint
DROP TRIGGER IF EXISTS `User Updated`;--> statement-breakpoint

-- No foreign keys into Servatrice's tables, legacy or not.
SET @legacy_sql = IF(
	(SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
		WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chickatrice_users'
		AND CONSTRAINT_NAME = 'FK_chickatrice_users_cockatrice_users' AND CONSTRAINT_TYPE = 'FOREIGN KEY') > 0,
	'ALTER TABLE `chickatrice_users` DROP FOREIGN KEY `FK_chickatrice_users_cockatrice_users`',
	'DO 0'
);--> statement-breakpoint
PREPARE legacy_stmt FROM @legacy_sql;--> statement-breakpoint
EXECUTE legacy_stmt;--> statement-breakpoint
DEALLOCATE PREPARE legacy_stmt;--> statement-breakpoint

-- `last_seen` and `last_sync` are TIMESTAMPs, which read back in the session's
-- time zone; our columns are UTC.
SET @legacy_tz = @@session.time_zone;--> statement-breakpoint
SET time_zone = '+00:00';--> statement-breakpoint

-- Unlinked accounts have snowflake 0. The old site recorded no link date, so the
-- earliest thing it knew of them stands in; the display fields are left empty
-- for the next sign-in to fill. Its `email` was the account's, not Discord's.
-- IGNORE lets the unique keys settle any account or snowflake linked twice:
-- the most recently synced row wins.
SET @legacy_sql = IF(
	(SELECT COUNT(*) FROM information_schema.TABLES
		WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chickatrice_users') > 0,
	'INSERT IGNORE INTO `chickatrice_identities`
		(`account_id`, `provider`, `subject`, `created_at`, `last_login_at`)
	SELECT `cockatrice_id`, ''discord'', CAST(`snowflake` AS CHAR),
		LEAST(`last_sync`, IFNULL(`last_seen`, `last_sync`)),
		IFNULL(`last_seen`, `last_sync`)
	FROM `chickatrice_users`
	WHERE `snowflake` > 0
	ORDER BY `last_sync` DESC, `id` DESC',
	'DO 0'
);--> statement-breakpoint
PREPARE legacy_stmt FROM @legacy_sql;--> statement-breakpoint
EXECUTE legacy_stmt;--> statement-breakpoint
DEALLOCATE PREPARE legacy_stmt;--> statement-breakpoint

SET time_zone = @legacy_tz;--> statement-breakpoint

-- Whichever of the old tables are here, in one RENAME.
SET @legacy_sql = (
	SELECT IFNULL(
		CONCAT('RENAME TABLE ', GROUP_CONCAT(CONCAT('`', TABLE_NAME, '` TO `legacy_', TABLE_NAME, '`') SEPARATOR ', ')),
		'DO 0'
	)
	FROM information_schema.TABLES
	WHERE TABLE_SCHEMA = DATABASE()
	AND TABLE_NAME IN ('chickatrice_users', 'chickatrice_users_backup', 'chickatrice_cards')
);--> statement-breakpoint
PREPARE legacy_stmt FROM @legacy_sql;--> statement-breakpoint
EXECUTE legacy_stmt;--> statement-breakpoint
DEALLOCATE PREPARE legacy_stmt;
