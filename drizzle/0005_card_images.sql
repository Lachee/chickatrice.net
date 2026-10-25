CREATE TABLE `chickatrice_card_images` (
	`key` varchar(255) NOT NULL,
	`normal` varchar(255),
	`art_crop` varchar(255),
	`artist` varchar(255),
	`uri` varchar(512),
	`fetched_at` datetime NOT NULL,
	CONSTRAINT `chickatrice_card_images_key` PRIMARY KEY(`key`)
);
