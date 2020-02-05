<?php

$Source = array(
    "DB_Name" => 'nursxjig_wp_site_elite_production',
    "DB_User" => 'nursxjig_test',
    "DB_Pass" => '^Test1234^',
    "Path" => '',
    "DB_Host" => 'localhost',
    "Url" => 'elitespecialtystaffing.com'
);

$Destination = array(
    "DB_Name" => 'nursxjig_wp_site_nursa_dev',
    "DB_User" => 'nursxjig_test',
    "DB_Pass" => '^Test1234^',
    "Path" => '',
    "DB_Host" => 'localhost',
    "Url" => 'dev.nursa.com'
);

$output = shell_exec('php SRDB/srdb.cli.php -h '.$Destination["DB_Host"].' -n '.$Destination["DB_Name"].' -u '.$Destination["DB_User"].' -p '.$Destination["DB_Pass"].' -s '.$Source["Url"].' -r '.$Destination["Url"].' -v true -z');
echo "<pre>$output</pre>";