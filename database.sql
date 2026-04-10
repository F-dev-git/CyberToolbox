-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost
-- Généré le : sam. 11 avr. 2026 à 03:49
-- Version du serveur : 10.5.8-MariaDB-log
-- Version de PHP : 8.2.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `cybertoolbox`
--

-- --------------------------------------------------------

--
-- Structure de la table `annuaire`
--

CREATE TABLE `annuaire` (
  `id` int(11) NOT NULL,
  `titre` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
  `description` text CHARACTER SET utf8mb4 DEFAULT NULL,
  `lien_image` varchar(255) COLLATE latin1_general_ci DEFAULT NULL,
  `lien_pc` varchar(255) COLLATE latin1_general_ci DEFAULT NULL,
  `lien_ios` varchar(255) COLLATE latin1_general_ci DEFAULT NULL,
  `lien_android` varchar(255) COLLATE latin1_general_ci DEFAULT NULL,
  `tags` varchar(100) COLLATE latin1_general_ci DEFAULT NULL,
  `location` varchar(255) COLLATE latin1_general_ci DEFAULT 'all',
  `login_id` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `password_id` varchar(255) CHARACTER SET utf8mb4 DEFAULT NULL,
  `note_id` text CHARACTER SET utf8mb4 DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notices`
--

CREATE TABLE `notices` (
  `id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `lien` varchar(255) NOT NULL,
  `location` varchar(50) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `presse_papier`
--

CREATE TABLE `presse_papier` (
  `texte` text CHARACTER SET utf8mb4 NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `wifi_credentials`
--

CREATE TABLE `wifi_credentials` (
  `id` int(11) NOT NULL,
  `ssid` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 NOT NULL,
  `qr_code_link` varchar(255) COLLATE latin1_general_ci DEFAULT NULL,
  `location` varchar(50) COLLATE latin1_general_ci DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `annuaire`
--
ALTER TABLE `annuaire`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `notices`
--
ALTER TABLE `notices`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `presse_papier`
--
ALTER TABLE `presse_papier`
  ADD UNIQUE KEY `texte` (`texte`) USING HASH;

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Index pour la table `wifi_credentials`
--
ALTER TABLE `wifi_credentials`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `annuaire`
--
ALTER TABLE `annuaire`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notices`
--
ALTER TABLE `notices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `wifi_credentials`
--
ALTER TABLE `wifi_credentials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
