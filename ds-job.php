<?php

/**
 * DesktopServer Job object provides a CLI shell and associated command queue
 * to process sequential commands. Attributes and methods enable terminating,
 * pausing, waiting, timing out, and prioritizing jobs.
 */

class DS_Job {
	public static $job_counter = 0;
	public $pause_request = false;
	public $estimate_total = 0;
	public $last_caption = '';
	public $last_result = '';
	public $wait_for = null;
	public $process = null;
	public $commands = [];
	public $priority = 10;
	public $pipes = null;
	public $timeout = 60;
	public $results = "";
	public $time_up = 0;
	public $title = '';
	public $waits = [];
	public $eol = "\n";
	public $state = 0;
	public $id = 0;

	/**
	 * Constants to define job state
	 */
	Const INITIALIZED = 0;
	Const PENDING = 1;
	Const RUNNING = 2;
	Const DONE = 3;

	/**
	 * Create our job object and open a native shell process.
	 *
	 * @param string $title A title that describes the given job (i.e. "Start Server").
	 * @param int $priority The priority (0 to 99) for the job, lower starts earlier.
	 */
	function __construct( $title = '', $priority = 10 ) {
		$this->priority = $priority;
		$this->title = $title;
		$descriptor_spec = array(
			0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
			1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
			2 => array("pipe", "a")   // stderr is a pipe that the child will write to
		);

		// Get native shell executable, platform end of line terminator
		$init_cmd = '/bin/bash';
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$init_cmd = 'cmd.exe';
			$this->eol = "\r\n";
		}
		$cwd = getenv("HOME");
		$env = [];
		$this->process = proc_open(
			$init_cmd,
			$descriptor_spec,
			$this->pipes,
			$cwd,
			$env
		);
		stream_set_blocking( $this->pipes[0], 0 );
		stream_set_blocking( $this->pipes[1], 0 );
		stream_set_blocking( $this->pipes[2], 0 );
		$this->id = self::$job_counter;
		self::$job_counter++;
		if ( $this->title == '' ) {
			$this->title = 'Job ID: ' . $this->id;
		}
	}

	/**
	 * Add a command to be processed by our native shell.
	 *
	 * @param string $command The command to execute on the native CLI shell.
	 * @param null $wait Seconds (int) or substring value to wait for from the command.
	 * @param null $eol Allows override to send "press key" events (sans line feed or carriage return).
	 */
	function add( $command = '', $wait = null, $eol = null ) {
		if ( $eol === null && $wait === null ) {

			/**
			 * Default to adding a sequential commmand that won't continue until '***done***'.
			 */
			$command .= '; echo ***done***';
			$wait = '***done***';
		}
		if ( $eol === null ) {
			$eol = $this->eol;
		}
		if ( $wait === null ) {
			$wait = 1; // default to waiting 1 second
		}
		array_push( $this->commands, $command . $eol );
		array_push( $this->waits, $wait );
	}


	/**
	 * Returns the percentage of the job that has been processed.
	 *
	 * @return float The percentage of the job that is completed.
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
		foreach( $this->waits as $wait ) {
			if ( is_string( $wait ) ) {
				$total = $total + $this->timeout;
			}else{
				$total = $total + $wait;
			}
		}
		if ( count( $this->waits ) > 0 ) {
			$total = $total + $this->time_up;
		}
		return $total;
	}

	/**
	 * Our process queued commands on the native shell.
	 */
	function process() {
		if ( $this->pause_request === true ) return;

		/**
		 * Send a command to our CLI instance.
		 */
		if ( $this->state === Self::RUNNING ) {
			if ( count( $this->commands ) > 0 ) {
				$command = array_shift( $this->commands );
				$wait_for = array_shift( $this->waits );
				fwrite( $this->pipes[0], $command );

				/**
				 * Process title and caption updates
				 */
				if ( $command[0] === "#" ) {
					$captitle = new String( $command );
					$value = $captitle->delLeftMost( " " );
					$captitle = $captite->getLeftMost( " " );
					if ( $captitle === "#caption" ) {
						$this->last_caption = $value;

						// Trigger caption change
						echo "#caption " . $this->last_caption . "\n";

					}elseif ( $captitle === "#title") {
						$this->title = $value;

						// Trigger title change
						echo "#title " . $this->title . "\n";
					}
				}else{

					/**
					 * Hide password commands in result set
					 */
					if ( substr( strtolower( $this->last_result ), 0, 8) === "password" ) {
						$this->results .= "********" . $this->eol;
					}else{
						$this->results .= $command;

						// Trigger last_results callback
					}
				}
				$this->time_up = $this->timeout;
				$this->state = Self::PENDING;
				$this->wait_for = $wait_for;
				$this->last_result = "";
			}else{
				$this->state = Self::DONE;
			}
		}

		/**
		 * Gather results from CLI.
		 */
		$this->last_result = stream_get_contents( $this->pipes[1] );
		if ( false !== $this->last_result ) {
			if ( 0 !== strlen( $this->last_result ) ) {
				$this->results .= $this->last_result;

				// Trigger last_results callback
				echo $this->results;
			}
		}

		/**
		 * Check for expected completion, wait, or timeout.
		 */
		if ( $this->state === Self::PENDING  && $this->wait_for !== null ) {
			if ( is_string( $this->wait_for ) ) {
				if ( false !== strpos( $this->last_result, $this->wait_for ) ) {
					$this->state = Self::RUNNING;
					$this->wait_for = null;
				}
			}else{
				$this->wait_for = $this->wait_for - 1;
				if ( $this->wait_for <= 0 ) {
					$this->state = Self::RUNNING;
					$this->wait_for = null;
				}
			}
			if ( $this->time_up > 0 ) {
				$this->time_up = $this->time_up - 1;
				if ( $this->time_up === 0 ) {
					$this->state = Self::RUNNING;
					$this->wait_for = null;
				}
			}
		}
	}

	/**
	 * Start our processing.
	 */
	function start() {
		if ( $this->state === Self::INITIALIZED || $this->state === Self::DONE ) {
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
	 * Clear the command queue and close our shell instance.
	 */
	function close() {
		fwrite( $this->pipes[0], 'exit' );
		fclose( $this->pipes[0] );
		fclose( $this->pipes[1] );
		fclose( $this->pipes[2] );
		$this->clear();
	}

	/**
	 * Clear the command queue of pending commands.
	 */
	function clear() {
		$this->last_caption = "";
		$this->last_result = "";
		$this->wait_for = null;
		$this->commands = [];
		$this->result = "";
		$this->waits = [];
	}

	/**
	 * Destroy and clean up after ourselves.
	 */
	function __destruct() {
		$this->close();
	}
}