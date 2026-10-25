CREATE DATABASE IF NOT EXISTS dbnilai
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE dbnilai;

CREATE TABLE IF NOT EXISTS nilai (
  nim VARCHAR(20) NOT NULL,
  nm_lengkap VARCHAR(100) NOT NULL,
  file_path VARCHAR(255) NULL,
  nilai_akhir DECIMAL(5,2) NOT NULL,
  nilai_huruf CHAR(1) NULL,
  predikat VARCHAR(50) NULL,
  created_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modified_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (nim),
  KEY nilai_nama_index (nm_lengkap),
  KEY nilai_predikat_index (predikat)
) ENGINE=InnoDB;

CREATE OR REPLACE VIEW nilaimhsw AS SELECT * FROM nilai;

INSERT INTO nilai (nim, nm_lengkap, file_path, nilai_akhir, nilai_huruf, predikat) VALUES
  ('3202310001', 'Ferry Faisal', 'img/user1-128x128.jpg', 45.65, 'D', 'Tidak Lulus'),
  ('3202310002', 'Ferry Irawan', 'img/user2-160x160.jpg', 78.50, 'B', 'Memuaskan'),
  ('3202310003', 'Habib Hidayat', 'img/user3-128x128.jpg', 60.75, 'C', 'Cukup'),
  ('3202310004', 'Irka Pratama', 'img/user4-128x128.jpg', 87.50, 'A', 'Sangat Memuaskan'),
  ('3202310005', 'Aqi Ramadhan', 'img/user5-128x128.jpg', 77.75, 'B', 'Memuaskan'),
  ('3202310006', 'Dimas Prasetyo', 'img/user6-128x128.jpg', 99.00, 'A', 'Sangat Memuaskan'),
  ('3202310007', 'Siti Nurhaliza', 'img/avatar2.png', 88.20, 'A', 'Sangat Memuaskan'),
  ('3202310008', 'Budi Santoso', 'img/user7-128x128.jpg', 55.40, 'D', 'Tidak Lulus'),
  ('3202310009', 'Dewi Anggraini', 'img/avatar3.png', 82.00, 'B', 'Memuaskan'),
  ('3202310010', 'Rian Hidayat', 'img/user8-128x128.jpg', 68.50, 'C', 'Cukup'),
  ('3202310011', 'Anisa Rahmawati', 'img/avatar4.png', 91.30, 'A', 'Sangat Memuaskan'),
  ('3202310012', 'Fajar Nugroho', 'img/avatar.png', 74.00, 'C', 'Cukup'),
  ('3202310013', 'Putri Wulandari', 'img/avatar5.png', 84.75, 'B', 'Memuaskan'),
  ('3202310014', 'Rizky Kurniawan', 'img/user1-128x128.jpg', 48.00, 'E', 'Tidak Lulus'),
  ('3202310015', 'Nadia Safitri', 'img/avatar2.png', 79.50, 'B', 'Memuaskan'),
  ('3202310016', 'Hendra Saputra', 'img/user3-128x128.jpg', 62.00, 'C', 'Cukup'),
  ('3202310017', 'Maya Indah', 'img/avatar3.png', 95.50, 'A', 'Sangat Memuaskan'),
  ('3202310018', 'Eko Prabowo', 'img/user5-128x128.jpg', 52.30, 'D', 'Tidak Lulus'),
  ('3202310019', 'Zahra Amelia', 'img/avatar4.png', 86.00, 'A', 'Sangat Memuaskan'),
  ('3202310020', 'Doni Kusuma', 'img/user6-128x128.jpg', 71.25, 'C', 'Cukup')
ON DUPLICATE KEY UPDATE
  nm_lengkap = VALUES(nm_lengkap),
  file_path = VALUES(file_path),
  nilai_akhir = VALUES(nilai_akhir),
  nilai_huruf = VALUES(nilai_huruf),
  predikat = VALUES(predikat);
