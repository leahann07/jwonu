<?php 
 $conn = mysqli_connect(hostname: "localhost", username: "root", password: "", database: "penk");

 if(!$conn) {
    die ("Connection Failed:" . mysqli_connect_error());
 }
 ?>