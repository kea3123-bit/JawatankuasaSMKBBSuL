-- Jawatankuasa SMKBBSL: Sessions + hierarchy + role templates
-- Generated 2026-11-04 for 2027 planning.
-- Run ONCE on the live MySQL database after making a backup.
SET NAMES utf8mb4;
SET time_zone = '+08:00';

CREATE TABLE IF NOT EXISTS sessions (
  session_year SMALLINT NOT NULL PRIMARY KEY,
  label VARCHAR(64) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'DRAFT',
  source_year SMALLINT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by VARCHAR(191) NULL,
  reviewed_at DATETIME NULL,
  reviewed_by VARCHAR(191) NULL,
  published_at DATETIME NULL,
  published_by VARCHAR(191) NULL,
  archived_at DATETIME NULL,
  archived_by VARCHAR(191) NULL,
  KEY idx_sessions_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO sessions (session_year,label,status,created_by,published_at,published_by)
VALUES (2026,'Sesi 2026','PUBLISHED','MIGRATION',NOW(),'MIGRATION')
ON DUPLICATE KEY UPDATE label=VALUES(label);

ALTER TABLE jawatankuasa
  ADD COLUMN IF NOT EXISTS session_year SMALLINT NOT NULL DEFAULT 2026,
  ADD COLUMN IF NOT EXISTS manual_ref VARCHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS component_code VARCHAR(32) NULL,
  ADD COLUMN IF NOT EXISTS parent_committee_id VARCHAR(16) NULL,
  ADD COLUMN IF NOT EXISTS hierarchy_level TINYINT NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS source_ref VARCHAR(255) NULL;

ALTER TABLE pelantikan
  ADD COLUMN IF NOT EXISTS session_year SMALLINT NOT NULL DEFAULT 2026,
  ADD COLUMN IF NOT EXISTS role_template_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS portfolio_basis VARCHAR(191) NULL,
  ADD COLUMN IF NOT EXISTS source_assignment_id VARCHAR(16) NULL;

ALTER TABLE point_rules
  ADD COLUMN IF NOT EXISTS recommended_points DECIMAL(10,2) NULL,
  ADD COLUMN IF NOT EXISTS role_level TINYINT NOT NULL DEFAULT 6,
  ADD COLUMN IF NOT EXISTS role_group VARCHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS source_ref VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS official_structure TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS committee_role_templates (
  template_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_year SMALLINT NOT NULL,
  committee_id VARCHAR(16) NOT NULL,
  role_name VARCHAR(191) NOT NULL,
  role_code VARCHAR(191) NOT NULL,
  hierarchy_rank TINYINT NOT NULL DEFAULT 6,
  min_count SMALLINT NOT NULL DEFAULT 0,
  max_count SMALLINT NULL,
  required_role TINYINT(1) NOT NULL DEFAULT 0,
  points_override DECIMAL(10,2) NULL,
  portfolio_basis VARCHAR(191) NULL,
  source_type VARCHAR(32) NOT NULL DEFAULT 'LOCAL',
  source_ref VARCHAR(255) NULL,
  notes TEXT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_committee_role (session_year,committee_id,role_code),
  KEY idx_crt_committee (committee_id,aktif),
  KEY idx_crt_session (session_year,aktif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS session_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_year SMALLINT NOT NULL,
  note_type VARCHAR(32) NOT NULL,
  title VARCHAR(191) NOT NULL,
  details TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by VARCHAR(191) NULL,
  KEY idx_session_notes (session_year,note_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_jawatankuasa_session ON jawatankuasa(session_year,aktif);
CREATE INDEX IF NOT EXISTS idx_jawatankuasa_parent ON jawatankuasa(parent_committee_id);
CREATE INDEX IF NOT EXISTS idx_pelantikan_session ON pelantikan(session_year,aktif);
CREATE INDEX IF NOT EXISTS idx_pelantikan_session_person ON pelantikan(session_year,person_id,aktif);
CREATE INDEX IF NOT EXISTS idx_pelantikan_session_committee ON pelantikan(session_year,committee_id,aktif);

UPDATE jawatankuasa SET session_year=2026 WHERE session_year IS NULL OR session_year=0;
UPDATE pelantikan SET session_year=2026 WHERE session_year IS NULL OR session_year=0;

-- Component classification from the 2026 manual.
UPDATE jawatankuasa SET component_code=
  CASE
    WHEN bidang IN ('Pengurusan & Pentadbiran','Pengurusan dan Pentadbiran') THEN 'PENGURUSAN'
    WHEN bidang IN ('Kurikulum','Pemantauan Kurikulum') THEN 'KURIKULUM'
    WHEN bidang='Hal Ehwal Murid' THEN 'HEM'
    WHEN bidang='Kokurikulum' THEN 'KOKURIKULUM'
    WHEN bidang='Bilik Khas' THEN 'LAMPIRAN'
    WHEN bidang='Tugas Mingguan' THEN 'TUGAS_KHAS'
    ELSE 'LAIN'
  END
WHERE session_year=2026;

-- Main manual roots.
UPDATE jawatankuasa SET manual_ref='JM01',component_code='PENGURUSAN',hierarchy_level=1,sort_order=100,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Induk Pengurusan dan Pentadbiran Sekolah';
UPDATE jawatankuasa SET manual_ref='JM21',component_code='KURIKULUM',hierarchy_level=1,sort_order=2100,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Induk Kurikulum Sekolah';
UPDATE jawatankuasa SET manual_ref='JM25',component_code='HEM',hierarchy_level=1,sort_order=2500,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Induk Hal Ehwal Murid';
UPDATE jawatankuasa SET manual_ref='JM30',component_code='KOKURIKULUM',hierarchy_level=1,sort_order=3000,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Induk Kokurikulum';

SET @JM01=(SELECT committee_id FROM jawatankuasa WHERE session_year=2026 AND manual_ref='JM01' LIMIT 1);
SET @JM21=(SELECT committee_id FROM jawatankuasa WHERE session_year=2026 AND manual_ref='JM21' LIMIT 1);
SET @JM25=(SELECT committee_id FROM jawatankuasa WHERE session_year=2026 AND manual_ref='JM25' LIMIT 1);
SET @JM30=(SELECT committee_id FROM jawatankuasa WHERE session_year=2026 AND manual_ref='JM30' LIMIT 1);

-- Pengurusan & Pentadbiran: hierarchy per Manual pages 66-71.
UPDATE jawatankuasa SET manual_ref='JM01/1',parent_committee_id=@JM01,hierarchy_level=2,sort_order=101,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Perancangan Strategik';
UPDATE jawatankuasa SET manual_ref='JM01/2',parent_committee_id=@JM01,hierarchy_level=2,sort_order=102,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Program Kecemerlangan Sekolah';
UPDATE jawatankuasa SET manual_ref='JM01/3',parent_committee_id=@JM01,hierarchy_level=2,sort_order=103,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Manual Pengurusan dan Takwim Sekolah';
UPDATE jawatankuasa SET manual_ref='JM01/4',parent_committee_id=@JM01,hierarchy_level=2,sort_order=104,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa LIKE 'Jawatankuasa Pembangunan dan Baikpulih Fizikal Sekolah%';
UPDATE jawatankuasa SET manual_ref='JM01/5',parent_committee_id=@JM01,hierarchy_level=2,sort_order=105,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Pengurusan Bilik Khas';
UPDATE jawatankuasa SET manual_ref='JM01/6',parent_committee_id=@JM01,hierarchy_level=2,sort_order=106,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Digital Sekolah';
UPDATE jawatankuasa SET manual_ref='JM01/7',parent_committee_id=@JM01,hierarchy_level=2,sort_order=107,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Pengurusan Majlis dan Program';
UPDATE jawatankuasa SET manual_ref='JM01/8',parent_committee_id=@JM01,hierarchy_level=2,sort_order=108,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Perhubungan Luar';
UPDATE jawatankuasa SET manual_ref='JM01/9',parent_committee_id=@JM01,hierarchy_level=2,sort_order=109,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa LIKE 'Jawatankuasa Dokumentasi / Laporan%';
UPDATE jawatankuasa SET manual_ref='JM01/10',parent_committee_id=@JM01,hierarchy_level=2,sort_order=110,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa LIKE 'Jawatankuasa Perkhidmatan Keselamatan dan Kebersihan%';
UPDATE jawatankuasa SET manual_ref='JM01/11',parent_committee_id=@JM01,hierarchy_level=2,sort_order=111,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND UPPER(nama_jawatankuasa) LIKE 'JAWATANKUASA PBPPP%';
UPDATE jawatankuasa SET manual_ref='JM01/12',parent_committee_id=@JM01,hierarchy_level=2,sort_order=112,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa STEM';

UPDATE jawatankuasa SET manual_ref='JM02',hierarchy_level=1,sort_order=200,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa LIKE 'Jawatankuasa Pengurusan Maklumat Sekolah%';
UPDATE jawatankuasa SET manual_ref='JM03',hierarchy_level=1,sort_order=300,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa LIKE 'Jawatankuasa Pengurusan Sumber Manusia%';
UPDATE jawatankuasa SET manual_ref='JM04',hierarchy_level=1,sort_order=400,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa LIKE 'Jawatankuasa Kewangan, Akaun dan Aset Sekolah%';
UPDATE jawatankuasa SET manual_ref='JM05',hierarchy_level=1,sort_order=500,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa SKPM Kualiti @ Sekolah';
UPDATE jawatankuasa SET manual_ref='JM06',hierarchy_level=1,sort_order=600,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa TS25 / Kbat / Pak21';
UPDATE jawatankuasa SET manual_ref='JM07',hierarchy_level=1,sort_order=700,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa LIKE 'Jawatankuasa Pengurusan Bencana%';
UPDATE jawatankuasa SET manual_ref='JM08',hierarchy_level=1,sort_order=800,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Persatuan Ibu Bapa dan Guru (PIBG)';
UPDATE jawatankuasa SET manual_ref='JM09',hierarchy_level=1,sort_order=900,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa LIKE 'Jawatankuasa Kelab Warga BBSuL%';

-- Kurikulum.
UPDATE jawatankuasa SET manual_ref='JM21/1',parent_committee_id=@JM21,hierarchy_level=2,sort_order=2101,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Perancangan Kurikulum – Jadual Waktu';
UPDATE jawatankuasa SET manual_ref='JM22/0/1',component_code='KURIKULUM',hierarchy_level=2,sort_order=2201,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Panitia Mata Pelajaran – Bidang Bahasa';
UPDATE jawatankuasa SET manual_ref='JM22/0/2',component_code='KURIKULUM',hierarchy_level=2,sort_order=2202,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Panitia Mata Pelajaran – Bidang Sains dan Matematik';
UPDATE jawatankuasa SET manual_ref='JM22/0/2-TV',component_code='KURIKULUM',hierarchy_level=2,sort_order=2203,source_ref='Manual Pengurusan SMKBBSL 2026 (manual repeats JM22/0/2)'
WHERE session_year=2026 AND nama_jawatankuasa='Panitia Mata Pelajaran – Bidang Teknik dan Vokasional';
UPDATE jawatankuasa SET manual_ref='JM22/0/2-KEM',component_code='KURIKULUM',hierarchy_level=2,sort_order=2204,source_ref='Manual Pengurusan SMKBBSL 2026 (manual repeats JM22/0/2)'
WHERE session_year=2026 AND nama_jawatankuasa='Panitia Mata Pelajaran – Bidang Kemanusiaan';
UPDATE jawatankuasa SET manual_ref='JM22/1',parent_committee_id=@JM21,hierarchy_level=2,sort_order=2210,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Kecemerlangan Akademik';
UPDATE jawatankuasa SET manual_ref='JM22/2',parent_committee_id=@JM21,hierarchy_level=2,sort_order=2220,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Kecemerlangan Akademik (SPM)';
UPDATE jawatankuasa SET manual_ref='JM23',parent_committee_id=@JM21,hierarchy_level=2,sort_order=2300,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Pentaksiran';
UPDATE jawatankuasa SET manual_ref='JM23-PSS',parent_committee_id=@JM21,hierarchy_level=2,sort_order=2310,source_ref='Manual Pengurusan SMKBBSL 2026 (TOC repeats JM23)'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Pusat Sumber Sekolah';

-- HEM.
UPDATE jawatankuasa SET manual_ref='JM25/1',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2501,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa LIKE 'Guru Kelas – %';
UPDATE jawatankuasa SET manual_ref='JM25/2',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2502,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Pembangunan Modal Insan';
UPDATE jawatankuasa SET manual_ref='JM25/3',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2503,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Pendaftaran dan Enrolmen Murid';
UPDATE jawatankuasa SET manual_ref='JM25/4',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2504,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Bantuan dan Kebajikan';
UPDATE jawatankuasa SET manual_ref='JM25/5',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2505,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Biasiswa';
UPDATE jawatankuasa SET manual_ref='JM25/6',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2506,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Kantin';
UPDATE jawatankuasa SET manual_ref='JM25/7',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2507,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Skim Pinjaman Buku Teks';
UPDATE jawatankuasa SET manual_ref='JM25/8',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2508,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Pendidikan Pencegahan Dadah (PPDa)';
UPDATE jawatankuasa SET manual_ref='JM25/9',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2509,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Kesihatan dan Pencegahan Denggi';
UPDATE jawatankuasa SET manual_ref='JM25/10',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2510,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Amalan Guru Penyayang';
UPDATE jawatankuasa SET manual_ref='JM26/1',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2601,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Pengurusan Disiplin Murid';
UPDATE jawatankuasa SET manual_ref='JM26/2',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2602,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Badan Pengawas';
UPDATE jawatankuasa SET manual_ref='JM26/3',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2603,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Perhimpunan dan Siar Raya';
UPDATE jawatankuasa SET manual_ref='JM26/4',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2604,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Kelab Pencegahan Jenayah';
UPDATE jawatankuasa SET manual_ref='JM27',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2700,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa LIKE 'Jawatankuasa 3K%';
UPDATE jawatankuasa SET manual_ref='JM28',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2800,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Bimbingan dan Kaunseling';
UPDATE jawatankuasa SET manual_ref='JM28-SUMUR',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2810,source_ref='Manual Pengurusan SMKBBSL 2026 (TOC repeats JM28)'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Sahsiah Unggul Murid (Sumur)';
UPDATE jawatankuasa SET manual_ref='JM29',parent_committee_id=@JM25,hierarchy_level=2,sort_order=2900,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Surau';

-- Kokurikulum.
UPDATE jawatankuasa SET manual_ref='JM30(E2)',parent_committee_id=@JM30,hierarchy_level=2,sort_order=3002,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Majalah Sekolah';
UPDATE jawatankuasa SET manual_ref='JM31',parent_committee_id=@JM30,hierarchy_level=2,sort_order=3100,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Pembangunan Sukan Tahunan Sekolah';
UPDATE jawatankuasa SET manual_ref='JM32/SUKAN',parent_committee_id=@JM30,hierarchy_level=2,sort_order=3201,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa LIKE 'Jawatankuasa Sukan dan Permainan%';
UPDATE jawatankuasa SET manual_ref='JM32/KELAB',parent_committee_id=@JM30,hierarchy_level=2,sort_order=3202,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa LIKE 'Jawatankuasa Kelab dan Persatuan%';
UPDATE jawatankuasa SET manual_ref='JM32/UNIFORM',parent_committee_id=@JM30,hierarchy_level=2,sort_order=3203,source_ref='Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa LIKE 'Jawatankuasa Badan Beruniform%';

-- Bilik khas are an appendix under the school special-room management structure.
SET @BILIK=(SELECT committee_id FROM jawatankuasa WHERE session_year=2026 AND nama_jawatankuasa='Jawatankuasa Pengurusan Bilik Khas' LIMIT 1);
UPDATE jawatankuasa SET parent_committee_id=@BILIK,hierarchy_level=3,component_code='LAMPIRAN',source_ref='Lampiran Manual Pengurusan SMKBBSL 2026'
WHERE session_year=2026 AND nama_jawatankuasa LIKE 'Pengurusan Bilik Khas – %';

-- Canonical role hierarchy metadata. Existing operational points remain unchanged for 2026.
UPDATE point_rules SET recommended_points=0,role_level=1,role_group='PENGURUSAN',official_structure=1,source_ref='MySG / Manual Pengurusan' WHERE UPPER(kod_skor) IN ('PENGETUA','PENGERUSI','PENASIHAT');
UPDATE point_rules SET recommended_points=0,role_level=2,role_group='PENGURUSAN',official_structure=1,source_ref='MySG / Manual Pengurusan' WHERE UPPER(kod_skor) IN ('NAIB PENGERUSI','PENOLONG KANAN PENTADBIRAN','PENOLONG KANAN HAL EHWAL MURID','PENOLONG KANAN KOKURIKULUM');
UPDATE point_rules SET recommended_points=3,role_level=3,role_group='PENYELARAS',official_structure=1,source_ref='Manual Pengurusan SMKBBSL 2026' WHERE UPPER(kod_skor) LIKE 'PENYELARAS%';
UPDATE point_rules SET recommended_points=3,role_level=4,role_group='SETIAUSAHA',official_structure=1,source_ref='Manual Pengurusan / MySG' WHERE UPPER(kod_skor) IN ('SU JAWATANKUASA','SETIAUSAHA','URUSETIA','URUS SETIA');
UPDATE point_rules SET recommended_points=2,role_level=5,role_group='PENOLONG_SETIAUSAHA',official_structure=1,source_ref='Manual Pengurusan SMKBBSL 2026' WHERE UPPER(kod_skor) IN ('PENOLONG SU JK','PEN. SU PAJSK');
UPDATE point_rules SET recommended_points=3,role_level=4,role_group='BENDAHARI',official_structure=1,source_ref='Manual Pengurusan SMKBBSL 2026' WHERE UPPER(kod_skor) LIKE 'BENDAHARI%';
UPDATE point_rules SET recommended_points=2,role_level=5,role_group='PENOLONG_BENDAHARI',official_structure=1,source_ref='Manual Pengurusan SMKBBSL 2026' WHERE UPPER(kod_skor) LIKE 'PEN.%BENDAHARI%' OR UPPER(kod_skor) LIKE 'PENOLONG BENDAHARI%';
UPDATE point_rules SET recommended_points=1,role_level=6,role_group='AJK',official_structure=1,source_ref='Manual Pengurusan / MySG' WHERE UPPER(kod_skor)='AJK';
UPDATE point_rules SET recommended_points=5,role_level=3,role_group='PORTFOLIO_KRITIKAL',official_structure=1,source_ref='SPI KPM Bil. 1 Tahun 2022' WHERE UPPER(kod_skor)='GURU DATA';
UPDATE point_rules SET recommended_points=5,role_level=3,role_group='PORTFOLIO_KRITIKAL',official_structure=1,source_ref='Manual Pengurusan SMKBBSL 2026' WHERE UPPER(kod_skor) IN ('SU KURIKULUM','SU HEM','SU SUKAN','GURU KELAS','GURU PSS');
UPDATE point_rules SET recommended_points=mata WHERE recommended_points IS NULL;

-- Generic manual-role templates for major committees. These are recommendations for 2027 and do not rewrite 2026 assignments.
INSERT IGNORE INTO committee_role_templates
(session_year,committee_id,role_name,role_code,hierarchy_rank,min_count,max_count,required_role,points_override,source_type,source_ref)
SELECT 2026,committee_id,'Pengerusi','Pengerusi',1,1,1,1,0,'MANUAL','Manual Pengurusan SMKBBSL 2026'
FROM jawatankuasa WHERE session_year=2026 AND manual_ref IN ('JM21','JM25','JM30','JM02','JM03','JM04','JM05','JM06','JM07');
INSERT IGNORE INTO committee_role_templates
(session_year,committee_id,role_name,role_code,hierarchy_rank,min_count,max_count,required_role,points_override,source_type,source_ref)
SELECT 2026,committee_id,'Naib Pengerusi','Naib Pengerusi',2,1,NULL,1,0,'MANUAL','Manual Pengurusan SMKBBSL 2026'
FROM jawatankuasa WHERE session_year=2026 AND manual_ref IN ('JM21','JM25','JM30','JM02','JM03','JM04','JM05','JM06','JM07');
INSERT IGNORE INTO committee_role_templates
(session_year,committee_id,role_name,role_code,hierarchy_rank,min_count,max_count,required_role,points_override,source_type,source_ref)
SELECT 2026,committee_id,'Setiausaha','Setiausaha',4,1,1,1,3,'MANUAL','Manual Pengurusan SMKBBSL 2026'
FROM jawatankuasa WHERE session_year=2026 AND manual_ref IN ('JM21','JM25','JM30','JM03','JM04','JM05','JM06','JM07');
INSERT IGNORE INTO committee_role_templates
(session_year,committee_id,role_name,role_code,hierarchy_rank,min_count,max_count,required_role,points_override,source_type,source_ref)
SELECT 2026,committee_id,'AJK','AJK',6,0,NULL,0,1,'MANUAL','Manual Pengurusan SMKBBSL 2026'
FROM jawatankuasa WHERE session_year=2026 AND manual_ref IS NOT NULL;

INSERT INTO session_notes(session_year,note_type,title,details,created_by)
VALUES
(2026,'SOURCE','Manual Pengurusan SMKBBSL 2026','Hierarki jawatankuasa dan jawatan dirujuk daripada Manual Pengurusan Sekolah SMKBBSL Versi 5. Beberapa kod dalam kandungan manual berulang; sistem mengekalkan rujukan asal dan menanda variasi secara eksplisit.','MIGRATION'),
(2026,'SOURCE','MySG / SPI KPM Bil. 4 Tahun 2021','Digunakan sebagai kerangka tadbir urus sekolah.','MIGRATION'),
(2026,'SOURCE','SPI KPM Bil. 2 Tahun 2023 - Panitia Mata Pelajaran','Digunakan untuk struktur panitia, Ketua Panitia dan keahlian Jawatankuasa Pengurusan Kurikulum.','MIGRATION'),
(2026,'SOURCE','SPI KPM Bil. 1 Tahun 2022 - Guru Data','Digunakan sebagai bukti beban masa khusus Guru Data; tujuh jam seminggu untuk pengurusan data dan waktu mengajar maksimum lapan jam seminggu.','MIGRATION');

-- Verification
SELECT session_year,label,status FROM sessions ORDER BY session_year;
SELECT component_code,COUNT(*) committees FROM jawatankuasa WHERE session_year=2026 AND aktif=1 GROUP BY component_code ORDER BY component_code;
SELECT COUNT(*) AS mapped_manual_committees FROM jawatankuasa WHERE session_year=2026 AND manual_ref IS NOT NULL;
SELECT COUNT(*) AS role_templates FROM committee_role_templates WHERE session_year=2026 AND aktif=1;
