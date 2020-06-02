CREATE DATABASE IF NOT EXISTS `statuslight`;

USE `statuslight`;

CREATE TABLE IF NOT EXISTS `mqttadmins` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(25) NULL,
  `username` varchar(10) NOT NULL,
  `password` varchar(50) NOT NULL,
  UNIQUE (username)
);

INSERT INTO mqttadmins (name, username, password) VALUES("SL Application", "adm_app", MD5("123qweQWE")) 
	ON DUPLICATE KEY UPDATE    
	password=MD5("123qweQWE");

CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `userId` varchar(100) NOT NULL,
  `provider` varchar(20) NOT NULL,
  `name` varchar(25) NULL,
  `email` varchar(50) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `devices` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `userId` int NOT NULL,
  `state` varchar(10) NOT NULL,
  `pin` varchar(50) NULL,
  `validity` datetime NOT NULL,
  `mqttClientId` varchar(50) NULL,
  `mqttUpdated` datetime NULL,
  `clientDetails` longtext NULL
);

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `userId` int NOT NULL,
  `type` varchar(10) NOT NULL,
  `target` varchar(10) NOT NULL,
  `token` longtext NULL,
  `refreshToken` varchar(200) NULL,
  `state` varchar(10) NOT NULL,
  `startTime` datetime NOT NULL,
  `updatedTime` datetime NOT NULL,
  `closedReason` varchar(100) NULL,
  `presenceStatus` varchar(25) NULL,
  `presenceStatusDetail` varchar(50) NULL
);

CREATE TABLE `mqttmessages` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `topic` varchar(100) NOT NULL,
  `content` varchar(100) NOT NULL,
  `queueIn` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `queueOut` datetime NULL,
  `state` varchar(10) NOT NULL
);