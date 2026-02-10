<?php

require __DIR__ . '/../vendor/autoload.php';


function curl(\HttpDriverRequest $request) : ?array
{
    $driver = new CurlDriver(new \Memcached());
    $request->headers['Authorization'] = 'Basic ' . base64_encode('guest:guest');
    $request->url = 'http://localhost:15672' . $request->url;
    $response = $driver->request($request);
    if ($response->httpCode < 200 || $response->httpCode >= 300) {
        throw new \Exception("Http error " . $response->httpCode);
    }
    $json = json_decode($response->body, true);
    return $json;
}


$queues = curl(new \HttpDriverRequest('/api/queues'));
echo "got " . count($queues) . " queues\n";

$queues = array_filter($queues, function(array $queue) {
    return strpos($queue['name'], 'watchdog_') === 0;
});
echo "filtered " . count($queues) . " watchdog queues\n";

//$queues = array_filter($queues, function(array $queue) {
//    return $queue['messages_ready'] > 5;
//});
//echo "filtered " . count($queues) . " not empty queues\n";

$queues = array_filter($queues, function(array $queue) {
    if (!isset($queue['idle_since'])) {
        return false;
    }

    $idleSince = strtotime($queue['idle_since']);
    return (time() - $idleSince) > 86400;
});
echo "filtered " . count($queues) . " idle queues\n";

echo "deleting\n";
foreach ($queues as $queue) {
    echo "deleting " . $queue['name'] . "\n";
    $result = curl(new \HttpDriverRequest('/api/queues/%2F/' . $queue['name'], 'DELETE'));
}
echo "done\n";