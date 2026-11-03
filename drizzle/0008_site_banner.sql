CREATE TABLE `chickatrice_site_banner` (
	`id` int unsigned NOT NULL,
	`message` text NOT NULL,
	`level` enum('info','warning','outage') NOT NULL,
	`expires_at` datetime,
	`updated_by` int unsigned NOT NULL,
	`updated_at` datetime NOT NULL,
	CONSTRAINT `chickatrice_site_banner_id` PRIMARY KEY(`id`)
);
