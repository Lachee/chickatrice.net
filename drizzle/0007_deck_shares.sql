CREATE TABLE `chickatrice_deck_shares` (
	`deck_id` int unsigned NOT NULL,
	`share_id` int unsigned NOT NULL,
	`content_hash` varchar(64) NOT NULL,
	`created_at` datetime NOT NULL,
	CONSTRAINT `chickatrice_deck_shares_deck_id` PRIMARY KEY(`deck_id`)
);
