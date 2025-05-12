<?php
  $servername = 'localhost';
  $username = 'root';
  $password = '';
  $database = 'alexandria3';

  $conn = new mysqli($servername,$username, $password, $database);

  if($conn -> connect_error){
    die("Connection failed.");
  }


?>