--
-- Table structure for table `galette_legalnotices_settings`
--
DROP TABLE IF EXISTS galette_legalnotices_settings;
CREATE TABLE galette_legalnotices_settings (
  id int unsigned NOT NULL auto_increment,
  name varchar(100) NOT NULL,
  value varchar(200) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY (name)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

--
-- Table structure for table `galette_legalnotices_pages`
--
DROP TABLE IF EXISTS galette_legalnotices_pages;
CREATE TABLE galette_legalnotices_pages (
  id int unsigned NOT NULL auto_increment,
  name varchar(20) NOT NULL,
  body longtext NOT NULL,
  url varchar(255) NOT NULL,
  lang varchar(16) NOT NULL,
  label character varying(255) NOT NULL,
  last_update datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY `localizedpage` (name, lang)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
