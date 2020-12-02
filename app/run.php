<?php

require __DIR__.'/vendor/autoload.php';

use App\Keboola\XeroEx\Xero;

$arguments = getopt("d::", ["data::"]);
if (!isset($arguments["data"])) {
    print "Data folder not set.";
    exit(1);
}

$config = json_decode(file_get_contents($arguments["data"]."/config.json"), true);

try {
    $xero = new Xero(
        $config['authorization'],
        $config['parameters'],
        $arguments["data"]."/out/tables/"
    );

    $xero->run();
} catch (Exception $e) {
    print $e->getMessage();
    exit(1);
}

exit(0);
