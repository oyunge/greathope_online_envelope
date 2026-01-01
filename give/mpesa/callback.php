<?php
header("Content-Type: application/json");
require __DIR__ . "/db.php";

$data = json_decode(file_get_contents("php://input"), true);
$stk = $data["Body"]["stkCallback"] ?? null;

if (!$stk) {
  exit(json_encode(["ResultCode" => 0, "ResultDesc" => "Accepted"]));
}

$checkoutId = $stk["CheckoutRequestID"];
$resultCode = $stk["ResultCode"];
$resultDesc = $stk["ResultDesc"];

$status = "FAILED";
if ((string)$resultCode === "0") $status = "SUCCESS";
else if ((string)$resultCode === "1037") $status = "TIMEOUT";
else if ((string)$resultCode === "1032") $status = "CANCELLED";

$receipt = null;
foreach ($stk["CallbackMetadata"]["Item"] ?? [] as $item) {
  if ($item["Name"] === "MpesaReceiptNumber") {
    $receipt = $item["Value"];
  }
}

$stmt = $mysqli->prepare("
  UPDATE mpesa_transactions
  SET status=?, result_code=?, result_desc=?, receipt=?
  WHERE checkout_request_id=?
");

$stmt->bind_param(
  "sisss",
  $status,
  $resultCode,
  $resultDesc,
  $receipt,
  $checkoutId
);

$stmt->execute();

echo json_encode(["ResultCode" => 0, "ResultDesc" => "Accepted"]);
