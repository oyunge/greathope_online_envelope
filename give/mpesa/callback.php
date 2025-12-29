<?php
$callbackData = file_get_contents("php://input");

$logFile = __DIR__ . "/mpesa_callbacks.log";
file_put_contents(
  $logFile,
  date("Y-m-d H:i:s") . " " . $callbackData . PHP_EOL,
  FILE_APPEND
);

echo json_encode(["ResultCode" => 0, "ResultDesc" => "Accepted"]);
