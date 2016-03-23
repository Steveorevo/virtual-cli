<?php
/**
 * This Virtual CLI Console is the actual native CLI on the given platform and is invoked by the Virtual CLI Server
 * to execute commands.
 */
namespace Steveorevo\VirtualCLI;

class VirtualCLIConsole {
	/**
	 * Constants to define job of commands state
	 */
	Const INITIALIZED = 0;
	Const PENDING = 1;
	Const RUNNING = 2;
	Const DONE = 3;

	public $pause_request = false;
	public $estimate_total = 0;
	public $last_result = "";
	public $wait_for = null;
	public $commands = [];
	public $priority = 10;
	public $results = "";
	public $pipes = null;
	public $timeout = 60;
	public $time_up = 0;
	public $title = "";
	public $state = 0;
	public $eol = "\n";
	public $id = "";

	/**
	 * Create our Virtual CLI Console by opening a native shell process.
	 */
	public function __construct()
	{

		// Create our native console
		$descriptor_spec = array(
			0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
			1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
			2 => array("pipe", "a")   // stderr is a pipe that the child will write to
		);
		$init_cmd = '/bin/bash';
		if (false !== stripos(PHP_OS, "win") && false === stripos(PHP_OS, "Darwin")) { // cygwin, win, not Dar'win'
			$init_cmd = 'cmd.exe';
			// TO DO: performance issue on windows, bypass native and jump to bash.exe, determine how to locate runtime and revisit eol issue.
			$init_cmd = 'C:\xampplite\ds-plugins\ds-cli\platform\win32\cygwin\bin\bash.exe';
			$this->eol = "\r\n";
		}
		$cwd = getenv("HOME");
		$this->process = proc_open(
			$init_cmd,
			$descriptor_spec,
			$this->pipes,
			$cwd,
			[]
		);
		stream_set_blocking( $this->pipes[0], 0 );
		stream_set_blocking( $this->pipes[1], 0 );
		stream_set_blocking( $this->pipes[2], 0 );
	}

	function add_command($c)
	{
		// Update job initial info
		$this->priority = $c->priority;
		$this->timeout = $c->timeout;
		$this->time_up = $c->timeout;
		$this->title = $c->title;
		$this->id = $c->id;

		// Add the given command
		array_push($this->commands, $c);
	}
	/**
	 * Our process queued commands on the native shell.
	 */
	function process() {
		if ($this->pause_request === true) return null;
		if ($this->state === Self::DONE) return null;

		/**
		 * Send a command to our CLI instance.
		 */
		if ($this->state === Self::RUNNING) {
			if (count( $this->commands) > 0 ) {
				$c = array_shift($this->commands);
				$command = $c->command;
				$wait_for = $c->wait;
				fwrite($this->pipes[0], $command);

				// Reveal comments back to UI
				if ("##" === substr($command, 0, 2)) {

					// TO DO: trigger UI caption callback
				}else{

					// Hide passwords in response to prompts
					if (substr( strtolower( $this->last_result ), 0, 8) === "password") {
						$this->results .= "********" . $this->eol;
					}else{
						$this->results .= $command;
					}
					// TO DO: trigger progress callback
				}
				$this->state = Self::PENDING;
				$this->wait_for = $wait_for;
				$this->last_result = "";
			}else{
				$this->state = Self::DONE;
				$this->results = str_replace(";echo ***done***", "", $this->results);
				$this->results = str_replace("***done***\n", "", $this->results);
				//$this->close();
				return $this->results; // Return final results
			}
		}

		/**
		 * Gather results from CLI.
		 */
		$this->last_result = stream_get_contents($this->pipes[1]);
		// TO DO: performance issue on windows, use fgets appears that it might resolve/but we need to compensate for output
		//$this->last_result = fgets($this->pipes[1]);
		if (false !== $this->last_result) {
			if (0 !== strlen( $this->last_result)) {
				$this->results .= $this->last_result;
				// TO DO: trigger progress callback
			}
		}

		/**
		 * Check for expected completion, wait, or timeout.
		 */
		if ($this->state === Self::PENDING  && $this->wait_for !== null) {
			static $timer;
			if (is_string($this->wait_for)) {
				if (false !== strpos( $this->last_result, $this->wait_for)) {
					$this->state = Self::RUNNING;
					$this->wait_for = null;
				}
			}else{
				if ($timer !== time()) {
					$this->wait_for = $this->wait_for - 1;
				}
				if ($this->wait_for <= 0) {
					$this->state = Self::RUNNING;
					$this->wait_for = null;
				}
			}
			if ($this->time_up > 0) {
				if ($timer !== time()) {
					$this->time_up = $this->time_up - 1;
					$timer = time();
				}
				if ( $this->time_up === 0 ) {
					$this->state = Self::RUNNING;
					$this->wait_for = null;
				}
			}
		}
		return null;
	}

	/**
	 * Start our processing.
	 */
	function start() {
		if ($this->state === Self::INITIALIZED || $this->state === Self::DONE) {
			$this->estimate_total = $this->calc_time();
			$this->state = Self::RUNNING;
		}
		$this->pause_request = false;
	}

	/**
	 * Pause the processing queue.
	 */
	function pause() {
		$this->pause_request = true;
	}

	/**
	 * Returns the percentage of the commands that have been processed.
	 *
	 * @return float The percentage of the commands that are completed.
	 */
	function progress() {
		$t = $this->calc_time();
		if ( $t !== 0 ) {
			$t = $this->calc_time() / $this->estimate_total;
		}else{
			$t = 0;
		}
		return $t;
	}

	/**
	 * Calculates the total amount of time (in seconds) estimated from wait requests.
	 *
	 * @return int The total time calculated from the number of waits requests.
	 */
	function calc_time() {
		$total = 0;
		foreach( $this->commands as $c ) {
			$wait = $c->wait;
			if ( is_string( $wait ) ) {
				$total = $total + $this->timeout;
			}else{
				$total = $total + $wait;
			}
		}
		if ( count( $this->commands ) > 0 ) {
			$total = $total + $this->time_up;
		}
		return $total;
	}

	/**
	 * Clear the command queue and close our shell instance.
	 */
	function close() {
		fwrite( $this->pipes[0], chr(3) . chr(3)); // Ctrl+C
		fwrite( $this->pipes[0], "exit\n" );
		fclose( $this->pipes[0] );
		fclose( $this->pipes[1] );
		fclose( $this->pipes[2] );
	}

	/**
	 * Destroy and clean up after ourselves.
	 */
	function __destruct() {
		$this->close();
	}
}
