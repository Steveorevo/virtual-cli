<?php
/**
 * Test file
 */
require('../vendor/autoload.php');
include('../src/Steveorevo/VirtualCLI/VirtualCLI.php');
use Steveorevo\VirtualCLI\VCLIManager;
use Steveorevo\VirtualCLI\VirtualCLI;

$myVCLI = new VirtualCLI();
echo "add_command " . $myVCLI->add_command("ls -la", null, function ($results) use ($myVCLI) {
		echo "add_command again " . $myVCLI->add_command('ls /Users/Shared', null, function($results) {
			echo $results;
		});
	}) . "\n";
//for ($n = 0; $n < 30; $n++) {
//	echo "is_done = " . var_dump($myVCLI->is_done()) . "\n";
//	echo $myVCLI->get_results() . "\n";
//	sleep(1);
//}

//var_dump(VCLIManager::cli_list());
//var_dump(VCLIManager::has_cli('001'));
//var_dump(VCLIManager::has_cli('002'));
//VCLIManager::shutdown();
