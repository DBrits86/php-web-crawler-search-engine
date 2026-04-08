-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 08, 2026 at 10:41 PM
-- Server version: 8.0.45-cll-lve
-- PHP Version: 8.4.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `capeunde_projects`
--

-- --------------------------------------------------------

--
-- Table structure for table `crawler_stats`
--

CREATE TABLE `crawler_stats` (
  `id` int NOT NULL DEFAULT '1',
  `scanned_sites` int NOT NULL DEFAULT '0',
  `total_words` int NOT NULL DEFAULT '0',
  `total_images` int NOT NULL DEFAULT '0',
  `total_videos` int NOT NULL DEFAULT '0',
  `total_links` int NOT NULL DEFAULT '0',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `domains`
--

CREATE TABLE `domains` (
  `id` int NOT NULL,
  `protocol` varchar(8) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'http://',
  `domain` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `index_count` int NOT NULL DEFAULT '0',
  `status` varchar(8) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `headers`
--

CREATE TABLE `headers` (
  `id` int NOT NULL,
  `url_id` int NOT NULL,
  `text` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `tag` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `indexed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `images`
--

CREATE TABLE `images` (
  `id` int NOT NULL,
  `url_id` int NOT NULL,
  `img_src` varchar(786) COLLATE utf8mb4_general_ci NOT NULL,
  `img_alt` text COLLATE utf8mb4_general_ci NOT NULL,
  `img_header` text COLLATE utf8mb4_general_ci NOT NULL,
  `indexed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `links`
--

CREATE TABLE `links` (
  `id` int NOT NULL,
  `protocol` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'http://',
  `domain` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `path` varchar(786) COLLATE utf8mb4_general_ci NOT NULL,
  `link_count` int NOT NULL DEFAULT '0',
  `img_count` int NOT NULL DEFAULT '0',
  `video_count` int NOT NULL DEFAULT '0',
  `indexed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `index_count` int NOT NULL DEFAULT '0',
  `status` varchar(8) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `links`
--

INSERT INTO `links` (`id`, `protocol`, `domain`, `path`, `link_count`, `img_count`, `video_count`, `indexed`, `index_count`, `status`) VALUES
(0, 'http://', 'post.craigslist.org', '/', 0, 0, 0, '2026-04-07 22:26:38', 0, 'Active'),
(1, 'https://', 'accounts.craigslist.org', '/login/home', 0, 0, 0, '2026-04-08 05:36:27', 1, 'Active'),
(2, 'http://', 'accounts.craigslist.org', '/', 0, 0, 0, '2026-04-07 22:26:38', 0, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `videos`
--

CREATE TABLE `videos` (
  `id` int NOT NULL,
  `url_id` int NOT NULL,
  `video_src` varchar(786) COLLATE utf8mb4_general_ci NOT NULL,
  `video_alt` text COLLATE utf8mb4_general_ci NOT NULL,
  `video_header` text COLLATE utf8mb4_general_ci NOT NULL,
  `indexed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `words`
--

CREATE TABLE `words` (
  `id` int NOT NULL,
  `word` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `indexed` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `word_image`
--

CREATE TABLE `word_image` (
  `id` int NOT NULL,
  `img_id` int NOT NULL,
  `word_id` int NOT NULL,
  `indexed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `word_url`
--

CREATE TABLE `word_url` (
  `id` int NOT NULL,
  `url_id` int NOT NULL,
  `word_id` int NOT NULL,
  `indexed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `word_video`
--

CREATE TABLE `word_video` (
  `id` int NOT NULL,
  `video_id` int NOT NULL,
  `word_id` int NOT NULL,
  `indexed` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `domains`
--
ALTER TABLE `domains`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_domain` (`domain`);

--
-- Indexes for table `headers`
--
ALTER TABLE `headers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_header` (`url_id`,`text`,`tag`);

--
-- Indexes for table `images`
--
ALTER TABLE `images`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `image_src_unique` (`img_src`(150),`url_id`),
  ADD UNIQUE KEY `unique_image_per_url` (`url_id`,`img_src`(150));

--
-- Indexes for table `links`
--
ALTER TABLE `links`
  ADD UNIQUE KEY `link_unique` (`id`,`path`(255)),
  ADD UNIQUE KEY `unique_domain_path` (`domain`,`path`(255));

--
-- Indexes for table `videos`
--
ALTER TABLE `videos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `video_src_unique` (`video_src`(255),`url_id`);

--
-- Indexes for table `words`
--
ALTER TABLE `words`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `word_unique` (`word`);

--
-- Indexes for table `word_image`
--
ALTER TABLE `word_image`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `word_image_unique` (`word_id`,`img_id`);

--
-- Indexes for table `word_url`
--
ALTER TABLE `word_url`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `word_url_unique` (`word_id`,`url_id`);

--
-- Indexes for table `word_video`
--
ALTER TABLE `word_video`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `word_video_unique` (`word_id`,`video_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `domains`
--
ALTER TABLE `domains`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `headers`
--
ALTER TABLE `headers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `images`
--
ALTER TABLE `images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `links`
--
ALTER TABLE `links`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `videos`
--
ALTER TABLE `videos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `words`
--
ALTER TABLE `words`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `word_image`
--
ALTER TABLE `word_image`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `word_url`
--
ALTER TABLE `word_url`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `word_video`
--
ALTER TABLE `word_video`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
