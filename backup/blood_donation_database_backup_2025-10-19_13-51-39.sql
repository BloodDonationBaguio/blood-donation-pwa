-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: blood_system
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

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
-- Table structure for table `admin_audit_log`
--

DROP TABLE IF EXISTS `admin_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_username` varchar(100) NOT NULL,
  `action` varchar(255) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin_username` (`admin_username`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_audit_log`
--

LOCK TABLES `admin_audit_log` WRITE;
/*!40000 ALTER TABLE `admin_audit_log` DISABLE KEYS */;
INSERT INTO `admin_audit_log` VALUES (1,'admin','login',NULL,NULL,'Admin logged into system','127.0.0.1','2025-10-19 11:39:24'),(2,'admin','donor_approved','donors_new',1,'Approved donor registration','127.0.0.1','2025-10-19 11:39:24'),(3,'admin','donor_updated','donors_new',2,'Updated donor information','127.0.0.1','2025-10-19 11:39:24'),(5,'admin','donor_match_created','donor_matching',1,'Created donor match','127.0.0.1','2025-10-19 11:39:24');
/*!40000 ALTER TABLE `admin_audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expiry` timestamp NULL DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','super_admin') DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES (1,'admin','','prc.baguio.blood@gmail.com','$2y$10$8lnY8VwihhrjYKfqrnK7PO0/VTwneLZ3LpBvK3ifOFZDzwJ89W2lm','204a7bd400005bc8b5c584de8f46bc1f7688f205e194fb1fcc3be63499d86630','2025-10-09 22:18:45','Philippine Red Cross - Baguio Chapter','super_admin',1,'2025-10-19 11:40:12','2025-10-09 11:46:17','2025-10-19 11:40:12');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blood_inventory`
--

DROP TABLE IF EXISTS `blood_inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blood_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unit_id` varchar(50) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') NOT NULL,
  `collection_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('available','used','expired','quarantined') DEFAULT 'available',
  `collection_site` varchar(100) DEFAULT 'Main Center',
  `storage_location` varchar(50) DEFAULT 'Storage A',
  `volume_ml` int(11) DEFAULT 450,
  `screening_status` enum('pending','passed','failed') DEFAULT 'pending',
  `test_results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`test_results`)),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `seed_flag` tinyint(1) DEFAULT 0 COMMENT 'Flag to identify seeded test data',
  `units_available` int(11) NOT NULL DEFAULT 0,
  `units_used` int(11) NOT NULL DEFAULT 0,
  `donation_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unit_id` (`unit_id`),
  KEY `idx_unit_id` (`unit_id`),
  KEY `idx_donor_id` (`donor_id`),
  KEY `idx_blood_type` (`blood_type`),
  KEY `idx_status` (`status`),
  KEY `idx_collection_date` (`collection_date`),
  KEY `idx_expiry_date` (`expiry_date`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_blood_inventory_seed_flag` (`seed_flag`),
  KEY `idx_blood_inventory_status_created` (`status`,`created_at`),
  CONSTRAINT `blood_inventory_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors_new` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=188 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blood_inventory`
--

LOCK TABLES `blood_inventory` WRITE;
/*!40000 ALTER TABLE `blood_inventory` DISABLE KEYS */;
INSERT INTO `blood_inventory` VALUES (132,'UNIT-B+-001',1,'B+','2025-10-08','2025-11-19','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(133,'UNIT-B+-002',2,'B+','2025-09-27','2025-11-08','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(134,'UNIT-A+-001',3,'A+','2025-09-26','2025-11-07','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(135,'UNIT-A--001',4,'A-','2025-10-06','2025-11-17','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(136,'UNIT-AB+-001',5,'AB+','2025-09-14','2025-10-26','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(137,'UNIT-A+-002',6,'A+','2025-09-24','2025-11-05','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(138,'UNIT-B+-003',7,'B+','2025-09-15','2025-10-27','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(139,'UNIT-O+-001',8,'O+','2025-09-21','2025-11-02','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(140,'UNIT-AB+-002',9,'AB+','2025-09-15','2025-10-27','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(141,'UNIT-AB+-003',10,'AB+','2025-10-07','2025-11-18','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(142,'UNIT-A+-003',11,'A+','2025-09-12','2025-10-24','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(143,'UNIT-A+-004',12,'A+','2025-09-20','2025-11-01','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(144,'UNIT-A+-005',13,'A+','2025-09-14','2025-10-26','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(145,'UNIT-A+-006',14,'A+','2025-09-19','2025-10-31','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(146,'UNIT-A--002',15,'A-','2025-09-24','2025-11-05','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(147,'UNIT-A+-007',16,'A+','2025-09-15','2025-10-27','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(148,'UNIT-A+-008',17,'A+','2025-09-13','2025-10-25','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(149,'UNIT-B+-004',18,'B+','2025-10-02','2025-11-13','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(150,'UNIT-A--003',19,'A-','2025-09-27','2025-11-08','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(151,'UNIT-O+-002',20,'O+','2025-09-18','2025-10-30','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(152,'UNIT-A+-009',21,'A+','2025-09-23','2025-11-04','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(153,'UNIT-A--004',22,'A-','2025-09-11','2025-10-23','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(154,'UNIT-A+-010',23,'A+','2025-09-12','2025-10-24','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(155,'UNIT-A+-011',24,'A+','2025-09-10','2025-10-22','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(156,'UNIT-O+-003',25,'O+','2025-09-26','2025-11-07','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(157,'UNIT-O+-004',26,'O+','2025-09-15','2025-10-27','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(158,'UNIT-B+-005',27,'B+','2025-10-01','2025-11-12','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(159,'UNIT-B+-006',28,'B+','2025-10-02','2025-11-13','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(160,'UNIT-B--001',29,'B-','2025-10-06','2025-11-17','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(161,'UNIT-AB+-004',30,'AB+','2025-09-17','2025-10-29','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(162,'UNIT-O+-005',31,'O+','2025-09-30','2025-11-11','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(163,'UNIT-B+-007',32,'B+','2025-09-20','2025-11-01','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(164,'UNIT-A+-012',33,'A+','2025-10-08','2025-11-19','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(165,'UNIT-O+-006',34,'O+','2025-10-07','2025-11-18','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(166,'UNIT-A--005',35,'A-','2025-10-03','2025-11-14','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(167,'UNIT-A+-013',36,'A+','2025-09-24','2025-11-05','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(168,'UNIT-B+-008',37,'B+','2025-09-09','2025-10-21','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(169,'UNIT-B+-009',38,'B+','2025-10-04','2025-11-15','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(170,'UNIT-A+-014',39,'A+','2025-10-06','2025-11-17','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(171,'UNIT-A+-015',40,'A+','2025-09-12','2025-10-24','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(172,'UNIT-AB+-005',41,'AB+','2025-10-06','2025-11-17','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(173,'UNIT-B+-010',42,'B+','2025-10-01','2025-11-12','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(174,'UNIT-B+-011',43,'B+','2025-09-13','2025-10-25','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(175,'UNIT-A--006',44,'A-','2025-09-09','2025-10-21','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(176,'UNIT-A+-016',45,'A+','2025-09-16','2025-10-28','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(177,'UNIT-A+-017',46,'A+','2025-10-08','2025-11-19','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(179,'UNIT-O+-007',48,'O+','2025-09-19','2025-10-31','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(180,'UNIT-O+-008',49,'O+','2025-09-28','2025-11-09','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(181,'UNIT-B+-012',50,'B+','2025-10-04','2025-11-15','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(183,'UNIT-AB--001',52,'AB-','2025-10-05','2025-11-16','available','Main Collection Center','A1',450,'passed',NULL,NULL,'2025-10-09 13:49:05','2025-10-09 13:49:05',NULL,NULL,0,1,0,NULL),(184,'PRC-20251009-3016',57,'AB-','2025-10-09','2025-11-20','available','Main Center','Storage A',450,'pending',NULL,NULL,'2025-10-09 14:54:36','2025-10-09 14:54:36',NULL,NULL,0,0,0,NULL),(185,'PRC-20251013-8305',58,'O-','2025-10-13','2025-11-24','used','Main Center','Storage A',450,'pending',NULL,NULL,'2025-10-13 01:26:58','2025-10-13 01:27:28',NULL,NULL,0,0,0,NULL),(186,'PRC-20251013-5053',59,'O+','2025-10-13','2025-11-24','used','Main Center','Storage A',450,'pending',NULL,'[2025-10-14 11:56] admin: Status changed from available to used — Reason: gggg','2025-10-13 01:45:19','2025-10-14 09:56:37',NULL,NULL,0,0,0,NULL),(187,'PRC-20251014-7933',58,'O-','2025-10-14','2025-11-08','used','Main Center','Storage A',450,'pending',NULL,'[2025-10-14 11:55] admin: Status changed from used to used — Reason: rrr','2025-10-14 09:50:06','2025-10-14 09:55:50',NULL,NULL,0,0,0,NULL);
/*!40000 ALTER TABLE `blood_inventory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `donations_new`
--

DROP TABLE IF EXISTS `donations_new`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `donations_new` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` int(11) NOT NULL,
  `donation_date` date NOT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `units_donated` int(11) DEFAULT 1,
  `donation_status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `units_collected` decimal(4,2) NOT NULL DEFAULT 0.00,
  `hemoglobin_level` decimal(4,2) DEFAULT NULL,
  `blood_pressure_systolic` int(11) DEFAULT NULL,
  `blood_pressure_diastolic` int(11) DEFAULT NULL,
  `pulse_rate` int(11) DEFAULT NULL,
  `temperature` decimal(4,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `status` enum('completed','cancelled','deferred') DEFAULT 'completed',
  PRIMARY KEY (`id`),
  KEY `idx_donor_id` (`donor_id`),
  KEY `idx_donation_date` (`donation_date`),
  KEY `idx_donation_status` (`donation_status`),
  CONSTRAINT `donations_new_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors_new` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `donations_new`
--

LOCK TABLES `donations_new` WRITE;
/*!40000 ALTER TABLE `donations_new` DISABLE KEYS */;
INSERT INTO `donations_new` VALUES (1,3,'2024-01-15','O+',1,'scheduled',NULL,'2025-10-09 13:00:03','2025-10-09 13:00:03',1.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'completed'),(2,6,'2024-01-20','A+',1,'scheduled',NULL,'2025-10-09 13:00:03','2025-10-09 13:00:03',1.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'completed'),(3,11,'2024-02-01','B+',1,'scheduled',NULL,'2025-10-09 13:00:03','2025-10-09 13:00:03',1.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'completed'),(4,57,'2025-10-09','AB-',1,'completed',NULL,'2025-10-09 14:56:09','2025-10-09 14:56:09',0.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'completed'),(5,58,'2025-10-13','O-',1,'completed',NULL,'2025-10-13 01:26:24','2025-10-13 01:26:24',0.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'completed'),(6,59,'2025-10-13','O+',1,'completed',NULL,'2025-10-13 01:44:56','2025-10-13 01:44:56',0.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'completed');
/*!40000 ALTER TABLE `donations_new` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `donor_medical_screening_simple`
--

DROP TABLE IF EXISTS `donor_medical_screening_simple`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `donor_medical_screening_simple` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` int(11) NOT NULL,
  `reference_code` varchar(50) NOT NULL,
  `screening_data` longtext NOT NULL,
  `all_questions_answered` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `donor_id` (`donor_id`),
  KEY `reference_code` (`reference_code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `donor_medical_screening_simple`
--

LOCK TABLES `donor_medical_screening_simple` WRITE;
/*!40000 ALTER TABLE `donor_medical_screening_simple` DISABLE KEYS */;
INSERT INTO `donor_medical_screening_simple` VALUES (2,55,'DNR-56BD9B','{\"q1\":\"yes\",\"q2\":\"yes\",\"q3\":\"yes\",\"q4\":\"no\",\"q5\":\"no\",\"q6\":\"no\",\"q7\":\"no\",\"q8\":\"no\",\"q9\":\"no\",\"q10\":\"no\",\"q11\":\"no\",\"q12\":\"no\",\"q13\":\"no\",\"q14\":\"no\",\"q15\":\"no\",\"q16\":\"no\",\"q17\":\"no\",\"q18\":\"no\",\"q19\":\"no\",\"q20\":\"no\",\"q21\":\"no\",\"q22\":\"no\",\"q23\":\"no\",\"q24\":\"no\",\"q25\":\"no\",\"q26\":\"no\",\"q27\":\"no\",\"q28\":\"no\",\"q29\":\"no\",\"q30\":\"no\",\"q31\":\"no\",\"q32\":\"no\",\"q33\":\"\",\"q34\":\"none\",\"q35\":\"\",\"q36\":\"\",\"q37\":\"\"}',1,'2025-10-09 14:43:12','2025-10-09 14:43:12'),(3,57,'DNR-21BE86','{\"q1\":\"yes\",\"q2\":\"yes\",\"q3\":\"yes\",\"q4\":\"yes\",\"q5\":\"no\",\"q6\":\"no\",\"q7\":\"no\",\"q8\":\"no\",\"q9\":\"no\",\"q10\":\"no\",\"q11\":\"no\",\"q12\":\"no\",\"q13\":\"no\",\"q14\":\"no\",\"q15\":\"no\",\"q16\":\"no\",\"q17\":\"no\",\"q18\":\"no\",\"q19\":\"no\",\"q20\":\"no\",\"q21\":\"no\",\"q22\":\"no\",\"q23\":\"no\",\"q24\":\"no\",\"q25\":\"no\",\"q26\":\"no\",\"q27\":\"no\",\"q28\":\"no\",\"q29\":\"no\",\"q30\":\"no\",\"q31\":\"no\",\"q32\":\"no\",\"q33\":\"\",\"q34\":\"none\",\"q35\":\"\",\"q36\":\"\",\"q37\":\"\"}',1,'2025-10-09 14:53:15','2025-10-09 14:53:15'),(4,58,'DNR-898859','{\"q1\":\"yes\",\"q2\":\"no\",\"q3\":\"no\",\"q4\":\"no\",\"q5\":\"no\",\"q6\":\"no\",\"q7\":\"no\",\"q8\":\"no\",\"q9\":\"no\",\"q10\":\"no\",\"q11\":\"no\",\"q12\":\"no\",\"q13\":\"no\",\"q14\":\"no\",\"q15\":\"no\",\"q16\":\"no\",\"q17\":\"no\",\"q18\":\"no\",\"q19\":\"no\",\"q20\":\"no\",\"q21\":\"no\",\"q22\":\"no\",\"q23\":\"no\",\"q24\":\"no\",\"q25\":\"no\",\"q26\":\"no\",\"q27\":\"no\",\"q28\":\"no\",\"q29\":\"no\",\"q30\":\"no\",\"q31\":\"no\",\"q32\":\"no\",\"q33\":\"\",\"q34\":\"none\",\"q35\":\"\",\"q36\":\"\",\"q37\":\"\"}',1,'2025-10-13 01:20:10','2025-10-13 01:20:10'),(5,59,'DNR-25C4F1','{\"q1\":\"yes\",\"q2\":\"no\",\"q3\":\"no\",\"q4\":\"no\",\"q5\":\"no\",\"q6\":\"no\",\"q7\":\"no\",\"q8\":\"no\",\"q9\":\"no\",\"q10\":\"no\",\"q11\":\"no\",\"q12\":\"no\",\"q13\":\"no\",\"q14\":\"no\",\"q15\":\"no\",\"q16\":\"no\",\"q17\":\"no\",\"q18\":\"no\",\"q19\":\"no\",\"q20\":\"no\",\"q21\":\"no\",\"q22\":\"no\",\"q23\":\"no\",\"q24\":\"no\",\"q25\":\"no\",\"q26\":\"no\",\"q27\":\"no\",\"q28\":\"no\",\"q29\":\"no\",\"q30\":\"no\",\"q31\":\"no\",\"q32\":\"no\",\"q33\":\"no\",\"q34\":\"none\",\"q35\":\"no\",\"q36\":\"no\",\"q37\":\"\",\"q37_date\":\"2025-09-13\"}',1,'2025-10-13 01:43:15','2025-10-13 01:43:15');
/*!40000 ALTER TABLE `donor_medical_screening_simple` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `donor_notes`
--

DROP TABLE IF EXISTS `donor_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `donor_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_donor_id` (`donor_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `donor_notes_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `donors_new` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `donor_notes`
--

LOCK TABLES `donor_notes` WRITE;
/*!40000 ALTER TABLE `donor_notes` DISABLE KEYS */;
INSERT INTO `donor_notes` VALUES (1,57,'gg',NULL,'2025-10-09 14:55:49'),(2,55,'gggg',NULL,'2025-10-09 14:56:18'),(3,58,'gg',NULL,'2025-10-13 01:23:34'),(4,58,'come again',NULL,'2025-10-13 01:24:26');
/*!40000 ALTER TABLE `donor_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `donors_new`
--

DROP TABLE IF EXISTS `donors_new`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `donors_new` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `reference_code` varchar(50) DEFAULT NULL,
  `status` enum('pending','approved','served','unserved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `seed_flag` tinyint(1) DEFAULT 0 COMMENT 'Flag to identify seeded test data',
  `last_donation_date` date DEFAULT NULL,
  `last_reminder_sent` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `reference_code` (`reference_code`),
  KEY `idx_email` (`email`),
  KEY `idx_reference_code` (`reference_code`),
  KEY `idx_status` (`status`),
  KEY `idx_blood_type` (`blood_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_donors_seed_flag` (`seed_flag`),
  KEY `idx_donors_status_created` (`status`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `donors_new`
--

LOCK TABLES `donors_new` WRITE;
/*!40000 ALTER TABLE `donors_new` DISABLE KEYS */;
INSERT INTO `donors_new` VALUES (1,'Brian','Hill','donor1@test.local','555-548-5155','B+','1981-12-07','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0001','served','2025-09-25 04:05:02','2025-10-09 13:45:10',1,NULL,NULL),(2,'John','Baker','donor2@test.local','456-340-7048','B+','1982-11-07','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0002','served','2025-09-09 04:05:02','2025-10-09 13:45:10',1,NULL,NULL),(3,'Carol','Adams','donor3@test.local','321-906-4749','A+','1978-09-18','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0003','served','2025-09-19 04:05:02','2025-10-09 13:45:10',1,NULL,NULL),(4,'George','Garcia','donor4@test.local','321-670-5106','A-','1983-07-04','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0004','served','2025-10-07 04:05:02','2025-10-09 13:45:10',1,NULL,NULL),(5,'Emily','Green','donor5@test.local','654-898-6906','AB+','1969-02-11','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0005','served','2025-10-02 04:05:02','2025-10-09 13:45:10',1,NULL,NULL),(6,'Sandra','Gonzalez','donor6@test.local','456-630-9231','A+','1967-09-26','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0006','served','2025-09-24 04:05:02','2025-10-09 13:45:10',1,NULL,NULL),(7,'Lisa','Walker','donor7@test.local','123-441-9053','B+','1961-12-13','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0007','served','2025-09-24 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(8,'Lisa','Gomez','donor8@test.local','789-699-3845','O+','1976-12-28','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0008','served','2025-09-23 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(9,'Jeffrey','Evans','donor9@test.local','321-483-3387','AB+','1986-03-16','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0009','served','2025-09-12 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(10,'Dorothy','Green','donor10@test.local','147-307-3059','AB+','1997-09-05','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0010','served','2025-10-06 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(11,'Donna','Campbell','donor11@test.local','147-449-3452','A+','1973-04-03','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0011','served','2025-09-13 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(12,'William','Martinez','donor12@test.local','258-468-1366','A+','1979-07-02','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0012','served','2025-09-23 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(13,'Daniel','Clark','donor13@test.local','258-392-8692','A+','1992-01-22','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0013','served','2025-10-06 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(14,'George','Baker','donor14@test.local','654-690-4709','A+','1978-12-16','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0014','served','2025-10-06 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(15,'Ruth','Thompson','donor15@test.local','369-429-7510','A-','1981-07-06','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0015','served','2025-09-13 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(16,'Jane','Garcia','donor16@test.local','258-192-2088','A+','1994-01-28','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0016','served','2025-09-13 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(17,'Kevin','Jackson','donor17@test.local','369-615-9451','A+','1975-11-17','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0017','served','2025-10-07 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(18,'William','Taylor','donor18@test.local','147-539-2838','B+','1984-10-09','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0018','served','2025-09-16 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(19,'Matthew','Brown','donor19@test.local','321-894-7920','A-','1983-11-26','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0019','served','2025-09-13 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(20,'Sarah','Smith','donor20@test.local','321-300-6589','O+','1993-02-06','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0020','served','2025-10-05 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(21,'Nancy','Brown','donor21@test.local','654-821-2376','A+','2000-02-08','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0021','served','2025-09-24 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(22,'Joshua','Mitchell','donor22@test.local','654-527-6919','A-','1977-04-02','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0022','served','2025-09-13 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(23,'Sandra','Lewis','donor23@test.local','123-807-2176','A+','1982-12-22','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0023','served','2025-09-15 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(24,'Michael','Hernandez','donor24@test.local','654-497-7681','A+','1986-09-12','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0024','served','2025-09-15 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(25,'Anthony','Roberts','donor25@test.local','321-713-3746','O+','1974-04-14','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0025','served','2025-09-23 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(26,'Edward','Baker','donor26@test.local','123-965-5089','O+','1972-02-05','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0026','served','2025-10-03 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(27,'Emily','Moore','donor27@test.local','147-276-4521','B+','1991-08-04','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0027','served','2025-09-29 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(28,'Jane','King','donor28@test.local','321-591-1599','B+','2001-10-28','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0028','served','2025-09-20 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(29,'Jason','Miller','donor29@test.local','369-520-2714','B-','1976-12-22','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0029','served','2025-10-03 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(30,'Donald','Jackson','donor30@test.local','456-300-3598','AB+','1979-09-02','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0030','served','2025-09-25 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(31,'Jessica','Allen','donor31@test.local','456-700-5322','O+','1998-01-24','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0031','served','2025-10-03 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(32,'Sarah','Lee','donor32@test.local','987-388-9396','B+','2007-03-27','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0032','served','2025-09-14 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(33,'Matthew','Wilson','donor33@test.local','987-455-6170','A+','1966-09-28','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0033','served','2025-10-06 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(34,'Steven','Clark','donor34@test.local','555-269-1982','O+','1995-11-20','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0034','served','2025-10-06 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(35,'Christopher','Walker','donor35@test.local','456-913-7786','A-','1982-10-16','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0035','served','2025-10-03 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(36,'Ronald','Jones','donor36@test.local','456-596-3731','A+','2002-01-27','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0036','served','2025-09-23 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(37,'Sarah','Williams','donor37@test.local','987-393-6501','B+','1963-02-03','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0037','served','2025-09-11 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(38,'David','Flores','donor38@test.local','123-910-9726','B+','1998-06-02','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0038','served','2025-10-06 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(39,'Robert','Rodriguez','donor39@test.local','147-959-1678','A+','1973-07-13','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0039','served','2025-10-03 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(40,'Ryan','Moore','donor40@test.local','555-602-3447','A+','1972-10-16','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0040','served','2025-09-28 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(41,'Deborah','Green','donor41@test.local','789-525-9792','AB+','1994-09-11','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0041','served','2025-10-07 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(42,'Ryan','Wright','donor42@test.local','789-402-1672','B+','1963-11-06','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0042','served','2025-09-21 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(43,'Jessica','Sanchez','donor43@test.local','987-384-1215','B+','1993-11-01','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0043','served','2025-09-10 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(44,'Brian','Young','donor44@test.local','654-892-6195','A-','2005-11-13','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0044','served','2025-09-24 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(45,'Karen','Gomez','donor45@test.local','321-150-5514','A+','2000-06-28','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0045','served','2025-09-24 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(46,'Deborah','Johnson','donor46@test.local','987-356-1585','A+','1985-04-15','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0046','served','2025-09-19 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(48,'Deborah','Gonzalez','donor48@test.local','123-677-1010','O+','1984-06-19','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0048','served','2025-09-26 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(49,'Jane','Flores','donor49@test.local','147-715-2603','O+','1972-04-20','Male',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0049','served','2025-09-25 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(50,'Sandra','Anderson','donor50@test.local','258-902-2087','B+','1983-02-12','Female',NULL,NULL,NULL,NULL,NULL,NULL,'TEST-0050','served','2025-10-08 04:05:03','2025-10-09 13:45:10',1,NULL,NULL),(52,'Sarah','Williams','sarah.williams@example.com','555-0105','AB-','1979-11-22','Female',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'served','2025-10-09 13:45:10','2025-10-09 13:45:10',0,NULL,NULL),(55,'najib','','najib4793y@gmail.com','09946698753','B-','2000-05-05','Male','Trancoville','City of Baguio','Benguet',NULL,66.00,160.00,'DNR-56BD9B','unserved','2025-10-09 14:43:12','2025-10-10 12:31:10',0,NULL,NULL),(57,'Darwin','','nageb964744@gmail.com','09946698753','AB-','1990-06-07','Male','Trancoville','City of Baguio','Benguet',NULL,68.00,177.00,'DNR-21BE86','served','2025-10-09 14:53:15','2025-10-09 14:56:09',0,NULL,NULL),(58,'najib','','nageb96414@gmail.com','09948652487','O-','2000-07-08','Male','Trancoville','City of Baguio','Benguet',NULL,60.00,177.00,'DNR-898859','served','2025-10-13 01:20:10','2025-10-13 01:26:24',0,NULL,NULL),(59,'jja','','bloodservices.redcrossbaguio@gmail.com','09088852707','O+','1987-10-13','Female','baguio city','City of Baguio','Benguet',NULL,50.00,100.00,'DNR-25C4F1','served','2025-10-13 01:43:15','2025-10-13 01:44:56',0,NULL,NULL);
/*!40000 ALTER TABLE `donors_new` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-19 19:51:40
