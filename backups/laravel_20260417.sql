-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: localhost    Database: laravel
-- ------------------------------------------------------
-- Server version	8.0.45

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
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `causer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `attribute_changes` json DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `batch_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
INSERT INTO `activity_log` VALUES (41,'users','created','App\\Models\\User','created',37,NULL,NULL,'{\"attributes\": {\"name\": \"Administrador Barbería\", \"email\": \"al222310427@gmail.com\"}}','[]',NULL,'2026-04-15 17:23:03','2026-04-15 17:23:03'),(42,'users','created','App\\Models\\User','created',39,'App\\Models\\User',37,'{\"attributes\": {\"name\": \"VILCHIS\", \"email\": \"alan.ruiz@gmail.com\"}}','[]',NULL,'2026-04-15 17:52:34','2026-04-15 17:52:34'),(43,'appointments','created','App\\Models\\Appointment','created',8,'App\\Models\\User',37,'{\"attributes\": {\"fecha\": \"2026-04-15T06:00:00.000000Z\", \"notas\": null, \"estado\": \"pendiente\", \"hora_fin\": \"21:00:00\", \"barber_id\": 9, \"client_id\": 13, \"service_id\": 9, \"hora_inicio\": \"18:00:00\", \"cancelada_en\": null, \"precio_cobrado\": null, \"reminder_2h_sent_at\": null, \"confirmation_sent_at\": null, \"reminder_24h_sent_at\": null, \"motivo_reagendamiento\": null, \"cancellation_notified_at\": null}}','[]',NULL,'2026-04-15 17:57:31','2026-04-15 17:57:31'),(44,'appointments','updated','App\\Models\\Appointment','updated',8,'App\\Models\\User',37,'{\"old\": {\"hora_fin\": \"21:00\", \"hora_inicio\": \"18:00\", \"confirmation_sent_at\": null}, \"attributes\": {\"hora_fin\": \"21:00:00\", \"hora_inicio\": \"18:00:00\", \"confirmation_sent_at\": \"2026-04-15T23:57:33.000000Z\"}}','[]',NULL,'2026-04-15 17:57:33','2026-04-15 17:57:33'),(45,'appointments','created','App\\Models\\Appointment','created',9,'App\\Models\\User',30,'{\"attributes\": {\"fecha\": \"2026-04-28T06:00:00.000000Z\", \"notas\": null, \"estado\": \"pendiente\", \"hora_fin\": \"12:39:00\", \"barber_id\": 10, \"client_id\": 9, \"service_id\": 9, \"hora_inicio\": \"10:00:00\", \"cancelada_en\": null, \"precio_cobrado\": null, \"reminder_2h_sent_at\": null, \"confirmation_sent_at\": null, \"reminder_24h_sent_at\": null, \"motivo_reagendamiento\": null, \"cancellation_notified_at\": null}}','[]',NULL,'2026-04-15 18:06:57','2026-04-15 18:06:57'),(46,'appointments','updated','App\\Models\\Appointment','updated',9,'App\\Models\\User',30,'{\"old\": {\"hora_inicio\": \"10:00\", \"confirmation_sent_at\": null}, \"attributes\": {\"hora_inicio\": \"10:00:00\", \"confirmation_sent_at\": \"2026-04-16T00:06:58.000000Z\"}}','[]',NULL,'2026-04-15 18:06:58','2026-04-15 18:06:58'),(47,'appointments','created','App\\Models\\Appointment','created',10,'App\\Models\\User',27,'{\"attributes\": {\"fecha\": \"2026-04-15T06:00:00.000000Z\", \"notas\": null, \"estado\": \"pendiente\", \"hora_fin\": \"22:29:00\", \"barber_id\": 9, \"client_id\": 10, \"service_id\": 9, \"hora_inicio\": \"21:29:00\", \"cancelada_en\": null, \"precio_cobrado\": null, \"reminder_2h_sent_at\": null, \"confirmation_sent_at\": null, \"reminder_24h_sent_at\": null, \"motivo_reagendamiento\": null, \"cancellation_notified_at\": null}}','[]',NULL,'2026-04-15 19:29:39','2026-04-15 19:29:39'),(48,'appointments','updated','App\\Models\\Appointment','updated',10,'App\\Models\\User',27,'{\"old\": {\"hora_fin\": \"22:29\", \"hora_inicio\": \"21:29\", \"confirmation_sent_at\": null}, \"attributes\": {\"hora_fin\": \"22:29:00\", \"hora_inicio\": \"21:29:00\", \"confirmation_sent_at\": \"2026-04-16T01:29:39.000000Z\"}}','[]',NULL,'2026-04-15 19:29:39','2026-04-15 19:29:39'),(49,'appointments','updated','App\\Models\\Appointment','updated',10,NULL,NULL,'{\"old\": {\"reminder_2h_sent_at\": null}, \"attributes\": {\"reminder_2h_sent_at\": \"2026-04-16T01:30:28.000000Z\"}}','[]',NULL,'2026-04-15 19:30:29','2026-04-15 19:30:29'),(50,'appointments','updated','App\\Models\\Appointment','updated',8,'App\\Models\\User',29,'{\"old\": {\"estado\": \"pendiente\"}, \"attributes\": {\"estado\": \"en_proceso\"}}','[]',NULL,'2026-04-15 22:07:48','2026-04-15 22:07:48'),(51,'appointments','updated','App\\Models\\Appointment','updated',10,'App\\Models\\User',29,'{\"old\": {\"estado\": \"pendiente\"}, \"attributes\": {\"estado\": \"en_proceso\"}}','[]',NULL,'2026-04-15 22:07:51','2026-04-15 22:07:51'),(52,'chatbot','chatbot_intelligence_error',NULL,NULL,NULL,'App\\Models\\User',29,'[]','{\"error\": \"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'tipo\' in \'where clause\' (Connection: mysql, Host: mysql, Port: 3306, Database: laravel, SQL: select `works`.*, (select count(*) from `reactions` where `works`.`id` = `reactions`.`work_id` and `tipo` = like) as `reactions_count`, (select count(*) from `comments` where `works`.`id` = `comments`.`work_id`) as `comments_count` from `works` where `created_at` >= 2026-01-15 22:42:46 order by `reactions_count` desc limit 5)\", \"message\": \"ver mi próxima cita\", \"user_id\": 29}',NULL,'2026-04-15 22:42:46','2026-04-15 22:42:46'),(53,'chatbot','chatbot_provider_telemetry',NULL,NULL,NULL,'App\\Models\\User',29,'[]','{\"error\": \"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'tipo\' in \'where clause\' (Connection: mysql, Host: mysql, Port: 3306, Database: laravel, SQL: select `works`.*, (select count(*) from `reactions` where `works`.`id` = `reactions`.`work_id` and `tipo` = like) as `reactions_count`, (select count(*) from `comments` where `works`.`id` = `comments`.`work_id`) as `comments_count` from `works` where `created_at` >= 2026-01-15 22:42:46 order by `reactions_count` desc limit 5)\", \"source\": \"intelligence\", \"status\": \"error\", \"user_id\": 29, \"latency_ms\": 161}',NULL,'2026-04-15 22:42:46','2026-04-15 22:42:46'),(54,'chatbot','chatbot_provider_telemetry',NULL,NULL,NULL,'App\\Models\\User',29,'[]','{\"intent\": \"cita\", \"source\": \"manual\", \"status\": \"success\", \"user_id\": 29, \"latency_ms\": 270}',NULL,'2026-04-15 22:42:47','2026-04-15 22:42:47'),(55,'chatbot','chatbot_intelligence_error',NULL,NULL,NULL,'App\\Models\\User',29,'[]','{\"error\": \"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'tipo\' in \'where clause\' (Connection: mysql, Host: mysql, Port: 3306, Database: laravel, SQL: select `works`.*, (select count(*) from `reactions` where `works`.`id` = `reactions`.`work_id` and `tipo` = like) as `reactions_count`, (select count(*) from `comments` where `works`.`id` = `comments`.`work_id`) as `comments_count` from `works` where `created_at` >= 2026-01-15 22:42:51 order by `reactions_count` desc limit 5)\", \"message\": \"¿cómo cambio mi contraseña?\", \"user_id\": 29}',NULL,'2026-04-15 22:42:51','2026-04-15 22:42:51'),(56,'chatbot','chatbot_provider_telemetry',NULL,NULL,NULL,'App\\Models\\User',29,'[]','{\"error\": \"SQLSTATE[42S22]: Column not found: 1054 Unknown column \'tipo\' in \'where clause\' (Connection: mysql, Host: mysql, Port: 3306, Database: laravel, SQL: select `works`.*, (select count(*) from `reactions` where `works`.`id` = `reactions`.`work_id` and `tipo` = like) as `reactions_count`, (select count(*) from `comments` where `works`.`id` = `comments`.`work_id`) as `comments_count` from `works` where `created_at` >= 2026-01-15 22:42:51 order by `reactions_count` desc limit 5)\", \"source\": \"intelligence\", \"status\": \"error\", \"user_id\": 29, \"latency_ms\": 93}',NULL,'2026-04-15 22:42:51','2026-04-15 22:42:51'),(57,'chatbot','chatbot_provider_telemetry',NULL,NULL,NULL,'App\\Models\\User',29,'[]','{\"model\": \"gemini-2.0-flash\", \"source\": \"gemini\", \"status\": \"success\", \"user_id\": 29, \"latency_ms\": 9946, \"estimated_cost_usd\": 0.000101, \"input_tokens_estimate\": 277, \"total_tokens_estimate\": 288, \"output_tokens_estimate\": 11}',NULL,'2026-04-15 22:43:01','2026-04-15 22:43:01');
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `barber_id` bigint unsigned NOT NULL,
  `service_id` bigint unsigned NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `estado` enum('pendiente','confirmada','en_proceso','completada','cancelada','no_asistio') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `precio_cobrado` decimal(10,2) DEFAULT NULL,
  `motivo_reagendamiento` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelada_en` timestamp NULL DEFAULT NULL,
  `confirmation_sent_at` timestamp NULL DEFAULT NULL,
  `reminder_24h_sent_at` timestamp NULL DEFAULT NULL,
  `reminder_2h_sent_at` timestamp NULL DEFAULT NULL,
  `cancellation_notified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `appointments_service_id_foreign` (`service_id`),
  KEY `appointments_barber_id_fecha_hora_inicio_index` (`barber_id`,`fecha`,`hora_inicio`),
  KEY `appointments_client_id_fecha_index` (`client_id`,`fecha`),
  CONSTRAINT `appointments_barber_id_foreign` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `appointments_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` VALUES (8,13,9,9,'2026-04-15','18:00:00','21:00:00','en_proceso',NULL,NULL,NULL,NULL,'2026-04-15 17:57:33',NULL,NULL,NULL,'2026-04-15 17:57:31','2026-04-15 22:07:48',NULL),(9,9,10,9,'2026-04-28','10:00:00','12:39:00','pendiente',NULL,NULL,NULL,NULL,'2026-04-15 18:06:58',NULL,NULL,NULL,'2026-04-15 18:06:56','2026-04-15 18:06:58',NULL),(10,10,9,9,'2026-04-15','21:29:00','22:29:00','en_proceso',NULL,NULL,NULL,NULL,'2026-04-15 19:29:39',NULL,'2026-04-15 19:30:28',NULL,'2026-04-15 19:29:39','2026-04-15 22:07:51',NULL);
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `barber_schedules`
--

DROP TABLE IF EXISTS `barber_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `barber_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `barber_id` bigint unsigned NOT NULL,
  `day_of_week` tinyint NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_working` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `barber_schedules_barber_id_day_of_week_unique` (`barber_id`,`day_of_week`),
  CONSTRAINT `barber_schedules_barber_id_foreign` FOREIGN KEY (`barber_id`) REFERENCES `barbers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `barber_schedules`
--

LOCK TABLES `barber_schedules` WRITE;
/*!40000 ALTER TABLE `barber_schedules` DISABLE KEYS */;
INSERT INTO `barber_schedules` VALUES (1,9,0,NULL,NULL,0,'2026-04-15 17:17:31','2026-04-15 17:53:51'),(2,9,1,'09:00:00','21:00:00',1,'2026-04-15 17:17:31','2026-04-15 17:53:51'),(3,9,2,'09:00:00','21:00:00',1,'2026-04-15 17:17:31','2026-04-15 17:17:31'),(4,9,3,'09:00:00','21:00:00',1,'2026-04-15 17:17:31','2026-04-15 17:53:51'),(5,9,4,'09:00:00','21:00:00',1,'2026-04-15 17:17:31','2026-04-15 17:17:31'),(6,9,5,'09:00:00','21:00:00',1,'2026-04-15 17:17:31','2026-04-15 17:17:31'),(7,9,6,'09:00:00','21:00:00',1,'2026-04-15 17:17:31','2026-04-15 17:17:31'),(8,10,1,'09:00:00','21:00:00',1,'2026-04-15 17:53:51','2026-04-15 17:53:51'),(9,10,2,'09:00:00','21:00:00',1,'2026-04-15 17:53:51','2026-04-15 17:53:51'),(10,10,3,'09:00:00','21:00:00',1,'2026-04-15 17:53:51','2026-04-15 17:53:51'),(11,10,4,'09:00:00','21:00:00',1,'2026-04-15 17:53:51','2026-04-15 17:53:51'),(12,10,5,'09:00:00','21:00:00',1,'2026-04-15 17:53:51','2026-04-15 17:53:51'),(13,10,6,'09:00:00','21:00:00',1,'2026-04-15 17:53:51','2026-04-15 17:53:51'),(14,10,0,NULL,NULL,0,'2026-04-15 17:53:51','2026-04-15 17:53:51'),(15,11,1,'09:00:00','21:00:00',1,'2026-04-15 17:53:51','2026-04-15 17:53:51'),(16,11,2,'09:00:00','21:00:00',1,'2026-04-15 17:53:51','2026-04-15 17:53:51'),(17,11,3,'09:00:00','21:00:00',1,'2026-04-15 17:53:51','2026-04-15 17:53:51'),(18,11,4,'09:00:00','21:00:00',1,'2026-04-15 17:53:51','2026-04-15 17:53:51'),(19,11,5,'09:00:00','21:00:00',1,'2026-04-15 17:53:51','2026-04-15 17:53:51'),(20,11,6,'09:00:00','21:00:00',1,'2026-04-15 17:53:51','2026-04-15 17:53:51'),(21,11,0,NULL,NULL,0,'2026-04-15 17:53:51','2026-04-15 17:53:51');
/*!40000 ALTER TABLE `barber_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `barbers`
--

DROP TABLE IF EXISTS `barbers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `barbers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `especialidades` text COLLATE utf8mb4_unicode_ci,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `barbers_user_id_unique` (`user_id`),
  CONSTRAINT `barbers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `barbers`
--

LOCK TABLES `barbers` WRITE;
/*!40000 ALTER TABLE `barbers` DISABLE KEYS */;
INSERT INTO `barbers` VALUES (9,29,'Fade, Barba','barbers/29/15/04/2026/cddcbff9-42ec-4209-bc8b-d724fd474b83.png','Barbero de pruebas automatizadas',1,'2026-04-15 17:10:19','2026-04-15 17:32:53'),(10,32,'Corte clásico',NULL,'Barbero de pruebas 1',1,'2026-04-15 17:10:20','2026-04-15 17:10:20'),(11,35,'Corte clásico',NULL,'Barbero de pruebas 2',1,'2026-04-15 17:10:21','2026-04-15 17:10:21');
/*!40000 ALTER TABLE `barbers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `barbershop_settings`
--

DROP TABLE IF EXISTS `barbershop_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `barbershop_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `horario_apertura` time DEFAULT NULL,
  `horario_cierre` time DEFAULT NULL,
  `politica_cancelacion` int unsigned NOT NULL DEFAULT '24',
  `redes_sociales` json DEFAULT NULL,
  `maintenance_mode` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `barbershop_settings`
--

LOCK TABLES `barbershop_settings` WRITE;
/*!40000 ALTER TABLE `barbershop_settings` DISABLE KEYS */;
INSERT INTO `barbershop_settings` VALUES (1,'BarberPro Elite',NULL,NULL,NULL,'09:00:00','21:00:00',24,NULL,0,'2026-04-15 17:53:50','2026-04-15 17:53:50'),(7,'Laravel',NULL,NULL,NULL,NULL,NULL,24,NULL,0,'2026-04-15 17:23:37','2026-04-15 17:23:37'),(8,'Laravel',NULL,NULL,NULL,NULL,NULL,24,NULL,0,'2026-04-15 17:28:24','2026-04-15 17:28:24'),(9,'Laravel',NULL,NULL,NULL,NULL,NULL,24,NULL,0,'2026-04-15 17:28:26','2026-04-15 17:28:26'),(10,'Laravel',NULL,NULL,NULL,NULL,NULL,24,NULL,0,'2026-04-15 17:39:01','2026-04-15 17:39:01');
/*!40000 ALTER TABLE `barbershop_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('laravel-cache-chatbot_feedback_29','a:2:{i:0;a:5:{s:8:\"question\";s:20:\"ver mi próxima cita\";s:8:\"response\";s:169:\"📅 Para agendar una cita:\n1. Regístrate o inicia sesión\n2. Ve a la sección \'Citas\'\n3. Selecciona barbero, servicio y horario\n4. Confirma tu reserva\n\n¡Te esperamos!\";s:7:\"helpful\";b:1;s:10:\"confidence\";d:0.8;s:9:\"timestamp\";s:25:\"2026-04-15T22:42:47-06:00\";}i:1;a:5:{s:8:\"question\";s:30:\"¿cómo cambio mi contraseña?\";s:8:\"response\";s:41:\"Error de conexión con el servicio de IA.\";s:7:\"helpful\";b:1;s:10:\"confidence\";d:0.8;s:9:\"timestamp\";s:25:\"2026-04-15T22:43:01-06:00\";}}',1778906581),('laravel-cache-chatbot_learned_questions','a:2:{s:4:\"cita\";a:1:{i:0;a:5:{s:8:\"question\";s:20:\"ver mi próxima cita\";s:10:\"normalized\";s:19:\"ver mi proxima cita\";s:10:\"confidence\";d:0.9;s:10:\"learned_at\";s:25:\"2026-04-15T22:42:46-06:00\";s:9:\"frequency\";i:1;}}s:7:\"general\";a:1:{i:0;a:5:{s:8:\"question\";s:30:\"¿cómo cambio mi contraseña?\";s:10:\"normalized\";s:25:\"como cambio mi contrasena\";s:10:\"confidence\";d:0.9;s:10:\"learned_at\";s:25:\"2026-04-15T22:42:51-06:00\";s:9:\"frequency\";i:1;}}}',1778906571),('laravel-cache-chatbot_profile_29','a:9:{s:7:\"user_id\";i:29;s:18:\"conversation_style\";s:21:\"professional_friendly\";s:16:\"topics_discussed\";a:2:{i:0;s:20:\"ver mi próxima cita\";i:1;s:30:\"¿cómo cambio mi contraseña?\";}s:11:\"last_intent\";s:7:\"general\";s:16:\"context_strength\";s:4:\"high\";s:13:\"response_tone\";s:12:\"professional\";s:11:\"preferences\";a:0:{}s:10:\"created_at\";s:25:\"2026-04-15T22:42:46-06:00\";s:12:\"last_updated\";s:25:\"2026-04-15T22:43:01-06:00\";}',1776919381),('laravel-cache-chatbot:user:29','i:2;',1776314626),('laravel-cache-chatbot:user:29:timer','i:1776314626;',1776314626),('laravel-cache-spatie.permission.cache','a:3:{s:5:\"alias\";a:4:{s:1:\"a\";s:2:\"id\";s:1:\"b\";s:4:\"name\";s:1:\"c\";s:10:\"guard_name\";s:1:\"r\";s:5:\"roles\";}s:11:\"permissions\";a:13:{i:0;a:4:{s:1:\"a\";i:222;s:1:\"b\";s:13:\"dashboard.ver\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:4:{i:0;i:69;i:1;i:70;i:2;i:71;i:3;i:72;}}i:1;a:4:{s:1:\"a\";i:223;s:1:\"b\";s:18:\"usuarios.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:69;}}i:2;a:4:{s:1:\"a\";i:224;s:1:\"b\";s:18:\"barberos.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:69;}}i:3;a:4:{s:1:\"a\";i:225;s:1:\"b\";s:18:\"clientes.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:69;i:1;i:71;}}i:4;a:4:{s:1:\"a\";i:226;s:1:\"b\";s:19:\"servicios.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:69;}}i:5;a:4:{s:1:\"a\";i:227;s:1:\"b\";s:15:\"citas.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:69;i:1;i:71;}}i:6;a:4:{s:1:\"a\";i:228;s:1:\"b\";s:17:\"citas.ver_propias\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:69;i:1;i:70;}}i:7;a:4:{s:1:\"a\";i:229;s:1:\"b\";s:15:\"pagos.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:69;i:1;i:71;}}i:8;a:4:{s:1:\"a\";i:230;s:1:\"b\";s:14:\"inventario.ver\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:69;i:1;i:71;}}i:9;a:4:{s:1:\"a\";i:231;s:1:\"b\";s:20:\"inventario.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:69;}}i:10;a:4:{s:1:\"a\";i:232;s:1:\"b\";s:12:\"reportes.ver\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:69;i:1;i:71;}}i:11;a:4:{s:1:\"a\";i:233;s:1:\"b\";s:23:\"configuracion.gestionar\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:69;}}i:12;a:4:{s:1:\"a\";i:234;s:1:\"b\";s:8:\"logs.ver\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:69;}}}s:5:\"roles\";a:4:{i:0;a:3:{s:1:\"a\";i:69;s:1:\"b\";s:13:\"administrador\";s:1:\"c\";s:3:\"web\";}i:1;a:3:{s:1:\"a\";i:70;s:1:\"b\";s:7:\"barbero\";s:1:\"c\";s:3:\"web\";}i:2;a:3:{s:1:\"a\";i:71;s:1:\"b\";s:13:\"recepcionista\";s:1:\"c\";s:3:\"web\";}i:3;a:3:{s:1:\"a\";i:72;s:1:\"b\";s:7:\"cliente\";s:1:\"c\";s:3:\"web\";}}}',1776386813);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `preferencias_notificacion` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clients_user_id_unique` (`user_id`),
  CONSTRAINT `clients_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (9,30,'+521234567890','1990-01-01','{\"sms\": false, \"email\": true, \"in_app\": true, \"whatsapp\": true}','2026-04-15 17:10:19','2026-04-15 17:10:19'),(10,33,'+521234567891','1991-02-01','{\"sms\": false, \"email\": true, \"in_app\": true, \"whatsapp\": false}','2026-04-15 17:10:20','2026-04-15 17:10:20'),(11,36,'+521234567892','1992-02-01','{\"sms\": false, \"email\": true, \"in_app\": true, \"whatsapp\": false}','2026-04-15 17:10:21','2026-04-15 17:26:26'),(12,38,'+521234567892','1992-02-01','{\"sms\": false, \"email\": true, \"in_app\": true, \"whatsapp\": true}','2026-04-15 17:47:00','2026-04-15 17:47:00'),(13,39,'7225013456','2005-02-15','{\"sms\": false, \"email\": true, \"in_app\": true, \"whatsapp\": false}','2026-04-15 17:52:34','2026-04-15 17:53:08');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `combo_service`
--

DROP TABLE IF EXISTS `combo_service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `combo_service` (
  `combo_id` bigint unsigned NOT NULL,
  `service_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`combo_id`,`service_id`),
  KEY `combo_service_service_id_foreign` (`service_id`),
  CONSTRAINT `combo_service_combo_id_foreign` FOREIGN KEY (`combo_id`) REFERENCES `service_combos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `combo_service_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `combo_service`
--

LOCK TABLES `combo_service` WRITE;
/*!40000 ALTER TABLE `combo_service` DISABLE KEYS */;
/*!40000 ALTER TABLE `combo_service` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` tinyint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comments_work_id_foreign` (`work_id`),
  KEY `comments_user_id_foreign` (`user_id`),
  CONSTRAINT `comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_work_id_foreign` FOREIGN KEY (`work_id`) REFERENCES `works` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comments`
--

LOCK TABLES `comments` WRITE;
/*!40000 ALTER TABLE `comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventories`
--

DROP TABLE IF EXISTS `inventories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `min_stock` int NOT NULL DEFAULT '1',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `description` text COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `imagen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventories`
--

LOCK TABLES `inventories` WRITE;
/*!40000 ALTER TABLE `inventories` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_movements`
--

DROP TABLE IF EXISTS `inventory_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `tipo` enum('entrada','salida') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` int unsigned NOT NULL,
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `appointment_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_movements_product_id_foreign` (`product_id`),
  KEY `inventory_movements_appointment_id_foreign` (`appointment_id`),
  KEY `inventory_movements_user_id_foreign` (`user_id`),
  KEY `inventory_movements_tipo_fecha_index` (`tipo`,`fecha`),
  CONSTRAINT `inventory_movements_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_movements_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_movements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_movements`
--

LOCK TABLES `inventory_movements` WRITE;
/*!40000 ALTER TABLE `inventory_movements` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_03_02_000110_create_barbers_and_clients_tables',1),(5,'2026_03_02_000120_create_services_and_combos_tables',1),(6,'2026_03_02_000130_create_appointments_table',1),(7,'2026_03_02_000140_create_payments_table',1),(8,'2026_03_02_000150_create_products_and_inventory_movements_tables',1),(9,'2026_03_02_000160_create_notifications_activity_logs_and_settings_tables',1),(10,'2026_03_03_025113_create_permission_tables',1),(11,'2026_03_03_025127_create_activity_log_table',1),(12,'2026_03_03_025128_add_event_column_to_activity_log_table',1),(13,'2026_03_03_025129_add_batch_uuid_column_to_activity_log_table',1),(14,'2026_03_03_040000_add_notification_tracking_to_appointments_table',1),(15,'2026_03_08_162605_create_inventories_table',1),(16,'2026_03_08_165957_create_works_table',1),(17,'2026_03_08_170001_create_comments_table',1),(18,'2026_03_08_170108_create_reactions_table',1),(19,'2026_03_08_171000_create_work_images_table',1),(20,'2026_03_08_173345_add_maintenance_mode_to_barbershop_settings_table',1),(21,'2026_03_08_183234_create_saved_works_table',1),(22,'2026_03_08_185250_add_imagen_to_products_table',1),(23,'2026_03_08_185825_add_imagen_to_inventories_table',1),(24,'2026_03_08_191459_create_barber_schedules_table',1),(25,'2026_04_01_000000_create_mobile_api_tokens_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mobile_api_tokens`
--

DROP TABLE IF EXISTS `mobile_api_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mobile_api_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` json DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mobile_api_tokens_token_hash_unique` (`token_hash`),
  KEY `mobile_api_tokens_user_id_name_index` (`user_id`,`name`),
  CONSTRAINT `mobile_api_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mobile_api_tokens`
--

LOCK TABLES `mobile_api_tokens` WRITE;
/*!40000 ALTER TABLE `mobile_api_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `mobile_api_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (69,'App\\Models\\User',27),(71,'App\\Models\\User',28),(70,'App\\Models\\User',29),(72,'App\\Models\\User',30),(71,'App\\Models\\User',31),(70,'App\\Models\\User',32),(72,'App\\Models\\User',33),(71,'App\\Models\\User',34),(70,'App\\Models\\User',35),(72,'App\\Models\\User',36),(69,'App\\Models\\User',37),(72,'App\\Models\\User',38),(72,'App\\Models\\User',39);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES ('351c4915-f206-42d9-be5a-6b9f987fc741','App\\Notifications\\AppointmentNotification','App\\Models\\User',39,'{\"type\":\"appointment\",\"appointment_id\":8,\"subject\":\"Confirmaci\\u00f3n de cita\",\"title\":\"Tu cita fue registrada\",\"message\":\"Tu cita fue confirmada en el sistema.\",\"fecha\":\"2026-04-15\",\"hora_inicio\":\"18:00:00\",\"hora_fin\":\"21:00:00\"}',NULL,'2026-04-15 17:57:39','2026-04-15 17:57:39'),('3a6a08e6-1a8c-47e4-bb6e-8bc47ccadda4','App\\Notifications\\AppointmentNotification','App\\Models\\User',30,'{\"type\":\"appointment\",\"appointment_id\":9,\"subject\":\"Confirmaci\\u00f3n de cita\",\"title\":\"Tu cita fue registrada\",\"message\":\"Tu cita fue confirmada en el sistema.\",\"fecha\":\"2026-04-28\",\"hora_inicio\":\"10:00:00\",\"hora_fin\":\"12:39:00\"}',NULL,'2026-04-15 18:07:00','2026-04-15 18:07:00'),('9193a526-58ca-440d-989d-c1e4a4031748','App\\Notifications\\AppointmentNotification','App\\Models\\User',33,'{\"type\":\"appointment\",\"appointment_id\":10,\"subject\":\"Recordatorio de cita (2h)\",\"title\":\"Tu cita es en 2 horas\",\"message\":\"Te recordamos que tu cita inicia aproximadamente en 2 horas.\",\"fecha\":\"2026-04-15\",\"hora_inicio\":\"21:29:00\",\"hora_fin\":\"22:29:00\"}',NULL,'2026-04-15 19:30:29','2026-04-15 19:30:29'),('dc19254a-7f1e-4d09-a687-e571aedc5f3c','App\\Notifications\\AppointmentNotification','App\\Models\\User',33,'{\"type\":\"appointment\",\"appointment_id\":10,\"subject\":\"Confirmaci\\u00f3n de cita\",\"title\":\"Tu cita fue registrada\",\"message\":\"Tu cita fue confirmada en el sistema.\",\"fecha\":\"2026-04-15\",\"hora_inicio\":\"21:29:00\",\"hora_fin\":\"22:29:00\"}',NULL,'2026-04-15 19:29:42','2026-04-15 19:29:42');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` bigint unsigned NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo_pago` enum('efectivo','tarjeta','transferencia','qr') COLLATE utf8mb4_unicode_ci NOT NULL,
  `propina` decimal(10,2) NOT NULL DEFAULT '0.00',
  `comprobante_pdf` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_appointment_id_foreign` (`appointment_id`),
  KEY `payments_created_by_foreign` (`created_by`),
  KEY `payments_metodo_pago_created_at_index` (`metodo_pago`,`created_at`),
  CONSTRAINT `payments_appointment_id_foreign` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=235 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (222,'dashboard.ver','web','2026-04-15 17:10:16','2026-04-15 17:10:16'),(223,'usuarios.gestionar','web','2026-04-15 17:10:16','2026-04-15 17:10:16'),(224,'barberos.gestionar','web','2026-04-15 17:10:16','2026-04-15 17:10:16'),(225,'clientes.gestionar','web','2026-04-15 17:10:16','2026-04-15 17:10:16'),(226,'servicios.gestionar','web','2026-04-15 17:10:16','2026-04-15 17:10:16'),(227,'citas.gestionar','web','2026-04-15 17:10:16','2026-04-15 17:10:16'),(228,'citas.ver_propias','web','2026-04-15 17:10:16','2026-04-15 17:10:16'),(229,'pagos.gestionar','web','2026-04-15 17:10:16','2026-04-15 17:10:16'),(230,'inventario.ver','web','2026-04-15 17:10:16','2026-04-15 17:10:16'),(231,'inventario.gestionar','web','2026-04-15 17:10:16','2026-04-15 17:10:16'),(232,'reportes.ver','web','2026-04-15 17:10:16','2026-04-15 17:10:16'),(233,'configuracion.gestionar','web','2026-04-15 17:10:16','2026-04-15 17:10:16'),(234,'logs.ver','web','2026-04-15 17:10:16','2026-04-15 17:10:16');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `precio_compra` decimal(10,2) NOT NULL DEFAULT '0.00',
  `precio_venta` decimal(10,2) NOT NULL DEFAULT '0.00',
  `stock_actual` int NOT NULL DEFAULT '0',
  `stock_minimo` int NOT NULL DEFAULT '0',
  `tipo` enum('venta_cliente','insumo_trabajo') COLLATE utf8mb4_unicode_ci NOT NULL,
  `imagen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reactions`
--

DROP TABLE IF EXISTS `reactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reactions_work_id_foreign` (`work_id`),
  KEY `reactions_user_id_foreign` (`user_id`),
  CONSTRAINT `reactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reactions_work_id_foreign` FOREIGN KEY (`work_id`) REFERENCES `works` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reactions`
--

LOCK TABLES `reactions` WRITE;
/*!40000 ALTER TABLE `reactions` DISABLE KEYS */;
INSERT INTO `reactions` VALUES (1,1,30,'like','2026-04-15 17:36:39','2026-04-15 17:36:39'),(2,1,29,'like','2026-04-15 22:07:39','2026-04-15 22:07:39');
/*!40000 ALTER TABLE `reactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
INSERT INTO `role_has_permissions` VALUES (222,69),(223,69),(224,69),(225,69),(226,69),(227,69),(228,69),(229,69),(230,69),(231,69),(232,69),(233,69),(234,69),(222,70),(228,70),(222,71),(225,71),(227,71),(229,71),(230,71),(232,71),(222,72);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (69,'administrador','web','2026-04-15 17:10:16','2026-04-15 17:10:16'),(70,'barbero','web','2026-04-15 17:10:17','2026-04-15 17:10:17'),(71,'recepcionista','web','2026-04-15 17:10:17','2026-04-15 17:10:17'),(72,'cliente','web','2026-04-15 17:10:17','2026-04-15 17:10:17');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saved_works`
--

DROP TABLE IF EXISTS `saved_works`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `saved_works` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `work_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `saved_works_user_id_work_id_unique` (`user_id`,`work_id`),
  KEY `saved_works_work_id_foreign` (`work_id`),
  CONSTRAINT `saved_works_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `saved_works_work_id_foreign` FOREIGN KEY (`work_id`) REFERENCES `works` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saved_works`
--

LOCK TABLES `saved_works` WRITE;
/*!40000 ALTER TABLE `saved_works` DISABLE KEYS */;
/*!40000 ALTER TABLE `saved_works` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_combos`
--

DROP TABLE IF EXISTS `service_combos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_combos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `precio_combo` decimal(10,2) NOT NULL,
  `descuento` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_combos`
--

LOCK TABLES `service_combos` WRITE;
/*!40000 ALTER TABLE `service_combos` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_combos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `duracion_min` int unsigned NOT NULL,
  `imagen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (9,'TEST DER SERVICE','BAHB',120.00,159,NULL,'TETVSG B',1,'2026-04-15 17:38:21','2026-04-15 17:38:21');
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('aHMdGpzfeYzuq2E9RO4gGU0U4o8lwbuJ5lAeuVNE',NULL,'172.18.0.1','curl/8.18.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoicWQzVnpYY0hwRmJ0Vmdjdm9oa0l4ZVVpcEl0enR3d1VPd3ZQU094byI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czoyMToiaHR0cDovL2xvY2FsaG9zdDo4MDAwIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1776299893),('Bb6VGhKoI6mOK6z6kSMHKn9h2GYGL4HouwkouqXA',37,'172.18.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiTFdlQWlLVDlyVHRZcGc0YzVRbzhFdFZZSEdJQzVycWVZb3BvellkViI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjMxOiJodHRwOi8vbG9jYWxob3N0OjgwMDAvZGFzaGJvYXJkIjtzOjU6InJvdXRlIjtzOjk6ImRhc2hib2FyZCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjM3O30=',1776299983),('BLOxioQl2Rfdmjvx7FdMDcqfdEPviagM60BX6NAx',29,'172.18.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiZ0p1TkZ1enByTERnQUVUem1tSjJBSTJlRzMzamYycUhQRTJHYjkxMiI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjM2OiJodHRwOi8vbG9jYWxob3N0OjgwMDAvYmFyYmVyby9hZ2VuZGEiO3M6NToicm91dGUiO3M6MTM6ImJhcmJlci5hZ2VuZGEiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToyOTt9',1776312471),('gf1CjJnjx3sOAgbgLkYUKvlJNWt72ixboWU8GN5D',NULL,'172.18.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiVXYxelNUVml2MDFhajJwTGJwaUN3SEM0M1dvTVZ3TjV2MnRZY09laiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9kZXNjdWJyaXIiO3M6NToicm91dGUiO3M6MTE6InNvY2lhbC5mZWVkIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1776320536),('HNAdRVXU7tInt2LqEczbaxgX5k8vlxgs7IO6YiP4',29,'172.18.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0','YTo0OntzOjY6Il90b2tlbiI7czo0MDoib1k2NVE4U2xzeUZaNHJhS3M2bXhUMDdEYmtuQ1hzbmtUdUVjYVFmRiI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9kYXNoYm9hcmQiO3M6NToicm91dGUiO3M6OToiZGFzaGJvYXJkIjt9czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6Mjk7fQ==',1776300595),('HQZ2rJ76RTyCPwV735RgF4kRR56sraTLFjHVhx24',NULL,'172.18.0.1','curl/8.18.0','YTozOntzOjY6Il90b2tlbiI7czo0MDoiQnBiQzBXTndWbm4zWDZ6TmtBT2NJRzZ3a3B4OTY5MEo3VTRtSWlDSyI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czoyMToiaHR0cDovL2xvY2FsaG9zdDo4MDAwIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1776300531),('kUrEONEmpY37z8ITRXL4dD64t625h5swnyyOWO3L',30,'172.18.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiYVFkeWdJSUQ5WlpNcXBQMVFBaTZ4Q3dNUVJJUmt6WUFIUmFRbnhUWCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9kYXNoYm9hcmQiO3M6NToicm91dGUiO3M6OToiZGFzaGJvYXJkIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MzA7fQ==',1776300643),('q821a2KnXHlIVMGvcEpL1ZyKmzJTrPcNqvSiD8lU',29,'172.18.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','YTo2OntzOjY6Il90b2tlbiI7czo0MDoidVI1R2ttckIxZGhLbWJYeEpGMDZrS1NrMm5ZdVJRMDJ1NDI0d3lmRyI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjMxOiJodHRwOi8vbG9jYWxob3N0OjgwMDAvZGFzaGJvYXJkIjtzOjU6InJvdXRlIjtzOjk6ImRhc2hib2FyZCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjI5O3M6MTg6ImNoYXRib3RfaGlzdG9yeV8yOSI7YToyOntpOjA7YTo1OntzOjk6InRpbWVzdGFtcCI7czoyNToiMjAyNi0wNC0xNVQyMjo0Mjo0Ny0wNjowMCI7czo0OiJ0eXBlIjtzOjM6ImJvdCI7czo3OiJtZXNzYWdlIjtzOjIwOiJ2ZXIgbWkgcHLDs3hpbWEgY2l0YSI7czo4OiJyZXNwb25zZSI7czoxNjk6IvCfk4UgUGFyYSBhZ2VuZGFyIHVuYSBjaXRhOgoxLiBSZWfDrXN0cmF0ZSBvIGluaWNpYSBzZXNpw7NuCjIuIFZlIGEgbGEgc2VjY2nDs24gJ0NpdGFzJwozLiBTZWxlY2Npb25hIGJhcmJlcm8sIHNlcnZpY2lvIHkgaG9yYXJpbwo0LiBDb25maXJtYSB0dSByZXNlcnZhCgrCoVRlIGVzcGVyYW1vcyEiO3M6NzoiY29udGV4dCI7YTo0OntzOjg6ImtleXdvcmRzIjthOjE6e2k6MDtzOjQ6ImNpdGEiO31zOjY6ImludGVudCI7czo3OiJnZW5lcmFsIjtzOjg6ImVudGl0aWVzIjthOjM6e3M6ODoic2VydmljZXMiO2E6MDp7fXM6NzoiYmFyYmVycyI7YTowOnt9czo1OiJ0aW1lcyI7YTowOnt9fXM6MTE6ImlzX2ZvbGxvd3VwIjtiOjA7fX1pOjE7YTo1OntzOjk6InRpbWVzdGFtcCI7czoyNToiMjAyNi0wNC0xNVQyMjo0MzowMS0wNjowMCI7czo0OiJ0eXBlIjtzOjM6ImJvdCI7czo3OiJtZXNzYWdlIjtzOjMwOiLCv2PDs21vIGNhbWJpbyBtaSBjb250cmFzZcOxYT8iO3M6ODoicmVzcG9uc2UiO3M6NDE6IkVycm9yIGRlIGNvbmV4acOzbiBjb24gZWwgc2VydmljaW8gZGUgSUEuIjtzOjc6ImNvbnRleHQiO2E6NDp7czo4OiJrZXl3b3JkcyI7YTowOnt9czo2OiJpbnRlbnQiO3M6ODoicXVlc3Rpb24iO3M6ODoiZW50aXRpZXMiO2E6Mzp7czo4OiJzZXJ2aWNlcyI7YTowOnt9czo3OiJiYXJiZXJzIjthOjA6e31zOjU6InRpbWVzIjthOjA6e319czoxMToiaXNfZm9sbG93dXAiO2I6MDt9fX19',1776314581),('qFQQp4UjBJ9fuVmFdQeyGvKNBps7ZLyj1XLZsl0z',27,'172.18.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiZ0NLRm5HNEU4UnRGdlVvYll5UndHdXIycjI4dnNCQzU2SnUwY2Q1RCI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzQ6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9hcHBvaW50bWVudHMiO3M6NToicm91dGUiO3M6MTg6ImFwcG9pbnRtZW50cy5pbmRleCI7fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjI3O30=',1776302979),('U4T0KicOVewmYrgXNRROj9TjcN6uiHNMEFfyf18p',29,'172.18.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoicmU0Q1ZrQkRad0NiSUZOMFRDRzJKbmpWVWxRRnZzT2I2SzZGdGpXeCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9kYXNoYm9hcmQiO3M6NToicm91dGUiO3M6OToiZGFzaGJvYXJkIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6Mjk7fQ==',1776388790),('W4mJQkEDZvdrgn8TG7Dlrudpi77kEGYcYHGSxxF0',NULL,'172.18.0.1','curl/8.18.0','YToyOntzOjY6Il90b2tlbiI7czo0MDoiaEpNOUE1VTNRd2tMY3FhSUNTMzhxSEdGQ0FTeFQzMUxXWmFzeEoxciI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1776300251),('XtQ1Wx1P8kZJ34p2wASTidXfoVhxEbqCOv7GRQiA',30,'172.18.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','YTo0OntzOjY6Il90b2tlbiI7czo0MDoiWGIwdndreE4xemtTOUlXYXZzbmRPbE83ZTFETFN0NXFSeFBOTWdMTiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly9sb2NhbGhvc3Q6ODAwMC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjMwO30=',1776300633);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (27,'Administrador Barbería','kikeramirez160418@gmail.com','2026-04-15 17:10:17','$2y$12$i2ZjDrO7aV6mWwN.3qHjUuQpdnbWyMZPR9aSXZ5qQlr3F/4H4NplO',NULL,'2026-04-15 17:10:18','2026-04-15 17:10:18',NULL),(28,'Recepcionista Test','recepcionista@test.com','2026-04-15 17:10:18','$2y$12$6KGkrBXXR/m9w2yMXV/vnet45MffGGsfDPXHsdd1RYtPHoeydV5bW',NULL,'2026-04-15 17:10:18','2026-04-15 17:10:18',NULL),(29,'Barbero Test','barbero@test.com','2026-04-15 17:10:18','$2y$12$8VvQ4I3Xd4hzJiCQQ0pnruuyUx12K2dzabRcG7pHOY4VnLIDSUb0G',NULL,'2026-04-15 17:10:18','2026-04-15 17:10:18',NULL),(30,'Cliente Test','cliente@test.com','2026-04-15 17:10:19','$2y$12$xrzaffMBBtP3.ATPhyKyCe8xzoWovJ/HQlKE8VTjQVb0unSyrHLSa',NULL,'2026-04-15 17:10:19','2026-04-15 17:10:19',NULL),(31,'Recepcionista Test 1','recepcionista1@test.com','2026-04-15 17:10:19','$2y$12$51qld11eaDdsasCuxdc2i.bHlZLIg0847nTrPnxg3Y9RPHOzCANMa',NULL,'2026-04-15 17:10:19','2026-04-15 17:10:19',NULL),(32,'Barbero Test 1','barbero1@test.com','2026-04-15 17:10:19','$2y$12$m2mlgbf5vGinIWFAquWv1ejS3QYWHcNg1Hv1F9J1bHW.Pwttwi5iC',NULL,'2026-04-15 17:10:20','2026-04-15 17:10:20',NULL),(33,'Cliente Test 1','cliente1@test.com','2026-04-15 17:10:20','$2y$12$Adt8yghkHSh6o40wW8eDSeiJO9gh.9XcGMtLM5Au.Y9oZm5JhP38e',NULL,'2026-04-15 17:10:20','2026-04-15 17:10:20',NULL),(34,'Recepcionista Test 2','recepcionista2@test.com','2026-04-15 17:10:20','$2y$12$uagS1MdeEqsce2ae4iEJAORscq2A8qn3e.2rdiQrUG0FIVPWbSwKK',NULL,'2026-04-15 17:10:20','2026-04-15 17:10:20',NULL),(35,'Barbero Test 2','barbero2@test.com','2026-04-15 17:10:20','$2y$12$/.19VRmwSfhBYYQpLq3zZuS83gcnowwsyECDd/CK8WJ5rMocGs8oa',NULL,'2026-04-15 17:10:21','2026-04-15 17:10:21',NULL),(36,'Cliente Test DOS','client34@test.com','2026-04-15 17:10:21','$2y$12$NVHJRtMPT2rVPvmiOKKq7uf2gXrSh3mbBVgj.SNHshRamDSd5pqt2',NULL,'2026-04-15 17:10:21','2026-04-15 17:26:26',NULL),(37,'Administrador Barbería','al222310427@gmail.com','2026-04-15 17:53:50','$2y$12$6M47/VUKsvS/f/ObRiERmOwOjtywSZIQUyPYxbYaQ.loWTT8mfmzK',NULL,'2026-04-15 17:23:03','2026-04-15 17:53:50',NULL),(38,'Cliente Test 2','cliente2@test.com','2026-04-15 17:46:59','$2y$12$QorAE9vu0ZSmICs8gE4zZ.KpPgOifbib1dsE3nTR6uZ1mJqqzz.Ry',NULL,'2026-04-15 17:47:00','2026-04-15 17:47:00',NULL),(39,'VILCHIS ALa','alan.ruiz@gmail.com',NULL,'$2y$12$lo6/JaD7qM.bNsUW9ftqyuyO/OWJ6/AVJofa5FdYKSfljbcFhAcXG',NULL,'2026-04-15 17:52:34','2026-04-15 17:53:08',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_images`
--

DROP TABLE IF EXISTS `work_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_id` bigint unsigned NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `work_images_work_id_foreign` (`work_id`),
  CONSTRAINT `work_images_work_id_foreign` FOREIGN KEY (`work_id`) REFERENCES `works` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_images`
--

LOCK TABLES `work_images` WRITE;
/*!40000 ALTER TABLE `work_images` DISABLE KEYS */;
INSERT INTO `work_images` VALUES (1,1,'portfolio/aY4tLlvTSMbsTWZjiKi7VwhzSqq7KVwTvmelfcST.png','2026-04-15 17:17:13','2026-04-15 17:17:13');
/*!40000 ALTER TABLE `work_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `works`
--

DROP TABLE IF EXISTS `works`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `works` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `barbero_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `work_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `works_barbero_id_foreign` (`barbero_id`),
  CONSTRAINT `works_barbero_id_foreign` FOREIGN KEY (`barbero_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `works`
--

LOCK TABLES `works` WRITE;
/*!40000 ALTER TABLE `works` DISABLE KEYS */;
INSERT INTO `works` VALUES (1,29,'TETS','YGBGTFVGB','2026-04-15 17:17:12','2026-04-15 17:17:12','2026-04-15 17:17:12');
/*!40000 ALTER TABLE `works` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'laravel'
--

--
-- Dumping routines for database 'laravel'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-17  6:11:44
