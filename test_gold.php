<?php
$url = 'https://query1.finance.yahoo.com/v8/finance/chart/GC=F';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
echo "GC=F:\n" . substr($response, 0, 500) . "\n" . curl_error($ch) . "\n\n";

$url2 = 'https://query1.finance.yahoo.com/v8/finance/chart/XAUUSD=X';
$ch2 = curl_init($url2);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
$response2 = curl_exec($ch2);
echo "XAUUSD=X:\n" . substr($response2, 0, 500) . "\n" . curl_error($ch2) . "\n\n";

$url3 = 'https://api.metals.live/v1/spot';
$ch3 = curl_init($url3);
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch3, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, false);
$response3 = curl_exec($ch3);
echo "Metals:\n" . substr($response3, 0, 500) . "\n" . curl_error($ch3) . "\n\n";
