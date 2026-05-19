
USE progetto_finale;

DROP TRIGGER IF EXISTS trg_letture_dopo_insert;
DROP TRIGGER IF EXISTS trg_commenti_modifica;
DROP FUNCTION IF EXISTS f_categoria_co2;
DROP VIEW IF EXISTS v_zone_con_ultima_lettura;
DROP VIEW IF EXISTS v_zone_categorizzate;
DROP VIEW IF EXISTS v_commenti_arricchiti;
DROP VIEW IF EXISTS v_confronti_utente;

-- Per ogni zona, ultima lettura
CREATE VIEW v_zone_con_ultima_lettura AS
SELECT
  z.id, z.codice_zona, z.nome_paese, z.codice_iso,
  z.latitudine AS lat,
  z.longitudine AS lng,
  l.carbon_intensity AS ultima_carbon_intensity,
  l.renewable_pct AS ultima_renewable_pct,
  l.rilevata_il AS ultima_rilevazione
FROM zone z
LEFT JOIN (
  SELECT l1.zona_id, l1.carbon_intensity, l1.renewable_pct, l1.rilevata_il
  FROM letture l1
  JOIN (
    SELECT zona_id, MAX(rilevata_il) AS max_ts
    FROM letture GROUP BY zona_id
  ) m ON m.zona_id = l1.zona_id AND m.max_ts = l1.rilevata_il
) l ON l.zona_id = z.id;

-- Categorie per zona 

CREATE VIEW v_zone_categorizzate AS
SELECT
  z.id AS zona_id,
  z.nome_paese,
  GROUP_CONCAT(c.codice ORDER BY c.codice SEPARATOR ',') AS categorie_codici,
  GROUP_CONCAT(c.nome ORDER BY c.codice SEPARATOR ', ') AS categorie_nomi
FROM zone z
LEFT JOIN zona_categoria zc ON zc.zona_id = z.id
LEFT JOIN categorie c ON c.id = zc.categoria_id
GROUP BY z.id, z.nome_paese;

-- Commenti con username + dati zona 


CREATE VIEW v_commenti_arricchiti AS
SELECT
  c.id, c.testo, c.creato_il, c.modificato_il,
  u.id AS utente_id, u.username,
  z.id AS zona_id, z.codice_zona, z.nome_paese
FROM commenti c
JOIN utenti u ON u.id = c.utente_id
JOIN zone z ON z.id = c.zona_id;

-- Confronti con conteggio zone.
CREATE VIEW v_confronti_utente AS
SELECT
  cf.id, cf.utente_id, cf.nome, cf.descrizione, cf.creato_il,
  COUNT(cz.zona_id) AS numero_zone,
  cf.creato_il AS ultima_modifica
FROM confronti cf
LEFT JOIN confronto_zone cz ON cz.confronto_id = cf.id
GROUP BY cf.id, cf.utente_id, cf.nome, cf.descrizione, cf.creato_il;

DELIMITER $$

-- carbon intensity.
CREATE FUNCTION f_categoria_co2(p_valore DECIMAL(8,2))
RETURNS VARCHAR(20)
DETERMINISTIC
BEGIN
  IF p_valore IS NULL THEN
    RETURN NULL;
  ELSEIF p_valore < 50 THEN
    RETURN 'molto_basso';
  ELSEIF p_valore < 200 THEN
    RETURN 'basso';
  ELSEIF p_valore < 400 THEN
    RETURN 'medio';
  ELSEIF p_valore < 600 THEN
    RETURN 'alto';
  ELSE
    RETURN 'molto_alto';
  END IF;
END$$

-- Quando arriva una nuova lettura, invalido le categorie vecchie (>7 giorni)
-- della zona. sp_classifica le ricostruira' alla prossima chiamata.
CREATE TRIGGER trg_letture_dopo_insert
AFTER INSERT ON letture
FOR EACH ROW
BEGIN
  DELETE FROM zona_categoria
  WHERE zona_id = NEW.zona_id
    AND assegnata_il < NOW() - INTERVAL 7 DAY;
END$$

-- Aggiorna modificato_il a NOW() esplicitamente.
-- La colonna ha gia' ON UPDATE CURRENT_TIMESTAMP, ma cosi' il comportamento
-- non dipende dalla DDL.
CREATE TRIGGER trg_commenti_modifica
BEFORE UPDATE ON commenti
FOR EACH ROW
BEGIN
  SET NEW.modificato_il = NOW();
END$$

DELIMITER ;
