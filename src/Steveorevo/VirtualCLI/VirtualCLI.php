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
	public $callbacks = [];
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
		$url .= "&id=" . rawurlencode($this->id) . '&w=' . $timeout . "&c=" . rawurlencode($shell);
		@file_get_contents($url);

		// Wait for initial result
		while (false === $this->is_done(0)) {
			usleep(100);
		}
	}

	/**
	 * Add a command to be processed by the virtual command line interface.
	 *
	 * @param string $command The command to execute on the native virtual commandline interface.
	 * @param null $wait Seconds (int) or the substring value to wait for from the command before continuing.
	 * @param null $callback An optional callback to invoke when $wait parameter has been met. Note: causes blocking.
	 * @param null $eol Allows override to send "press key" events (sans line feed or carriage return), i.e. Press 'Y'
	 *
	 * @return int A unique number that identifies the submitted command, aka "command_id".
	 */
	public function add_command($command = "", $wait = null, $callback = null, $eol = null)
	{
		if ($eol === null && $wait === null) {

			// Default to adding a sequential command that won't continue until '***done***'.
			$command .= ";echo ***done***";
			$wait = '***done***';
		}
		if ($eol === null) {
			$eol = $this->eol;
		}
		if ($wait === null) {
			$wait = 1; // default to waiting at least 1 second
		}

		// Send the command to the native shell instance
		$url = 'http://127.0.0.1:' . VCLIManager::$port . '/vcli?s=' . VCLIManager::$security_key . '&a=add';
		$url .= '&id=' . rawurlencode($this->id) . '&w=' . rawurlencode($wait) . '&c=' . rawurlencode($command . $eol);
		$command_id = @file_get_contents($url);
		if (null !== $callback) {

			// Wait for completion
			while (false === $this->is_done($command_id)) {
				usleep(100);
			}
			call_user_func( $callback, $this->get_results($command_id));
		}
		return $command_id;
	}

	/**
	 * Get the current results from the virtual commandline interface. Specify an optional command_id to retrieve a
	 * command's specific results or none (-1 default) to return all results of the given session.
	 *
	 * @param int $command_id Optional id of a specific command to get the results for or -1 (default) to return all
	 *
	 * @return string The given results.
	 */
	public function get_results($command_id = -1) {
		$url = 'http://127.0.0.1:' . VCLIManager::$port . '/vcli?s=' . VCLIManager::$security_key . '&a=result';
		$url .= '&w=' . $command_id . '&id=' . rawurlencode($this->id);
		return @file_get_contents($url);
	}

	/**
	 * Check if the a given command or if all commands are done running on the virtual commandline interface.
	 *
	 * @param int $command_id Optional command_id to check for a completed command or none if all commands are done.
	 *
	 * @return bool Returns true if done or false if pending/running.
	 */
	public function is_done($command_id = -1) {
		$url = 'http://127.0.0.1:' . VCLIManager::$port . '/vcli?s=' . VCLIManager::$security_key . '&a=is_done';
		$url .= '&w=' . $command_id . '&id=' . rawurlencode($this->id);
		return @file_get_contents($url) === "1";
	}
}
