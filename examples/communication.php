<?php
/**
 * Override the native shell and use DS-CLI's (https://github.com/Steveorevo/ds-cli) bash on Mac or Windows to lftp
 * the lastest copy of WordPress to an example hosting provider.
 *
 * Prerequisites - DesktopServer 3.8.1 or better with the DS-CLI plugin installed.
 *
 */
require('../vendor/autoload.php');
include('../src/Steveorevo/VirtualCLI/VirtualCLI.php');
use Steveorevo\VirtualCLI\VCLIManager;
use Steveorevo\VirtualCLI\VirtualCLI;

// Determine the shell runtime via DS-CLI's boot script for the given platform
if (VCLIManager::$platform === 'win32') {
	$shell = '"c:\\xampplite\\ds-plugins\\ds-cli\\platform\\win32\\boot.bat" bash.exe --posix -i';
}else{
	$shell = "/Applications/XAMPP/ds-plugins/ds-cli/platform/mac/boot.sh bash";
}

// Create a new virtual command line interface running bash with a 5 minute timeout
$myVCLI = new VirtualCLI("ds-cli-example", 5 * 60, $shell, "bash", ";");

// Change directories to the home folder
$myVCLI->add_command("ls -la");

// Change directories to the home folder
$myVCLI->add_command("cd ~");

// Remove any prior wordpress folder and latest.zip file
$myVCLI->add_command("rm latest.zip;rm -rf wordpress");

// Download the latest copy of WordPress
$myVCLI->add_command("wget https://wordpress.org/latest.zip");

// Unzip it
$myVCLI->add_command("unzip -q latest.zip");

// LFTP into our host's site, wait 5 seconds to connect
$myVCLI->add_command("lftp ftp://spress-deploy:J3NeM4yx@deploy.postmy.info/web --debug 5", 5);

// Remove prior wordpress folder, recreate it, cd into it, and wait for 'cd ok' confirmation
$myVCLI->add_command("rm -rf wordpress;mkdir wordpress;cd wordpress");

// Change local directory to our unzipped home folder / wordpress and wait for 'lcd ok' confirmation
$myVCLI->add_command("lcd ~/wordpress");

// Mirror local to remote using 10 parallel connections (much faster than 1 at a time!), wait for "Total:" confirmation in response
$myVCLI->add_command("mirror -R --parallel=10", "Total:");

// Disconnect from lftp, and give it a couple of seconds
$myVCLI->add_command("bye", 2);

// Cleanup the zip and remove the local wordpress folder
$myVCLI->add_command("rm latest.zip;rm -rf wordpress");

// Start the processing queue
$myVCLI->start();

// Wait until the queue is done
while(false === $myVCLI->is_done()) {

	// Peek at results every 5 seconds
	echo $myVCLI->get_results();
	sleep(5);
}

// Show the complete console history
echo $myVCLI->get_results();

// Automatically close all terminals and shutdown the VCLI daemon
VCLIManager::shutdown();
