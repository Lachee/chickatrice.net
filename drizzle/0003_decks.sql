CREATE TABLE `chickatrice_decks` (
	`deck_id` int unsigned NOT NULL,
	`account_id` int unsigned NOT NULL,
	`slug` varchar(16) NOT NULL,
	`visibility` enum('private','unlisted','public') NOT NULL DEFAULT 'private',
	`source_url` varchar(512),
	`created_at` datetime NOT NULL,
	CONSTRAINT `chickatrice_decks_deck_id` PRIMARY KEY(`deck_id`),
	CONSTRAINT `chickatrice_decks_slug` UNIQUE(`slug`)
);
--> statement-breakpoint
CREATE INDEX `chickatrice_decks_account` ON `chickatrice_decks` (`account_id`);