<?php

use App\Keboola\XeroEx\Xero;
use Symfony\Component\Yaml\Yaml;

system('ls -al');

require __DIR__.'/vendor/autoload.php';


$arguments = getopt("d::", array ("data::"));
if (!isset($arguments["data"])) {
    print "Data folder not set.";
    exit(1);
}

$config = Yaml::parse(file_get_contents($arguments["data"]."/config.yml"));

try {
    $xero = new Xero(
        $config['parameters'],
        $arguments["data"]."/out/tables/"
    );

    $xero->run();
} catch (Exception $e) {
    print $e->getMessage();
    exit(1);
}

exit(0);
