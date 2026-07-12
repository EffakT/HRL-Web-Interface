Table 	Create table
failed_jobs 	CREATE TABLE `failed_jobs` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT,
 `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
 `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
 `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
 `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
 `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
jobs 	CREATE TABLE `jobs` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT,
 `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
 `attempts` tinyint unsigned NOT NULL,
 `reserved_at` int unsigned DEFAULT NULL,
 `available_at` int unsigned NOT NULL,
 `created_at` int unsigned NOT NULL,
 PRIMARY KEY (`id`),
 KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
lap_times 	CREATE TABLE `lap_times` (
 `id` int unsigned NOT NULL AUTO_INCREMENT,
 `server_id` int unsigned NOT NULL,
 `map_id` int unsigned NOT NULL,
 `player_id` int unsigned NOT NULL,
 `time` decimal(10,2) NOT NULL,
 `created_at` date DEFAULT NULL,
 `updated_at` date DEFAULT NULL,
 PRIMARY KEY (`id`),
 KEY `lap_times_server_id_foreign` (`server_id`),
 KEY `lap_times_map_id_foreign` (`map_id`),
 KEY `lap_times_player_id_foreign` (`player_id`),
 CONSTRAINT `lap_times_map_id_foreign` FOREIGN KEY (`map_id`) REFERENCES `maps` (`id`),
 CONSTRAINT `lap_times_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
 CONSTRAINT `lap_times_server_id_foreign` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1942 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
lap_time_splits 	CREATE TABLE `lap_time_splits` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT,
 `created_at` timestamp NULL DEFAULT NULL,
 `updated_at` timestamp NULL DEFAULT NULL,
 `lap_time_id` int unsigned NOT NULL,
 `checkpoint_id` int NOT NULL,
 `duration` double(8,2) NOT NULL,
 `start_time` double(8,2) NOT NULL,
 `end_time` double(8,2) NOT NULL,
 PRIMARY KEY (`id`),
 KEY `lap_time_splits_lap_time_id_foreign` (`lap_time_id`),
 CONSTRAINT `lap_time_splits_lap_time_id_foreign` FOREIGN KEY (`lap_time_id`) REFERENCES `lap_times` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=440 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
logs 	CREATE TABLE `logs` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT,
 `instance` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `channel` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `level` enum('DEBUG','INFO','NOTICE','WARNING','ERROR','CRITICAL','ALERT','EMERGENCY') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INFO',
 `message` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
 `context` text COLLATE utf8mb4_unicode_ci NOT NULL,
 `created_at` timestamp NULL DEFAULT NULL,
 `updated_at` timestamp NULL DEFAULT NULL,
 PRIMARY KEY (`id`),
 KEY `logs_instance_index` (`instance`),
 KEY `logs_channel_index` (`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
maps 	CREATE TABLE `maps` (
 `id` int unsigned NOT NULL AUTO_INCREMENT,
 `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `created_at` timestamp NULL DEFAULT NULL,
 `updated_at` timestamp NULL DEFAULT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
migrations 	CREATE TABLE `migrations` (
 `id` int unsigned NOT NULL AUTO_INCREMENT,
 `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `batch` int NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
password_resets 	CREATE TABLE `password_resets` (
 `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `created_at` timestamp NULL DEFAULT NULL,
 KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
players 	CREATE TABLE `players` (
 `id` int unsigned NOT NULL AUTO_INCREMENT,
 `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `created_at` timestamp NULL DEFAULT NULL,
 `updated_at` timestamp NULL DEFAULT NULL,
 `user_id` int unsigned DEFAULT NULL,
 PRIMARY KEY (`id`),
 KEY `players_user_id_foreign` (`user_id`),
 CONSTRAINT `players_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=823 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
players_servers 	CREATE TABLE `players_servers` (
 `id` int unsigned NOT NULL AUTO_INCREMENT,
 `player_id` int unsigned NOT NULL,
 `server_id` int unsigned NOT NULL,
 `created_at` timestamp NULL DEFAULT NULL,
 `updated_at` timestamp NULL DEFAULT NULL,
 PRIMARY KEY (`id`),
 KEY `players_servers_server_id_foreign` (`server_id`),
 KEY `players_servers_player_id_foreign` (`player_id`),
 CONSTRAINT `players_servers_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
 CONSTRAINT `players_servers_server_id_foreign` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1172 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
servers 	CREATE TABLE `servers` (
 `id` int unsigned NOT NULL AUTO_INCREMENT,
 `ip` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `port` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
 `created_at` timestamp NULL DEFAULT NULL,
 `updated_at` timestamp NULL DEFAULT NULL,
 `deleted_at` timestamp NULL DEFAULT NULL,
 `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PC',
 `notify_outage` tinyint(1) NOT NULL DEFAULT '0',
 `notify_outage_last` datetime DEFAULT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
servers_maps 	CREATE TABLE `servers_maps` (
 `id` int unsigned NOT NULL AUTO_INCREMENT,
 `server_id` int unsigned NOT NULL,
 `map_id` int unsigned NOT NULL,
 `created_at` timestamp NULL DEFAULT NULL,
 `updated_at` timestamp NULL DEFAULT NULL,
 PRIMARY KEY (`id`),
 KEY `servers_maps_server_id_foreign` (`server_id`),
 KEY `servers_maps_map_id_foreign` (`map_id`),
 CONSTRAINT `servers_maps_map_id_foreign` FOREIGN KEY (`map_id`) REFERENCES `maps` (`id`),
 CONSTRAINT `servers_maps_server_id_foreign` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=249 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
sessions 	CREATE TABLE `sessions` (
 `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `user_id` int unsigned DEFAULT NULL,
 `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
 `user_agent` text COLLATE utf8mb4_unicode_ci,
 `payload` text COLLATE utf8mb4_unicode_ci NOT NULL,
 `last_activity` int NOT NULL,
 UNIQUE KEY `sessions_id_unique` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
users 	CREATE TABLE `users` (
 `id` int unsigned NOT NULL AUTO_INCREMENT,
 `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `email_verified_at` timestamp NULL DEFAULT NULL,
 `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `api_token` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
 `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
 `created_at` timestamp NULL DEFAULT NULL,
 `updated_at` timestamp NULL DEFAULT NULL,
 PRIMARY KEY (`id`),
 UNIQUE KEY `users_email_unique` (`email`),
 UNIQUE KEY `users_api_token_unique` (`api_token`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
users_players 	CREATE TABLE `users_players` (
 `id` int unsigned NOT NULL AUTO_INCREMENT,
 `user_id` int unsigned NOT NULL,
 `player_id` int unsigned NOT NULL,
 `claim_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `claimed_at` timestamp NULL DEFAULT NULL,
 `created_at` timestamp NULL DEFAULT NULL,
 `updated_at` timestamp NULL DEFAULT NULL,
 `deleted_at` timestamp NULL DEFAULT NULL,
 PRIMARY KEY (`id`),
 KEY `users_players_user_id_foreign` (`user_id`),
 KEY `users_players_player_id_foreign` (`player_id`),
 CONSTRAINT `users_players_player_id_foreign` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`),
 CONSTRAINT `users_players_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
users_servers 	CREATE TABLE `users_servers` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT,
 `user_id` int unsigned NOT NULL,
 `server_id` int unsigned NOT NULL,
 `claim_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 `claimed_at` timestamp NULL DEFAULT NULL,
 `created_at` timestamp NULL DEFAULT NULL,
 `updated_at` timestamp NULL DEFAULT NULL,
 `deleted_at` timestamp NULL DEFAULT NULL,
 PRIMARY KEY (`id`),
 KEY `users_servers_user_id_foreign` (`user_id`),
 KEY `users_servers_server_id_foreign` (`server_id`),
 CONSTRAINT `users_servers_server_id_foreign` FOREIGN KEY (`server_id`) REFERENCES `servers` (`id`),
 CONSTRAINT `users_servers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci