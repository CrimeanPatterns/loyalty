#!/usr/bin/env php
<?php

echo "setting up dev environment\n";

//execCommand('sudo chown `whoami`:`whoami` ' . ini_get("session.save_path"));

if(!file_exists(__DIR__ . '/../vendor/autoload.php')){
    echo "*** installing vendors ***\n";
    execCommand('composer install --no-scripts');
    if(!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        echo "ERROR, still no vendors\n";
        exit(1);
    }
}

require __DIR__ . '/../vendor/autoload.php';

initConfig("app/config/parameters.yml", "app/config/parameters.yml.dist", []);

echo "*** running post-install ***\n";
execCommand("rm -Rf var/cache/*");
execCommand("composer install");

echo "*** setup successful ***\n";

function execCommand($command){
    echo "running: " . $command . "\n";
    passthru($command, $exitCode);
    if($exitCode != 0) {
        echo "command $command failed, code $exitCode\n";
        exit(1);
    }
}

function updateParam(&$values, $pathStr, $value){
    $path = explode("/", $pathStr);
    $modified = false;
    while(!empty($path)){
        $slug = array_shift($path);
        if(!isset($values[$slug])){
            echo("missing key $slug\n");
            exit(3);
        }
        if(empty($path)) {
            if(empty($values[$slug]) || $values[$slug] != $value) {
                $values[$slug] = $value;
                $modified = true;
                echo "set param $pathStr to $value\n";
            }
        }
        else
            $values = &$values[$slug];
    }
    return $modified;
}

function initConfig($file, $template, array $params){
    echo "*** checking $file ***\n";
    if(!file_exists($file) && file_exists($template)) {
        echo "initialized $file from $template\n";
        file_put_contents($file, file_get_contents($template));
    }
    $content = file_get_contents($file);
    if(empty($content)){
        echo("failed to load file {$file}, did you checked out code from github?\n");
        exit(2);
    }

    $yml = \Symfony\Component\Yaml\Yaml::parse($content);
    $modified = false;
    foreach ($params as $key => $value){
        if(updateParam($yml, $key, $value)){
            $modified = true;
        }
    }
    if($modified) {
        if (!file_put_contents($file, \Symfony\Component\Yaml\Yaml::dump($yml))) {
            echo "failed to write file {$file}\n";
            exit(4);
        }
        echo "updated $file\n";
    }
}