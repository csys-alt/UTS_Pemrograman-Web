CREATE DATABASE IF NOT EXISTS dbnilai
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE dbnilai;

CREATE TABLE IF NOT EXISTS nilai (
  nim VARCHAR(20) NOT NULL,
  nm_lengkap VARCHAR(100) NOT NULL,
  nilai_akhir DECIMAL(5,2) NOT NULL,
  nilai_huruf CHAR(1) NULL,
  predikat VARCHAR(50) NULL,
  created_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modified_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (nim),
  KEY nilai_nama_index (nm_lengkap),
  KEY nilai_predikat_index (predikat)
) ENGINE=InnoDB;

INSERT IGNORE INTO nilai (nim, nm_lengkap, nilai_akhir, nilai_huruf, predikat) VALUES
  ('3202310001', 'Ferry Faisal', 45.65, NULL, NULL),
  ('3202310002', 'Ferry', 78.50, NULL, NULL),
  ('3202310003', 'Habib', 60.75, NULL, NULL),
  ('3202310004', 'Irka', 87.50, 'A', 'Sangat Memuaskan'),
  ('3202310005', 'Aqi', 77.75, 'B', 'Memuaskan');
