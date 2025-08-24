<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "academic_system";

$conn = new mysqli($servername, $username, $password, $dbname);
if($conn->connect_error){
    die("Error Connecting Database".$conn->connect_error);
}
?>