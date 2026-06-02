-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: sql213.ezyro.com
-- Üretim Zamanı: 02 Haz 2026, 16:49:40
-- Sunucu sürümü: 11.4.12-MariaDB
-- PHP Sürümü: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `ezyro_41408315_plus`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `app_settings`
--

CREATE TABLE `app_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `app_settings`
--

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('feed_hide_private_questions', '1', NULL),
('site_name', 'geminy.me', NULL),
('version', '5.0', '2026-06-02 12:05:18');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `ip_hash` varchar(64) NOT NULL,
  `username` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `ip_hash`, `username`, `created_at`) VALUES
(1, '4f3ffa29c4d259cb4a92536b2643e9196f0a898770a7324f8df6c0ea674c6995', 'mahmutamagola', '2026-06-01 23:36:26'),
(2, 'fe33e478d02110dc0e4467e0338bc498e7dc8d37d985c4675989a846d15dec9b', 'mahmuttuncer', '2026-06-02 08:23:37'),
(3, 'fe33e478d02110dc0e4467e0338bc498e7dc8d37d985c4675989a846d15dec9b', 'mahmutdreams@gmail.com', '2026-06-02 08:26:56'),
(4, 'f4e0665d9a041d1c1c66a83d3c1c04a95c5d5a5e2924b9dfab4a44e6085f3e62', 'mahmutamagola', '2026-06-02 11:56:08');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `messages`
--

CREATE TABLE `messages` (
  `id` char(36) NOT NULL,
  `to_user` varchar(30) NOT NULL,
  `text` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `messages`
--

INSERT INTO `messages` (`id`, `to_user`, `text`, `created_at`) VALUES
('6a1e7a48de08c8.81435231', 'mahmuttuncer', 'Demo', '2026-06-01 23:38:01'),
('6a1e7ac63a12f3.78662189', 'selinsekerr', 'Selinimm nasılsın', '2026-06-01 23:40:06'),
('6a1e81375fbbe3.17021255', 'mahmuttuncer', 'Mahmut abi gotunu skmm senin emii', '2026-06-02 00:07:35'),
('6a1e81aa044826.66671505', 'mahmuttuncer', 'Mahmut gelirken biraları unutma canımm', '2026-06-02 00:09:30'),
('6a1ef5cbb199d8.45304168', 'selinsekerss', 'Naber canimm', '2026-06-02 08:24:59'),
('6a1f2888b36cf4.72114164', 'mahmuttuncer', 'Selamss canımm', '2026-06-02 12:01:28');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `message_likes`
--

CREATE TABLE `message_likes` (
  `id` int(10) UNSIGNED NOT NULL,
  `message_id` char(36) NOT NULL,
  `ip_hash` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `message_likes`
--

INSERT INTO `message_likes` (`id`, `message_id`, `ip_hash`, `created_at`) VALUES
(1, '6a1e7a48de08c8.81435231', '4f3ffa29c4d259cb4a92536b2643e9196f0a898770a7324f8df6c0ea674c6995', '2026-06-01 23:38:23'),
(2, '6a1e7ac63a12f3.78662189', '4f3ffa29c4d259cb4a92536b2643e9196f0a898770a7324f8df6c0ea674c6995', '2026-06-01 23:40:49'),
(3, '6a1e81aa044826.66671505', 'fe33e478d02110dc0e4467e0338bc498e7dc8d37d985c4675989a846d15dec9b', '2026-06-02 08:30:18'),
(4, '6a1e81375fbbe3.17021255', 'fe33e478d02110dc0e4467e0338bc498e7dc8d37d985c4675989a846d15dec9b', '2026-06-02 08:30:19'),
(5, '6a1ef5cbb199d8.45304168', 'fe33e478d02110dc0e4467e0338bc498e7dc8d37d985c4675989a846d15dec9b', '2026-06-02 08:32:08'),
(6, '6a1f2888b36cf4.72114164', 'f4e0665d9a041d1c1c66a83d3c1c04a95c5d5a5e2924b9dfab4a44e6085f3e62', '2026-06-02 12:01:32'),
(7, '6a1e7ac63a12f3.78662189', 'f4e0665d9a041d1c1c66a83d3c1c04a95c5d5a5e2924b9dfab4a44e6085f3e62', '2026-06-02 12:02:34');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(120) NOT NULL,
  `token` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 'rhodes0515gq@gmail.com', 'd1375358c3e91321b8fb665930ed154b67903a8755bf9d535dadb5d4eec17326', '2026-06-01 23:51:06', '0000-00-00 00:00:00', '2026-06-01 23:36:06'),
(2, 'rhodes0515gq@gmail.com', '4bd214d5a814ad54cdea49ab14fa7e8156f99f59f8c3324c9161df10c9baaed4', '2026-06-02 00:03:16', '0000-00-00 00:00:00', '2026-06-01 23:48:16'),
(3, 'rhodes0515gq@gmail.com', 'a822cdeeb49a7f30f5295715073b30e104d4abec4e8bf724452611ed549ab2b3', '2026-06-02 08:45:36', '0000-00-00 00:00:00', '2026-06-02 08:30:36'),
(4, 'rhodes0515gq@gmail.com', 'f1010a09f9049885e657ec5902987189a8d9483b4981f6e737bc08b464c71e61', '2026-06-02 12:10:18', '0000-00-00 00:00:00', '2026-06-02 11:55:18'),
(5, 'rhodes0515gq@gmail.com', '9dffc04ff93dcdfbe6eccde36813753a0478c8f5ab4812065527cb8d399cfeac', '2026-06-02 17:00:05', NULL, '2026-06-02 13:45:05');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `privacy_messages`
--

CREATE TABLE `privacy_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `from_user` varchar(30) NOT NULL,
  `to_user` varchar(30) NOT NULL,
  `text` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `privacy_messages`
--

INSERT INTO `privacy_messages` (`id`, `from_user`, `to_user`, `text`, `is_read`, `created_at`) VALUES
(1, 'selinsekerss', 'mahmuttuncer', 'Selam', 1, '2026-06-02 08:24:35'),
(2, 'mahmuttuncer', 'selinsekerss', 'Selam sanada', 0, '2026-06-02 12:00:10'),
(3, 'sarisinsss', 'mahmuttuncer', 'Naber ak', 1, '2026-06-02 12:01:40'),
(4, 'sarisinsss', 'mahmuttuncer', 'Naber ak', 1, '2026-06-02 12:01:45'),
(5, 'sarisinsss', 'mahmuttuncer', 'Naber ak', 1, '2026-06-02 12:01:45'),
(6, 'sarisinsss', 'mahmuttuncer', 'Naber ak', 1, '2026-06-02 12:01:45'),
(7, 'sarisinsss', 'mahmuttuncer', 'Naber ak', 1, '2026-06-02 12:01:45'),
(8, 'mahmuttuncer', 'sarisinsss', 'Yuhha yavaş aq', 1, '2026-06-02 12:01:56'),
(9, 'sarisinsss', 'mahmuttuncer', 'Anlıyorum 🍻', 1, '2026-06-02 12:02:08'),
(10, 'mahmuttuncer', 'sarisinsss', 'Selamm', 0, '2026-06-02 13:46:21');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `replies`
--

CREATE TABLE `replies` (
  `id` int(10) UNSIGNED NOT NULL,
  `message_id` char(36) NOT NULL,
  `text` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `smtp_accounts`
--

CREATE TABLE `smtp_accounts` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `host` varchar(180) NOT NULL,
  `port` int(10) UNSIGNED NOT NULL DEFAULT 587,
  `encryption` enum('tls','ssl','none') NOT NULL DEFAULT 'tls',
  `username` varchar(180) NOT NULL,
  `from_email` varchar(180) DEFAULT NULL,
  `from_name` varchar(120) DEFAULT 'geminy.me',
  `priority` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `smtp_accounts`
--

INSERT INTO `smtp_accounts` (`id`, `name`, `host`, `port`, `encryption`, `username`, `from_email`, `from_name`, `priority`, `is_active`, `last_error`, `created_at`) VALUES
(1, 'Demo', 'smtp.mailersend.net', 587, 'tls', 'MS_6bUZbB@test-51ndgwv971dlzqx8.mlsender.net', 'MS_6bUZbB@test-51ndgwv971dlzqx8.mlsender.net', 'geminy.me', 10, 1, NULL, '2026-06-01 23:19:51');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `two_factor_codes`
--

CREATE TABLE `two_factor_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(30) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `purpose` enum('login','enable') NOT NULL DEFAULT 'login',
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(30) NOT NULL,
  `display_name` varchar(50) DEFAULT NULL,
  `bio` varchar(150) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `instagram` varchar(60) DEFAULT NULL,
  `tiktok` varchar(60) DEFAULT NULL,
  `twitter` varchar(60) DEFAULT NULL,
  `pinterest` varchar(60) DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `song_title` varchar(120) DEFAULT NULL,
  `song_artist` varchar(80) DEFAULT NULL,
  `song_cover` varchar(500) DEFAULT NULL,
  `song_url` varchar(500) DEFAULT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT 0,
  `two_fa_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `two_fa_method` enum('email','totp') NOT NULL DEFAULT 'email',
  `totp_secret` varchar(64) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `username`, `display_name`, `bio`, `avatar_url`, `email`, `password_hash`, `instagram`, `tiktok`, `twitter`, `pinterest`, `website`, `song_title`, `song_artist`, `song_cover`, `song_url`, `is_private`, `two_fa_enabled`, `two_fa_method`, `totp_secret`, `updated_at`, `created_at`) VALUES
(1, 'ilhankaragumruk', 'İlhan K.', 'Polis beni arıyor 👮', NULL, NULL, NULL, 'ara', 'mrk', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'email', NULL, NULL, '2026-05-29 17:22:24'),
(2, 'selena', 'Selena', 'Sihirli dünyanın günlüğü ✨', NULL, NULL, NULL, 'selenay', 'selany', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'email', NULL, NULL, '2026-05-29 17:22:24'),
(3, 'tommy', 'Tommy', 'Vice City rigo 🎮', NULL, NULL, NULL, 'barisultss0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'email', NULL, NULL, '2026-05-29 17:22:24'),
(4, 'mahmutsenturk', 'Mahmutt', NULL, NULL, 'rhodes0515gq@gmail.com', '$2y$10$R4B/KgV0lTAVMnEbB4jtH.5kiV/5ZxdgQvkzwH2Y8EjaOCN4ti9Vy', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'email', NULL, NULL, '2026-05-29 17:24:11'),
(5, 'mahmutdreams', 'Mahmut', NULL, NULL, 'mahmutdreams@gmail.com', '$2y$10$7qY90wpDUC6zKCz4bUCicOJ0yl7NC7HWxBHFuYmJfJfrdtX3DYLS2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'email', NULL, NULL, '2026-05-29 17:35:14'),
(6, 'selindmrly', 'Selinss', NULL, 'https://64.media.tumblr.com/be522c2a998fbe938d0a713aed481819/tumblr_prs3379DOc1rkrpvd_1280.jpg', 'selinsdmrly@gmail.com', '$2y$10$5thnq40sMUeHJHJ4x62hS.Ay9vu6Xd4FjUWrKnrfmV1iCRY89FD/K', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'email', NULL, NULL, '2026-05-30 08:53:28'),
(7, 'selinsekerr', 'Selinss❤️', 'Purple 💯', 'https://64.media.tumblr.com/be522c2a998fbe938d0a713aed481819/tumblr_prs3379DOc1rkrpvd_1280.jpg', 'selinsekerr@gmail.com', '$2y$10$7UqzvGoBfiR7/JJNmSWxk.YoB/vAr4c8n7lbs54sx3UoUndus2ikK', 'demo', 'demo', 'Demo', 'Demo', NULL, NULL, NULL, NULL, NULL, 0, 0, 'email', NULL, NULL, '2026-05-30 09:40:01'),
(8, 'mahmuttuncer', 'mahmuttoo', 'İf Your Heart', 'https://64.media.tumblr.com/be522c2a998fbe938d0a713aed481819/tumblr_prs3379DOc1rkrpvd_1280.jpg', 'mahmuttuncer@gmail.com', '$2y$10$ZJo.i9CpTz7cchwjJpdn0.9wSDxQzCsTl5YALRXuvMhuRfl4zhGiS', 'demo', 'demo', 'demo', 'demo', 'https://geminyask.unaux.com/', 'Summers', 'Calvin Harris', NULL, 'https://youtu.be/EgqUJOudrcM?si=lISGv_4CQwvjVFzA', 1, 0, 'email', NULL, '2026-06-02 12:02:58', '2026-05-30 09:45:33'),
(9, 'selinsekerss', 'esmerimm', NULL, NULL, 'selinimmmsensin@gmail.com', '$2y$10$WTUVZD0cyT.TMjDBnUrBr.beTBB8TbHDTIudASF0.8qjVoMT1AFzq', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'email', NULL, NULL, '2026-06-02 08:24:18'),
(10, 'sarisinsss', 'Sarışınlar vipss', NULL, NULL, 'sarissono@mail.com', '$2y$10$nkLkQBPna8JFYXnjJ2D1k.S9qD2mPK.A70YIZGSSir4v.BcMNB9Ku', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 'email', NULL, '2026-06-02 12:06:35', '2014-06-02 12:00:54');

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Tablo için indeksler `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip` (`ip_hash`),
  ADD KEY `idx_time` (`created_at`);

--
-- Tablo için indeksler `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_to_user` (`to_user`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Tablo için indeksler `message_likes`
--
ALTER TABLE `message_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_like` (`message_id`,`ip_hash`),
  ADD KEY `idx_like_msg` (`message_id`);

--
-- Tablo için indeksler `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_email` (`email`);

--
-- Tablo için indeksler `privacy_messages`
--
ALTER TABLE `privacy_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conv` (`from_user`,`to_user`),
  ADD KEY `idx_to` (`to_user`),
  ADD KEY `idx_time` (`created_at`);

--
-- Tablo için indeksler `replies`
--
ALTER TABLE `replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_message_id` (`message_id`);

--
-- Tablo için indeksler `smtp_accounts`
--
ALTER TABLE `smtp_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_smtp_active_priority` (`is_active`,`priority`);

--
-- Tablo için indeksler `two_factor_codes`
--
ALTER TABLE `two_factor_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_purpose` (`username`,`purpose`,`expires_at`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_username` (`username`),
  ADD UNIQUE KEY `uq_email` (`email`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `message_likes`
--
ALTER TABLE `message_likes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Tablo için AUTO_INCREMENT değeri `privacy_messages`
--
ALTER TABLE `privacy_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Tablo için AUTO_INCREMENT değeri `replies`
--
ALTER TABLE `replies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `smtp_accounts`
--
ALTER TABLE `smtp_accounts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `two_factor_codes`
--
ALTER TABLE `two_factor_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_msg_user` FOREIGN KEY (`to_user`) REFERENCES `users` (`username`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `message_likes`
--
ALTER TABLE `message_likes`
  ADD CONSTRAINT `fk_like_msg` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `privacy_messages`
--
ALTER TABLE `privacy_messages`
  ADD CONSTRAINT `fk_pm_from` FOREIGN KEY (`from_user`) REFERENCES `users` (`username`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pm_to` FOREIGN KEY (`to_user`) REFERENCES `users` (`username`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Tablo kısıtlamaları `replies`
--
ALTER TABLE `replies`
  ADD CONSTRAINT `fk_reply_msg` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
