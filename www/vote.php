<?php

// ini_set('error_reporting', E_ALL);
// error_reporting(E_ALL);
// ini_set("display_errors", 1);

// set_error_handler(function ($severity, $message, $file, $line) {
//   if (error_reporting() & $severity) {
//     throw new ErrorException($message, 0, $severity, $file, $line);
//   }
// });

setlocale(LC_CTYPE, 'tr_TR.UTF8');

abstract class error_codes
{
  const success = 0;
  const invalid_argument = 1;
  const voted_already = 2;
  const invalid_credentials = 3;
  const session_timeout = 4;
  const internal_server_error = 5;
}

class context
{
  public $db_connection;

  function __construct()
  {
    session_start();

    $_POST = json_decode(file_get_contents("php://input"), true);

    $db_credentials = json_decode(file_get_contents('../db_credentials.json'));

    $this->db_connection = new mysqli($db_credentials->host, $db_credentials->username, $db_credentials->password, $db_credentials->db_name);
  }

  // returns the argument, fails if the argument is missing, optionally stores the argument to session
  function get_argument($arg_name, $should_store_to_session = false)
  {
    $arg = $_POST[$arg_name];

    if (!isset($arg)) {
      $this->log_invalid_argument_and_die("missing argument: $arg_name");
    }

    if ($should_store_to_session) {
      $_SESSION[$arg_name] = $arg;
    }

    return $arg;
  }

  // gets a session variable, fails if the variable is missing
  function get_session_var($var_name)
  {
    $var = $_SESSION[$var_name];

    if (!isset($var)) {
      $this->log_invalid_argument_and_die("missing session var: $var_name");
    }

    return $var;
  }

  // to be called when we receive an invalid argument
  function log_invalid_argument_and_die($reason)
  {
    $ip = $this->db_connection->real_escape_string($_SERVER['REMOTE_ADDR']);
    $post = $this->db_connection->real_escape_string(json_encode($_POST, true));
    $get = $this->db_connection->real_escape_string(json_encode($_GET, true));
    $reason = $this->db_connection->real_escape_string($reason);

    $sql = "INSERT INTO invalid_argument (ip, post_parameters, get_parameters, error_reason) VALUES ('$ip', '$post', '$get', '$reason')";

    $this->db_connection->query($sql);

    die((string)error_codes::invalid_argument);
  }

  // to be called when we see that someone is trying to vote multiple times
  // call this only if they deliberately bypassed our clientside validation
  function log_vote_fraud_attempt_and_die($id_number, $name, $surname, $birth_year, $province_id, $terminal_id, $voted_for)
  {
    $id_number = $this->db_connection->real_escape_string($id_number);
    $name = $this->db_connection->real_escape_string(mb_strtoupper($name, 'UTF-8'));
    $surname = $this->db_connection->real_escape_string(mb_strtoupper($surname, 'UTF-8'));
    $birth_year = $this->db_connection->real_escape_string($birth_year);
    $terminal_id = $this->db_connection->real_escape_string($terminal_id);
    $province_id = $this->db_connection->real_escape_string($province_id);
    $voted_for = $this->db_connection->real_escape_string($voted_for);
    $ip = $this->db_connection->real_escape_string($_SERVER['REMOTE_ADDR']);

    // see if the other details such as name and surname match with an existing entry, or if only the id_number is the same
    $sql = "SELECT identification_number FROM votes WHERE identification_number = '$id_number' AND forename = '$name' AND surname = '$surname'";
    $result = $this->db_connection->query($sql);
    $is_valid_person = $result->num_rows > 0;

    $sql = <<<SQL
    INSERT INTO invalid_votes (identification_number, forename, surname, birth_year, terminal_id, province_id, voted_for, ip, is_valid_person)
    VALUES ('$id_number', '$name', '$surname', '$birth_year', '$terminal_id', '$province_id', '$voted_for', '$ip', '$is_valid_person')
SQL;

    $this->db_connection->query($sql);
    die((string)error_codes::voted_already);
  }

  // check if the voter has already voted
  function did_vote_already($id_number)
  {
    $id_number = $this->db_connection->real_escape_string($id_number);

    $sql = "SELECT identification_number FROM votes WHERE identification_number = '$id_number'";
    $result = $this->db_connection->query($sql);

    return $result->num_rows > 0;
  }

  // store a new vote
  function store_new_vote($id_number, $name, $surname, $birth_year, $province_id, $terminal_id, $voted_for)
  {
    $id_number = $this->db_connection->real_escape_string($id_number);
    $name = $this->db_connection->real_escape_string(mb_strtoupper($name, 'UTF-8'));
    $surname = $this->db_connection->real_escape_string(mb_strtoupper($surname, 'UTF-8'));
    $birth_year = $this->db_connection->real_escape_string($birth_year);
    $terminal_id = $this->db_connection->real_escape_string($terminal_id);
    $province_id = $this->db_connection->real_escape_string($province_id);
    $voted_for = $this->db_connection->real_escape_string($voted_for);
    $ip = $this->db_connection->real_escape_string($_SERVER['REMOTE_ADDR']);

    $sql = <<<SQL
    INSERT INTO votes (identification_number, forename, surname, birth_year, terminal_id, province_id, voted_for, ip)
    VALUES ('$id_number', '$name', '$surname', '$birth_year', '$terminal_id', '$province_id', '$voted_for', '$ip')
SQL;

    if (!$this->db_connection->query($sql)) {
      // insertion failed, log this
      file_put_contents("failed_votes.log", "$sql failed with error message: " . $this->db_connection->connect_error . "\n\n-----", FILE_APPEND | LOCK_EX);
      die((string)error_codes::internal_server_error);
    } else {
      echo error_codes::success;
    }
  }
}

function validate_id_number($id_number)
{
  $length = strlen($id_number);

  if ($length !== 11) {
    return false;
  }

  $erroneous = [
    '11111111110' => true,
    '22222222220' => true,
    '33333333330' => true,
    '44444444440' => true,
    '55555555550' => true,
    '66666666660' => true,
    '77777777770' => true,
    '88888888880' => true,
    '99999999990' => true
  ];

  if (isset($erroneous[$id_number])) {
    return false;
  }

  $array = str_split($id_number);

  if ($array[0] == "0") {
    return false;
  }

  $sum = 0;
  for ($i = 0; $i < 10; $i++) {
    $sum += intval($array[$i]);
  }

  if ($sum % 10 != $array[10]) {
    return false;
  }

  return true;
}

function validate_birth_year($birth_year)
{
  if (!is_numeric($birth_year)) {
    return false;
  }

  $birth_year_int = intval($birth_year);

  if ($birth_year_int > 2022 || $birth_year_int < 1800) {
    return false;
  }

  return true;
}

function validate_identity($id_number, $name, $surname, $birth_year)
{
  $soap_request = '<?xml version="1.0" encoding="utf-8"?>
  <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
      <TCKimlikNoDogrula xmlns="http://tckimlik.nvi.gov.tr/WS">
        <TCKimlikNo>' . $id_number . '</TCKimlikNo>
        <Ad>' . $name . '</Ad>
        <Soyad>' . $surname . '</Soyad>
        <DogumYili>' . $birth_year . '</DogumYili>
      </TCKimlikNoDogrula>
    </soap:Body>
  </soap:Envelope>';

  $header = array(
    "Content-type: text/xml;charset=\"utf-8\"",
    "Accept: text/xml",
    "Cache-Control: no-cache",
    "Pragma: no-cache",
    "SOAPAction: \"http://tckimlik.nvi.gov.tr/WS/TCKimlikNoDogrula\"",
    "Content-Length: " . strlen($soap_request),
  );

  $curl = curl_init();

  curl_setopt($curl, CURLOPT_URL,            "https://tckimlik.nvi.gov.tr/Service/KPSPublic.asmx");
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_POST,           true);
  curl_setopt($curl, CURLOPT_POSTFIELDS,     $soap_request);
  curl_setopt($curl, CURLOPT_HTTPHEADER,     $header);

  $result = curl_exec($curl);

  $response1 = str_replace("<soap:Body>", "", $result);
  $response2 = str_replace("</soap:Body>", "", $response1);

  $parser = simplexml_load_string($response2);

  return $parser->TCKimlikNoDogrulaResponse->TCKimlikNoDogrulaResult == 'true';
}

function validate_province_id($province_id)
{
  return isset(json_decode(file_get_contents("./provinces.json"), true)[$province_id]);
}

function validate_voted_for($vote_options)
{
  return isset(json_decode(file_get_contents("./vote_options.json"), true)[$vote_options]);
}

function main()
{
  $context = new context;

  $action = $context->get_argument('action');

  if ($action == 'check') {
    $id_number = $context->get_argument('id_number', true);
    $name = $context->get_argument('name', true);
    $surname = $context->get_argument('surname', true);
    $birth_year = $context->get_argument('birth_year', true);
    $province_id = $context->get_argument('province_id', true);
    $terminal_id = $context->get_argument('terminal_id', true);

    if (!validate_id_number($id_number)) {
      $context->log_invalid_argument_and_die('invalid id number checksum');
    }

    if (!validate_birth_year($birth_year)) {
      $context->log_invalid_argument_and_die('invalid birth year');
    }

    if (!validate_province_id($province_id)) {
      $context->log_invalid_argument_and_die('invalid province id');
    }

    if ($context->did_vote_already($id_number)) {
      die((string)error_codes::voted_already);
    }

    if (!validate_identity($id_number, $name, $surname, $birth_year)) {
      die((string)error_codes::invalid_credentials);
    }

    // they are eligible to vote at this point
    $_SESSION['checked'] = true;

    echo error_codes::success;
  } else if ($action == 'vote') {
    if (!isset($_SESSION['checked'])) {
      // probably session timed out
      die((string)error_codes::session_timeout);
    }

    $voted_for = $context->get_argument('voted_for');

    if (!validate_voted_for($voted_for)) {
      $context->log_invalid_argument_and_die('invalid voted for');
    }

    $id_number = $context->get_session_var('id_number');
    $name = $context->get_session_var('name');
    $surname = $context->get_session_var('surname');
    $birth_year = $context->get_session_var('birth_year');
    $province_id = $context->get_session_var('province_id');
    $terminal_id = $context->get_session_var('terminal_id');

    if ($context->did_vote_already($id_number)) {
      $context->log_vote_fraud_attempt_and_die($id_number, $name, $surname, $birth_year, $province_id, $terminal_id, $voted_for);
    }

    $context->store_new_vote($id_number, $name, $surname, $birth_year, $province_id, $terminal_id, $voted_for);
  } else {
    $context->log_invalid_argument_and_die("invalid action: $action");
  }
};

main();
