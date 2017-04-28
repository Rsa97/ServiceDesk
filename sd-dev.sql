-- MySQL dump 10.13  Distrib 5.5.46, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: sd-dev-1c
-- ------------------------------------------------------
-- Server version	5.5.46-0ubuntu0.12.04.2

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
-- Table structure for table `contractDivisions`
--

DROP TABLE IF EXISTS `contractDivisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contractDivisions` (
  `guid` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(64) DEFAULT NULL,
  `phone` varchar(64) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `yurAddress` varchar(255) DEFAULT NULL,
  `contract_guid` binary(16) DEFAULT NULL,
  `contragent_guid` binary(16) DEFAULT NULL,
  `type_guid` binary(16) DEFAULT NULL,
  `addProblem` text NOT NULL,
  `isDisabled` tinyint(1) NOT NULL DEFAULT '0',
  `update` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`guid`),
  UNIQUE KEY `byNameInContract_UNIQ` (`contract_guid`,`name`),
  KEY `fk_contractDivisions_contract_IDX` (`contract_guid`),
  KEY `fk_contractDivisions_contragent_IDX` (`contragent_guid`),
  KEY `fk_contractDivisions_type_IDX` (`type_guid`),
  KEY `byName` (`name`),
  KEY `combo1` (`contract_guid`,`name`),
  CONSTRAINT `fk_contractDivisions_contract` FOREIGN KEY (`contract_guid`) REFERENCES `contracts` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_contractDivisions_contragent` FOREIGN KEY (`contragent_guid`) REFERENCES `contragents` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_contractDivisions_type` FOREIGN KEY (`type_guid`) REFERENCES `divisionTypes` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Территориальные подразделения заказчика';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contractServices`
--

DROP TABLE IF EXISTS `contractServices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contractServices` (
  `contract_guid` binary(16) NOT NULL,
  `service_guid` binary(16) NOT NULL,
  `update` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`contract_guid`,`service_guid`),
  KEY `fk_service_IDX` (`service_guid`),
  KEY `fk_contract_IDX` (`contract_guid`),
  CONSTRAINT `fk_contractServices_contract` FOREIGN KEY (`contract_guid`) REFERENCES `contracts` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_contractServices_service` FOREIGN KEY (`service_guid`) REFERENCES `services` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contracts`
--

DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contracts` (
  `guid` binary(16) NOT NULL,
  `number` varchar(64) NOT NULL,
  `email` varchar(64) DEFAULT NULL,
  `phone` varchar(64) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `yurAddress` varchar(255) DEFAULT NULL,
  `contractStart` datetime DEFAULT NULL,
  `contractEnd` datetime DEFAULT NULL,
  `contragent_guid` binary(16) DEFAULT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT '1',
  `isStopped` tinyint(1) NOT NULL DEFAULT '0',
  `update` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`guid`),
  KEY `byContragent` (`contragent_guid`),
  KEY `byStart` (`contractStart`),
  KEY `byEnd` (`contractEnd`),
  KEY `byNumber` (`number`),
  CONSTRAINT `fk_contracts_contragent` FOREIGN KEY (`contragent_guid`) REFERENCES `contragents` (`guid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Договоры';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `contragents`
--

DROP TABLE IF EXISTS `contragents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contragents` (
  `guid` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `fullName` text,
  `parent` binary(16) DEFAULT NULL,
  `INN` varchar(12) DEFAULT NULL,
  `KPP` varchar(9) DEFAULT NULL,
  PRIMARY KEY (`guid`),
  KEY `byName` (`name`),
  KEY `byParent` (`parent`),
  KEY `byINN` (`INN`),
  CONSTRAINT `fk_contragents_parent` FOREIGN KEY (`parent`) REFERENCES `contragents` (`guid`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Контрагенты';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `divServicesSLA`
--

DROP TABLE IF EXISTS `divServicesSLA`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `divServicesSLA` (
  `guid` binary(16) NOT NULL,
  `contract_guid` binary(16) NOT NULL,
  `service_guid` binary(16) NOT NULL,
  `divType_guid` binary(16) NOT NULL,
  `slaLevel` enum('critical','high','medium','low') NOT NULL,
  `dayType` set('work','weekend') NOT NULL,
  `toReact` int(10) unsigned NOT NULL DEFAULT '30',
  `toFix` int(10) unsigned NOT NULL DEFAULT '2880',
  `toRepair` int(10) unsigned NOT NULL DEFAULT '14400',
  `quality` float NOT NULL DEFAULT '90',
  `startDayTime` time DEFAULT '00:00:00',
  `endDayTime` time DEFAULT '23:59:59',
  `isDefault` tinyint(1) NOT NULL DEFAULT '0',
  `update` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`contract_guid`,`service_guid`,`divType_guid`,`slaLevel`,`dayType`),
  KEY `fk_service_IDX` (`service_guid`),
  KEY `fk_divType_IDX` (`divType_guid`),
  CONSTRAINT `fk_divServicesSLA_contract` FOREIGN KEY (`contract_guid`) REFERENCES `contracts` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_divServicesSLA_divType` FOREIGN KEY (`divType_guid`) REFERENCES `divisionTypes` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_divServicesSLA_service` FOREIGN KEY (`service_guid`) REFERENCES `services` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `divisionTypes`
--

DROP TABLE IF EXISTS `divisionTypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `divisionTypes` (
  `guid` binary(16) NOT NULL,
  `name` varchar(64) NOT NULL,
  `comment` text,
  PRIMARY KEY (`guid`),
  UNIQUE KEY `name_UNIQUE` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `divisionWorkplaces`
--

DROP TABLE IF EXISTS `divisionWorkplaces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `divisionWorkplaces` (
  `guid` binary(16) NOT NULL,
  `division_guid` binary(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  PRIMARY KEY (`guid`),
  UNIQUE KEY `byNameInDiv` (`division_guid`,`name`),
  KEY `fk_division_IDX` (`division_guid`),
  KEY `byName` (`name`),
  CONSTRAINT `fk_divisionWorkplaces_division` FOREIGN KEY (`division_guid`) REFERENCES `contractDivisions` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `guid` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `uniqueName` varchar(255) DEFAULT NULL,
  `requestEvent_id` bigint(20) NOT NULL,
  PRIMARY KEY (`guid`),
  KEY `fk_documents_requestEvent_IDX` (`requestEvent_id`),
  CONSTRAINT `fk_documents_requestEvent` FOREIGN KEY (`requestEvent_id`) REFERENCES `requestEvents` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Документы';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `equipment`
--

DROP TABLE IF EXISTS `equipment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipment` (
  `guid` binary(16) NOT NULL,
  `serviceNumber` bigint(20) NOT NULL,
  `serialNumber` varchar(45) DEFAULT NULL,
  `warrantyEnd` datetime DEFAULT NULL,
  `onService` tinyint(1) NOT NULL DEFAULT '0',
  `equipmentModel_guid` binary(16) DEFAULT NULL,
  `contractDivision_guid` binary(16) DEFAULT NULL,
  `rem` varchar(255) DEFAULT NULL,
  `workplace_guid` binary(16) DEFAULT NULL,
  `contract_guid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`guid`),
  KEY `fk_equipment_equipmentModel_IDX` (`equipmentModel_guid`),
  KEY `byOnService` (`onService`),
  KEY `byContractDivision` (`contractDivision_guid`),
  KEY `fk_workplace_IDX` (`workplace_guid`),
  KEY `fk_contract_IDX` (`contract_guid`),
  KEY `combo1` (`onService`,`contractDivision_guid`),
  KEY `combo2` (`contractDivision_guid`,`serviceNumber`),
  KEY `combo3` (`workplace_guid`,`serviceNumber`),
  CONSTRAINT `fk_equipment_contract` FOREIGN KEY (`contract_guid`) REFERENCES `contracts` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_equipment_contractDivision` FOREIGN KEY (`contractDivision_guid`) REFERENCES `contractDivisions` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_equipment_equipmentModel` FOREIGN KEY (`equipmentModel_guid`) REFERENCES `equipmentModels` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_equipment_workplace` FOREIGN KEY (`workplace_guid`) REFERENCES `divisionWorkplaces` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Оборудование клиента';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `equipment_BINS` BEFORE INSERT ON `equipment` FOR EACH ROW BEGIN
	IF (NEW.`contractDivision_guid` IS NOT NULL) THEN 
		SET NEW.`contract_guid` = (SELECT `contract_guid` FROM `contractDivisions` WHERE `guid` = NEW.`contractDivision_guid`);
	END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `equipment_BUPD` BEFORE UPDATE ON `equipment` FOR EACH ROW BEGIN  
  IF (NEW.`contractDivision_guid` IS NOT NULL) THEN
    SET NEW.`contract_guid` = (SELECT `contract_guid` FROM `contractDivisions` WHERE `guid` = NEW.`contractDivision_guid`);
  END IF; 
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `equipmentManufacturers`
--

DROP TABLE IF EXISTS `equipmentManufacturers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipmentManufacturers` (
  `guid` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`guid`),
  UNIQUE KEY `name_UNIQ` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Производители оборудования';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `equipmentModels`
--

DROP TABLE IF EXISTS `equipmentModels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipmentModels` (
  `guid` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `equipmentSubType_guid` binary(16) DEFAULT NULL,
  `equipmentManufacturer_guid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`guid`),
  UNIQUE KEY `name_UNIQ` (`name`,`equipmentSubType_guid`,`equipmentManufacturer_guid`),
  KEY `fk_equipmentModels_equipmentSubType_IDX` (`equipmentSubType_guid`),
  KEY `fk_equipmentModels_equipmentManufacturer_IDX` (`equipmentManufacturer_guid`),
  CONSTRAINT `fk_equipmentModels_equipmentManufacturer` FOREIGN KEY (`equipmentManufacturer_guid`) REFERENCES `equipmentManufacturers` (`guid`) ON UPDATE CASCADE,
  CONSTRAINT `fk_equipmentModels_equipmentSubType` FOREIGN KEY (`equipmentSubType_guid`) REFERENCES `equipmentSubTypes` (`guid`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Модели оборудования';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `equipmentOnServiceLog`
--

DROP TABLE IF EXISTS `equipmentOnServiceLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipmentOnServiceLog` (
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `newState` tinyint(1) NOT NULL DEFAULT '0',
  `equipment_guid` binary(16) NOT NULL,
  `contractDivision_guid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`timestamp`,`equipment_guid`,`newState`),
  KEY `fk_equipmentOnServiceLog_equipment_IDX` (`equipment_guid`),
  KEY `fk_equipmentOnServiceLog_contractDivision_IDX` (`contractDivision_guid`),
  CONSTRAINT `fk_equipmentOnServiceLog_contractDivision` FOREIGN KEY (`contractDivision_guid`) REFERENCES `contractDivisions` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_equipmentOnServiceLog_equipment` FOREIGN KEY (`equipment_guid`) REFERENCES `equipment` (`guid`) ON DELETE NO ACTION ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `equipmentSubTypes`
--

DROP TABLE IF EXISTS `equipmentSubTypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipmentSubTypes` (
  `guid` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `equipmentType_guid` binary(16) NOT NULL,
  PRIMARY KEY (`guid`),
  UNIQUE KEY `name_UNIQ` (`name`,`equipmentType_guid`),
  KEY `fk_eqSubTypes_eqType_IDX` (`equipmentType_guid`),
  CONSTRAINT `fk_eqSubTypes_equipmentType` FOREIGN KEY (`equipmentType_guid`) REFERENCES `equipmentTypes` (`guid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Подтипы оборудования (второй уровень)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `equipmentTypes`
--

DROP TABLE IF EXISTS `equipmentTypes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipmentTypes` (
  `guid` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`guid`),
  UNIQUE KEY `name_UNIQUE` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Типы оборудования (верхний уровень)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `equipmentWorkplaceLog`
--

DROP TABLE IF EXISTS `equipmentWorkplaceLog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipmentWorkplaceLog` (
  `equipment_guid` binary(16) NOT NULL,
  `workplace_guid` binary(16) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`equipment_guid`,`timestamp`),
  KEY `fk_equipment_IDX` (`equipment_guid`),
  KEY `fk_workplace_IDX` (`workplace_guid`),
  CONSTRAINT `fk_equipmentWorkplaceLog_equipment` FOREIGN KEY (`equipment_guid`) REFERENCES `equipment` (`guid`) ON DELETE NO ACTION ON UPDATE CASCADE,
  CONSTRAINT `fk_equipmentWorkplaceLog_workplace` FOREIGN KEY (`workplace_guid`) REFERENCES `divisionWorkplaces` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `partnerDivisions`
--

DROP TABLE IF EXISTS `partnerDivisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partnerDivisions` (
  `contractDivision_guid` binary(16) NOT NULL,
  `partner_guid` binary(16) NOT NULL,
  `update` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`contractDivision_guid`,`partner_guid`),
  KEY `fk_partnerDivisions_contractDivision_IDX` (`contractDivision_guid`),
  KEY `fk_partnerDivisions_partner_IDX` (`partner_guid`),
  CONSTRAINT `fk_partnerDivisions_contractDivision` FOREIGN KEY (`contractDivision_guid`) REFERENCES `contractDivisions` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_partnerDivisions_partner` FOREIGN KEY (`partner_guid`) REFERENCES `partners` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `partners`
--

DROP TABLE IF EXISTS `partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partners` (
  `guid` binary(16) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `address` text,
  PRIMARY KEY (`guid`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Подрядчики';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `plannedRequests`
--

DROP TABLE IF EXISTS `plannedRequests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plannedRequests` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `contractDivision_guid` binary(16) NOT NULL,
  `service_guid` binary(16) DEFAULT NULL,
  `slaLevel` enum('critical','high','medium','low') DEFAULT 'medium',
  `intervalYears` int(11) NOT NULL DEFAULT '0',
  `intervalMonths` int(11) NOT NULL DEFAULT '0',
  `intervalWeeks` int(11) NOT NULL DEFAULT '0',
  `intervalDays` int(11) NOT NULL DEFAULT '0',
  `nextDate` date DEFAULT NULL,
  `preStart` int(10) unsigned DEFAULT NULL,
  `problem` text,
  `partner_guid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_service_idx` (`service_guid`),
  KEY `fk_division_idx` (`contractDivision_guid`),
  KEY `fk_planned_partner_idx` (`partner_guid`),
  CONSTRAINT `fk_contractDivisions` FOREIGN KEY (`contractDivision_guid`) REFERENCES `contractDivisions` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_planned_partner` FOREIGN KEY (`partner_guid`) REFERENCES `partners` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_service` FOREIGN KEY (`service_guid`) REFERENCES `services` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `requestEvents`
--

DROP TABLE IF EXISTS `requestEvents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `requestEvents` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `event` set('open','changeState','changeDate','comment','addDocument','onWait','offWait','unClose','unCancel','eqChange','changePartner','changeContact','changeService') DEFAULT NULL,
  `text` text,
  `newState` set('received','accepted','fixed','repaired','closed','canceled','planned') DEFAULT NULL,
  `request_guid` binary(16) NOT NULL,
  `user_guid` binary(16) DEFAULT NULL,
  `mailed` tinyint(1) unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `byRequest_time_name` (`request_guid`,`timestamp`,`event`),
  KEY `fk_requestEvents_request_IDX` (`request_guid`),
  KEY `fk_requestEvents_user_IDX` (`user_guid`),
  KEY `byMailed` (`mailed`),
  KEY `byTimestamp` (`timestamp`),
  CONSTRAINT `fk_requestEvents_request` FOREIGN KEY (`request_guid`) REFERENCES `requests` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_requestEvents_user` FOREIGN KEY (`user_guid`) REFERENCES `users` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=3384 DEFAULT CHARSET=utf8 COMMENT='Комментарии к заявкам';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `requests`
--

DROP TABLE IF EXISTS `requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `requests` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `guid` binary(16) DEFAULT NULL,
  `problem` text,
  `createdAt` datetime DEFAULT NULL,
  `reactBefore` datetime DEFAULT NULL,
  `reactedAt` datetime DEFAULT NULL,
  `fixBefore` datetime DEFAULT NULL,
  `fixedAt` datetime DEFAULT NULL,
  `repairBefore` datetime DEFAULT NULL,
  `repairedAt` datetime DEFAULT NULL,
  `currentState` enum('received','accepted','fixed','repaired','closed','canceled','planned','preReceived') DEFAULT NULL,
  `stateChangedAt` datetime DEFAULT NULL,
  `contactPerson_guid` binary(16) DEFAULT NULL,
  `contractDivision_guid` binary(16) NOT NULL,
  `slaLevel` enum('critical','high','medium','low') DEFAULT NULL,
  `engineer_guid` binary(16) DEFAULT NULL,
  `equipment_guid` binary(16) DEFAULT NULL,
  `service_guid` binary(16) DEFAULT NULL,
  `onWait` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `alarm` tinyint(1) unsigned DEFAULT '0',
  `solutionProblem` text,
  `solution` text,
  `solutionRecomendation` text,
  `toReact` int(10) unsigned DEFAULT '30',
  `toFix` int(10) unsigned DEFAULT '300',
  `toRepair` int(10) unsigned DEFAULT '3000',
  `reactRate` double DEFAULT NULL,
  `fixRate` double DEFAULT NULL,
  `repairRate` double DEFAULT NULL,
  `num1c` varchar(16) DEFAULT '',
  `syncId` tinyint(1) NOT NULL DEFAULT '1',
  `totalWait` bigint(20) unsigned NOT NULL DEFAULT '0',
  `isPlanned` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `partner_guid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `guid` (`guid`),
  KEY `fk_request_contactPerson_IDX` (`contactPerson_guid`),
  KEY `fk_request_contractDivision_IDX` (`contractDivision_guid`),
  KEY `fk_request_slaLevel_IDX` (`slaLevel`),
  KEY `fk_request_engineer_IDX` (`engineer_guid`),
  KEY `fk_requiest_equipment_IDX` (`equipment_guid`),
  KEY `fk_request_service_IDX` (`service_guid`),
  KEY `byState` (`currentState`),
  KEY `byAlarm` (`alarm`),
  KEY `fk_request_partner_idx` (`partner_guid`),
  CONSTRAINT `fk_request_contactPerson` FOREIGN KEY (`contactPerson_guid`) REFERENCES `users` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_request_contractDivision` FOREIGN KEY (`contractDivision_guid`) REFERENCES `contractDivisions` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_request_engineer` FOREIGN KEY (`engineer_guid`) REFERENCES `users` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_request_equipment` FOREIGN KEY (`equipment_guid`) REFERENCES `equipment` (`guid`) ON DELETE NO ACTION ON UPDATE CASCADE,
  CONSTRAINT `fk_request_partner` FOREIGN KEY (`partner_guid`) REFERENCES `partners` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_request_service` FOREIGN KEY (`service_guid`) REFERENCES `services` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=1548 DEFAULT CHARSET=utf8 COMMENT='Заявка';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
  `guid` binary(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `shortName` varchar(10) NOT NULL,
  `utility` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`guid`),
  UNIQUE KEY `name_UNIQ` (`name`),
  UNIQUE KEY `shortname_UNIQ` (`shortName`),
  KEY `byUtility` (`utility`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `userContractDivisions`
--

DROP TABLE IF EXISTS `userContractDivisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `userContractDivisions` (
  `user_guid` binary(16) NOT NULL,
  `contractDivision_guid` binary(16) NOT NULL,
  `update` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`user_guid`,`contractDivision_guid`),
  KEY `fk_userContractDivision_IDX` (`contractDivision_guid`),
  CONSTRAINT `fk_userContractDivisions_division` FOREIGN KEY (`contractDivision_guid`) REFERENCES `contractDivisions` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_userContractDivisions_user` FOREIGN KEY (`user_guid`) REFERENCES `users` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `userContracts`
--

DROP TABLE IF EXISTS `userContracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `userContracts` (
  `user_guid` binary(16) NOT NULL,
  `contract_guid` binary(16) NOT NULL,
  `update` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`user_guid`,`contract_guid`),
  KEY `fk_userContract_IDX` (`contract_guid`),
  CONSTRAINT `fk_userContracts_contract` FOREIGN KEY (`contract_guid`) REFERENCES `contracts` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_userContracts_user` FOREIGN KEY (`user_guid`) REFERENCES `users` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `guid` binary(16) NOT NULL,
  `lastName` varchar(32) DEFAULT NULL,
  `firstName` varchar(32) DEFAULT NULL,
  `middleName` varchar(32) DEFAULT NULL,
  `login` varchar(32) DEFAULT NULL,
  `passwordHash` varchar(64) DEFAULT NULL,
  `isDisabled` tinyint(1) DEFAULT NULL,
  `rights` enum('client','operator','engineer','admin','partner') DEFAULT NULL,
  `email` varchar(64) DEFAULT NULL,
  `phone` varchar(64) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `partner_guid` binary(16) DEFAULT NULL,
  `loginDB` enum('ldap','mysql') DEFAULT NULL,
  `token` varchar(40) DEFAULT 'new',
  PRIMARY KEY (`guid`),
  UNIQUE KEY `login_UNIQ` (`login`),
  KEY `byPartner` (`partner_guid`),
  KEY `byRights` (`rights`),
  KEY `byActive` (`isDisabled`),
  KEY `byDB` (`loginDB`),
  KEY `byFName` (`firstName`),
  KEY `byLName` (`lastName`),
  KEY `byMName` (`middleName`),
  KEY `byNames` (`lastName`,`firstName`,`middleName`),
  KEY `combo1` (`rights`,`isDisabled`,`loginDB`),
  CONSTRAINT `fk_users_partner` FOREIGN KEY (`partner_guid`) REFERENCES `partners` (`guid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `variables`
--

DROP TABLE IF EXISTS `variables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `variables` (
  `name` varchar(32) NOT NULL,
  `value` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `workCalendar`
--

DROP TABLE IF EXISTS `workCalendar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `workCalendar` (
  `date` date NOT NULL,
  `type` enum('work','weekend') DEFAULT NULL,
  PRIMARY KEY (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'sd-dev-1c'
--
/*!50003 DROP FUNCTION IF EXISTS `calcTime_v2` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` FUNCTION `calcTime_v2`(`reqNum` BIGINT(20), `endDateTime` DATETIME) RETURNS double
BEGIN
	DECLARE createDate, finDate DATE;
	DECLARE createTime, finTime TIME;
	DECLARE cdiv, srv BINARY(16);
    DECLARE sec BIGINT;
	DECLARE sla VARCHAR(16);

	SET finDate = DATE(endDateTime);
	SET finTime = TIME(endDateTime);

	SELECT DATE(`createdAt`), TIME(`createdAt`), `contractDivision_guid`, `service_guid`, `slaLevel`
		INTO createDate, createTime, cdiv, srv, sla
		FROM `requests` 
		WHERE `id` = reqNum;
	
	SELECT SUM(TIME_TO_SEC(TIMEDIFF(IF(`endTime` = '00:00:00', '24:00:00', `endTime`), `startTime`)))
		INTO sec
		FROM (
			SELECT DISTINCT createTime AS `startTime`, `dss`.`endDayTime` AS `endTime`, `wc`.`date` 
				FROM `contractDivisions` AS `cd`
				LEFT JOIN `contracts` AS `c` ON `c`.`guid` = `cd`.`contract_guid`
				LEFT JOIN `contractServices` AS `cs` ON `cs`.`contract_guid` = `c`.`guid`
				LEFT JOIN `divServicesSLA` AS `dss` ON `dss`.`contract_guid` = `c`.`guid` 
					AND `cd`.`type_guid` = `dss`.`divType_guid`
				LEFT JOIN `workCalendar` AS `wc` ON FIND_IN_SET(`wc`.`type`, `dss`.`dayType`)
				WHERE `cd`.`guid` = cdiv AND `dss`.`service_guid` = srv AND `dss`.`slaLevel` = sla 
					AND `wc`.`date` = createDate 
					AND (`dss`.`endDayTime` > createTime OR `dss`.`endDayTime` = '00:00:00')
			UNION SELECT `dss`.`startDayTime`, `dss`.`endDayTime`, `wc`.`date` 
				FROM `contractDivisions` AS `cd`
				LEFT JOIN `contracts` AS `c` ON `c`.`guid` = `cd`.`contract_guid`
				LEFT JOIN `contractServices` AS `cs` ON `cs`.`contract_guid` = `c`.`guid`
				LEFT JOIN `divServicesSLA` AS `dss` ON `dss`.`contract_guid` = `c`.`guid` 
					AND `cd`.`type_guid` = `dss`.`divType_guid`
				LEFT JOIN `workCalendar` AS `wc` ON FIND_IN_SET(`wc`.`type`, `dss`.`dayType`)
				WHERE `cd`.`guid` = cdiv AND `dss`.`service_guid` = srv AND `dss`.`slaLevel` = sla 
					AND `wc`.`date` > createDate AND `wc`.`date` < finDate
			UNION SELECT `dss`.`startDayTime`, finTime, `wc`.`date` 
				FROM `contractDivisions` AS `cd`
				LEFT JOIN `contracts` AS `c` ON `c`.`guid` = `cd`.`contract_guid`
				LEFT JOIN `contractServices` AS `cs` ON `cs`.`contract_guid` = `c`.`guid`
				LEFT JOIN `divServicesSLA` AS `dss` ON `dss`.`contract_guid` = `c`.`guid` 
					AND `cd`.`type_guid` = `dss`.`divType_guid`
				LEFT JOIN `workCalendar` AS `wc` ON FIND_IN_SET(`wc`.`type`, `dss`.`dayType`)
				WHERE `cd`.`guid` = cdiv AND `dss`.`service_guid` = srv AND `dss`.`slaLevel` = sla 
					AND `wc`.`date` = finDate AND `dss`.`startDayTime` < finTime
		) AS `d`;
	RETURN sec/60.;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `calcTime_v3` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` FUNCTION `calcTime_v3`(`reqNum` BIGINT(20), `endDateTime` DATETIME) RETURNS double
BEGIN
	DECLARE createDate, finDate DATE;
	DECLARE createTime, finTime TIME;
	DECLARE cdiv, srv BINARY(16);
    DECLARE sec BIGINT;
	DECLARE sla VARCHAR(16);

	SET finDate = DATE(endDateTime);
	SET finTime = TIME(endDateTime);

	SELECT DATE(`createdAt`), TIME(`createdAt`), `contractDivision_guid`, `service_guid`, `slaLevel`
		INTO createDate, createTime, cdiv, srv, sla
		FROM `requests` 
		WHERE `id` = reqNum;
	
	SELECT SUM(TIME_TO_SEC(TIMEDIFF(IF(`endTime` = '00:00:00', '24:00:00', `endTime`), `startTime`)))
		INTO sec
		FROM (
			SELECT DISTINCT IF(createTime > `dss`.`startDayTime`, createTime, `dss`.`startDayTime`) AS `startTime`, 
							IF (`dss`.`endDayTime` = '00:00:00' OR finTime > `dss`.`endDayTime`, `dss`.`endDayTime`, finTime) AS `endTime`, 
                            `wc`.`date` 
				FROM `contractDivisions` AS `cd`
				LEFT JOIN `contracts` AS `c` ON `c`.`guid` = `cd`.`contract_guid`
				LEFT JOIN `contractServices` AS `cs` ON `cs`.`contract_guid` = `c`.`guid`
				LEFT JOIN `divServicesSLA` AS `dss` ON `dss`.`contract_guid` = `c`.`guid` 
					AND `cd`.`type_guid` = `dss`.`divType_guid`
				LEFT JOIN `workCalendar` AS `wc` ON FIND_IN_SET(`wc`.`type`, `dss`.`dayType`)
				WHERE finDate = createDate AND `cd`.`guid` = cdiv AND `dss`.`service_guid` = srv 
					AND `dss`.`slaLevel` = sla AND `wc`.`date` = createDate 
                    AND (createTime < `dss`.`endDayTime` OR `dss`.`endDayTime` = '00:00:00')
                    AND finTime > `dss`.`startDayTime`
			UNION SELECT IF(createTime > `dss`.`startDayTime`, createTime, `dss`.`startDayTime`) AS `startTime`, 
						`dss`.`endDayTime` AS `endTime`, `wc`.`date` 
				FROM `contractDivisions` AS `cd`
				LEFT JOIN `contracts` AS `c` ON `c`.`guid` = `cd`.`contract_guid`
				LEFT JOIN `contractServices` AS `cs` ON `cs`.`contract_guid` = `c`.`guid`
				LEFT JOIN `divServicesSLA` AS `dss` ON `dss`.`contract_guid` = `c`.`guid` 
					AND `cd`.`type_guid` = `dss`.`divType_guid`
				LEFT JOIN `workCalendar` AS `wc` ON FIND_IN_SET(`wc`.`type`, `dss`.`dayType`)
				WHERE finDate != createDate AND `cd`.`guid` = cdiv AND `dss`.`service_guid` = srv 
					AND `dss`.`slaLevel` = sla AND `wc`.`date` = createDate 
                    AND (createTime < `dss`.`endDayTime` OR `dss`.`endDayTime` = '00:00:00')
			UNION SELECT `dss`.`startDayTime`, `dss`.`endDayTime`, `wc`.`date` 
				FROM `contractDivisions` AS `cd`
				LEFT JOIN `contracts` AS `c` ON `c`.`guid` = `cd`.`contract_guid`
				LEFT JOIN `contractServices` AS `cs` ON `cs`.`contract_guid` = `c`.`guid`
				LEFT JOIN `divServicesSLA` AS `dss` ON `dss`.`contract_guid` = `c`.`guid` 
					AND `cd`.`type_guid` = `dss`.`divType_guid`
				LEFT JOIN `workCalendar` AS `wc` ON FIND_IN_SET(`wc`.`type`, `dss`.`dayType`)
				WHERE `cd`.`guid` = cdiv AND `dss`.`service_guid` = srv AND `dss`.`slaLevel` = sla 
					AND `wc`.`date` > createDate AND `wc`.`date` < finDate
			UNION SELECT `dss`.`startDayTime`, 
						IF (`dss`.`endDayTime` = '00:00:00' OR finTime > `dss`.`endDayTime`, `dss`.`endDayTime`, finTime) AS `endTime`,
                        `wc`.`date` 
				FROM `contractDivisions` AS `cd`
				LEFT JOIN `contracts` AS `c` ON `c`.`guid` = `cd`.`contract_guid`
				LEFT JOIN `contractServices` AS `cs` ON `cs`.`contract_guid` = `c`.`guid`
				LEFT JOIN `divServicesSLA` AS `dss` ON `dss`.`contract_guid` = `c`.`guid` 
					AND `cd`.`type_guid` = `dss`.`divType_guid`
				LEFT JOIN `workCalendar` AS `wc` ON FIND_IN_SET(`wc`.`type`, `dss`.`dayType`)
				WHERE finDate != createDate AND `cd`.`guid` = cdiv AND `dss`.`service_guid` = srv 
					AND `dss`.`slaLevel` = sla AND `wc`.`date` = finDate AND `dss`.`startDayTime` < finTime
                    AND finTime > `dss`.`startDayTime`
		) AS `d`;
	RETURN sec/60.;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2017-04-28 11:08:09
