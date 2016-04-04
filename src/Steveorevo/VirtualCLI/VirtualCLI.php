<?php
/**
 * A Virtual CLI instance provides programmable interactive access to the native OS command line interface. Commands
 * can be queued, prioritized, sequentially executed, paused, and terminated. Methods can be used to retrieve queue
 * progress and output.
 */
namespace Steveorevo\VirtualCLI;
use Steveorevo\VirtualCLI\VCLIManager;
use Steveorevo\String;

// Ensure Virtual CLI Manager is initialized
VCLIManager::init();

class VirtualCLI {
	public $eol = "\n";
	public $id = null;

	/**
	 * Creates an Virtual CLI (job) object to submit commands to the native vcli service.
	 *
	 * @param string id A unique ID to identify the command line interface session.
	 * @param int $timeout The amount of time allocated for any given command to execute before a timeout occurs.
	 * @param string $shell The initial shell to use for the CLI. Defaults to native environment shell.
	 */
	public function __construct($id = null, $timeout = 60, $shell = null) {

		// Windows default usually c:\windows\System32\cmd.exe from ComSpec and /bin/bash from SHELL on *nix
		if ($shell === null) {
			if (VCLIManager::$platform === 'win32') {
				$shell = getenv("ComSpec");
			}else{
				$shell = getenv("SHELL");
			}
		}

		// Create unique id if none supplied
		if ($id === null) {
			$id = uniqid() . dechex(rand(0, 32000));
		}
		$this->id = $id;

		// Create the native shell instance
		$url = 'http://127.0.0.1:' . VCLIManager::$port . '/vcli?s=' . VCLIManager::$security_key . '&a=create';
		$url .= "&id=" . rawurlencode($this->id) . "&w=" . $timeout . "&c=" . rawurlencode($shell);
		@file_get_contents($url);
	}
//
//		// Create unique id for this VirtualCLI instance
//      $this->id = uniqid() . dechex(rand(0, 32000));
//
//
//		// Create unique security key for all VirtualCLI instances to use
//		if (VirtualCLI::$security_key === '') {
//			VirtualCLI::$security_key = uniqid() . dechex(rand(0, 32000));
//		}
//
//		// Check for existing vcli instance
//		$process_id = false;
//		$cmd = '"' . __DIR__  . "/Builds - vcli.xojo_xml_project/";
//		if ($this->platform === 'win32') {
//			exec("tasklist.exe", $ps);
//			foreach($ps as $p) {
//				if (false !== strpos($p, "vcli.exe --port")) {
//					$p = new String($p);
//					$process_id = intval($p->delLeftMost("vcli.exe")->trim()->getLeftMost(" ")->__toString());
//					break;
//				}
//			}
//			$cmd .= 'Windows\vcli\vcli.exe" --port ' . $this->port . ' --security_key ' . VirtualCLI::$security_key;
//			$cmd =  str_replace('/', '\\', $cmd);
//			$cmd = 'start /b "vcli" ' . $cmd;
//
//			// Windows default is usually c:\windows\System32\cmd.exe
//			if ($shell === null) {
//				$shell = getenv("ComSpec");
//			}
//		}else{
//			$process_id =  exec("ps -a | awk '/[v]cli\\/vcli/{print $1}'") | false;
//			if ($this->platform === 'linux') {
//				$cmd .= 'Mac OS X (Intel)/vcli/vcli --port ' . $this->port . ' --security_key ';
//				$cmd .= VirtualCLI::$security_key . '" > /dev/null 2>&1 &';
//			}else{
//				$cmd .= 'Linux/vcli/vcli --port ' . $this->port . ' --security_key ' . VirtualCLI::$security_key;
//				$cmd .= '" > /dev/null 2>&1 &';
//			}
//
//			// Linux, Darwin default is usually /bin/bash
//			if ($shell === null) {
//				$shell = getenv("SHELL");
//			}
//		}
//
//		// Launch vcli instance
//		if (false === $process_id) {
//			if ($this->platform === 'win32') {
//				pclose(popen($cmd, "r"));
//			}else{
//				exec($cmd);
//			}
//		}
//
//		// Start the session
//		$url = 'http://127.0.0.1:' . $this->port . '/vcli?s=' . VirtualCLI::$security_key . '&id=' . $this->id;
//		$url .= '&c=' . rawurlencode($shell);
//		$url . "\n";
//		file_get_contents($url);
//	}
//
//	/**
//	 * Add a command to be processed by our native shell.
//	 *
//	 * @param string $command The command to execute on the native CLI shell.
//	 * @param null $wait Seconds (int) or the substring value to wait for from the command.
//	 * @param null $callback An optional callback to invoke when the #wait parameter has been met.
//	 * @param null $eol Allows override to send "press key" events (sans line feed or carriage return), i.e. Press 'Y'
//	 */
//	public function add_command($command = "", $wait = null, $callback = null, $eol = null)
//	{
//		if ($eol === null && $wait === null) {
//
//			// Default to adding a sequential command that won't continue until '***done***'.
//			$command .= ";echo ***done***";
//			$wait = '***done***';
//		}
//		if ($eol === null) {
//			$eol = $this->eol;
//		}
//		if ($wait === null) {
//			$wait = 1; // default to waiting at least 1 second
//		}
//
//
//	}
}
