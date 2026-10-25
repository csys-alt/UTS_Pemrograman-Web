<?php

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: readnilai.php?new=1', true, 303);
    exit;
}

require __DIR__ . '/readnilai.php';
