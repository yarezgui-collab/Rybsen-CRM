-- ============================================================
-- RYBSEN CRM - Module Facturation
-- Tables: documents, document_lignes, paiements_recus
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero VARCHAR(25) NOT NULL UNIQUE,
  type ENUM('Devis','Facture','Pro forma','Bon de livraison') NOT NULL DEFAULT 'Facture',
  statut VARCHAR(40) NOT NULL DEFAULT 'Brouillon',
  -- Client (snapshot)
  client_id INT DEFAULT NULL,
  client_nom VARCHAR(200) NOT NULL DEFAULT '',
  client_adresse TEXT,
  client_mf VARCHAR(100),
  client_pays VARCHAR(100),
  client_email VARCHAR(150),
  -- Dates
  date_document DATE,
  date_echeance DATE,
  date_validite DATE,
  -- Financier
  sous_total_ht DECIMAL(12,3) DEFAULT 0.000,
  taux_tva DECIMAL(5,2) DEFAULT 19.00,
  montant_tva DECIMAL(12,3) DEFAULT 0.000,
  timbre DECIMAL(10,3) DEFAULT 1.000,
  total_ttc DECIMAL(12,3) DEFAULT 0.000,
  devise CHAR(3) DEFAULT 'TND',
  -- Paiement
  mode_paiement TEXT,
  -- Lien entre documents (devis → facture)
  document_lie_id INT DEFAULT NULL,
  notes TEXT,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients_prospects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS document_lignes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  document_id INT NOT NULL,
  position INT DEFAULT 1,
  description TEXT NOT NULL,
  quantite DECIMAL(10,3) DEFAULT 1.000,
  prix_unitaire_ht DECIMAL(12,3) DEFAULT 0.000,
  total_ht DECIMAL(12,3) DEFAULT 0.000,
  FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS paiements_recus (
  id INT AUTO_INCREMENT PRIMARY KEY,
  document_id INT NOT NULL,
  date_paiement DATE,
  montant DECIMAL(12,3) DEFAULT 0.000,
  mode VARCHAR(60) DEFAULT 'Virement',
  reference VARCHAR(120),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
