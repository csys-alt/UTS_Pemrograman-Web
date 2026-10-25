<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connection.php';

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('pageUrl')) {
    function pageUrl(int $page, string $search, string $predicate, int $limit = 10, string $sort = '', string $order = ''): string
    {
        $params = [
            'page' => $page,
            'limit' => $limit,
        ];
        if ($search !== '') {
            $params['search'] = $search;
        }
        if ($predicate !== '') {
            $params['filter_predikat'] = $predicate;
        }
        if ($sort !== '') {
            $params['sort'] = $sort;
            $params['order'] = $order;
        }
        return '?' . http_build_query($params);
    }
}

$predicateOptions = ['Sangat Memuaskan', 'Memuaskan', 'Cukup', 'Tidak Lulus'];
$letterOptions = ['A', 'B', 'C', 'D', 'E'];
$allowedLimits = [5, 10, 15];

$errors = [];
$actionError = null;
$formMode = 'create';
$originalNim = '';
$old = [
    'nim' => '',
    'nm_lengkap' => '',
    'file_path' => '',
    'nilai_akhir' => '',
    'nilai_huruf' => '',
    'predikat' => '',
];
$records = [];
$databaseError = null;
$stats = [
    'total' => 0,
    'average' => null,
    'highest' => null,
];

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 2. Searching parameters
$search = trim((string) ($_GET['search'] ?? ''));
$filterPredicate = trim((string) ($_GET['filter_predikat'] ?? ''));
if (!in_array($filterPredicate, $predicateOptions, true)) {
    $filterPredicate = '';
}

// 4. Pagination limit parameter (5, 10, 15)
$limit = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT);
if (!in_array($limit, $allowedLimits, true)) {
    $limit = 10;
}

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, [
    'options' => ['default' => 1, 'min_range' => 1],
]);

// 3. Sorting by nilai_akhir
$sort = trim((string) ($_GET['sort'] ?? ''));
$order = strtolower(trim((string) ($_GET['order'] ?? '')));
if ($sort !== 'nilai_akhir') {
    $sort = '';
}
if (!in_array($order, ['asc', 'desc'], true)) {
    $order = 'asc';
}
$nextOrder = ($sort === 'nilai_akhir' && $order === 'asc') ? 'desc' : 'asc';

try {
    $pdo = databaseConnection();

    // Ensure file_path column exists in nilai table
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM nilai LIKE 'file_path'")->fetchAll();
        if (empty($checkCol)) {
            $pdo->exec("ALTER TABLE nilai ADD COLUMN file_path VARCHAR(255) NULL AFTER nm_lengkap");
        }
        $pdo->exec("CREATE OR REPLACE VIEW nilaimhsw AS SELECT * FROM nilai");
    } catch (Throwable $e) {
        // Table or view check fallback
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $action = (string) ($_POST['action'] ?? 'create');
        $submittedToken = (string) ($_POST['csrf_token'] ?? '');
        $validToken = hash_equals((string) $_SESSION['csrf_token'], $submittedToken);

        if ($action === 'delete') {
            $deleteNim = trim((string) ($_POST['nim'] ?? ''));

            if (!$validToken) {
                $actionError = 'Sesi penghapusan tidak valid. Muat ulang halaman lalu coba lagi.';
            } elseif (preg_match('/^[A-Za-z0-9.-]{3,20}$/', $deleteNim) !== 1) {
                $actionError = 'Data yang akan dihapus tidak valid.';
            } else {
                $statement = $pdo->prepare('DELETE FROM nilai WHERE nim = :nim');
                $statement->execute(['nim' => $deleteNim]);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: readnilai.php?deleted=1', true, 303);
                exit;
            }
        } else {
            $formMode = $action === 'update' ? 'edit' : 'create';
            $originalNim = trim((string) ($_POST['original_nim'] ?? ''));

            foreach ($old as $field => $default) {
                $old[$field] = trim((string) ($_POST[$field] ?? $default));
            }

            // Handle optional photo upload
            $filePath = trim((string) ($_POST['existing_file_path'] ?? ''));
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $uploadedFile = $_FILES['foto'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $fileInfo = @getimagesize($uploadedFile['tmp_name']);
                $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));

                if ($fileInfo === false || !in_array($fileInfo['mime'], $allowedTypes, true)) {
                    $errors['foto'] = 'File yang diunggah harus berupa gambar (JPG, PNG, GIF, WEBP).';
                } elseif ($uploadedFile['size'] > 2000000) {
                    $errors['foto'] = 'Ukuran file foto maksimal 2 MB.';
                } else {
                    $targetDir = __DIR__ . '/img';
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    $fileName = 'foto_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                    $targetFile = $targetDir . '/' . $fileName;
                    if (move_uploaded_file($uploadedFile['tmp_name'], $targetFile)) {
                        $filePath = 'img/' . $fileName;
                    } else {
                        $errors['foto'] = 'Gagal menyimpan file foto.';
                    }
                }
            }
            $old['file_path'] = $filePath;

            if (!in_array($action, ['create', 'update'], true)) {
                $errors['form'] = 'Aksi formulir tidak dikenali.';
            } elseif (!$validToken) {
                $errors['form'] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
            }

            if ($formMode === 'edit' && preg_match('/^[A-Za-z0-9.-]{3,20}$/', $originalNim) !== 1) {
                $errors['form'] = 'Data yang akan diubah tidak valid.';
            }

            if (preg_match('/^[A-Za-z0-9.-]{3,20}$/', $old['nim']) !== 1) {
                $errors['nim'] = 'NIM wajib diisi, terdiri dari 3-20 huruf atau angka.';
            }

            if ($old['nm_lengkap'] === '' || mb_strlen($old['nm_lengkap']) > 100) {
                $errors['nm_lengkap'] = 'Nama lengkap wajib diisi dan maksimal 100 karakter.';
            }

            $numericGrade = filter_var($old['nilai_akhir'], FILTER_VALIDATE_FLOAT);
            if ($numericGrade === false || $numericGrade < 0 || $numericGrade > 100) {
                $errors['nilai_akhir'] = 'Nilai akhir wajib berupa angka dari 0 sampai 100.';
            }

            $old['nilai_huruf'] = strtoupper($old['nilai_huruf']);
            if ($old['nilai_huruf'] !== '' && !in_array($old['nilai_huruf'], $letterOptions, true)) {
                $errors['nilai_huruf'] = 'Nilai huruf harus A, B, C, D, atau E.';
            }

            if ($old['predikat'] !== '' && !in_array($old['predikat'], $predicateOptions, true)) {
                $errors['predikat'] = 'Pilih predikat yang tersedia.';
            }

            if ($errors === []) {
                try {
                    $parameters = [
                        'nim' => $old['nim'],
                        'nm_lengkap' => $old['nm_lengkap'],
                        'file_path' => $old['file_path'] !== '' ? $old['file_path'] : null,
                        'nilai_akhir' => number_format((float) $numericGrade, 2, '.', ''),
                        'nilai_huruf' => $old['nilai_huruf'] !== '' ? $old['nilai_huruf'] : null,
                        'predikat' => $old['predikat'] !== '' ? $old['predikat'] : null,
                    ];

                    if ($formMode === 'edit') {
                        $statement = $pdo->prepare(
                            'UPDATE nilai SET nim = :nim, nm_lengkap = :nm_lengkap, file_path = :file_path, '
                                . 'nilai_akhir = :nilai_akhir, nilai_huruf = :nilai_huruf, predikat = :predikat '
                                . 'WHERE nim = :original_nim'
                        );
                        $parameters['original_nim'] = $originalNim;
                    } else {
                        $statement = $pdo->prepare(
                            'INSERT INTO nilai (nim, nm_lengkap, file_path, nilai_akhir, nilai_huruf, predikat) '
                                . 'VALUES (:nim, :nm_lengkap, :file_path, :nilai_akhir, :nilai_huruf, :predikat)'
                        );
                    }

                    $statement->execute($parameters);
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $successFlag = $formMode === 'edit' ? 'updated' : 'created';
                    header('Location: readnilai.php?' . $successFlag . '=1', true, 303);
                    exit;
                } catch (PDOException $exception) {
                    if ($exception->getCode() === '23000') {
                        $errors['nim'] = 'NIM tersebut sudah digunakan oleh data lain.';
                    } else {
                        throw $exception;
                    }
                }
            }
        }
    }

    $stats = $pdo->query(
        'SELECT COUNT(*) AS total, AVG(nilai_akhir) AS average, MAX(nilai_akhir) AS highest FROM nilai'
    )->fetch() ?: $stats;

    $where = [];
    $queryParameters = [];

    // Search by name (and NIM)
    if ($search !== '') {
        $where[] = '(nm_lengkap LIKE :search_name OR nim LIKE :search_nim)';
        $queryParameters['search_name'] = '%' . $search . '%';
        $queryParameters['search_nim'] = '%' . $search . '%';
    }

    // Search by predicate
    if ($filterPredicate !== '') {
        $where[] = 'predikat = :predicate';
        $queryParameters['predicate'] = $filterPredicate;
    }

    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $countStatement = $pdo->prepare('SELECT COUNT(*) FROM nilai' . $whereSql);
    $countStatement->execute($queryParameters);
    $totalData = (int) $countStatement->fetchColumn();
    $totalPages = max(1, (int) ceil($totalData / $limit));
    $page = min((int) $page, $totalPages);
    $offset = ($page - 1) * $limit;

    // 3. Sorting by nilai_akhir
    if ($sort === 'nilai_akhir') {
        $direction = $order === 'desc' ? 'DESC' : 'ASC';
        $orderSql = " ORDER BY nilai_akhir {$direction}, nim ASC";
    } else {
        $orderSql = " ORDER BY created_date DESC, nim DESC";
    }

    $dataStatement = $pdo->prepare(
        'SELECT nim, nm_lengkap, file_path, nilai_akhir, nilai_huruf, predikat, created_date, modified_date '
            . 'FROM nilai'
            . $whereSql
            . $orderSql
            . ' LIMIT :limit OFFSET :offset'
    );

    foreach ($queryParameters as $parameter => $value) {
        $dataStatement->bindValue(':' . $parameter, $value, PDO::PARAM_STR);
    }
    $dataStatement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $dataStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStatement->execute();
    $records = $dataStatement->fetchAll();
} catch (Throwable $exception) {
    $databaseError = 'Koneksi database gagal. Pastikan MySQL XAMPP aktif dan skema database sudah diimpor.';
    $totalData = 0;
    $totalPages = 1;
    $offset = 0;
}

$startNumber = $totalData > 0 ? $offset + 1 : 0;
$endNumber = min($offset + $limit, $totalData);
$showModal = $errors !== [] || isset($_GET['new']);
$created = isset($_GET['created']) && $_GET['created'] === '1';
$updated = isset($_GET['updated']) && $_GET['updated'] === '1';
$deleted = isset($_GET['deleted']) && $_GET['deleted'] === '1';
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <title>Rekap Nilai Mahasiswa</title>
    <script>
        (() => {
            'use strict';
            const root = document.documentElement;
            const storageKey = 'lte-theme';
            let storedTheme = null;
            try {
                storedTheme = localStorage.getItem(storageKey);
            } catch {
                storedTheme = null;
            }

            let theme = storedTheme;
            if (theme !== 'light' && theme !== 'dark') {
                theme = globalThis.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                root.setAttribute('data-lte-theme-resolved', '');
            }

            root.setAttribute('data-bs-theme', theme);
            root.style.colorScheme = theme;
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
    <meta
        name="description"
        content="Dashboard rekap nilai mahasiswa yang terhubung ke MySQL XAMPP." />
    <link rel="preload" href="dist/css/adminlte.css" as="style" />
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css"
        integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q="
        crossorigin="anonymous" />
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css"
        crossorigin="anonymous" />
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="dist/css/adminlte.css" />
    <link rel="stylesheet" href="assets/app.css" />
</head>

<body
    class="layout-fixed sidebar-expand-lg bg-body-tertiary"
    data-show-grade-modal="<?= $showModal ? 'true' : 'false' ?>"
    data-grade-form-mode="<?= e($formMode) ?>">
    <div class="app-wrapper">
        <main class="app-main" id="main-content">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-sm-6">
                            <h1 class="mb-0 fs-3">Rekap Nilai Mahasiswa</h1>
                        </div>
                        <div class="col-sm-6">
                            <nav aria-label="Breadcrumb">
                                <ol class="breadcrumb float-sm-end mb-0">
                                    <li class="breadcrumb-item"><a href="readnilai.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Data Nilai</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">
                    <?php if ($created): ?>
                        <div class="alert alert-success alert-dismissible" role="status">
                            <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>
                            Data nilai berhasil ditambahkan.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($updated): ?>
                        <div class="alert alert-success alert-dismissible" role="status">
                            <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>
                            Data nilai berhasil diperbarui.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($deleted): ?>
                        <div class="alert alert-success alert-dismissible" role="status">
                            <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>
                            Data nilai berhasil dihapus.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($actionError !== null): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
                            <?= e($actionError) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($databaseError !== null): ?>
                        <div class="alert alert-danger" role="alert">
                            <h2 class="h5 alert-heading">Database belum dapat dibaca</h2>
                            <p class="mb-0"><?= e($databaseError) ?></p>
                        </div>
                    <?php endif; ?>

                    <section class="card" aria-labelledby="grade-directory-title">
                        <div class="card-header">
                            <h2 class="card-title" id="grade-directory-title">Direktori Nilai</h2>
                            <div class="card-tools text-body-secondary small">
                                <?= e((string) $totalData) ?> hasil
                            </div>
                        </div>

                        <!-- 2. Fitur Pencarian Data (Searching) -->
                        <div class="card-body border-bottom">
                            <form method="get" action="readnilai.php" class="grade-toolbar" role="search">
                                <input type="hidden" name="limit" value="<?= e((string) $limit) ?>" />
                                <?php if ($sort !== ''): ?>
                                    <input type="hidden" name="sort" value="<?= e($sort) ?>" />
                                    <input type="hidden" name="order" value="<?= e($order) ?>" />
                                <?php endif; ?>

                                <!-- 2.1 Pencarian berdasarkan Nama (search by name) -->
                                <div class="grade-toolbar__search">
                                    <label for="search" class="form-label">Cari Nama Mahasiswa</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-search" aria-hidden="true"></i>
                                        </span>
                                        <input
                                            type="search"
                                            id="search"
                                            name="search"
                                            class="form-control"
                                            placeholder="Cari berdasarkan nama..."
                                            value="<?= e($search) ?>" />
                                    </div>
                                </div>

                                <!-- 2.2 Pencarian berdasarkan Predikat (search by predicate) -->
                                <div class="grade-toolbar__filter">
                                    <label for="filter_predikat" class="form-label">Predikat</label>
                                    <select id="filter_predikat" name="filter_predikat" class="form-select">
                                        <option value="">Semua predikat</option>
                                        <?php foreach ($predicateOptions as $predicate): ?>
                                            <option value="<?= e($predicate) ?>" <?= $filterPredicate === $predicate ? 'selected' : '' ?>>
                                                <?= e($predicate) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="grade-toolbar__actions d-flex gap-2">
                                    <button type="submit" class="btn btn-outline-secondary">
                                        <i class="bi bi-funnel me-1" aria-hidden="true"></i>
                                        Terapkan
                                    </button>
                                    <?php if ($search !== '' || $filterPredicate !== '' || $sort !== ''): ?>
                                        <a href="readnilai.php<?= $limit !== 10 ? '?limit=' . $limit : '' ?>" class="btn btn-outline-secondary">Reset</a>
                                    <?php endif; ?>
                                    <button
                                        type="button"
                                        class="btn btn-primary"
                                        data-grade-action="create"
                                        data-bs-toggle="modal"
                                        data-bs-target="#grade-modal">
                                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
                                        Tambah Nilai
                                    </button>
                                </div>
                            </form>
                        </div>

                        <?php if ($records === []): ?>
                            <div class="card-body">
                                <div class="empty-state">
                                    <i class="bi bi-inbox text-body-secondary" aria-hidden="true"></i>
                                    <h3 class="h5">Data nilai tidak ditemukan</h3>
                                    <p class="text-body-secondary">
                                        <?= $search !== '' || $filterPredicate !== ''
                                            ? 'Ubah pencarian atau reset filter untuk melihat data lain.'
                                            : 'Tambahkan data pertama untuk mulai membuat rekap nilai.' ?>
                                    </p>
                                    <?php if ($search === '' && $filterPredicate === ''): ?>
                                        <button
                                            type="button"
                                            class="btn btn-primary"
                                            data-grade-action="create"
                                            data-bs-toggle="modal"
                                            data-bs-target="#grade-modal">
                                            Tambah Nilai
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 grade-table">
                                    <caption class="visually-hidden">Daftar nilai mahasiswa</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col" class="ps-3">NIM</th>
                                            <th scope="col">Nama Mahasiswa</th>
                                            <!-- 3. Pengurutan Data (Sorting) berdasarkan data Nilai Akhir -->
                                            <th scope="col">
                                                <a href="<?= e(pageUrl(1, $search, $filterPredicate, $limit, 'nilai_akhir', $nextOrder)) ?>" 
                                                   class="text-decoration-none text-body d-inline-flex align-items-center" 
                                                   title="Urutkan berdasarkan Nilai Akhir (<?= $nextOrder === 'asc' ? 'Naik' : 'Turun' ?>)">
                                                    <span>Nilai Akhir</span>
                                                    <?php if ($sort === 'nilai_akhir'): ?>
                                                        <?php if ($order === 'asc'): ?>
                                                            <i class="bi bi-arrow-up text-primary ms-1" aria-label="(Urutan Naik)"></i>
                                                        <?php else: ?>
                                                            <i class="bi bi-arrow-down text-primary ms-1" aria-label="(Urutan Turun)"></i>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <i class="bi bi-arrow-down-up text-body-secondary ms-1 small" aria-hidden="true"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th scope="col">Nilai Huruf</th>
                                            <th scope="col">Predikat</th>
                                            <th scope="col">Dibuat</th>
                                            <th scope="col">Diubah</th>
                                            <th scope="col" class="text-center pe-3">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($records as $record): ?>
                                            <tr>
                                                <th scope="row" class="ps-3 fw-semibold"><?= e($record['nim']) ?></th>
                                                <!-- 1. Pembacaan Data Foto di kolom Nama Mahasiswa -->
                                                <td>
                                                    <?php
                                                        $fotoPath = !empty($record['file_path']) ? $record['file_path'] : 'img/default-150x150.png';
                                                        if (!str_starts_with($fotoPath, 'http') && !file_exists(__DIR__ . '/' . $fotoPath)) {
                                                            $fotoPath = 'img/default-150x150.png';
                                                        }
                                                    ?>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img
                                                            src="<?= e($fotoPath) ?>"
                                                            alt="Foto <?= e($record['nm_lengkap']) ?>"
                                                            class="rounded-circle border shadow-sm flex-shrink-0"
                                                            style="width: 36px; height: 36px; object-fit: cover;"
                                                            onerror="this.src='img/default-150x150.png'" />
                                                        <span class="fw-medium"><?= e($record['nm_lengkap']) ?></span>
                                                    </div>
                                                </td>
                                                <td><?= e(number_format((float) $record['nilai_akhir'], 2, ',', '.')) ?></td>
                                                <td>
                                                    <?php if ($record['nilai_huruf'] !== null): ?>
                                                        <span class="badge text-bg-primary"><?= e($record['nilai_huruf']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-body-secondary">Belum diisi</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= $record['predikat'] !== null
                                                        ? e($record['predikat'])
                                                        : '<span class="text-body-secondary">Belum ditentukan</span>' ?>
                                                </td>
                                                <td>
                                                    <time datetime="<?= e($record['created_date']) ?>">
                                                        <?= e(date('d/m/Y H:i', strtotime((string) $record['created_date']))) ?>
                                                    </time>
                                                </td>
                                                <td>
                                                    <time datetime="<?= e($record['modified_date']) ?>">
                                                        <?= e(date('d/m/Y H:i', strtotime((string) $record['modified_date']))) ?>
                                                    </time>
                                                </td>
                                                <td class="pe-3">
                                                    <div
                                                        class="grade-actions"
                                                        role="group"
                                                        aria-label="Aksi untuk <?= e($record['nm_lengkap']) ?>">
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-primary"
                                                            data-grade-action="edit"
                                                            data-nim="<?= e($record['nim']) ?>"
                                                            data-nama="<?= e($record['nm_lengkap']) ?>"
                                                            data-foto="<?= e($record['file_path'] ?? '') ?>"
                                                            data-nilai-akhir="<?= e(number_format((float) $record['nilai_akhir'], 2, '.', '')) ?>"
                                                            data-nilai-huruf="<?= e($record['nilai_huruf'] ?? '') ?>"
                                                            data-predikat="<?= e($record['predikat'] ?? '') ?>"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#grade-modal"
                                                            title="Edit data"
                                                            aria-label="Edit data <?= e($record['nm_lengkap']) ?>">
                                                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-danger"
                                                            data-grade-action="delete"
                                                            data-nim="<?= e($record['nim']) ?>"
                                                            data-nama="<?= e($record['nm_lengkap']) ?>"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#delete-grade-modal"
                                                            title="Hapus data"
                                                            aria-label="Hapus data <?= e($record['nm_lengkap']) ?>">
                                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <!-- 4. Fitur Pagination dan Pilihan Limit Record (5, 10, 15) -->
                        <div class="card-footer d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-center">
                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <p class="mb-0 text-body-secondary small" aria-live="polite">
                                    Menampilkan <?= e((string) $startNumber) ?>-<?= e((string) $endNumber) ?> dari <?= e((string) $totalData) ?> data.
                                </p>
                                <!-- Drop-down limit 5, 10, 15 record per page -->
                                <div class="d-flex align-items-center gap-2">
                                    <label for="limit-select" class="text-body-secondary small mb-0">Tampilkan:</label>
                                    <select id="limit-select" class="form-select form-select-sm" style="width: auto;" onchange="window.location.href=this.value">
                                        <?php foreach ($allowedLimits as $optLimit): ?>
                                            <option value="<?= e(pageUrl(1, $search, $filterPredicate, $optLimit, $sort, $order)) ?>" <?= $limit === $optLimit ? 'selected' : '' ?>>
                                                <?= $optLimit ?> record per page
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Tombol First Page, Previous, Angka, Next, Last Page -->
                            <nav aria-label="Halaman data nilai">
                                <ul class="pagination pagination-sm mb-0 flex-wrap">
                                    <!-- First Page -->
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a
                                            class="page-link"
                                            href="<?= e(pageUrl(1, $search, $filterPredicate, $limit, $sort, $order)) ?>"
                                            aria-label="First Page"
                                            title="First Page">
                                            <i class="bi bi-chevron-double-left" aria-hidden="true"></i>
                                            <span class="d-none d-sm-inline ms-1">First</span>
                                        </a>
                                    </li>

                                    <!-- Previous Page -->
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a
                                            class="page-link"
                                            href="<?= e(pageUrl(max(1, $page - 1), $search, $filterPredicate, $limit, $sort, $order)) ?>"
                                            aria-label="Previous Page"
                                            title="Previous Page">
                                            <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                            <span class="d-none d-sm-inline ms-1">Previous</span>
                                        </a>
                                    </li>

                                    <!-- Numeric Pages -->
                                    <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                                        <li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>">
                                            <a
                                                class="page-link"
                                                href="<?= e(pageUrl($pageNumber, $search, $filterPredicate, $limit, $sort, $order)) ?>"
                                                <?= $pageNumber === $page ? 'aria-current="page"' : '' ?>>
                                                <?= e((string) $pageNumber) ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <!-- Next Page -->
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a
                                            class="page-link"
                                            href="<?= e(pageUrl(min($totalPages, $page + 1), $search, $filterPredicate, $limit, $sort, $order)) ?>"
                                            aria-label="Next Page"
                                            title="Next Page">
                                            <span class="d-none d-sm-inline me-1">Next</span>
                                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                        </a>
                                    </li>

                                    <!-- Last Page -->
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a
                                            class="page-link"
                                            href="<?= e(pageUrl($totalPages, $search, $filterPredicate, $limit, $sort, $order)) ?>"
                                            aria-label="Last Page"
                                            title="Last Page">
                                            <span class="d-none d-sm-inline me-1">Last</span>
                                            <i class="bi bi-chevron-double-right" aria-hidden="true"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </section>
                </div>
            </div>
        </main>

        <footer class="app-footer">
            <div class="float-end d-none d-sm-inline">MySQL XAMPP</div>
            <strong>Sistem Nilai Mahasiswa</strong>
        </footer>
    </div>

    <!-- Modal Form Tambah / Edit -->
    <div
        class="modal fade"
        id="grade-modal"
        tabindex="-1"
        aria-labelledby="grade-modal-title"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="readnilai.php" id="grade-form" enctype="multipart/form-data" novalidate>
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="grade-modal-title">
                            <?= $formMode === 'edit' ? 'Edit Data Nilai' : 'Tambahkan Data Nilai' ?>
                        </h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body modal-body-scroll">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>" />
                        <input
                            type="hidden"
                            name="action"
                            id="grade-form-action"
                            value="<?= $formMode === 'edit' ? 'update' : 'create' ?>" />
                        <input
                            type="hidden"
                            name="original_nim"
                            id="grade-original-nim"
                            value="<?= e($originalNim) ?>" />
                        <input
                            type="hidden"
                            name="existing_file_path"
                            id="grade-existing-foto"
                            value="<?= e($old['file_path']) ?>" />

                        <?php if (isset($errors['form'])): ?>
                            <div class="alert alert-danger" role="alert"><?= e($errors['form']) ?></div>
                        <?php elseif ($errors !== []): ?>
                            <div class="alert alert-danger" role="alert">
                                Periksa kembali kolom yang ditandai sebelum menyimpan.
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="nim" class="form-label">NIM</label>
                            <input
                                type="text"
                                id="nim"
                                name="nim"
                                class="form-control <?= isset($errors['nim']) ? 'is-invalid' : '' ?>"
                                value="<?= e($old['nim']) ?>"
                                placeholder="Contoh: 3202310001"
                                maxlength="20"
                                inputmode="numeric"
                                autocomplete="off"
                                required
                                <?= isset($errors['nim']) ? 'aria-invalid="true" aria-describedby="nim-error"' : '' ?> />
                            <?php if (isset($errors['nim'])): ?>
                                <div class="invalid-feedback" id="nim-error"><?= e($errors['nim']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="nm_lengkap" class="form-label">Nama Lengkap</label>
                            <input
                                type="text"
                                id="nm_lengkap"
                                name="nm_lengkap"
                                class="form-control <?= isset($errors['nm_lengkap']) ? 'is-invalid' : '' ?>"
                                value="<?= e($old['nm_lengkap']) ?>"
                                placeholder="Contoh: Ferry Faisal"
                                maxlength="100"
                                autocomplete="name"
                                required
                                <?= isset($errors['nm_lengkap']) ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?> />
                            <?php if (isset($errors['nm_lengkap'])): ?>
                                <div class="invalid-feedback" id="name-error"><?= e($errors['nm_lengkap']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- 1. Input Foto Mahasiswa -->
                        <div class="mb-3">
                            <label for="foto" class="form-label">Foto Mahasiswa <span class="text-body-secondary small">(Opsional)</span></label>
                            <input
                                type="file"
                                id="foto"
                                name="foto"
                                class="form-control <?= isset($errors['foto']) ? 'is-invalid' : '' ?>"
                                accept="image/jpeg,image/png,image/gif,image/webp" />
                            <div class="form-text">Format: JPG, PNG, GIF, atau WEBP. Maksimal 2 MB.</div>
                            <?php if (isset($errors['foto'])): ?>
                                <div class="invalid-feedback"><?= e($errors['foto']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="nilai_akhir" class="form-label">Nilai Akhir</label>
                            <input
                                type="number"
                                id="nilai_akhir"
                                name="nilai_akhir"
                                class="form-control <?= isset($errors['nilai_akhir']) ? 'is-invalid' : '' ?>"
                                value="<?= e($old['nilai_akhir']) ?>"
                                placeholder="Contoh: 85,50"
                                min="0"
                                max="100"
                                step="0.01"
                                inputmode="decimal"
                                required
                                <?= isset($errors['nilai_akhir']) ? 'aria-invalid="true" aria-describedby="grade-error"' : '' ?> />
                            <?php if (isset($errors['nilai_akhir'])): ?>
                                <div class="invalid-feedback" id="grade-error"><?= e($errors['nilai_akhir']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-5">
                                <label for="nilai_huruf" class="form-label">Nilai Huruf</label>
                                <select
                                    id="nilai_huruf"
                                    name="nilai_huruf"
                                    class="form-select <?= isset($errors['nilai_huruf']) ? 'is-invalid' : '' ?>"
                                    <?= isset($errors['nilai_huruf']) ? 'aria-invalid="true" aria-describedby="letter-error"' : '' ?>>
                                    <option value="">Belum diisi</option>
                                    <?php foreach ($letterOptions as $letter): ?>
                                        <option value="<?= e($letter) ?>" <?= $old['nilai_huruf'] === $letter ? 'selected' : '' ?>>
                                            <?= e($letter) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['nilai_huruf'])): ?>
                                    <div class="invalid-feedback" id="letter-error"><?= e($errors['nilai_huruf']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-12 col-md-7">
                                <label for="predikat" class="form-label">Predikat</label>
                                <select
                                    id="predikat"
                                    name="predikat"
                                    class="form-select <?= isset($errors['predikat']) ? 'is-invalid' : '' ?>"
                                    <?= isset($errors['predikat']) ? 'aria-invalid="true" aria-describedby="predicate-error"' : '' ?>>
                                    <option value="">Belum ditentukan</option>
                                    <?php foreach ($predicateOptions as $predicate): ?>
                                        <option value="<?= e($predicate) ?>" <?= $old['predikat'] === $predicate ? 'selected' : '' ?>>
                                            <?= e($predicate) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['predikat'])): ?>
                                    <div class="invalid-feedback" id="predicate-error"><?= e($errors['predikat']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="save-grade">
                            <i class="bi bi-floppy me-1" aria-hidden="true"></i>
                            <span id="save-grade-label">
                                <?= $formMode === 'edit' ? 'Simpan Perubahan' : 'Simpan Nilai' ?>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus Data -->
    <div
        class="modal fade"
        id="delete-grade-modal"
        tabindex="-1"
        aria-labelledby="delete-grade-title"
        aria-describedby="delete-grade-description"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <form method="post" action="readnilai.php" id="delete-grade-form">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="delete-grade-title">Hapus Data Nilai</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>" />
                        <input type="hidden" name="action" value="delete" />
                        <input type="hidden" name="nim" id="delete-grade-nim" value="" />
                        <p id="delete-grade-description" class="mb-0">
                            Data nilai <strong id="delete-grade-name"></strong> akan dihapus permanen.
                            Tindakan ini tidak dapat dibatalkan.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-danger" id="delete-grade-submit">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>
                            Hapus Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script
        src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"
        crossorigin="anonymous"></script>
    <script
        src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        crossorigin="anonymous"></script>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"
        crossorigin="anonymous"></script>
    <script src="dist/js/adminlte.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebarWrapper = document.querySelector('.sidebar-wrapper');
            if (
                sidebarWrapper &&
                window.innerWidth > 992 &&
                globalThis.OverlayScrollbarsGlobal?.OverlayScrollbars !== undefined
            ) {
                globalThis.OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
                    scrollbars: {
                        theme: 'os-theme-light',
                        autoHide: 'leave',
                        clickScroll: true,
                    },
                });
            }

            const modalElement = document.getElementById('grade-modal');
            const form = document.getElementById('grade-form');
            const saveButton = document.getElementById('save-grade');
            const saveButtonLabel = document.getElementById('save-grade-label');
            const modalTitle = document.getElementById('grade-modal-title');
            const formAction = document.getElementById('grade-form-action');
            const originalNim = document.getElementById('grade-original-nim');
            const existingFoto = document.getElementById('grade-existing-foto');
            const fotoInput = document.getElementById('foto');
            const nimField = document.getElementById('nim');
            const nameField = document.getElementById('nm_lengkap');
            const gradeField = document.getElementById('nilai_akhir');
            const letterField = document.getElementById('nilai_huruf');
            const predicateField = document.getElementById('predikat');
            const deleteForm = document.getElementById('delete-grade-form');
            const deleteNim = document.getElementById('delete-grade-nim');
            const deleteName = document.getElementById('delete-grade-name');
            const deleteButton = document.getElementById('delete-grade-submit');

            const clearValidationState = () => {
                form.querySelectorAll('.is-invalid').forEach((field) => field.classList.remove('is-invalid'));
                form.querySelectorAll('.invalid-feedback, .alert-danger').forEach((message) => message.remove());
            };

            document.querySelectorAll('[data-grade-action="create"]').forEach((button) => {
                button.addEventListener('click', () => {
                    form.reset();
                    clearValidationState();
                    formAction.value = 'create';
                    originalNim.value = '';
                    if (existingFoto) existingFoto.value = '';
                    if (fotoInput) fotoInput.value = '';
                    modalTitle.textContent = 'Tambahkan Data Nilai';
                    saveButtonLabel.textContent = 'Simpan Nilai';
                });
            });

            document.querySelectorAll('[data-grade-action="edit"]').forEach((button) => {
                button.addEventListener('click', () => {
                    form.reset();
                    clearValidationState();
                    formAction.value = 'update';
                    originalNim.value = button.dataset.nim ?? '';
                    nimField.value = button.dataset.nim ?? '';
                    nameField.value = button.dataset.nama ?? '';
                    gradeField.value = button.dataset.nilaiAkhir ?? '';
                    letterField.value = button.dataset.nilaiHuruf ?? '';
                    predicateField.value = button.dataset.predikat ?? '';
                    if (existingFoto) existingFoto.value = button.dataset.foto ?? '';
                    if (fotoInput) fotoInput.value = '';
                    modalTitle.textContent = 'Edit Data Nilai';
                    saveButtonLabel.textContent = 'Simpan Perubahan';
                });
            });

            document.querySelectorAll('[data-grade-action="delete"]').forEach((button) => {
                button.addEventListener('click', () => {
                    deleteNim.value = button.dataset.nim ?? '';
                    deleteName.textContent = `${button.dataset.nama ?? ''} (${button.dataset.nim ?? ''})`;
                });
            });

            modalElement.addEventListener('shown.bs.modal', () => {
                nimField.focus();
            });

            form.addEventListener('submit', () => {
                saveButton.disabled = true;
                saveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Menyimpan';
            });

            deleteForm.addEventListener('submit', () => {
                deleteButton.disabled = true;
                deleteButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Menghapus';
            });

            if (document.body.dataset.showGradeModal === 'true') {
                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            }
        });
    </script>
</body>

</html>
