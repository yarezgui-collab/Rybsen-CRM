-- ============================================================
-- RYBSEN CRM - Data Room : restrictions de documents par investisseur
-- À importer si vous avez DÉJÀ importé ajout_dataroom.sql auparavant.
-- (Sinon, ajout_dataroom.sql contient déjà cette table.)
-- ============================================================

SET NAMES utf8mb4;

-- Présence d'une ligne = document MASQUÉ pour cet investisseur.
-- Par défaut (aucune ligne), tout document actif est visible par tous.
CREATE TABLE IF NOT EXISTS dataroom_doc_restrictions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  acces_id INT NOT NULL,
  document_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_restriction (acces_id, document_id),
  FOREIGN KEY (acces_id) REFERENCES dataroom_acces(id) ON DELETE CASCADE,
  FOREIGN KEY (document_id) REFERENCES dataroom_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
