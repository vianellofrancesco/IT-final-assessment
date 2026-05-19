

USE progetto_finale;

DROP PROCEDURE IF EXISTS sp_salva_pref;
DROP PROCEDURE IF EXISTS sp_save_lettura;
DROP PROCEDURE IF EXISTS sp_nuovo_commento;
DROP PROCEDURE IF EXISTS sp_classifica;
DROP PROCEDURE IF EXISTS sp_crea_conf;
DROP PROCEDURE IF EXISTS sp_dati_conf;
DROP PROCEDURE IF EXISTS sp_top_co2;
DROP PROCEDURE IF EXISTS sp_del_utente;

DELIMITER $$

CREATE PROCEDURE sp_salva_pref(
  IN p_uid INT UNSIGNED,
  IN p_codice VARCHAR(10),
  IN p_etich VARCHAR(80),
  IN p_note TEXT
)
BEGIN
  DECLARE z_id INT UNSIGNED DEFAULT NULL;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;

  SELECT id INTO z_id FROM zone WHERE codice_zona = p_codice LIMIT 1;
  IF z_id IS NULL THEN
    INSERT INTO zone (codice_zona) VALUES (p_codice);
    SET z_id = LAST_INSERT_ID();
  END IF;

  INSERT INTO zone_salvate (utente_id, zona_id, etichetta, note)
  VALUES (p_uid, z_id, p_etich, p_note)
  ON DUPLICATE KEY UPDATE etichetta = VALUES(etichetta), note = VALUES(note);

  COMMIT;
END$$

CREATE PROCEDURE sp_save_lettura(
  IN p_zid INT UNSIGNED,
  IN p_co2 DECIMAL(8,2),
  IN p_ff DECIMAL(5,2),
  IN p_renew DECIMAL(5,2),
  IN p_mix JSON
)
BEGIN
  DECLARE lid INT UNSIGNED;
  DECLARE n INT;
  DECLARE i INT DEFAULT 0;
  DECLARE k VARCHAR(20);
  DECLARE pct DECIMAL(5,2);
  DECLARE fid INT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;

  INSERT INTO letture (zona_id, rilevata_il, carbon_intensity, fossil_free_pct, renewable_pct)
  VALUES (p_zid, NOW(), p_co2, p_ff, p_renew);
  SET lid = LAST_INSERT_ID();

  IF p_mix IS NOT NULL THEN
    SET n = JSON_LENGTH(JSON_KEYS(p_mix));
    WHILE i < n DO
      SET k = JSON_UNQUOTE(JSON_EXTRACT(JSON_KEYS(p_mix), CONCAT('$[', i, ']')));
      SET pct = CAST(JSON_UNQUOTE(JSON_EXTRACT(p_mix, CONCAT('$."', k, '"'))) AS DECIMAL(5,2));

      SET fid = NULL;
      SELECT id INTO fid FROM fonti_energetiche WHERE codice = k LIMIT 1;
      IF fid IS NOT NULL THEN
        INSERT INTO lettura_mix (lettura_id, fonte_id, percentuale) VALUES (lid, fid, pct);
      END IF;
      SET i = i + 1;
    END WHILE;
  END IF;

  COMMIT;
END$$

-- Nuovo commento 
CREATE PROCEDURE sp_nuovo_commento(
  IN p_uid INT UNSIGNED,
  IN p_zid INT UNSIGNED,
  IN p_txt TEXT
)
BEGIN
  DECLARE new_id INT UNSIGNED;
  DECLARE len INT;

  SET len = CHAR_LENGTH(COALESCE(p_txt, ''));
  IF len < 1 OR len > 2000 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Il testo deve essere tra 1 e 2000 caratteri.';
  END IF;

  INSERT INTO commenti (utente_id, zona_id, testo) VALUES (p_uid, p_zid, p_txt);
  SET new_id = LAST_INSERT_ID();

  SELECT c.id, c.utente_id, u.username, c.zona_id, c.testo, c.creato_il, c.modificato_il
  FROM commenti c JOIN utenti u ON u.id = c.utente_id
  WHERE c.id = new_id;
END$$

-- Ricostruisce zona_categoria sulle medie dei 7 giorni precedenti.

CREATE PROCEDURE sp_classifica()
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;

  DELETE FROM zona_categoria;

  INSERT IGNORE INTO zona_categoria (zona_id, categoria_id)
  SELECT l.zona_id, (SELECT id FROM categorie WHERE codice = 'top_renewable')
  FROM letture l
  WHERE l.rilevata_il >= NOW() - INTERVAL 7 DAY
  GROUP BY l.zona_id
  HAVING AVG(l.renewable_pct) > 80;

  INSERT IGNORE INTO zona_categoria (zona_id, categoria_id)
  SELECT l.zona_id, (SELECT id FROM categorie WHERE codice = 'low_carbon')
  FROM letture l
  WHERE l.rilevata_il >= NOW() - INTERVAL 7 DAY
  GROUP BY l.zona_id
  HAVING AVG(l.carbon_intensity) < 100;

  INSERT IGNORE INTO zona_categoria (zona_id, categoria_id)
  SELECT l.zona_id, (SELECT id FROM categorie WHERE codice = 'high_carbon')
  FROM letture l
  WHERE l.rilevata_il >= NOW() - INTERVAL 7 DAY
  GROUP BY l.zona_id
  HAVING AVG(l.carbon_intensity) > 500;

  INSERT IGNORE INTO zona_categoria (zona_id, categoria_id)
  SELECT l.zona_id, (SELECT id FROM categorie WHERE codice = 'coal_heavy')
  FROM letture l
  JOIN lettura_mix lm ON lm.lettura_id = l.id
  JOIN fonti_energetiche f ON f.id = lm.fonte_id
  WHERE l.rilevata_il >= NOW() - INTERVAL 7 DAY AND f.codice = 'coal'
  GROUP BY l.zona_id
  HAVING AVG(lm.percentuale) > 30;

  INSERT IGNORE INTO zona_categoria (zona_id, categoria_id)
  SELECT l.zona_id, (SELECT id FROM categorie WHERE codice = 'nuclear_powered')
  FROM letture l
  JOIN lettura_mix lm ON lm.lettura_id = l.id
  JOIN fonti_energetiche f ON f.id = lm.fonte_id
  WHERE l.rilevata_il >= NOW() - INTERVAL 7 DAY AND f.codice = 'nuclear'
  GROUP BY l.zona_id
  HAVING AVG(lm.percentuale) > 50;

  COMMIT;
END$$

-- Crea un nuovo confronto. zone_ids arriva come stringa "1,3,5".
CREATE PROCEDURE sp_crea_conf(
  IN p_uid INT UNSIGNED,
  IN p_nome VARCHAR(100),
  IN p_descr TEXT,
  IN p_csv VARCHAR(500)
)
BEGIN
  DECLARE cid INT UNSIGNED;
  DECLARE rest VARCHAR(500);
  DECLARE tok VARCHAR(20);
  DECLARE ord TINYINT DEFAULT 1;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;

  INSERT INTO confronti (utente_id, nome, descrizione) VALUES (p_uid, p_nome, p_descr);
  SET cid = LAST_INSERT_ID();

  SET rest = TRIM(COALESCE(p_csv, ''));
  WHILE CHAR_LENGTH(rest) > 0 DO
    SET tok = TRIM(SUBSTRING_INDEX(rest, ',', 1));
    IF CHAR_LENGTH(tok) > 0 THEN
      INSERT INTO confronto_zone (confronto_id, zona_id, ordine)
      VALUES (cid, CAST(tok AS UNSIGNED), ord);
      SET ord = ord + 1;
    END IF;
    IF LOCATE(',', rest) > 0 THEN
      SET rest = SUBSTRING(rest, LOCATE(',', rest) + 1);
    ELSE
      SET rest = '';
    END IF;
  END WHILE;

  COMMIT;

  SELECT cid AS confronto_id;
END$$

-- Dati aggregati per le zone di un confronto: ultima lettura + medie 7gg.
CREATE PROCEDURE sp_dati_conf(IN p_cid INT UNSIGNED)
BEGIN
  SELECT
    cz.ordine,
    z.id AS zona_id, z.codice_zona, z.nome_paese, z.codice_iso,
    z.latitudine, z.longitudine,
    ult.rilevata_il AS ultima_data,
    ult.carbon_intensity AS ultima_co2,
    ult.fossil_free_pct AS ultima_fossil_free,
    ult.renewable_pct AS ultima_renewable,
    medie.media_co2_7d,
    medie.media_renew_7d
  FROM confronto_zone cz
  JOIN zone z ON z.id = cz.zona_id
  LEFT JOIN (
    SELECT l.zona_id, l.rilevata_il, l.carbon_intensity, l.fossil_free_pct, l.renewable_pct
    FROM letture l
    JOIN (
      SELECT zona_id, MAX(rilevata_il) AS max_ts FROM letture GROUP BY zona_id
    ) m ON m.zona_id = l.zona_id AND m.max_ts = l.rilevata_il
  ) ult ON ult.zona_id = z.id
  LEFT JOIN (
    SELECT zona_id,
           AVG(carbon_intensity) AS media_co2_7d,
           AVG(renewable_pct) AS media_renew_7d
    FROM letture
    WHERE rilevata_il >= NOW() - INTERVAL 7 DAY
    GROUP BY zona_id
  ) medie ON medie.zona_id = z.id
  WHERE cz.confronto_id = p_cid
  ORDER BY cz.ordine;
END$$

-- Top zone per CO2 media in una finestra di N giorni.
CREATE PROCEDURE sp_top_co2(IN p_limite INT, IN p_giorni INT)
BEGIN
  SELECT z.id, z.codice_zona, z.nome_paese,
         AVG(l.carbon_intensity) AS media_co2,
         COUNT(*) AS letture_count
  FROM zone z
  JOIN letture l ON l.zona_id = z.id
  WHERE l.rilevata_il >= NOW() - INTERVAL p_giorni DAY
  GROUP BY z.id, z.codice_zona, z.nome_paese
  ORDER BY media_co2 DESC
  LIMIT p_limite;
END$$

-- Cancella un utente esplicitando i child DELETE 
CREATE PROCEDURE sp_del_utente(IN p_uid INT UNSIGNED)
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;
    DELETE FROM commenti WHERE utente_id = p_uid;
    DELETE FROM zone_salvate WHERE utente_id = p_uid;
    DELETE FROM confronti WHERE utente_id = p_uid;
    DELETE FROM utenti WHERE id = p_uid;
  COMMIT;
END$$

DELIMITER ;
