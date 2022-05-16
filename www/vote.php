<?php

ini_set('error_reporting', E_ALL);
error_reporting(E_ALL);
ini_set("display_errors", 1);

set_error_handler(function ($severity, $message, $file, $line) {
  if (error_reporting() & $severity) {
    throw new ErrorException($message, 0, $severity, $file, $line);
  }
});

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

  function get_session_var($var_name)
  {
    $var = $_SESSION[$var_name];

    if (!isset($var)) {
      $this->log_invalid_argument_and_die("missing session var: $var_name");
    }

    return $var;
  }

  function log_invalid_argument_and_die($reason)
  {
    $ip = $this->db_connection->real_escape_string($_SERVER['REMOTE_ADDR']);
    $post = $this->db_connection->real_escape_string(var_export($_POST, true));
    $get = $this->db_connection->real_escape_string(var_export($_GET, true));
    $reason = $this->db_connection->real_escape_string($reason);

    $sql = "INSERT INTO invalid_argument (ip, post_parameters, get_parameters, error_reason) VALUES ('$ip', '$post', '$get', '$reason')";

    $this->db_connection->query($sql);

    die(error_codes::invalid_argument);
  }

  function log_vote_fraud_attempt_and_die($id_number, $name, $surname, $birth_year, $province_id, $terminal_id, $voted_for)
  {
    $id_number = $this->db_connection->real_escape_string($id_number);
    $name = $this->db_connection->real_escape_string($name);
    $surname = $this->db_connection->real_escape_string($surname);
    $birth_year = $this->db_connection->real_escape_string($birth_year);
    $terminal_id = $this->db_connection->real_escape_string($terminal_id);
    $province_id = $this->db_connection->real_escape_string($province_id);
    $voted_for = $this->db_connection->real_escape_string($voted_for);
    $ip = $this->db_connection->real_escape_string($_SERVER['REMOTE_ADDR']);

    // TODO:
    // - utilize the is_valid_person column:
    //   if not only the id number but also the name, surname etc. match, then set is_valid_person to 1
    //   because otherwise the id number might be just a random guess by the attacker which coincided with an existing vote entry
    //   but if is_valid_person is 1, then it is of higher chance that the attacker gave their real details
    // - utilize the extra_args column:
    //   if they accidentally called the api with more arguments than needed, log them to extra_args

    $sql = <<<SQL
    INSERT INTO invalid_votes (identification_number, forename, surname, birth_year, terminal_id, province_id, voted_for, ip)
    VALUES ('$id_number', '$name', '$surname', '$birth_year', '$terminal_id', '$province_id', '$voted_for', '$ip')
SQL;

    $this->db_connection->query($sql);
    die(error_codes::voted_already);
  }

  function did_vote_already($id_number)
  {
    $id_number = $this->db_connection->real_escape_string($id_number);

    $sql = "SELECT identification_number FROM votes WHERE identification_number = '$id_number'";
    $result = $this->db_connection->query($sql);

    return $result->num_rows > 0;
  }

  function store_new_vote($id_number, $name, $surname, $birth_year, $province_id, $terminal_id, $voted_for)
  {
    $id_number = $this->db_connection->real_escape_string($id_number);
    $name = $this->db_connection->real_escape_string($name);
    $surname = $this->db_connection->real_escape_string($surname);
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
      die(error_codes::internal_server_error);
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
  $soap = new SoapClient('https://tckimlik.nvi.gov.tr/service/kpspublic.asmx?wsdl');
  $result = $soap->TCKimlikNoDogrula($id_number, $name, $surname, $birth_year)->TCKimlikNoDogrulaResult;
  echo "id: " . $id_number . " name: " . $name . " surname: " . $surname . " year " . $birth_year;
  echo print_r($result);
  return $result;
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

    // TODO: if we have any extra arguments than the ones we read, log it as invalid argument

    if (!validate_id_number($id_number)) {
      $context->log_invalid_argument_and_die('invalid id number checksum');
    }

    if (!validate_birth_year($birth_year)) {
      $context->log_invalid_argument_and_die('invalid birth year');
    }

    if ($context->did_vote_already($id_number)) {
      die(error_codes::voted_already);
    }

    if (!validate_identity($id_number, $name, $surname, $birth_year)) {
      die(error_codes::invalid_credentials);
    }

    // they are eligible to vote at this point
    $_SESSION['checked'] = true;

    echo error_codes::success;
  } else if ($action == 'vote') {
    if (!isset($_SESSION['checked'])) {
      // probably session timed out
      die(error_codes::session_timeout);
    }

    $voted_for = $context->get_argument('voted_for');

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
