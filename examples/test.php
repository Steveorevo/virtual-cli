<?php
/**
 * Test file
 */
require('../vendor/autoload.php');
include('../src/Steveorevo/VirtualCLI/VirtualCLI.php');
use Steveorevo\VirtualCLI\VCLIManager;
use Steveorevo\VirtualCLI\VirtualCLI;

$myVCLI = new VirtualCLI();

if (VCLIManager::$platform === 'win32') {
	$myVCLI->add_command("dir", null, function ($results) use ($myVCLI) {
		echo $results . "\n";
		$myVCLI->add_command('dir c:\Users', null, function($results) {
			echo $results . "\n";
		}) + "\n";
	}) . "\n";
}else{
	$myVCLI->add_command("ls -la", null, function ($results) use ($myVCLI) {
	    echo $results . "\n";
	    $myVCLI->add_command('ls /Users', null, function($results) {
	        echo $results . "\n";
	    }) + "\n";
	}) . "\n";
}

var_dump(VCLIManager::cli_list());
var_dump(VCLIManager::has_cli('001'));
var_dump(VCLIManager::has_cli('myuniqueid-000'));
$oldCLI = new VirtualCLI("5711d6ff208b31871");
$oldCLI->close();
var_dump(VCLIManager::cli_list());
VCLIManager::shutdown();
