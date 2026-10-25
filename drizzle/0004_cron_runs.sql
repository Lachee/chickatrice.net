CREATE TABLE `chickatrice_cron_runs` (
	`id` serial AUTO_INCREMENT NOT NULL,
	`job` varchar(64) NOT NULL,
	`started_at` datetime NOT NULL,
	`duration_ms` int unsigned NOT NULL,
	`ok` boolean NOT NULL,
	`manual` boolean NOT NULL,
	`summary` text,
	`error` text,
	CONSTRAINT `chickatrice_cron_runs_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE INDEX `chickatrice_cron_runs_job` ON `chickatrice_cron_runs` (`job`,`id`);