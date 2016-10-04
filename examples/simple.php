<?php
/**
 * Open a virtual command line, get the current directory listing, then close command line. It's best practice to close
 * the terminal when we don't need it anymore otherwise it will persist in memory. We later shutdown the vcli daemon
 * (use wisely as this will close down all the terminals, regardless of who created them or if we forgot to close 'em).
 *
 */
require('../vendor/autoload.php');
include('../src/Steveorevo/VirtualCLI/VirtualCLI.php');
use Steveorevo\VirtualCLI\VCLIManager;
use Steveorevo\VirtualCLI\VirtualCLI;

// Create a new virtual command line interface named "test" (or continue accessing an existing one of that name)
VCLIManager::init();
$myVCLI = new VirtualCLI("test");

// Queue typing 'ls' or 'dir' (Windows) on the command line
if (VCLIManager::$platform === 'win32') {
	$myVCLI->add_command('dir');
}else{
	$myVCLI->add_command('ls');
}

// List any existing consoles
var_dump( VCLIManager::cli_list( 2 ) );

// Start processing commands in queue
$myVCLI->start();

// Wait until the queue is done
while(false === $myVCLI->is_done()) {
	usleep(1000); // Wait a second
}

// Echo out the console history
echo $myVCLI->get_results();

// List any existing consoles
var_dump( VCLIManager::cli_list( 2 ) );

// Close the terminal
$myVCLI->close();

// Shutdown the VCLI daemon
VCLIManager::shutdown();
