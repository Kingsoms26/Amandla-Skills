<?php
// database connection details for infinityfree hosting
    $server="sql311.infinityfree.com";
    $username="if0_41246522";
    $password="XoMEqr0xrYs2UY";
    $port=3306;
    $dbname = "if0_41246522_amandla_db";

    $conn = new mysqli($server, $username, $password, $dbname, $port);


    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
?>