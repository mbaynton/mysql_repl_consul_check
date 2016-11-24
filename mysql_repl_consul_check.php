<?php

/**
 * @file
 * Given a 3-line file called connection.dat in same directory as this script
 * with a PDO_MYSQL-complaint DSN on line one, username on line 2 and password
 * on line 3, tests whether the server connected to is a healthy replication
 * slave.
 *
 * Results reported as exit codes for consul.
 */

function report($code, $msg) {
  fwrite(STDERR, $msg . "\n");
  exit($code);
}

function test(\PDO $pdo) {
  $result = $pdo->query('SHOW SLAVE STATUS');
  $columns = $result->fetch(\PDO::FETCH_ASSOC);
  $result->closeCursor();
  return $columns;
}

list($conn_string, $user, $password) = explode("\n", file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'connection.dat'));
$pdo = new \PDO($conn_string, $user, $password);

$columns = test($pdo);
$seconds = $columns['Seconds_Behind_Master'];
$master = $columns['Master_Host'];
$state = $columns['Slave_IO_State'];

if ($seconds > 30 || $state != 'Waiting for master to send event') {
  // This indicator can have transient false positives
  sleep(10);
  $columns = test($pdo);
  $seconds = $columns['Seconds_Behind_Master'];
  $master = $columns['Master_Host'];
  $state = $columns['Slave_IO_State'];
}

$code = 0;
if ($seconds >= 5) {
  $code = 1;
}
if ($seconds >= 30 || $state != 'Waiting for master to send event') {
  $code = 2;
}

report($code, "$state\n$seconds seconds behind master at $master");
