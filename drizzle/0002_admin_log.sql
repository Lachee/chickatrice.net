CREATE TABLE `chickatrice_admin_log` (
	`id` serial AUTO_INCREMENT NOT NULL,
	`actor_id` int unsigned NOT NULL,
	`actor_name` varchar(35) NOT NULL,
	`action` varchar(64) NOT NULL,
	`target` varchar(255),
	`details` text,
	`created_at` datetime NOT NULL,
	CONSTRAINT `chickatrice_admin_log_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE INDEX `chickatrice_admin_log_created` ON `chickatrice_admin_log` (`created_at`);--> statement-breakpoint
CREATE INDEX `chickatrice_admin_log_target` ON `chickatrice_admin_log` (`target`);