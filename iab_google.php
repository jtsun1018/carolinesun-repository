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
