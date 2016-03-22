<?php
/**
 * Get the current folder directory listing.
 *
 * To use this example, move this file above vendor.
 */
require __DIR__ . '/vendor/autoload.php';
use Steveorevo\VirtualCLI\VirtualCLI;

$myVCLI = new VirtualCLI("Test");
$myVCLI->add_command('ls');
$myVCLI->get_results(function($r){
	echo $r;
});
