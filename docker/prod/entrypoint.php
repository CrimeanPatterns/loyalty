#!/usr/bin/env php
<?php
error_reporting(E_ALL);

echo "symfony env: " . getenv("SYMFONY_ENV") . "\n";

$output = [];
if (getenv('CHECK_PROXY_IP') == '1') {
    passthru("/www/loyalty/current/docker/prod/proxycheck.sh", $exitCode);
    if ($exitCode !== 0) {
        exit($exitCode);
    }
}
passthru("chown -R www-data:www-data src/AppBundle/Engine", $exitCode);
if ($exitCode !== 0) {
    exit($exitCode);
}

if (!waitEngineSync()) {
    echo "failed to sync engine\n";
    exit(1);
}

function waitEngineSync() : bool
{
    if (getenv("SYNC_ENGINE") === "0") {
        echo "engine sync disabled\n";
        return true;
    }

    echo "waiting for engine folder to be synced\n";
    $file = "src/AppBundle/Engine/.sync-time";
    $synced = false;
    $startTime = time();
    while(($delay = (time() - $startTime)) < 90 && !$synced){
        if(file_exists($file)){
            $date = strtotime(file_get_contents($file));
            $curDate = time();
            echo "date in $file: " . date("Y-m-d H:i:s", $date) . ", current date: " . date("Y-m-d H:i:s", $curDate) . "\n";
            if($date < $curDate && $date > strtotime('2000-01-01')){
                echo "synced\n";
                $synced = true;
            }
        }
        else
            echo "waiting $delay seconds for $file\n";
        if(!$synced)
            sleep(1);
    }

    return $synced;
}

if (getenv("SSM_WARMUP") == "1") {
    passthru('gosu www-data bin/console aw:ssm-warmup-cache -vv', $exitCode);
    if ($exitCode !== 0) {
        exit($exitCode);
    }
}

var_dump($argv);

pcntl_exec($argv[1], array_slice($argv, 2));
