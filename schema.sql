-- MySQL dump 10.13  Distrib 5.6.32-78.0, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: tj
-- ------------------------------------------------------
-- Server version	5.6.32-78.0-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `twitter_lists`
--

DROP TABLE IF EXISTS `twitter_lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `twitter_lists` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `twitter_media`
--

DROP TABLE IF EXISTS `twitter_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `twitter_media` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `tweet_id` bigint(20) NOT NULL,
  `type` tinyint(2) NOT NULL DEFAULT '0',
  `service` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thumbnail_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `thumbnail_width` mediumint(9) NOT NULL DEFAULT '0',
  `thumbnail_height` mediumint(9) NOT NULL DEFAULT '0',
  `ratio` decimal(10,4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tweet_id` (`tweet_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4462452 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `twitter_tweets`
--

DROP TABLE IF EXISTS `twitter_tweets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `twitter_tweets` (
  `id` bigint(20) NOT NULL,
  `created_at` int(11) NOT NULL,
  `date` int(11) NOT NULL,
  `text` text COLLATE utf8mb4_unicode_ci,
  `parsed_text` mediumtext COLLATE utf8mb4_unicode_ci,
  `tweople_id` bigint(20) unsigned NOT NULL,
  `geo_lat` decimal(10,6) DEFAULT NULL,
  `geo_lon` decimal(10,6) DEFAULT NULL,
  `has_media` tinyint(2) NOT NULL DEFAULT '0',
  `retweet_count` int(11) unsigned DEFAULT '0',
  `favorite_count` int(11) unsigned DEFAULT '0',
  `counters_last_update` int(11) unsigned DEFAULT '0',
  `lang` varchar(16) CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL,
  `quoted_status_id` bigint(20) NOT NULL DEFAULT '0',
  `deleted` tinyint(2) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `id_date_created_at_deleted` (`id`,`date`,`created_at`,`deleted`),
  KEY `counters_last_update` (`counters_last_update`,`date`,`retweet_count`),
  KEY `created_at` (`created_at`,`tweople_id`,`date`,`retweet_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `twitter_tweets_lists`
--

DROP TABLE IF EXISTS `twitter_tweets_lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `twitter_tweets_lists` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `tweet_id` bigint(20) NOT NULL,
  `list_id` smallint(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tweet_id_list_id` (`tweet_id`,`list_id`)
) ENGINE=InnoDB AUTO_INCREMENT=103868685 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `twitter_tweople`
--

DROP TABLE IF EXISTS `twitter_tweople`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `twitter_tweople` (
  `id` bigint(20) NOT NULL,
  `screen_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `profile_image_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  `followers_count` int(10) unsigned NOT NULL DEFAULT '0',
  `friends_count` int(10) unsigned NOT NULL DEFAULT '0',
  `statuses_count` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `twitter_tweople_lists`
--

DROP TABLE IF EXISTS `twitter_tweople_lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `twitter_tweople_lists` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `tweople_id` bigint(20) NOT NULL,
  `list_id` smallint(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tweople_id_list_id` (`tweople_id`,`list_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1756 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `twitter_tweople_stoplists`
--

DROP TABLE IF EXISTS `twitter_tweople_stoplists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `twitter_tweople_stoplists` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `tweople_id` bigint(20) NOT NULL,
  `list_id` smallint(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tweople_id_list_id` (`tweople_id`,`list_id`)
) ENGINE=InnoDB AUTO_INCREMENT=207 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2019-05-21 13:46:15
