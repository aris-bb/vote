<?php

$db_credentials = json_decode(file_get_contents('../db_credentials.json'));

$conn = new mysqli($db_credentials->host, $db_credentials->username, $db_credentials->password);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$sql = 'CREATE DATABASE ' . $db_credentials->db_name;
if (!$conn->query($sql)) {
  if ($conn->connect_error) {
    die('Failed creating the database: ' . $conn->connect_error);
  } else {
    die('Database already created.');
  }
}

$conn->select_db($db_credentials->db_name);

$sql = "CREATE TABLE votes (
    identification_number INT NOT NULL,
    forename TEXT NOT NULL,
    surname TEXT NOT NULL,
    birth_year INT NOT NULL,
    terminal_id TEXT NOT NULL,
    province_id INT NOT NULL,
    voted_for INT NOT NULL,
    ip TEXT NOT NULL,
    PRIMARY KEY (identification_number)
);";

if (!$conn->query($sql)) {
  die('Failed creating the votes table: ' . $conn->connect_error);
}

$sql = "CREATE TABLE invalid_votes (
  id INT NOT NULL AUTO_INCREMENT,
  identification_number INT NOT NULL,
  forename TEXT NOT NULL,
  surname TEXT NOT NULL,
  birth_year INT NOT NULL,
  terminal_id TEXT NOT NULL,
  province_id INT NOT NULL,
  voted_for INT NOT NULL,
  ip TEXT NOT NULL,
  is_valid_person INT,
  extra_args TEXT,
  PRIMARY KEY (id)
);";

if (!$conn->query($sql)) {
  die('Failed creating the invalid_votes table: ' . $conn->connect_error);
}

$sql = "CREATE TABLE invalid_argument (
  id INT NOT NULL AUTO_INCREMENT,
  ip TEXT NOT NULL,
  post_parameters TEXT,
  get_parameters TEXT,
  error_reason TEXT,
  PRIMARY KEY (id)
);";

if (!$conn->query($sql)) {
  die('Failed creating the invalid_argument table: ' . $conn->connect_error);
}

echo 'Database created successfully.';
