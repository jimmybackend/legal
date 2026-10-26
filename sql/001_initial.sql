-- Esforzados Legal · esquema nuevo e independiente.
-- Ejecutar manualmente en una base MySQL 8+/MariaDB 10.6+ elegida por el administrador.
-- No contiene datos de clientes, secretos ni tokens válidos.
-- Las filas de asuntos/documentos/hallazgos/avances son PRIVADAS por defecto.
CREATE TABLE IF NOT EXISTS legal_clients (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  folio VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'activo',
  notes_public TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_legal_clients_folio (folio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legal_cases (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id BIGINT UNSIGNED NOT NULL,
  case_reference VARCHAR(120) NOT NULL,
  subject VARCHAR(190) NOT NULL,
  court VARCHAR(190) NULL,
  jurisdiction VARCHAR(160) NULL,
  status VARCHAR(100) NOT NULL DEFAULT 'En registro',
  progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  summary_public TEXT NULL,
  next_step TEXT NULL,
  client_visible TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_legal_cases_client (client_id, client_visible),
  CONSTRAINT fk_legal_cases_client FOREIGN KEY (client_id) REFERENCES legal_clients (id) ON DELETE RESTRICT,
  CONSTRAINT ck_legal_cases_progress CHECK (progress_percent <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legal_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_id BIGINT UNSIGNED NOT NULL,
  display_name VARCHAR(255) NOT NULL,
  document_type VARCHAR(80) NOT NULL DEFAULT 'Documento',
  status VARCHAR(100) NOT NULL DEFAULT 'Registrado',
  -- Referencia INTERNA opcional, nunca se presenta al navegador
  storage_reference VARCHAR(500) NULL,
  notes_public TEXT NULL,
  client_visible TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_legal_documents_case (case_id, client_visible, created_at),
  CONSTRAINT fk_legal_documents_case FOREIGN KEY (case_id) REFERENCES legal_cases (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legal_findings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_id BIGINT UNSIGNED NOT NULL,
  heading VARCHAR(200) NOT NULL,
  summary TEXT NOT NULL,
  client_visible TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_legal_findings_case (case_id, client_visible, created_at),
  CONSTRAINT fk_legal_findings_case FOREIGN KEY (case_id) REFERENCES legal_cases (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legal_case_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  case_id BIGINT UNSIGNED NOT NULL,
  heading VARCHAR(200) NOT NULL,
  details TEXT NULL,
  event_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  client_visible TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_legal_case_events_case (case_id, client_visible, event_date),
  CONSTRAINT fk_legal_case_events_case FOREIGN KEY (case_id) REFERENCES legal_cases (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS legal_access_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_legal_token_hash (token_hash),
  KEY ix_legal_access_tokens_client (client_id, expires_at),
  CONSTRAINT fk_legal_tokens_client FOREIGN KEY (client_id) REFERENCES legal_clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
