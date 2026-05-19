
CREATE DATABASE IF NOT EXISTS progetto_finale
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE progetto_finale;

CREATE TABLE utenti (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50)  NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL UNIQUE,
  password VARCHAR(100) NOT NULL,
  ruolo ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE zone (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codice_zona VARCHAR(30) NOT NULL UNIQUE,
  nome_paese VARCHAR(100),
  codice_iso CHAR(2),
  latitudine DECIMAL(9,6),
  longitudine DECIMAL(9,6)
) ENGINE=InnoDB;


CREATE TABLE zone_salvate (
  utente_id INT UNSIGNED NOT NULL,
  zona_id INT UNSIGNED NOT NULL,
  etichetta VARCHAR(80),
  note TEXT,
  salvata_il  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (utente_id, zona_id),
  FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
  FOREIGN KEY (zona_id) REFERENCES zone(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE letture (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  zona_id INT UNSIGNED NOT NULL,
  rilevata_il DATETIME NOT NULL,
  carbon_intensity DECIMAL(8,2),
  fossil_free_pct  DECIMAL(5,2),
  renewable_pct DECIMAL(5,2),
  FOREIGN KEY (zona_id) REFERENCES zone(id) ON DELETE CASCADE,
  UNIQUE KEY uq_zona_ts (zona_id, rilevata_il),
  INDEX idx_zona_ts_desc (zona_id, rilevata_il DESC)
) ENGINE=InnoDB;

CREATE TABLE fonti_energetiche (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codice VARCHAR(20)  NOT NULL UNIQUE,
  nome_visualizzato VARCHAR(50),
  categoria ENUM('rinnovabile','fossile','nucleare','altro') NOT NULL DEFAULT 'altro'
) ENGINE=InnoDB;


CREATE TABLE lettura_mix (
  lettura_id   INT UNSIGNED NOT NULL,
  fonte_id INT UNSIGNED NOT NULL,
  percentuale  DECIMAL(5,2) NOT NULL,
  PRIMARY KEY (lettura_id, fonte_id),
  FOREIGN KEY (lettura_id) REFERENCES letture(id) ON DELETE CASCADE,
  FOREIGN KEY (fonte_id) REFERENCES fonti_energetiche(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE news_cache (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  zona_id INT UNSIGNED NOT NULL,
  titolo VARCHAR(300),
  descrizione TEXT,
  url VARCHAR(500) NOT NULL UNIQUE,
  fonte_nome VARCHAR(100),
  pubblicata_il DATETIME,
  recuperata_il TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (zona_id) REFERENCES zone(id) ON DELETE CASCADE,
  INDEX idx_news_zona_pub (zona_id, pubblicata_il DESC)
) ENGINE=InnoDB;

CREATE TABLE commenti (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utente_id INT UNSIGNED NOT NULL,
  zona_id INT UNSIGNED NOT NULL,
  testo TEXT NOT NULL,
  creato_il TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificato_il TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
  FOREIGN KEY (zona_id) REFERENCES zone(id)   ON DELETE CASCADE,
  INDEX idx_comm_zona_ts (zona_id, creato_il DESC)
) ENGINE=InnoDB;

CREATE TABLE categorie (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codice VARCHAR(30) NOT NULL UNIQUE,
  nome VARCHAR(60),
  descrizione TEXT,
  colore_hex CHAR(7)
) ENGINE=InnoDB;


CREATE TABLE zona_categoria (
  zona_id INT UNSIGNED NOT NULL,
  categoria_id INT UNSIGNED NOT NULL,
  assegnata_il TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (zona_id, categoria_id),
  FOREIGN KEY (zona_id) REFERENCES zone(id) ON DELETE CASCADE,
  FOREIGN KEY (categoria_id) REFERENCES categorie(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE confronti (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utente_id INT UNSIGNED NOT NULL,
  nome VARCHAR(100),
  descrizione TEXT,
  creato_il TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE confronto_zone (
  confronto_id INT UNSIGNED NOT NULL,
  zona_id INT UNSIGNED NOT NULL,
  ordine TINYINT NOT NULL,
  PRIMARY KEY (confronto_id, zona_id),
  FOREIGN KEY (confronto_id) REFERENCES confronti(id) ON DELETE CASCADE,
  FOREIGN KEY (zona_id) REFERENCES zone(id) ON DELETE CASCADE
) ENGINE=InnoDB;
