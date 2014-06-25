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

//驗證訂單
function processPurchaseResult($trans_data)
{
    global $access_token, $token_count, $is_stop, $db_member, $t_pkg;

    if (1 == sid_valid($trans_data['sid'])) {

        $sid = $trans_data['sid'];
        $msno = sid_parse($sid, 'msno');
        $terr_id = sid_parse($sid, 'terr_id');

    } else {
        //result
        $trans_re = array(
            "orderId"     => $trans_data['orderId'],
            "sid"         => $trans_data['sid'],
            "status"      => '0',
            "status_desc" => 'Invalid sid',
        );
        return $trans_re;
    }

    if ($db_member->is_conn_fail()) {
        $db_member->connect();
    }
    //先檢查 DB 內是否有此方案
    $sql = sprintf(
        "SELECT * FROM kkbox_member.google_iab_pkg WHERE product_id='%s' and terr_id='%d'",
        mysql_real_escape_string($trans_data['productId']),
        mysql_real_escape_string($terr_id)
    );
    $res = $db_member->query($sql, 1);

    if ($db_member->is_fail() || 0 === $db_member->count($res)) {
        //result
        $trans_re = array(
            "orderId"     => $trans_data['orderId'],
            "sid"         => $trans_data['sid'],
            "status"      => '0',
            "status_desc" => 'Package not found',
        );
        return $trans_re;
    } else {
        $row = $db_member->fetch_array($res);
        $t_pkg = $row['t_pkg'];
    }

    while (1) {
        if (1 == $is_stop) {                                        //若已經重新產生access token三次, 不再繼續, 直接重送訂單
            $status = '-1';
            $receipt_desc = 'resend';
            break;
        }

        $result = getPurchaseStatus($access_token, $trans_data['productId'], $trans_data['purchaseToken']); //查詢訂單狀態

        if (!array_key_exists('error', $result)) {                  //查詢成功
            $purchase_state = $result['purchaseState'];             //0: Purchased, 1: Cancelled
            $consumption_state = $result['consumptionState'];       //0: Yet to be consumed, 1: Consumed

            if (0 == $purchase_state) {                             //purchased, 交易正常(沒有取消)
                if (1 == $consumption_state) {                      //consumed, consume正常

                    //查是否存過receipt
                    $sql = sprintf(
                        "SELECT * FROM kkbox_member.google_iab_receipt WHERE order_id='%s'",
                        mysql_real_escape_string($trans_data['orderId'])
                    );
                    $res = $db_member->query($sql, 1);
                    if ($db_member->is_fail()) {                    //查詢失敗, 需重送此筆訂單
                        $status = '-1';
                        $receipt_desc = 'resend';
                        break;
                    } elseif (0 === $db_member->count($res)) {
                        //沒存過, 存入receipt
                        unset($receipt_params);
                        $receipt_params = array();
                        $receipt_params['msno'] = $msno;
                        $receipt_params['order_id'] = $trans_data['orderId'];
                        $receipt_params['product_id'] = $trans_data['productId'];
                        $receipt_params['purchase_time'] = $trans_data['purchaseTime'];
                        $receipt_params['purchase_token'] = $trans_data['purchaseToken'];
                        $receipt_params['created_at'] = time();
                        $receipt_params['terr_id'] = $terr_id;

                        unset($field_str, $value_str);
                        foreach ($receipt_params as $receipt_field => $receipt_value) {
                            $field_str .= $receipt_field . ",";
                            $value_str .= "'" . mysql_real_escape_string($receipt_value) . "',";
                        }
                        $field_str = substr($field_str, 0, -1);
                        $value_str = substr($value_str, 0, -1);

                        $sql_insert_receipt = "INSERT INTO kkbox_member.google_iab_receipt(" . $field_str . ") VALUES (" . $value_str . ")";
                        $res_insert_receipt = $db_member->query($sql_insert_receipt, 1);

                        if ($db_member->is_fail()) {                //insert 失敗, 需重送此筆訂單
                            $status = '-1';
                            $receipt_desc = 'resend';
                            break;
                        }
                    }

                    //insert receipt成功
                    $member = new Member($terr_id);
                    require_once HOME_DIR . 'public_member_lib/set_language/msg_' . $member->getDefaultLang() . '.php';

                    list($p_status, $p_desc, $p_time) = processTrans($msno, $terr_id, $trans_data);  //寫交易記錄, 開通使用期

                    if ('0' == $p_status) {                         //失敗, 需重送此筆訂單
                        $status = '-1';
                        $receipt_desc = 'resend';
                    } elseif ('1' == $p_status) {
                        $status = '1';
                        $receipt_desc = 'success';
                    }

                    if ('' != $p_desc) {
                        $receipt_desc = $p_desc;
                    }

                    $finish_time = $p_time;
                    break;
                } else {                                            //yet to be consumed, 還沒consume, 需先consume再重送訂單
                    $status = '-2';
                    $receipt_desc = 'consume and resend';
                    break;
                }
            } else {                                                //cancelled, 交易取消
                $status = '0';
                $receipt_desc = 'fail';
                break;
            }
        } else {                                                    //查詢失敗
            if (401 != $result['code']) {                           //非access token錯誤
                $status = '0';
                $receipt_desc = $result['error']['code'] . " " . $result['error']['message'];
                break;
            } else {                                                //access token過期
                if (3 <= $token_count) {                            //若已經重新產生access token三次, 不再繼續, 直接重送訂單
                    $status = '-1';
                    $receipt_desc = 'resend';
                    $is_stop = 1;
                    break;
                } else {
                    $access_token = getAccessToken();               //access token過期, 產生一個新的
                    $token_count = $token_count + 1;
                }
            }
        }
    }

    //result
    $trans_re = array(
        "orderId"     => $trans_data['orderId'],
        "sid"         => $sid,
        "status"      => $status,
        "status_desc" => $receipt_desc,
        "finish_time" => $finish_time
    );
    return $trans_re;
}
