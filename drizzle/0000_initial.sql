CREATE TABLE `chickatrice_identities` (
	`id` serial AUTO_INCREMENT NOT NULL,
	`account_id` int unsigned NOT NULL,
	`provider` varchar(64) NOT NULL,
	`subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
	`username` varchar(255),
	`email` varchar(255),
	`image` varchar(2048),
	`created_at` datetime NOT NULL,
	`last_login_at` datetime NOT NULL,
	CONSTRAINT `chickatrice_identities_id` PRIMARY KEY(`id`),
	CONSTRAINT `chickatrice_identities_provider_subject` UNIQUE(`provider`,`subject`),
	CONSTRAINT `chickatrice_identities_account_provider` UNIQUE(`account_id`,`provider`)
);
--> statement-breakpoint
CREATE TABLE `chickatrice_sessions` (
	`id` varchar(64) NOT NULL,
	`account_id` int unsigned NOT NULL,
	`created_at` datetime NOT NULL,
	`expires_at` datetime NOT NULL,
	`method` varchar(64) NOT NULL,
	`user_agent` varchar(255),
	CONSTRAINT `chickatrice_sessions_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE INDEX `chickatrice_sessions_account` ON `chickatrice_sessions` (`account_id`);--> statement-breakpoint
CREATE INDEX `chickatrice_sessions_expires` ON `chickatrice_sessions` (`expires_at`);