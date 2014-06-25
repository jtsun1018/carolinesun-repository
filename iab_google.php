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
