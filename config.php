<?php
// database connection details for infinityfree hosting
    $server="";
    $username="";
    $password="";
    $port=;
    $dbname = "";

    $conn = new mysqli($server, $username, $password, $dbname, $port);


    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
?>
