<?php
/**
 * Test file
 */
require('../vendor/autoload.php');
include('../src/Steveorevo/VirtualCLI/VirtualCLI.php');
use Steveorevo\VirtualCLI\VCLIManager;
use Steveorevo\VirtualCLI\VirtualCLI;

$myVCLI = new VirtualCLI();
//$myVCLI->add_command("ls -la", null, function ($results) use ($myVCLI) {
//    echo $results . "\n";
//    $myVCLI->add_command('ls /Users/Shared', null, function($results) {
//        echo $results . "\n";
//    }) + "\n";
//}) . "\n";

$myVCLI->add_command("dir", null, function ($results) use ($myVCLI) {
    echo $results . "\n";
    $myVCLI->add_command('dir c:\Users', null, function($results) {
        echo $results . "\n";
    }) + "\n";
}) . "\n";

//for ($n = 0; $n < 30; $n++) {
//	echo "is_done = " . var_dump($myVCLI->is_done()) . "\n";
//	echo $myVCLI->get_results() . "\n";
//	sleep(1);
//}

var_dump(VCLIManager::cli_list());
var_dump(VCLIManager::has_cli('001'));
var_dump(VCLIManager::has_cli('myuniqueid-000'));
$oldCLI = new VirtualCLI("5711d6ff208b31871");
$oldCLI->close();
var_dump(VCLIManager::cli_list());
VCLIManager::shutdown();
