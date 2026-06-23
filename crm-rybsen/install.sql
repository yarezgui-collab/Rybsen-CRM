-- ============================================================
-- RYBSEN CRM - Script d'installation MySQL
-- Base: u293743867_crmrybsen
-- Créé le: 2026-06-15
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ============================================================
-- TABLE: users (authentification)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','manager','viewer') DEFAULT 'viewer',
  avatar CHAR(2) DEFAULT 'YR',
  actif TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Utilisateurs initiaux (mot de passe: Rybsen2026!)
INSERT INTO users (nom, email, password_hash, role, avatar) VALUES
('Yassine Rezgui', 'yrezgui@rybsen.fr', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'YR'),
('Nadia Benaissa', 'nadia@rybsen.fr', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'NB'),
('Hela Darouez', 'hela@rybsen.fr', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'viewer', 'HD'),
('Adnane Rezgui', 'adnane@rybsen.fr', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'AR');

-- ============================================================
-- MODULE 1: LEVÉE DE FONDS - Investisseurs / Fonds
-- ============================================================
CREATE TABLE IF NOT EXISTS investisseurs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(150) NOT NULL,
  organisation VARCHAR(150),
  type ENUM('VC','Business Angel','Accélérateur','Institution','Fonds Impact','Autre') DEFAULT 'VC',
  pays VARCHAR(80),
  email VARCHAR(150),
  linkedin VARCHAR(250),
  ticket_min INT DEFAULT 0,
  ticket_max INT DEFAULT 0,
  devise CHAR(3) DEFAULT 'EUR',
  statut ENUM('Identifié','Contacté','Relancé','Meeting planifié','Due Diligence','Décision','Investi','Refusé','En pause') DEFAULT 'Identifié',
  score_chaleur ENUM('🔥 Chaud','🟡 Tiède','⚪ Froid') DEFAULT '⚪ Froid',
  connexions_communes INT DEFAULT 0,
  date_premier_contact DATE,
  date_dernier_contact DATE,
  date_prochain_contact DATE,
  source_rencontre VARCHAR(100),
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Données pré-remplies GITEX + autres contacts
INSERT INTO investisseurs (nom, organisation, type, pays, email, ticket_min, ticket_max, statut, score_chaleur, connexions_communes, source_rencontre, notes) VALUES
('Anil Maguru', 'Satgana', 'Fonds Impact', 'France', 'anil@satgana.com', 100000, 300000, 'Contacté', '🔥 Chaud', 28, 'GITEX Africa Marrakech 2026', 'Lead prioritaire absolu. Thématique climate/water parfaite. Décideur Partner-level.'),
('Vincent Previ', 'Partech', 'VC', 'France', 'vprevi@partechpartners.com', 500000, 3000000, 'Contacté', '🟡 Tiède', 0, 'GITEX Africa Marrakech 2026', 'A contacté directement sur LinkedIn'),
('Maxime Bayen', 'Catalyst Fund', 'Fonds Impact', 'International', 'maxime@thecatalystfund.com', 100000, 500000, 'Contacté', '🟡 Tiède', 0, 'GITEX Africa Marrakech 2026', 'A contacté directement sur LinkedIn'),
('Yasmine Afifi', 'F6 Ventures', 'VC', 'Maroc', 'yasmine.afifi@f6.vc', 100000, 500000, 'Contacté', '🟡 Tiède', 0, 'GITEX Africa Marrakech 2026', 'Même groupe F6 que Flat6Labs. Envoyer emails mêm jour que Chaimae.'),
('Chaimae Ezzahiri', 'Flat6Labs Morocco', 'Accélérateur', 'Maroc', 'chaimae.ezzahiri@flat6labs.com', 50000, 200000, 'Contacté', '🟡 Tiède', 0, 'GITEX Africa Marrakech 2026', 'Même groupe F6 que F6 Ventures. Envoyer emails même jour que Yasmine.'),
('Nadia Moukaddem', 'Anara Impact Capital', 'Fonds Impact', 'Tunisie', NULL, 100000, 500000, 'Contacté', '🟡 Tiède', 15, 'Impact Afterwork Tunisie', '15 connexions communes. Rencontrée à Impact afterwork Tunisie.'),
('Mark Kleyner', 'Dream VC', 'VC', 'International', 'mark@dream-vc.com', 100000, 500000, 'Contacté', '🟡 Tiède', 0, 'GITEX Africa Marrakech 2026', NULL),
('Sam Alaoui', '9D Capital', 'VC', 'USA', NULL, 500000, 2000000, 'Contacté', '🟡 Tiède', 0, 'GITEX Africa Marrakech 2026', 'Contact LinkedIn uniquement'),
('Taiwo Obasan', 'Seven Capital Ventures', 'VC', 'International', 'taiwo@sevencapitalventures.com', 100000, 500000, 'Contacté', '⚪ Froid', 0, 'GITEX Africa Marrakech 2026', NULL),
('Mouna Tsidi', 'CDG Invest', 'Institution', 'Maroc', 'mouna.tsidi@cdginvest.ma', 200000, 1000000, 'Contacté', '🟡 Tiède', 0, 'GITEX Africa Marrakech 2026', 'Angle Maroc: entité marocaine + distribution locale, pas levée directe.'),
('Fay Cowper', 'UM6P', 'Institution', 'Maroc', 'fay.cowper@um6p.ma', 100000, 500000, 'Contacté', '🟡 Tiède', 0, 'GITEX Africa Marrakech 2026', 'CC: outmane.jebari@um6p.ma. Angle Maroc.'),
('Dr. Jamal Kamal', 'Indépendant', 'Business Angel', 'International', 'drjamalkamal@hotmail.com', 50000, 200000, 'Contacté', '🟡 Tiède', 0, 'GITEX Africa Marrakech 2026', NULL),
('Chokrane Hamoudi', 'Betawaves', 'Accélérateur', 'International', 'chokrane.hamoudi@betawaves.io', 50000, 200000, 'Identifié', '⚪ Froid', 0, 'Post-call GITEX', NULL);

-- ============================================================
-- MODULE 2: PIPELINE COMMERCIAL - Clients & Prospects
-- ============================================================
CREATE TABLE IF NOT EXISTS clients_prospects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom_entreprise VARCHAR(150) NOT NULL,
  pays VARCHAR(80),
  ville VARCHAR(100),
  secteur ENUM('Offset / Imprimerie','Textile','Agri-food','Pharmaceutique','Autre') DEFAULT 'Offset / Imprimerie',
  source ENUM('MGM France','Direct','Apporteur affaires','Salon','LinkedIn','Recommandation','Autre') DEFAULT 'Direct',
  contact_nom VARCHAR(150),
  contact_email VARCHAR(150),
  contact_tel VARCHAR(50),
  stade ENUM('Prospect','Devis envoyé','Négociation','Bon de commande','Installé','Perdu','En pause') DEFAULT 'Prospect',
  probabilite_closing INT DEFAULT 0,
  version_aquaclean ENUM('V1','V2') DEFAULT 'V1',
  machine_attribuee VARCHAR(20),
  prix_ht DECIMAL(10,2) DEFAULT 30000.00,
  devise CHAR(3) DEFAULT 'EUR',
  roi_estime_mois INT DEFAULT 12,
  date_premier_contact DATE,
  date_devis DATE,
  date_closing_prevu DATE,
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clients installés
INSERT INTO clients_prospects (nom_entreprise, pays, secteur, source, stade, version_aquaclean, machine_attribuee, probabilite_closing, notes) VALUES
('Client Côte d\'Ivoire 1', 'Côte d\'Ivoire', 'Offset / Imprimerie', 'Direct', 'Installé', 'V1', 'AQC-001', 100, 'Première installation Afrique'),
('Client Côte d\'Ivoire 2', 'Côte d\'Ivoire', 'Offset / Imprimerie', 'Direct', 'Installé', 'V1', 'AQC-002', 100, 'Deuxième installation CI'),
('Client Algérie', 'Algérie', 'Offset / Imprimerie', 'Direct', 'Installé', 'V1', 'AQC-003', 100, NULL),
('Nouha Eco Print', 'Tunisie', 'Offset / Imprimerie', 'Direct', 'Installé', 'V1', 'AQC-004', 100, 'Case study principal. 85,000€/an économisés. ROI < 12 mois.');

-- ============================================================
-- MODULE 3: FABRICATION AQUACLEAN
-- ============================================================
CREATE TABLE IF NOT EXISTS fabrication (
  id INT AUTO_INCREMENT PRIMARY KEY,
  machine_id VARCHAR(20) NOT NULL UNIQUE,
  client_id INT,
  version ENUM('V1','V2') DEFAULT 'V1',
  pays VARCHAR(80),
  statut ENUM('Conception','Approvisionnement','Composants commandés','Assemblage Nielsen','Câblage','QA / Tests','Prêt expédition','Expédié','Installé','SAV actif') DEFAULT 'Conception',
  pompes_recues TINYINT(1) DEFAULT 0,
  hydraulique_recu TINYINT(1) DEFAULT 0,
  filtres_recus TINYINT(1) DEFAULT 0,
  assemblage_nielsen_ok TINYINT(1) DEFAULT 0,
  date_lancement DATE,
  date_installation DATE,
  numero_serie VARCHAR(50),
  blocages TEXT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients_prospects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO fabrication (machine_id, client_id, version, pays, statut, pompes_recues, hydraulique_recu, filtres_recus, assemblage_nielsen_ok, date_installation) VALUES
('AQC-001', 1, 'V1', 'Côte d\'Ivoire', 'Installé', 1, 1, 1, 1, '2024-01-01'),
('AQC-002', 2, 'V1', 'Côte d\'Ivoire', 'Installé', 1, 1, 1, 1, '2024-06-01'),
('AQC-003', 3, 'V1', 'Algérie', 'Installé', 1, 1, 1, 1, '2024-09-01'),
('AQC-004', 4, 'V1', 'Tunisie', 'Installé', 1, 1, 1, 1, '2025-03-01');

-- ============================================================
-- MODULE 4: PARTENAIRES & DISTRIBUTEURS
-- ============================================================
CREATE TABLE IF NOT EXISTS partenaires (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(150) NOT NULL,
  type ENUM('Distributeur','OEM','Apporteur affaires','Fournisseur','Sous-traitant','Autre') DEFAULT 'Distributeur',
  territoire VARCHAR(150),
  pays VARCHAR(80),
  contact_nom VARCHAR(150),
  contact_email VARCHAR(150),
  contact_tel VARCHAR(50),
  contrat_signe TINYINT(1) DEFAULT 0,
  type_contrat VARCHAR(100),
  date_signature DATE,
  date_expiration DATE,
  volume_objectif INT DEFAULT 0,
  volume_realise INT DEFAULT 0,
  marge_pct DECIMAL(5,2) DEFAULT 0,
  statut ENUM('Actif','Phase 2','En négociation','Suspendu','Terminé') DEFAULT 'Actif',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO partenaires (nom, type, territoire, pays, contact_nom, contact_email, contrat_signe, type_contrat, date_expiration, marge_pct, statut, notes) VALUES
('MGM France', 'Distributeur', 'France', 'France', 'Isabelle Triantopoulos / Bruno Zaccone', 'isabelle@mgm-france.fr', 1, 'Lettre de Bonne Intention', '2027-03-31', 25.00, 'Actif', '50 ans expérience marché graphique. 600 clients. 25% marge (7500€/machine). Exclusivité conditionnelle jusqu au 31/03/2027. Forfait démo 4000€ à MGM déductible si vente dans 6 mois. C-Print 2027 50/50.'),
('Nielsen Tunisie', 'Sous-traitant', 'Tunisie', 'Tunisie', 'Mokhtar Zannad', NULL, 1, 'Contrat sous-traitance Phase 1', NULL, 0, 'Actif', 'Fabrication châssis + assemblage électrique + automate. Phase 1 active. PDG aussi au board advisory.'),
('BINDER', 'OEM', 'International', 'Espagne', 'Khaled Chamari', NULL, 0, 'En cours', NULL, 10.00, 'Phase 2', 'Reus, Espagne. RYBSEN fournit 6 modules filtration + flexibles + pompe SAER INOX à 18,000€ HT + 10% royalties. BINDER gère automation/IHM/software/cabinet électrique. Phase 2 uniquement, pas encore actif.');

-- ============================================================
-- MODULE 5: CANDIDATURES & PROGRAMMES
-- ============================================================
CREATE TABLE IF NOT EXISTS candidatures (
  id INT AUTO_INCREMENT PRIMARY KEY,
  programme VARCHAR(150) NOT NULL,
  organisme VARCHAR(150),
  type ENUM('Subvention','Prêt','Accélération','Prix/Concours','Investissement public','Autre') DEFAULT 'Subvention',
  pays VARCHAR(80),
  montant_demande DECIMAL(12,2),
  devise CHAR(3) DEFAULT 'TND',
  statut ENUM('À préparer','Soumis','En attente décision','Accepté','Refusé','Reporté','En cours remboursement') DEFAULT 'À préparer',
  date_soumission DATE,
  date_reponse_prevue DATE,
  date_reponse_reelle DATE,
  contact_referent VARCHAR(150),
  contact_email VARCHAR(150),
  documents_soumis TINYINT(1) DEFAULT 0,
  priorite ENUM('🔴 Urgent','🟡 Important','🟢 Normal') DEFAULT '🟢 Normal',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO candidatures (programme, organisme, type, pays, montant_demande, devise, statut, date_soumission, contact_referent, documents_soumis, priorite, notes) VALUES
('Lab\'ess', 'Lab\'ess (UE)', 'Prêt', 'Tunisie', 37000, 'TND', 'En cours remboursement', '2024-01-01', NULL, 1, '🔴 Urgent', 'Accepté. 37,000 TND. Remboursement reporté à septembre 2026. Demande de report envoyée.'),
('MAIR - Smart Capital', 'Smart Capital Tunisie', 'Subvention', 'Tunisie', 200000, 'TND', 'Soumis', '2026-05-01', NULL, 1, '🔴 Urgent', '200,000 TND soumis. Budget: IA 60K + industrialisation 50K + certification 20K + déploiement 25K + marketing 25K + équipe 15K + logistique 5K.'),
('Co-Innov / INAT', 'INAT / Enactus INAT', 'Subvention', 'Tunisie', 140000, 'TND', 'Soumis', '2026-05-01', 'Marouene Trabelsi', 1, '🔴 Urgent', '100K subvention + 40K RYBSEN. AquaClean V2 multi-secteur. 2ème brevet visé.'),
('The Gap in Between', 'The Gap in Between', 'Prix/Concours', 'International', 0, 'EUR', 'À préparer', NULL, NULL, 0, '🔴 Urgent', 'Flagué PRIORITÉ. À soumettre en urgence.'),
('DIV Fund', 'DIV Fund', 'Investissement public', 'International', 300000, 'EUR', 'À préparer', NULL, NULL, 0, '🟡 Important', 'Candidat fort.'),
('Milken-Motsepe', 'Milken-Motsepe Prize', 'Prix/Concours', 'International', 0, 'EUR', 'À préparer', NULL, NULL, 0, '🟡 Important', 'Candidat fort. Prix tech Afrique.'),
('Paris Saclay Hardware Accelerator', 'Paris Saclay', 'Accélération', 'France', 0, 'EUR', 'Accepté', NULL, NULL, 1, '🟢 Normal', 'Backing confirmé.');

-- ============================================================
-- MODULE 6: TÂCHES & ALERTES
-- ============================================================
CREATE TABLE IF NOT EXISTS taches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titre VARCHAR(250) NOT NULL,
  module_lie ENUM('Levée de fonds','Commercial','Fabrication','Partenaires','Candidatures','Admin/Légal','Marketing','Autre') DEFAULT 'Autre',
  priorite ENUM('🔴 Urgent','🟡 Important','🟢 Normal') DEFAULT '🟢 Normal',
  responsable_id INT,
  deadline DATE,
  statut ENUM('À faire','En cours','En attente','Terminé') DEFAULT 'À faire',
  recurrence ENUM('Aucune','Quotidien','Hebdo','Mensuel') DEFAULT 'Aucune',
  alerte_brevet TINYINT(1) DEFAULT 0,
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO taches (titre, module_lie, priorite, deadline, statut, alerte_brevet, notes) VALUES
('⚠️ Payer annuité 10 brevet FR3070137', 'Admin/Légal', '🔴 Urgent', '2026-08-31', 'À faire', 1, 'Sur inpi.fr. ~130€. Annuité 10. DÉLAI ABSOLU: 31 août 2026.'),
('⚠️ Vérifier statut EP3444017 sur epo.org', 'Admin/Légal', '🔴 Urgent', '2026-07-01', 'À faire', 1, 'Vérifier si statut B1 (granted) confirmé sur epo.org. Critique avant approche constructeurs japonais/américains.'),
('Relancer Satgana / Anil Maguru', 'Levée de fonds', '🔴 Urgent', '2026-06-20', 'À faire', 0, 'anil@satgana.com. Lead prioritaire. 28 connexions communes. Paris-based.'),
('Soumettre The Gap in Between', 'Candidatures', '🔴 Urgent', '2026-07-15', 'À faire', 0, 'Programme flagué priorité absolue.'),
('Contacter Technotrans pour licensing', 'Commercial', '🟡 Important', '2026-07-01', 'À faire', 0, 'Premier constructeur cible pour licensing. Email initial à préparer.'),
('Envoyer rapport mensuel MGM France', 'Partenaires', '🟡 Important', '2026-07-01', 'À faire', 0, 'Pipeline prospects + démonstrations planifiées.'),
('Finaliser statut EP3444017 avant approche KBA', 'Admin/Légal', '🟡 Important', '2026-08-01', 'À faire', 1, 'Ne pas approcher constructeurs japon/USA sans confirmation brevet européen.'),
('Publication LinkedIn post suivant', 'Marketing', '🟢 Normal', '2026-06-22', 'À faire', 0, 'Calendrier éditorial en cours. Semaine 2.');

-- ============================================================
-- TABLE: messages_log (traçabilité communications)
-- ============================================================
CREATE TABLE IF NOT EXISTS messages_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  destinataire VARCHAR(150) NOT NULL,
  organisation VARCHAR(150),
  canal ENUM('Email','LinkedIn','WhatsApp','Téléphone','Réunion','Autre') DEFAULT 'Email',
  objet VARCHAR(250),
  statut ENUM('Envoyé','Répondu','Sans réponse','À envoyer') DEFAULT 'Envoyé',
  date_envoi DATE,
  date_reponse DATE,
  module_lie ENUM('Investisseur','Client','Partenaire','Candidature','Autre') DEFAULT 'Autre',
  reference_id INT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: licensing_constructeurs
-- ============================================================
CREATE TABLE IF NOT EXISTS licensing (
  id INT AUTO_INCREMENT PRIMARY KEY,
  constructeur VARCHAR(150) NOT NULL,
  pays VARCHAR(80),
  parc_machines_mondial INT,
  contact_nom VARCHAR(150),
  contact_email VARCHAR(150),
  statut ENUM('Cible identifiée','Approche initiale','Intérêt confirmé','Négociation','Accord signé','Refusé') DEFAULT 'Cible identifiée',
  priorite ENUM('🔴 Priorité 1','🟡 Priorité 2','🟢 Priorité 3') DEFAULT '🟡 Priorité 2',
  prerequis_brevet TINYINT(1) DEFAULT 1,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO licensing (constructeur, pays, statut, priorite, prerequis_brevet, notes) VALUES
('Technotrans SE', 'Allemagne', 'Cible identifiée', '🔴 Priorité 1', 0, 'Spécialiste eau de mouillage. Cible licensing principale.'),
('Koenig & Bauer (KBA)', 'Allemagne', 'Cible identifiée', '🔴 Priorité 1', 1, 'Famille officielle distributeur. Attendre confirmation EP3444017 avant approche.'),
('Baldwin Technology', 'USA', 'Cible identifiée', '🟡 Priorité 2', 1, 'Attendre brevet européen confirmé.'),
('Heidelberg', 'Allemagne', 'Cible identifiée', '🔴 Priorité 1', 1, 'Leader mondial presses offset. Attendre brevet européen.'),
('manroland Sheetfed', 'Allemagne', 'Cible identifiée', '🟡 Priorité 2', 1, NULL),
('Komori', 'Japon', 'Cible identifiée', '🟡 Priorité 2', 1, 'Attendre brevet européen + PCT extension.'),
('RMGT', 'Japon', 'Cible identifiée', '🟢 Priorité 3', 1, 'Attendre PCT extension pour marché japonais.');

