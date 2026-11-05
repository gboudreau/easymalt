/*M!999999\- enable the sandbox mode */
-- MariaDB dump 10.19  Distrib 10.5.29-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: local_finances
-- ------------------------------------------------------
-- Server version	10.5.29-MariaDB-ubu2004-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
                            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                            `name` tinytext DEFAULT NULL,
                            `routing_number` tinytext NOT NULL,
                            `account_number` tinytext NOT NULL,
                            `balance` float(12,2) NOT NULL DEFAULT 0.00,
                            `currency` char(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'CAD',
                            `balance_date` datetime DEFAULT NULL,
                            `plaid_id` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
                            `type` varchar(255) DEFAULT NULL,
                            PRIMARY KEY (`id`),
                            UNIQUE KEY `routing_number` (`routing_number`,`account_number`) USING HASH
) ENGINE=InnoDB AUTO_INCREMENT=477 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
                              `name` varchar(100) NOT NULL DEFAULT '',
                              `hidden` enum('yes','no') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'no',
                              PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `plaid_events`
--

DROP TABLE IF EXISTS `plaid_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `plaid_events` (
                                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                                `webhook_type` enum('ITEM','TRANSACTIONS') DEFAULT NULL,
                                `webhook_code` varchar(50) DEFAULT NULL,
                                `item_id` varchar(50) DEFAULT NULL,
                                `json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
                                `event_date` timestamp NULL DEFAULT current_timestamp(),
                                PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=206 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `plaid_tokens`
--

DROP TABLE IF EXISTS `plaid_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `plaid_tokens` (
                                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                                `plaid_item_id` varchar(50) NOT NULL DEFAULT '',
                                `access_token` varchar(200) DEFAULT NULL,
                                `metadata` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
                                PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `post_processing`
--

DROP TABLE IF EXISTS `post_processing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `post_processing` (
                                   `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                                   `regex` varchar(100) NOT NULL DEFAULT '',
                                   `amount_equals` float(7,2) DEFAULT NULL,
                                   `display_name` varchar(255) DEFAULT NULL,
                                   `memo` tinytext DEFAULT NULL,
                                   `category` varchar(100) DEFAULT NULL,
                                   `tags` varchar(255) DEFAULT NULL,
                                   `prio` tinyint(3) unsigned NOT NULL DEFAULT 0,
                                   PRIMARY KEY (`id`),
                                   UNIQUE KEY `regex` (`regex`,`amount_equals`)
) ENGINE=InnoDB AUTO_INCREMENT=764 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
                        `name` varchar(30) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
                                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                                `account_id` int(11) unsigned NOT NULL,
                                `unique_id` varchar(200) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
                                `date` datetime NOT NULL,
                                `type` varchar(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
                                `amount` float(9,2) NOT NULL,
                                `name` varchar(255) DEFAULT NULL,
                                `display_name` varchar(255) DEFAULT NULL,
                                `memo` text DEFAULT NULL,
                                `tags` varchar(255) DEFAULT NULL,
                                `category` varchar(100) DEFAULT NULL,
                                `post_processed` enum('yes','no') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'no',
                                `linked_txn_id` int(11) unsigned DEFAULT NULL,
                                `hidden` enum('yes','no') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'no',
                                `plaid_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
                                PRIMARY KEY (`id`),
                                UNIQUE KEY `account_id` (`account_id`,`unique_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10575874 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `v_transactions_reports`
--

DROP TABLE IF EXISTS `v_transactions_reports`;
/*!50001 DROP VIEW IF EXISTS `v_transactions_reports`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_transactions_reports` AS SELECT
                                                     1 AS `id`,
                                                     1 AS `date`,
                                                     1 AS `month`,
                                                     1 AS `amount`,
                                                     1 AS `category`,
                                                     1 AS `group_by_category`,
                                                     1 AS `tags`,
                                                     1 AS `name`,
                                                     1 AS `original_name`,
                                                     1 AS `memo`,
                                                     1 AS `type`,
                                                     1 AS `account_id`,
                                                     1 AS `hidden`,
                                                     1 AS `post_processed` */;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `v_transactions_reports`
--

/*!50001 DROP VIEW IF EXISTS `v_transactions_reports`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
    /*!50013 DEFINER=`root`@`192.168.156.44` SQL SECURITY DEFINER */
    /*!50001 VIEW `v_transactions_reports` AS select `t`.`id` AS `id`,`t`.`date` AS `date`,substr(`t`.`date`,1,7) AS `month`,`t`.`amount` AS `amount`,`t`.`category` AS `category`,substr(`t`.`category`,1,octet_length(`t`.`category`) - locate(':',reverse(`t`.`category`))) AS `group_by_category`,`t`.`tags` AS `tags`,ifnull(`t`.`display_name`,`t`.`name`) AS `name`,`t`.`name` AS `original_name`,`t`.`memo` AS `memo`,`t`.`type` AS `type`,`t`.`account_id` AS `account_id`,if(`t`.`hidden` = 'yes' or `t`.`category` in (select `categories`.`name` from `categories` where `categories`.`hidden` = 'yes'),'yes','no') AS `hidden`,`t`.`post_processed` AS `post_processed` from `transactions` `t` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-25 23:12:58
