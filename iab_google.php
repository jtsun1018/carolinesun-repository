<?php
/*
https://issue.kkcorp/trac/wiki/BillingApi/IAB/Google
[Android] 接收client回傳交易成功的收據
*/
require_once dirname(__FILE__) . '/header_billing.php';
require_once dirname(__FILE__) . '/../public_member_lib/lib_member.php';
require_once dirname(__FILE__) . '/../public_member_lib/lib_billing.php';
require_once LIB . 'enc.php';
require_once LIB . 'sid.php';
require_once LIB . 'sid_parser.php';

define('ANDROID_PACKAGE_NAME', 'com.skysoft.kkbox.android');
define('GOOGLE_IAB_API_CLIENT_LOG', BILLING_LOGFILE . 'iap_client.log');                // 與 client 串接的 log file
define('GOOGLE_IAB_API_CLIENT_RECEIPT_LOG', BILLING_LOGFILE . 'iap_receipt.log');       // 驗證的 receipt 的 log file

$oenc = $_GET['oenc'];
$lang = $_GET['lang'];
$of = $_GET['of'];

$client_data = trim($_POST['trans_list']);
$data = json_decode(rc4decrypt(RC4_KEY, base64_decode($client_data)), true);

if (!is_array($data)) {             //client傳來的資料格式錯誤

    //把錯誤格式的 input 寫進 log 檔記錄
    setLog(GOOGLE_IAB_API_CLIENT_LOG, "GET: " . print_r($_GET, true) . "\nPOST: " . $client_data);

    unset($response);
    $response = array();
    $response['status'] = '0';
    $response['msg'] = 'client 傳遞資料格式錯誤';

    // 將 output 寫入 log
    setLog(GOOGLE_IAB_API_CLIENT_LOG, print_r($response, true));

    echo rc4encrypt(RC4_KEY, json_encode($response));
    exit;
}

// 將正確的 input 寫進 log 檔
$log_str = "GET: " . print_r($_GET, true) . "\nPOST: " . print_r($data, true) . "\n";
setLog(GOOGLE_IAB_API_CLIENT_LOG, $log_str);

$is_stop = 0;
$token_count = 0;
$access_token = getAccessToken();   //產生連google api需要的access token
$trans_list_re = array();           //做完驗證後的收據列表

foreach ($data as $trans) {
    $trans_list_re[] = processPurchaseResult($trans);  //驗證訂單
}

$response_client['status'] = '1';
$resiponse_client['msg'] = 'success';
$response_client['trans_list'] = $trans_list_re;

// 將 output 寫入 log
setLog(GOOGLE_IAB_API_CLIENT_LOG, print_r($response_client, true));

echo rc4encrypt(RC4_KEY, json_encode($response_client));
exit;

//產生連google api需要的access token
function getAccessToken()
{
    $data = array(
        "grant_type" => "refresh_token",
        "client_id" => GOOGLE_CLIENT_ID,
        "client_secret" => GOOGLE_CLIENT_SECRET,
        "refresh_token" => GOOGLE_REFRESH_TOKEN
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, 'https://accounts.google.com/o/oauth2/token');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $result = curl_exec($ch);
    curl_close($ch);
    $return_data = json_decode($result, true);
    $access_token = $return_data["access_token"];
    return $access_token;
}

//查詢訂單狀態
function getPurchaseStatus($access_token, $product_id, $token)
{
    $package_name = ANDROID_PACKAGE_NAME;

    //送出的資料寫入 log 記錄
    setLog(GOOGLE_IAB_API_CLIENT_RECEIPT_LOG, "package name: " . $package_name . ", product id: " . $product_id . ", token: " . $token . "\n");

    $url = "https://www.googleapis.com/androidpublisher/v1.1/applications/$package_name/inapp/$product_id/purchases/$token?access_token=$access_token";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $result = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($result, true);

    //接到的資料寫入 log 記錄
    setLog(GOOGLE_IAB_API_CLIENT_RECEIPT_LOG, print_r($result, true));

    return $result;
}
