<?php
date_default_timezone_set("Asia/Pontianak");

$daftarGambar = [];
$daftarFile = is_dir(__DIR__ . "/img") ? scandir(__DIR__ . "/img") : [];
foreach ($daftarFile ?: [] as $namaFile) {
    $file = __DIR__ . "/img/" . $namaFile;
    $tipeFile = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (is_file($file) && in_array($tipeFile, ["jpg", "jpeg", "png", "gif"], true)) {
        $daftarGambar[] = $file;
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Gambar</title>
</head>

<body>

    <h2>Form Upload Gambar</h2>

    <?php if (isset($_GET["uploaded"]) && $_GET["uploaded"] == "1") { ?>
        <p role="status">Gambar berhasil diupload dan ditampilkan pada tabel di bawah.</p>
    <?php } ?>

    <form action="upload.php" method="POST" enctype="multipart/form-data">

        <label for="photo">Pilih Gambar:</label>
        <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/gif" aria-describedby="photo-help" required>
        <p id="photo-help">Format JPG, JPEG, PNG, atau GIF. Maksimal 1 MB.</p>

        <br><br>

        <input type="submit" name="submit" value="Upload Gambar">

    </form>

    <h2>Daftar Gambar</h2>

    <table border="1" cellpadding="10" cellspacing="0" style="border-collapse: collapse; width: 100%;">
        <thead>
            <tr>
                <th scope="col">No.</th>
                <th scope="col">Nama File</th>
                <th scope="col">Tgl Unggah</th>
                <th scope="col">Preview</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($daftarGambar == []) { ?>
                <tr>
                    <td colspan="4">Belum ada gambar yang diupload.</td>
                </tr>
            <?php } ?>

            <?php foreach ($daftarGambar as $nomor => $file) {
                $namaFile = basename($file);
                $alamatGambar = "img/" . rawurlencode($namaFile);
            ?>
                <tr>
                    <td><?= $nomor + 1 ?></td>
                    <td><?= htmlspecialchars("img/" . $namaFile, ENT_QUOTES, "UTF-8") ?></td>
                    <td><?= date("Y-m-d H:i:s", filemtime($file)) ?></td>
                    <td>
                        <a href="<?= $alamatGambar ?>" target="_blank" rel="noopener">
                            <img src="<?= $alamatGambar ?>" alt="<?= htmlspecialchars($namaFile, ENT_QUOTES, "UTF-8") ?>" style="max-width: 180px; max-height: 150px;">
                        </a>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

</body>

</html>
