<?php
/**
 * An example of set, release, and wait for mutexes.
 *
 */
require('../vendor/autoload.php');
include('../src/Steveorevo/VirtualCLI/VirtualCLI.php');
use Steveorevo\VirtualCLI\VCLIManager;
use Steveorevo\VirtualCLI\VirtualCLI;

//
//
// TO DO: test mutexes with one thread pinging x number of times while another waits before completing a listing
//
//
