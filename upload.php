<?php
// $namafile = $_FILES['photo']['name'];
// $tipefile = $_FILES['photo']['type'];
// $ukuranfile = $_FILES['photo']['size'];
// $temporaryfile = $_FILES['photo']['tmp_name'];
// $errfile = $_FILES['photo']['error'];

// echo "Nama file: " . $namafile . "<br>";
// echo "Tipe data: " . $tipefile . "<br>";
// echo "ukuran file: " . $ukuranfile . "<br>";
// echo "Alamat Temporary: " . $temporaryfile . "<br>";
// echo "Status Error: " . $errfile . "<br>";

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: form_upload.php");
    exit;
}

if (!isset($_FILES["photo"]["error"]) || $_FILES["photo"]["error"] !== UPLOAD_ERR_OK) {
    echo "Gambar gagal diterima. Pilih gambar maksimal 1 MB lalu coba lagi.<br>";
    echo '<a href="form_upload.php">Kembali ke form upload</a>';
    exit;
}

// menentukan direktori tujuan/target
$target_dir = "img/";
$target_file = $target_dir . basename($_FILES["photo"]["name"]);
$uploadOk = 1;
$imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

// Check if image file is a actual image or fake image
$check = @getimagesize($_FILES["photo"]["tmp_name"]);
if ($check !== false && in_array($check[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
    $uploadOk = 1;
} else {
    echo "File is not an image.<br>";
    $uploadOk = 0;
}

// Check if file already exists
if (file_exists(__DIR__ . "/" . $target_file)) {
    echo "Sorry, file already exists.<br>";
    $uploadOk = 0;
}

// Check file size
if ($_FILES["photo"]["size"] > 1000000) {
    echo "Sorry, your file is too large.<br>";
    $uploadOk = 0;
}

// Allow certain file formats
if (
    $imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
    && $imageFileType != "gif"
) {
    echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.<br>";
    $uploadOk = 0;
}

// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
    echo "Sorry, your file was not uploaded.<br>";
    // if everything is ok, try to upload file
} else {
    // Buat folder tujuan jika belum ada.
    if (!is_dir(__DIR__ . "/" . $target_dir) && !mkdir(__DIR__ . "/" . $target_dir, 0755, true)) {
        echo "Maaf, folder penyimpanan gambar tidak dapat dibuat.<br>";
        echo '<a href="form_upload.php">Kembali ke form upload</a>';
        exit;
    }

    if (move_uploaded_file($_FILES["photo"]["tmp_name"], __DIR__ . "/" . $target_file)) {
        header("Location: form_upload.php?uploaded=1", true, 303);
        exit;
    } else {
        echo "Maaf, terdapat kesalahan saat mengunggah file.<br>";
    }
}

echo '<br><a href="form_upload.php">Kembali ke form dan daftar gambar</a>';
