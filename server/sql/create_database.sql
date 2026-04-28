CREATE DATABASE IF NOT EXISTS greengrid
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE greengrid;

CREATE TABLE IF NOT EXISTS zones_monitored (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    zone_key    VARCHAR(20)  NOT NULL,
    zone_name   VARCHAR(100) NOT NULL,
    user_label  VARCHAR(100) DEFAULT NULL,
    notes       TEXT         DEFAULT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_zone_key (zone_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS carbon_readings (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    zone_key             VARCHAR(20)    NOT NULL,
    carbon_intensity     DECIMAL(8,2)   DEFAULT NULL,
    renewable_percentage DECIMAL(5,2)   DEFAULT NULL,
    fossil_percentage    DECIMAL(5,2)   DEFAULT NULL,
    power_breakdown_json TEXT           DEFAULT NULL,
    reading_datetime     DATETIME       NOT NULL,
    fetched_at           TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone_key         (zone_key),
    INDEX idx_reading_datetime (reading_datetime),
    CONSTRAINT fk_readings_zone
        FOREIGN KEY (zone_key)
        REFERENCES zones_monitored(zone_key)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO zones_monitored (zone_key, zone_name, user_label, notes) VALUES
    ('IT-NO', 'Italy (North)', 'Casa mia',       'Zona nord Italia, mix idroelettrico elevato'),
    ('FR',    'France',        'Riferimento EU',  'Francia, alta quota nucleare — buon benchmark a bassa CO₂'),
    ('DE',    'Germany',       'Benchmark carbone', 'Germania, ancora dipendente dal carbone — utile per confronto');
