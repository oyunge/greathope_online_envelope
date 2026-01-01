<?php
// =========================
// CORS + JSON
// =========================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
  http_response_code(200);
  exit;
}

// =========================
// Errors
// =========================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// =========================
// Includes
// =========================
require __DIR__ . "/config.php";
require __DIR__ . "/db.php";

// =========================
// Input
// =========================
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$name   = trim($data["name"] ?? "");
$email  = trim($data["email"] ?? "");
$phone  = trim($data["phone"] ?? "");
$amount = intval($data["amount"] ?? 0);

if (!$name || !$phone || $amount <= 0) {
  http_response_code(400);
  exit(json_encode(["error" => "Invalid request"]));
}

if (substr($phone, 0, 2) === "07") {
  $phone = "254" . substr($phone, 1);
}

// =========================
// OAuth
// =========================
$oauthUrl = MPESA_ENV === "live"
  ? "https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials"
  : "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";

$credentials = base64_encode(MPESA_CONSUMER_KEY . ":" . MPESA_CONSUMER_SECRET);

$ch = curl_init($oauthUrl);
curl_setopt_array($ch, [
  CURLOPT_HTTPHEADER => ["Authorization: Basic $credentials"],
  CURLOPT_RETURNTRANSFER => true
]);

$oauthResponse = curl_exec($ch);
curl_close($ch);

$token = json_decode($oauthResponse, true)["access_token"] ?? null;

if (!$token) {
  exit(json_encode(["error" => "OAuth failed"]));
}

// =========================
// STK Push
// =========================
$timestamp = date("YmdHis");
$password  = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

$stkUrl = MPESA_ENV === "live"
  ? "https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest"
  : "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest";

$payload = [
  "BusinessShortCode" => MPESA_SHORTCODE,
  "Password" => $password,
  "Timestamp" => $timestamp,
  "TransactionType" => "CustomerPayBillOnline",
  "Amount" => $amount,
  "PartyA" => $phone,
  "PartyB" => MPESA_SHORTCODE,
  "PhoneNumber" => $phone,
  "CallBackURL" => MPESA_CALLBACK_URL,
  "AccountReference" => "GREAT HOPE SDA",
  "TransactionDesc" => "Offering by $name"
];

$ch = curl_init($stkUrl);
curl_setopt_array($ch, [
  CURLOPT_HTTPHEADER => [
    "Authorization: Bearer $token",
    "Content-Type: application/json"
  ],
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => json_encode($payload),
  CURLOPT_RETURNTRANSFER => true
]);

$result = curl_exec($ch);
curl_close($ch);

$resArr = json_decode($result, true);

// =========================
// INSERT PENDING
// =========================
if (isset($resArr["ResponseCode"]) && $resArr["ResponseCode"] === "0") {
  $stmt = $mysqli->prepare("
    INSERT INTO mpesa_transactions
    (checkout_request_id, merchant_request_id, phone, name, email, amount, status)
    VALUES (?, ?, ?, ?, ?, ?, 'PENDING')
  ");

  if (!$stmt) {
    error_log("Prepare failed: " . $mysqli->error);
  } else {
    $stmt->bind_param(
      "sssssd",
      $resArr["CheckoutRequestID"],
      $resArr["MerchantRequestID"],
      $phone,
      $name,
      $email,
      $amount
    );
    $stmt->execute();

    if ($stmt->error) {
      error_log("Insert error: " . $stmt->error);
    }
  }
}

// =========================
// ALWAYS return JSON
// =========================
echo json_encode($resArr);
