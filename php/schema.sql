SET NAMES utf8mb4;
SET time_zone = '+08:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS guru (
  person_id VARCHAR(16) NOT NULL PRIMARY KEY,
  nama VARCHAR(255) NOT NULL,
  kumpulan VARCHAR(32) NOT NULL DEFAULT 'Guru',
  jawatan_hakiki VARCHAR(255) NOT NULL DEFAULT '',
  emel VARCHAR(191) NULL,
  kategori_login VARCHAR(64) NOT NULL DEFAULT '',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  normalized_name VARCHAR(255) NOT NULL DEFAULT '',
  catatan TEXT NULL,
  total_mata DECIMAL(10,2) NOT NULL DEFAULT 0,
  bil_tugas INT NOT NULL DEFAULT 0,
  KEY idx_guru_emel (emel),
  KEY idx_guru_aktif_kumpulan (aktif,kumpulan),
  KEY idx_guru_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS point_rules (
  kod_skor VARCHAR(191) NOT NULL PRIMARY KEY,
  mata DECIMAL(10,2) NOT NULL DEFAULT 0,
  sumber TEXT NULL,
  catatan TEXT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_point_rules_aktif (aktif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jawatankuasa (
  committee_id VARCHAR(16) NOT NULL PRIMARY KEY,
  kod_manual VARCHAR(64) NULL,
  nama_jawatankuasa VARCHAR(500) NOT NULL,
  bidang VARCHAR(191) NOT NULL DEFAULT '',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_jawatankuasa_aktif (aktif),
  KEY idx_jawatankuasa_bidang (bidang),
  KEY idx_jawatankuasa_nama (nama_jawatankuasa(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pelantikan (
  assignment_id VARCHAR(16) NOT NULL PRIMARY KEY,
  person_id VARCHAR(16) NOT NULL,
  nama VARCHAR(255) NOT NULL,
  kumpulan VARCHAR(32) NOT NULL DEFAULT 'Guru',
  committee_id VARCHAR(16) NOT NULL,
  jawatankuasa VARCHAR(500) NOT NULL,
  peranan VARCHAR(191) NOT NULL,
  kod_skor VARCHAR(191) NOT NULL,
  mata DECIMAL(10,2) NOT NULL DEFAULT 0,
  bidang VARCHAR(191) NOT NULL DEFAULT '',
  jenis VARCHAR(64) NOT NULL DEFAULT 'Terus',
  catatan TEXT NULL,
  mapping_note TEXT NULL,
  perlu_semakan TINYINT(1) NOT NULL DEFAULT 0,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NULL,
  updated_by VARCHAR(191) NULL,
  KEY idx_pelantikan_person (person_id,aktif),
  KEY idx_pelantikan_committee (committee_id,aktif),
  KEY idx_pelantikan_rule (kod_skor,aktif),
  KEY idx_pelantikan_bidang (bidang),
  CONSTRAINT fk_pelantikan_person FOREIGN KEY (person_id) REFERENCES guru(person_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pelantikan_committee FOREIGN KEY (committee_id) REFERENCES jawatankuasa(committee_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pelantikan_rule FOREIGN KEY (kod_skor) REFERENCES point_rules(kod_skor) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
  setting_value TEXT NULL,
  catatan TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  email VARCHAR(191) NULL,
  nama VARCHAR(255) NULL,
  action VARCHAR(100) NOT NULL,
  target_type VARCHAR(100) NULL,
  target_id VARCHAR(191) NULL,
  details TEXT NULL,
  KEY idx_audit_event_at (event_at),
  KEY idx_audit_email (email),
  KEY idx_audit_target (target_type,target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
