-- Energie-Dashboard: Datenbankschema (MySQL / MariaDB)
-- In phpMyAdmin im Reiter "SQL" ausführen. Nicht auf den Webserver hochladen.

CREATE TABLE IF NOT EXISTS metrics (
  k   VARCHAR(40)  NOT NULL,
  v   DOUBLE       NULL,
  txt VARCHAR(100) NULL,
  ts  INT UNSIGNED NOT NULL,
  PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  k  VARCHAR(40)  NOT NULL,
  v  DOUBLE       NOT NULL,
  ts INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_k_ts (k, ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
