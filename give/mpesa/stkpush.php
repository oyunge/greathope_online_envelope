<?php
require "config.php";

header("Content-Type: application/json");

// Read input JSON
$data = json_decode(file_get_contents("php://input"), true);

$name   = $data["name"] ?? "";
$email  = $data["email"] ?? "";
$phone  = $data["phone"] ?? "";
$amount = intval($data["amount"] ?? 0);

// Validate
if (!$name || !$phone || $amount <= 0) {
  http_response_code(400);
  echo json_encode(["error" => "Invalid request"]);
  exit;
}

// Format phone (07xx → 2547xx)
if (substr($phone, 0, 2) === "07") {
  $phone = "254" . substr($phone, 1);
}

/* =========================
   Get OAuth Token
========================= */
$url = MPESA_ENV === "live"
  ? "https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials"
  : "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";

$credentials = base64_encode(MPESA_CONSUMER_KEY . ":" . MPESA_CONSUMER_SECRET);

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_HTTPHEADER => ["Authorization: Basic $credentials"],
  CURLOPT_RETURNTRANSFER => true
]);

$response = curl_exec($ch);
curl_close($ch);

$token = json_decode($response)->access_token ?? null;

if (!$token) {
  http_response_code(500);
  echo json_encode(["error" => "Failed to get access token"]);
  exit;
}

/* =========================
   STK Push
========================= */
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

echo $result;
