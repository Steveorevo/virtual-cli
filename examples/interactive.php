<?php
/**
 * Interactively create a text file and then delete it by typing 'y' at the command line interface. Here we will start
 * executing commands right away and use callbacks to interactively respond to the prompts.
 *
 */
require('../vendor/autoload.php');
include('../src/Steveorevo/VirtualCLI/VirtualCLI.php');
use Steveorevo\VirtualCLI\VCLIManager;
use Steveorevo\VirtualCLI\VirtualCLI;

// Create a new virtual command line interface named "test" (or access an existing one by that name)
VCLIManager::init();
$myVCLI = new VirtualCLI("test");

// Start processing commands as we interactively add them
$myVCLI->start();

// Echo 'hello world' to a file in the current directory
$myVCLI->add_command('echo Hello World > test.txt');

// Remove the file interactively using del or rm and by waiting for the Y/n prompt
if (VCLIManager::$platform === 'win32') {
	$command = 'del /P test.txt'; // /P for prompt flag on Windows
}else{
	$command = 'rm -i test.txt';  // -i for interactive mode on *nix
}

// Wait for the question mark to appear and invoke the callback
$myVCLI->add_command($command, '?', function($results) use ($myVCLI) {

	// Send a Y for 'yes' and pause a second
	$myVCLI->add_command('Y', 1);
});

// Wait until the queue is done
while(false === $myVCLI->is_done()) {
	usleep(1000); // Wait a second
}

// Show the complete console history
echo $myVCLI->get_results();

// Automatically close all terminals and shutdown the VCLI daemon
VCLIManager::shutdown();
