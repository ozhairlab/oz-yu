<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $mysqli = new mysqli('localhost', 'root', '', 'klinik_kecantikan');
    echo 'Success';
} catch (Exception $e) {
    echo $e->getMessage();
}
