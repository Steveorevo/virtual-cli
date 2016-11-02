<?php
/**
 * A Virtual CLI instance provides programmable interactive access to the native OS command line interface. Commands
 * can be queued, prioritized, sequentially executed, paused, and terminated. Methods can be used to retrieve queue
 * progress and output.
 */
namespace Steveorevo\VirtualCLI;
use Steveorevo\VirtualCLI\VCLIManager;
use Steveorevo\GString;

class VirtualCLI {
	public $concat_char = ";";
	public $callbacks = [];
	public $completed = 0;
	public $eol = "\n";
	public $id = null;

	/**
	 * Creates an Virtual CLI (job) object to submit commands to the native vcli service.
	 *
	 * @param null $id A unique ID to identify the command line interface session.
	 * @param int $timeout The amount of time allocated for any given command to execute before a timeout occurs.
	 * @param string $shell The initial shell to use for the CLI. Defaults to native environment shell.
	 * @param null $wait Optional initial substring to wait for when the CLI is initialized.
	 * @param string $concat_char Optional override for the command concatenation character.
	 */
	public function __construct($id = null, $timeout = 60, $shell = null, $wait = null, $concat_char = null) {

		// Windows default usually c:\windows\System32\cmd.exe from ComSpec and /bin/bash from SHELL on *nix
		if ($shell === null) {
			if (VCLIManager::$platform === 'win32') {
				$shell = getenv("ComSpec");
			}else{
				$shell = getenv("SHELL");
			}
		}

		// Windows default is "Microsoft Windows" and "bash" for *nix
		if ($wait === null) {
			if (VCLIManager::$platform === 'win32') {
				$wait = "Microsoft Windows";
			}else{
				$wait = "bash";
			}
		}

		// Allow customization of the command concatenation character
		if ($concat_char === null) {
			if (VCLIManager::$platform === 'win32') {
				$concat_char = "&";
			}else{
				$concat_char = ";";
			}
		}
		$this->concat_char = $concat_char;

		// Create new instance and unique id if none supplied
		if ($id === null) {
			$id = uniqid() . dechex( rand( 0, 32000 ) );

			// Immediately create the native shell instance
			$args = array(
				'console_id'    =>  $id,
				'timeout'       =>  $timeout,
				'wait_for'      =>  $wait,
				'action'        =>  'create',
				'command'       =>  $shell
			);
			VCLIManager::send($args);

			// Check for existing instance by name and create it if nec
		}else{
			if (! VCLIManager::has_cli($id)) {
				$args = array(
					'console_id'    =>  $id,
					'timeout'       =>  $timeout,
					'wait_for'      =>  $wait,
					'action'        =>  'create',
					'command'       =>  $shell
				);
				VCLIManager::send($args);
			}
		}
		$this->id = $id;
	}

	/**
	 * Start processing the commands on the virtual command line interface.
	 */
	public function start()
	{
		// Send the command to the native shell instance
		$args = array(
			'console_id'    =>  $this->id,
			'action'        =>  'start'
		);
		VCLIManager::send($args);
	}

	/**
	 * Stop processing the commands on the virtual command line interface.
	 */
	public function stop()
	{
		// Send the command to the native shell instance
		$args = array(
			'console_id'    =>  $this->id,
			'action'        =>  'stop'
		);
		VCLIManager::send($args);
	}

	/**
	 * Wait for the given mutex to finish before continuing processing of subsequent add_commands.
	 *
	 * @param $name The name of the mutex to wait for.
	 */
	public function wait_for_mutex($name)
	{
		// Schedule the mutex waiting (like adding a command), to be processed as apart of the command queue
		$args = array(
			'command'       =>  "## mutex wait " . $name,
			'console_id'    =>  $this->id,
			'wait_for'      =>  0,
			'action'        =>  'add'
		);
		VCLIManager::send($args);
	}

	/**
	 * Create or increment the given mutex count.
	 *
	 * @param $name The name of the mutex to set.
	 */
	public function set_mutex($name)
	{
		// Schedule the mutex creation (like adding a command(, to be processed as apart of the command queue
		$args = array(
			'command'       =>  "## mutex set " . $name,
			'console_id'    =>  $this->id,
			'wait_for'      =>  0,
			'action'        =>  'add'
		);
		VCLIManager::send($args);
	}

	/**
	 * Release the given mutex.
	 *
	 * @param $name The name of the mutex to release.
	 */
	public function release_mutex($name)
	{
		// Schedule the mutex release (like adding a command), to be processed as apart of the command queue
		$args = array(
			'command'       =>  "## mutex release " . $name,
			'console_id'    =>  $this->id,
			'wait_for'      =>  0,
			'action'        =>  'add'
		);
		VCLIManager::send($args);
	}

	/**
	 * Add a command to be processed by the virtual command line interface.
	 *
	 * @param string $command The command to execute on the native virtual commandline interface.
	 * @param null $wait Seconds (int) or the substring value to wait for from the command before continuing.
	 * @param null $callback An optional callback to invoke when $wait parameter has been met. Note: causes blocking, ensure vcli is started!
	 * @param null $eol Allows override to send "press key" events (sans line feed or carriage return), i.e. Press 'Y'
	 *
	 * @return int A unique number that identifies the submitted command, aka "command_id".
	 */
	public function add_command($command = "", $wait = null, $callback = null, $eol = null)
	{
		if ($eol === null && $wait === null) {

			// Default to adding a sequential command that won't continue until '~~~done~~~'.
			$command .= $this->concat_char . "echo ~~~done~~~";
			$wait = '~~~done~~~';
		}
		if ($eol === null) {
			$eol = $this->eol;
		}
		if ($wait === null) {
			$wait = 1; // default to waiting at least 1 second
		}


		// Send the command to the native shell instance
		$args = array(
			'command'       =>  $command . $eol,
			'console_id'    =>  $this->id,
			'wait_for'      =>  $wait,
			'action'        =>  'add'
		);
		$command_id = VCLIManager::send($args);
		if (null !== $callback) {

			// Wait for completion
			while ("0" === $this->is_done($command_id)) {
				usleep(500);
			}
			call_user_func( $callback, $this->get_results($command_id));
		}
		return $command_id;
	}

	/**
	 * Get the current results from the virtual commandline interface. Non-blocking. Specify an optional command_id to
	 * retrieve a command's specific results or none (-1 default) to return all results of the current given session.
	 *
	 * @param int $command_id Optional id of a specific command to get the results for or -1 (default) to return all
	 *
	 * @return string The given results.
	 */
	public function get_results($command_id = -1) {
		$args = array(
			'wait_for'      =>  $command_id,
			'console_id'    =>  $this->id,
			'action'        =>  'result'
		);
		$results = VCLIManager::send($args);

		// Filter out password and ~~~done~~~ echos if present
		$lines = explode(Chr(10), $results);
		$results = "";
		$prev = "";
		foreach ($lines as $line) {

			// Hide passwords in response to prompts
			if (substr(strtolower($prev), 0, 8) === "password") {
				$line = "********" . $this->eol;
			}

			// Hide done waiting mechanism
			if (false !== strpos($line, "~~~done~~~")) {
				$line = str_replace($this->concat_char . "echo ~~~done~~~", "", $line);
				$line = str_replace("~~~done~~~", "", $line);
			}

			// Eat echo'd command on Windows
			if (VCLIManager::$platform === 'win32') {
				if ($line === substr($prev, -strlen($line)) && strlen($prev) > strlen($line)) {
					continue;
				}
			}

			// Eat invisible [?1034h
			if (substr($line, 0, 8) === '[?1034h') {
				$line = substr($line, 8);
			}

			$prev = $line;
			$results .= $line . Chr(10);
		}

		// Parse out the percent complete from the initial response
		$results = new GString( $results );
		$this->completed = intval( $results->getLeftMost("|\n") );
		return $results->delLeftMost("|\n")->__toString();
	}

	/**
	 * Check if the a given command or if all commands are done running on the virtual commandline interface.
	 *
	 * @param int $command_id Optional command_id to check for a completed command or none if all commands are done.
	 *
	 * @return bool Returns true if done or false if pending/running.
	 */
	public function is_done($command_id = -1) {
		$args = array(
			'wait_for'      =>  $command_id,
			'console_id'    =>  $this->id,
			'action'        =>  'is_done'
		);
		return VCLIManager::send($args) === "1";
	}

	/**
	 * Close the given virtual commandline.
	 */
	public function close() {
		$args = array(
			'console_id'    =>  $this->id,
			'action'        =>  'close'
		);
		return VCLIManager::send($args);
	}
}
