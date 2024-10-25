<?php
/*
参照https://www.volcengine.com/docs/6561/1305191上面的go版本，ai帮我转成的php版本，我自己修改了一下。
*/

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

// 请求凭证，从访问控制申请
const ACCESS_KEY_ID = 'YOUR ACCESS_KEY_ID';
const SECRET_ACCESS_KEY = 'YOUR_SECRET_ACCESS_KEY';

// 请求地址
const ADDR = 'https://open.volcengineapi.com';
const PATH = '/'; // 路径，不包含 Query

// 请求接口信息
const SERVICE = 'speech_saas_prod';
const REGION = 'cn-north-1';
const ACTION = 'ListMegaTTSTrainStatus';
const VERSION = '2023-11-07';

function hmacSHA256($key, $content) {
    return hash_hmac('sha256', $content, $key, true);
}

function getSignedKey($secretKey, $date, $region, $service) {
    $kDate = hmacSHA256($secretKey, $date);
    $kRegion = hmacSHA256($kDate, $region);
    $kService = hmacSHA256($kRegion, $service);
    return hmacSHA256($kService, 'request');
}

function hashSHA256($data) {
    return hash('sha256', $data, true);
}

function doRequest($method, $queries, $body) {
    // 1. 构建请求
    $queries['Version'] = VERSION;
    $requestAddr = ADDR . PATH . '?' . http_build_query($queries);
    echo "request addr: $requestAddr\n";

    $now = new DateTime('now', new DateTimeZone('UTC'));
    $date = $now->format('Ymd\THis\Z');
    $authDate = substr($date, 0, 8);

    $payload = bin2hex(hashSHA256($body));
    $headers = [
        'x-date' => $date,
        'x-content-sha256' => $payload,
        'content-type' => 'application/x-www-form-urlencoded',
    ];

    $queryString = str_replace('+', '%20', http_build_query($queries));
    $signedHeaders = ['host', 'x-date', 'x-content-sha256', 'content-type'];
    $headerList = [];
    foreach ($signedHeaders as $header) {
        if ($header == 'host') {
            $headerList[] = $header . ':' . parse_url(ADDR, PHP_URL_HOST);
        } else {
            $headerList[] = $header . ':' . trim($headers[$header]);
        }
    }
    $headerString = implode("\n", $headerList);

    $canonicalString = implode("\n", [
        $method,
        PATH,
        $queryString,
        $headerString . "\n",
        implode(';', $signedHeaders),
        $payload,
    ]);
    echo "canonical string:\n$canonicalString\n";

    $hashedCanonicalString = bin2hex(hashSHA256($canonicalString));
    echo "hashed canonical string: $hashedCanonicalString\n";

    $credentialScope = $authDate . '/' . REGION . '/' . SERVICE . '/request';
    $signString = implode("\n", [
        'HMAC-SHA256',
        $date,
        $credentialScope,
        $hashedCanonicalString,
    ]);
    echo "sign string:\n$signString\n";

    // 3. 构建认证请求头
    $signedKey = getSignedKey(SECRET_ACCESS_KEY, $authDate, REGION, SERVICE);
    $signature = bin2hex(hmacSHA256($signedKey, $signString));
    echo "signature: $signature\n";

    $authorization = 'HMAC-SHA256' .
        ' Credential=' . ACCESS_KEY_ID . '/' . $credentialScope .
        ', SignedHeaders=' . implode(';', $signedHeaders) .
        ', Signature=' . $signature;
    $headers['Authorization'] = $authorization;
    echo "authorization: $authorization\n";

    // 4. 发起请求
    $client = new Client();
    $request = new Request($method, $requestAddr, $headers, $body);
    echo "request:\n" . $request->getMethod() . ' ' . $request->getUri() . "\n";
    echo $request->getBody() . "\n";

    try {
        $response = $client->send($request);

        // 5. 打印响应
        echo "response:\n" . $response->getStatusCode() . ' ' . $response->getReasonPhrase() . "\n";
        echo $response->getBody() . "\n";

        if ($response->getStatusCode() == 200) {
            echo "请求成功\n";
        } else {
            echo "请求失败\n";
        }
    } catch (Exception $e) {
        echo "请求错误: " . $e->getMessage() . "\n";
    }
}

// 主程序
$query1 = ['Action' => 'ListMegaTTSTrainStatus'];
//doRequest('POST', $query1, json_encode(['AppID' => '7781505293']));

// ListMegaTTSTrainStatus 指定音色
 doRequest('POST', $query1, json_encode(['AppID' => '填入真实值', 'SpeakerIDs' => ['填入真实值']]));

// $query2 = ['Action' => 'ActivateMegaTTSTrainStatus'];
// doRequest('POST', $query2, json_encode(['AppID' => '填入真实值', 'SpeakerIDs' => ['填入真实值']]));
