CREATE TABLE `chickatrice_name_changes` (
	`id` serial AUTO_INCREMENT NOT NULL,
	`account_id` int unsigned NOT NULL,
	`old_name` varchar(35),
	`new_name` varchar(35) NOT NULL,
	`source` enum('signup','user','staff') NOT NULL,
	`changed_by` int unsigned,
	`created_at` datetime NOT NULL,
	CONSTRAINT `chickatrice_name_changes_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE INDEX `chickatrice_name_changes_account` ON `chickatrice_name_changes` (`account_id`,`id`);--> statement-breakpoint
CREATE INDEX `chickatrice_name_changes_created` ON `chickatrice_name_changes` (`created_at`);