
### 1. Fitur Pembacaan Data Foto
*Menyimpan data foto dan menampilkan foto kecil di sebelah Nama Mahasiswa pada kolom yang sama.*

- **Database ([database/schema.sql](file:///c:/xampp/htdocs/admin-lte-v4100/selectdb/database/schema.sql#L9-L18))**:
  - Kolom `file_path VARCHAR(255) NULL` ditambahkan pada tabel `nilai`.
  - `CREATE OR REPLACE VIEW nilaimhsw AS SELECT * FROM nilai;` dibuat untuk memenuhi acuan nama tabel `nilaimhsw`.
- **Query & Penanganan Foto ([readnilai.php](file:///c:/xampp/htdocs/admin-lte-v4100/selectdb/readnilai.php#L98-L162))**:
  - Baris 98–105: Pemeriksaan otomatis kolom `file_path` dan *view* `nilaimhsw`.
  - Baris 137–162: Penanganan unggah file foto ke folder `img/` dan penetapan `$old['file_path']`.
  - Baris 201, 209, 216: Binding kolom `file_path` pada query `INSERT` dan `UPDATE`.
  - Baris 268–275: Kolom `file_path` dimasukkan ke query `SELECT`.
- **Tampilan Tabel ([readnilai.php](file:///c:/xampp/htdocs/admin-lte-v4100/selectdb/readnilai.php#L516-L562))**:
  - Baris 516: Judul kolom diubah menjadi `<th scope="col">Nama Mahasiswa</th>`.
  - Baris 545–562: Tag `<img>` kecil berukuran 36x36px (`class="rounded-circle border shadow-sm flex-shrink-0"`) ditampilkan berdampingan dengan `<span><?= e($record['nm_lengkap']) ?></span>` di dalam satu kolom yang sama, dilengkapi *fallback* avatar default jika file belum ada.
- **Form Modal ([readnilai.php](file:///c:/xampp/htdocs/admin-lte-v4100/selectdb/readnilai.php#L722-L773))**:
  - Baris 722: Penambahan `enctype="multipart/form-data"`.
  - Baris 735: Input tersembunyi `existing_file_path`.
  - Baris 762–773: Komponen `<input type="file" id="foto" name="foto">`.

---

### 2. Fitur Pencarian Data (Searching)
*Menggunakan text input dan drop-down option yang telah disediakan template.*

- **1. Pencarian berdasarkan Nama (*search by name*)**:
  - Logika PHP (Baris 68, 245–249): Menangkap `$_GET['search']` dan menerapkan kondisi SQL:
    ```php
    $where[] = '(nm_lengkap LIKE :search_name OR nim LIKE :search_nim)';
    ```
  - Komponen UI (Baris 437–451): Input teks dengan ikon `bi-search`:
    ```html
    <div class="grade-toolbar__search">
        <label for="search" class="form-label">Cari Nama Mahasiswa</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" id="search" name="search" class="form-control" placeholder="Cari berdasarkan nama..." value="<?= e($search) ?>" />
        </div>
    </div>
    ```
- **2. Pencarian berdasarkan Predikat (*search by predicate*)**:
  - Logika PHP (Baris 69–72, 252–255): Menangkap `$_GET['filter_predikat']` dan menerapkan kondisi SQL:
    ```php
    $where[] = 'predikat = :predicate';
    ```
  - Komponen UI (Baris 453–465): Dropdown select dari template:
    ```html
    <div class="grade-toolbar__filter">
        <label for="filter_predikat" class="form-label">Predikat</label>
        <select id="filter_predikat" name="filter_predikat" class="form-select">
            <option value="">Semua predikat</option>
            <!-- opsi: Sangat Memuaskan, Memuaskan, Cukup, Tidak Lulus -->
        </select>
    </div>
    ```

---

### 3. Fitur Pengurutan Data (Sorting) berdasarkan Nilai Akhir
*Menggunakan ikon panah kecil di judul kolom yang telah disediakan template.*

- **Logika & Query SQL ([readnilai.php](file:///c:/xampp/htdocs/admin-lte-v4100/selectdb/readnilai.php#L84-L93))**:
  - Baris 84–93: Validasi parameter `$_GET['sort'] === 'nilai_akhir'`, `$_GET['order']` (`asc` / `desc`), serta penentuan `$nextOrder`.
  - Baris 261–266: Penentuan klausa `ORDER BY nilai_akhir ASC/DESC, nim ASC`.
- **Komponen Ikon Panah di Header ([readnilai.php](file:///c:/xampp/htdocs/admin-lte-v4100/selectdb/readnilai.php#L517-L535))**:
  - Tautan interaktif pada judul kolom "Nilai Akhir" menggunakan ikon Bootstrap Icons dari template:
    - `bi bi-arrow-up`: Muncul saat urutan aktif *Ascending*.
    - `bi bi-arrow-down`: Muncul saat urutan aktif *Descending*.
    - `bi bi-arrow-down-up`: Muncul saat kolom belum aktif diurutkan.

---

### 4. Fitur Pagination (Limit 5, 10, 15 & Tombol First, Prev, Next, Last)
*Membatasi limit record per page dan navigasi tombol First Page, Previous, Next, Last Page.*

- **Drop-down Limit 5, 10, 15 Record ([readnilai.php](file:///c:/xampp/htdocs/admin-lte-v4100/selectdb/readnilai.php#L75-L78))**:
  - Baris 36, 75–78: Definisi `$allowedLimits = [5, 10, 15]` dan validasi `$_GET['limit']`.
  - Baris 635–644: Elemen `<select id="limit-select">` di footer tabel untuk memilih 5, 10, atau 15 *record per page* secara langsung (`onchange="window.location.href=this.value"`).
- **Tombol Navigasi Pagination ([readnilai.php](file:///c:/xampp/htdocs/admin-lte-v4100/selectdb/readnilai.php#L647-L708))**:
  - Baris 19–32: Fungsi `pageUrl()` yang menyertakan parameter `page`, `limit`, `search`, `filter_predikat`, `sort`, dan `order`.
  - Baris 650–659: Tombol **First Page** (`<i class="bi bi-chevron-double-left"></i> First`).
  - Baris 662–671: Tombol **Previous Page** (`<i class="bi bi-chevron-left"></i> Previous`).
  - Baris 674–683: Tombol angka halaman (`1`, `2`, dst.).
  - Baris 686–695: Tombol **Next Page** (`Next <i class="bi bi-chevron-right"></i>`).
  - Baris 698–707: Tombol **Last Page** (`Last <i class="bi bi-chevron-double-right"></i>`).
  - Status `disabled` otomatis diterapkan pada First/Prev saat di halaman 1, dan pada Next/Last saat di halaman terakhir.

---

### 5. Pengisian Data Dummy
*Menyediakan data dummy secukupnya agar semua fitur dapat didemonstrasikan.*

- **Database ([database/schema.sql](file:///c:/xampp/htdocs/admin-lte-v4100/selectdb/database/schema.sql#L20-L47))**:
  - 20 data mahasiswa dengan path foto di folder `img/`, variasi nilai akhir (45 s/d 99), nilai huruf (A–E), dan keempat kategori predikat.
