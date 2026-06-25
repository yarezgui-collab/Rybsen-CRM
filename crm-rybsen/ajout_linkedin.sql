-- ============================================================
-- AJOUT — Table Calendrier Éditorial LinkedIn
-- À exécuter une seule fois dans phpMyAdmin (onglet SQL) sur ta base
-- N'affecte aucune table existante
-- ============================================================
CREATE TABLE IF NOT EXISTS linkedin_calendar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  numero INT,
  titre VARCHAR(250) NOT NULL,
  semaine VARCHAR(20),
  jour ENUM('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche') DEFAULT 'Lundi',
  heure TIME DEFAULT '08:00:00',
  date_publication DATE,
  texte_post TEXT,
  prompt_image TEXT,
  hashtags VARCHAR(500),
  secteur ENUM('Offset','Textile','Agri-food','Transversal','Reglementation','Autre') DEFAULT 'Transversal',
  statut ENUM('À programmer','Prêt','Publié','Republié page') DEFAULT 'À programmer',
  lien_post VARCHAR(300),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
