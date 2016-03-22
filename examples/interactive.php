<?php
/**
 * TO DO: lftp or ssh example of waiting, pressing 'y', and callback conditional.
 *
 * To use this example, move this file above vendor.
 */

//
///**
// * Example...
// */
////$myVCLI = [];
////for ($y = 0; $y < 10; $y++) {
////	$myVCLI[$y] = new VirtualCLI("Test " . $y);
////	for ($x = 0; $x < 10; $x++) {
////		$msg = "echo $y$x";
////		$myVCLI[$y]->add_command($msg);
////	}
////	echo $myVCLI[$y]->get_results();
////}
//global $myVCLI;
//$myVCLI = new VirtualCLI("Test", 10, 15);
//$myVCLI->add_command("echo Hello");
//$myVCLI->get_results(function($results){
//	echo $results;
//	global $myVCLI;
//	$myVCLI->add_command("echo mars");
//	$myVCLI->get_results(function($results){
//		echo $results;
//		global $myVCLI;
//		$myVCLI->closeAll();
//	});
//});
////$myVCLI->add_command("echo Goodbye");
////$myVCLI->get_results(function($results){
////	echo $results;
////});