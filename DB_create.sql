-- MySQL dump 10.13  Distrib 8.0.42, for Linux (x86_64)
--
-- Host: localhost    Database: lost_and_found
-- ------------------------------------------------------
-- Server version	8.0.42-0ubuntu0.22.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `found_comment`
--

DROP TABLE IF EXISTS `found_comment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `found_comment` (
  `comment_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `found_listing_id` int NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`comment_id`),
  KEY `user_id` (`user_id`),
  KEY `found_listing_id` (`found_listing_id`),
  CONSTRAINT `found_comment_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `found_comment_ibfk_2` FOREIGN KEY (`found_listing_id`) REFERENCES `found_listings` (`found_listing_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `found_listings`
--

DROP TABLE IF EXISTS `found_listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `found_listings` (
  `found_listing_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `description` text,
  `location_details` varchar(255) DEFAULT NULL,
  `location_coordinates` varchar(100) DEFAULT NULL,
  `event_time` datetime DEFAULT NULL,
  `image_file_path` varchar(255) DEFAULT NULL,
  `status` enum('unclaimed','claimed') NOT NULL DEFAULT 'unclaimed',
  `comment_is_updated` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `category` varchar(50) NOT NULL,
  PRIMARY KEY (`found_listing_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `found_listings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lost_comment`
--

DROP TABLE IF EXISTS `lost_comment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lost_comment` (
  `comment_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `lost_listing_id` int NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`comment_id`),
  KEY `user_id` (`user_id`),
  KEY `lost_listing_id` (`lost_listing_id`),
  CONSTRAINT `lost_comment_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `lost_comment_ibfk_2` FOREIGN KEY (`lost_listing_id`) REFERENCES `lost_listings` (`lost_listing_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lost_listings`
--

DROP TABLE IF EXISTS `lost_listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lost_listings` (
  `lost_listing_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `description` text,
  `location_details` varchar(255) DEFAULT NULL,
  `location_coordinates` varchar(100) DEFAULT NULL,
  `event_time` datetime DEFAULT NULL,
  `image_file_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','solved') NOT NULL DEFAULT 'pending',
  `comment_is_updated` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `category` varchar(50) NOT NULL,
  PRIMARY KEY (`lost_listing_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `lost_listings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=132 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `matched_listings`
--

DROP TABLE IF EXISTS `matched_listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `matched_listings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `listing_id` int NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `listing_id` (`listing_id`),
  CONSTRAINT `matched_listings_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `lost_listings` (`lost_listing_id`),
  CONSTRAINT `matched_listings_ibfk_2` FOREIGN KEY (`listing_id`) REFERENCES `found_listings` (`found_listing_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `matched_notifications`
--

DROP TABLE IF EXISTS `matched_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `matched_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `listing_id` int NOT NULL,
  `listing_type` enum('lost','found') NOT NULL,
  `source_listing_type` enum('lost','found') DEFAULT NULL,
  `source_listing_id` int DEFAULT NULL,
  `type` enum('match','claim') NOT NULL DEFAULT 'match' COMMENT '消息类型：match=系统匹配通知，claim=认领申请通知',
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_user_type` (`user_id`,`type`)
) ENGINE=InnoDB AUTO_INCREMENT=125 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `matches`
--

DROP TABLE IF EXISTS `matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `matches` (
  `match_id` int NOT NULL AUTO_INCREMENT,
  `lost_listing_id` int NOT NULL,
  `found_listing_id` int NOT NULL,
  `match_score` float DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`match_id`),
  UNIQUE KEY `lost_listing_id` (`lost_listing_id`,`found_listing_id`),
  KEY `found_listing_id` (`found_listing_id`),
  CONSTRAINT `matches_ibfk_1` FOREIGN KEY (`lost_listing_id`) REFERENCES `lost_listings` (`lost_listing_id`) ON DELETE CASCADE,
  CONSTRAINT `matches_ibfk_2` FOREIGN KEY (`found_listing_id`) REFERENCES `found_listings` (`found_listing_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `notification_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` enum('new_match','new_comment') NOT NULL,
  `message` varchar(255) NOT NULL,
  `reference_id` int NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `solve`
--

DROP TABLE IF EXISTS `solve`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `solve` (
  `solve_id` int NOT NULL AUTO_INCREMENT,
  `lost_listing_id` int NOT NULL,
  `found_listing_id` int NOT NULL,
  `lost_user_id` int NOT NULL,
  `found_user_id` int NOT NULL,
  `claim_features` varchar(500) NOT NULL COMMENT '物品特征（必填，认领申请三要素之一）',
  `lost_story` text NOT NULL COMMENT '丢失经过（必填，认领申请三要素之二）',
  `verification_info` text COMMENT '其他验证信息（选填，认领申请三要素之三）',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '认领申请提交时间',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '申请状态最后更新时间',
  `status` enum('processing','completed') NOT NULL DEFAULT 'processing',
  `solved_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`solve_id`),
  UNIQUE KEY `uk_lost_found_user` (`lost_listing_id`,`found_listing_id`,`lost_user_id`),
  KEY `lost_listing_id` (`lost_listing_id`),
  KEY `found_listing_id` (`found_listing_id`),
  KEY `lost_user_id` (`lost_user_id`),
  KEY `found_user_id` (`found_user_id`),
  CONSTRAINT `solve_ibfk_1` FOREIGN KEY (`lost_listing_id`) REFERENCES `lost_listings` (`lost_listing_id`),
  CONSTRAINT `solve_ibfk_2` FOREIGN KEY (`found_listing_id`) REFERENCES `found_listings` (`found_listing_id`),
  CONSTRAINT `solve_ibfk_3` FOREIGN KEY (`lost_user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `solve_ibfk_4` FOREIGN KEY (`found_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suggested_matches`
--

DROP TABLE IF EXISTS `suggested_matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suggested_matches` (
  `match_id` int NOT NULL AUTO_INCREMENT,
  `listing_id` int NOT NULL,
  `matched_listing_id` int NOT NULL,
  `match_score` float NOT NULL,
  `type` enum('lost','found') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`match_id`),
  KEY `listing_id` (`listing_id`),
  KEY `matched_listing_id` (`matched_listing_id`),
  CONSTRAINT `suggested_matches_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `lost_listings` (`lost_listing_id`) ON DELETE CASCADE,
  CONSTRAINT `suggested_matches_ibfk_2` FOREIGN KEY (`matched_listing_id`) REFERENCES `lost_listings` (`lost_listing_id`) ON DELETE CASCADE,
  CONSTRAINT `suggested_matches_ibfk_3` FOREIGN KEY (`listing_id`) REFERENCES `found_listings` (`found_listing_id`) ON DELETE CASCADE,
  CONSTRAINT `suggested_matches_ibfk_4` FOREIGN KEY (`matched_listing_id`) REFERENCES `found_listings` (`found_listing_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `real_name` varchar(50) DEFAULT NULL COMMENT '姓名（正式需求新增：用户实名）',
  `student_id` varchar(50) DEFAULT NULL COMMENT '学号（正式需求新增：校园学号；UNIQUE）',
  `phone` varchar(20) DEFAULT NULL COMMENT '联系电话（正式需求新增）',
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` varchar(20) DEFAULT 'user',
  `security_question` varchar(255) DEFAULT NULL,
  `security_answer` varchar(255) DEFAULT NULL,
  `verification_code` varchar(10) DEFAULT NULL,
  `verification_code_expires_at` timestamp NULL DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `student_id` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-07-04 12:11:45
