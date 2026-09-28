-- SCAIMS structure-only schema baseline, generated 2026-09-28.
-- Contains no application records, credentials, password hashes, or uploads.
-- Import into an empty database, then run: php database/migrate.php --apply

SET FOREIGN_KEY_CHECKS=0;

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin` (
  `AdminID` varchar(234) NOT NULL,
  `AdminName` varchar(234) NOT NULL,
  `AdminPass` varchar(225) NOT NULL,
  `AdminRole` varchar(32) NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`AdminID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crop_catalog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `commodity` varchar(32) NOT NULL,
  `crop_name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_crop_catalog_commodity_name` (`commodity`,`crop_name`),
  KEY `idx_crop_catalog_active` (`is_active`),
  KEY `idx_crop_catalog_commodity_sort` (`commodity`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crop_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parcel_detail_id` int(11) NOT NULL,
  `crop_commodity` varchar(255) NOT NULL,
  `crop_type` varchar(255) NOT NULL,
  `crop_variety` varchar(255) NOT NULL,
  `num_trees` int(11) DEFAULT 0,
  `tree_status` varchar(255) DEFAULT '',
  `created_by` varchar(28) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parcel_detail_id` (`parcel_detail_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crop_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `location` varchar(255) NOT NULL,
  `crop_commodity` varchar(255) NOT NULL,
  `crop_type` varchar(255) NOT NULL,
  `crop_variety` varchar(255) NOT NULL,
  `date_planted` date NOT NULL,
  `harvest_date` date NOT NULL,
  `estimate_harvest` float NOT NULL,
  `FarmerID` int(11) DEFAULT NULL,
  `reportDate` date DEFAULT NULL,
  `created_by` varchar(28) NOT NULL,
  `farm_gate_price` decimal(10,2) NOT NULL,
  `crop_status` varchar(50) NOT NULL,
  `number_of_trees` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `photo_capture_date` date DEFAULT NULL,
  `photo_capture_time` varchar(8) DEFAULT NULL,
  `photo_location` text DEFAULT NULL,
  `photo_latitude` decimal(12,9) DEFAULT NULL,
  `photo_longitude` decimal(12,9) DEFAULT NULL,
  `photo_timezone` varchar(16) DEFAULT NULL,
  `photo_ocr_version` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_farmer` (`FarmerID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `damage_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dmgLocation` varchar(255) NOT NULL,
  `dmgCropCommodity` varchar(255) NOT NULL,
  `dmgCropType` varchar(255) NOT NULL,
  `dmgCropVariety` varchar(255) NOT NULL,
  `date_occurrence` date NOT NULL,
  `damage_cause` varchar(255) NOT NULL,
  `percent_damage` decimal(10,2) NOT NULL,
  `estimated_loss` float NOT NULL,
  `FarmerID` int(11) DEFAULT NULL,
  `reportDate` date DEFAULT NULL,
  `created_by` varchar(28) NOT NULL,
  `dmgFarmGatePrice` decimal(10,2) NOT NULL,
  `damage_number_of_trees` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `photo_capture_date` date DEFAULT NULL,
  `photo_capture_time` varchar(8) DEFAULT NULL,
  `photo_location` text DEFAULT NULL,
  `photo_latitude` decimal(12,9) DEFAULT NULL,
  `photo_longitude` decimal(12,9) DEFAULT NULL,
  `photo_timezone` varchar(16) DEFAULT NULL,
  `photo_ocr_version` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_farmer` (`FarmerID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farm_parcels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_location` varchar(255) NOT NULL,
  `farm_area` decimal(10,4) NOT NULL,
  `num_parcels` int(11) NOT NULL,
  `farm_type` varchar(255) NOT NULL,
  `organic` varchar(15) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(28) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farmer` (
  `FarmerID` int(11) NOT NULL AUTO_INCREMENT,
  `FirstName` varchar(255) NOT NULL,
  `Surname` varchar(255) NOT NULL,
  `MiddleName` varchar(255) NOT NULL,
  `Suffix` varchar(10) DEFAULT NULL,
  `Sex` enum('Male','Female') NOT NULL,
  `BirthDate` date NOT NULL,
  `ContactNo` varchar(15) DEFAULT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `Barangay` varchar(255) DEFAULT NULL,
  `registration_date` date NOT NULL DEFAULT current_timestamp(),
  `UserID` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `RSBSA_Number` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`FarmerID`),
  KEY `fk_user_farmer` (`UserID`),
  CONSTRAINT `fk_user_farmer` FOREIGN KEY (`UserID`) REFERENCES `user` (`UserID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farmer_report_defaults` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `FarmerID` int(11) NOT NULL,
  `report_type` enum('crop','damage') NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `crop_commodity` varchar(255) DEFAULT NULL,
  `crop_type` varchar(255) DEFAULT NULL,
  `crop_variety` varchar(255) DEFAULT NULL,
  `date_planted` date DEFAULT NULL,
  `harvest_date` date DEFAULT NULL,
  `estimate_harvest` decimal(12,2) DEFAULT NULL,
  `crop_status` varchar(50) DEFAULT NULL,
  `number_of_trees` varchar(255) DEFAULT NULL,
  `farm_gate_price` decimal(10,2) DEFAULT NULL,
  `date_occurrence` date DEFAULT NULL,
  `damage_cause` varchar(255) DEFAULT NULL,
  `percent_damage` decimal(5,2) DEFAULT NULL,
  `estimated_loss` decimal(12,2) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_farmer_report_defaults` (`FarmerID`,`report_type`),
  KEY `idx_farmer_report_defaults_farmer` (`FarmerID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `parcel_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `farm_parcel_id` int(11) NOT NULL,
  `location` varchar(255) NOT NULL,
  `total_area` varchar(20) DEFAULT NULL,
  `ownership_type` varchar(255) NOT NULL,
  `created_by` varchar(28) NOT NULL,
  `parcel_number` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `farm_parcel_id` (`farm_parcel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `image_paths` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suggestions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `suggestion` text NOT NULL,
  `submission_date` datetime NOT NULL,
  `status` enum('new','read') DEFAULT 'new',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user` (
  `UserID` int(11) NOT NULL AUTO_INCREMENT,
  `UName` varchar(255) NOT NULL,
  `UPass` varchar(255) NOT NULL,
  `UserRole` enum('admin','farmer','researcher','consultant') NOT NULL,
  `creationDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `ProfileImage` longblob DEFAULT NULL,
  `plain_password` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET FOREIGN_KEY_CHECKS=1;
