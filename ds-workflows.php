<?php

/**
 * DesktopServer Workflows object is a Virtual CLI server that contains job queues
 * to process CLI (command line interface) shell processes. Jobs can be paused
 * for mutexes to complete or be assigned a priority for earlier or latent
 * execution. The virtual CLI can be invoked with elevated privileges or run as
 * a regular user and utilizes a local authorization key schema to ensure that
 * only authorized connections are accepted.
 *
 * @uses DS_Job
 *
 */
require 'vendor/autoload.php';
require 'ds-job.php';

// Our native trace function for PHP
function trace($msg, $j = false){
	if (! is_string($msg) && $j===false ){
		$msg = "(" . gettype($msg) . ") " . var_export($msg, true);
	}else{
		if ($j===false) {
			$msg = "(" . gettype($msg) . ") " . $msg;
		}
	}
	$h = @fopen('http://127.0.0.1:8189/trace?m='.substr(rawurlencode($msg),0,2000),'r');
	if ($h !== FALSE){
		fclose($h);
	}
}

class DS_Workflows {
	public $server = null;
	public $loop = null;
	public $jobs = [];
	public $idle = 0;

	/**
	 * Create our Workflows server to listen for connections and process job
	 * queues in a timely manner.
	 */
	function __construct() {
		$this->loop = new React\EventLoop\StreamSelectLoop();
		$this->loop->addPeriodicTimer( 1, array( $this, 'process_jobs' ) );
		$this->server = new DNode\DNode( $this->loop, $this );
		$this->server->on( 'error', array( $this, 'error') );
		$this->server->listen(6567);
		$this->loop->run();
	}

	/**
	 * Process any pending jobs or exit if idle longer than a minute
	 */
	function process_jobs() {
		if ( count( $this->jobs ) == 0 ) {
			if ( $this->idle > 60 ) {
				$this->loop->stop();
			}
			$this->idle++;
			return;
		}
		foreach( $this->jobs as $job ) {
			$job->progress();
			$job->process();
		}
		$this->idle = 0;
	}

	/**
	 * Creates a job and returns the unique associated job identifier.
	 *
	 * @param function $cb The callback function to invoke with the unique job id.
	 * @param string $title The title for the given job. I.e. 'Start services', etc.
	 * @param int $priority The priority for the given job, lower for earlier execution. Default is 10.
	 *
	 */
	function create_job( $cb, $title = '', $priority = 10 ) {
		trace( "create_job" );
		$job = new DS_Job( $title, $priority );
		$this->jobs[$job->id] = $job;
		if ( $cb !== null ) {
			$cb( $job->id );
		}
	}

	function start_job( $id ) {
		if ( false === isset( $this->jobs[$id]) ) {
			trigger_error( 'Job not found. Unable to start_job.', E_USER_ERROR );
		}
		$this->jobs[$id]->start();
	}

	function remove_job( $id ) {
		if ( false === isset( $this->jobs[$id]) ) {
			trigger_error( 'Job not found. Unable to remove_job.', E_USER_ERROR );
		}
		unset( $this->jobs[$id] );
	}

	function add_command( $id, $command = '', $wait = null, $eol = null ) {
		if ( false === isset( $this->jobs[$id]) ) {
			trigger_error( 'Job not found. Unable to add_command.', E_USER_ERROR );
		}
		$this->jobs[$id]->add( $command, $wait, $eol );
	}

	function add_caption( $id, $cap ) {
		if ( false === isset( $this->jobs[$id]) ) {
			trigger_error( 'Job not found. Unable to add_caption.', E_USER_ERROR );
		}
		$this->jobs[$id]->add_command( "#caption " . $cap, 0, '' );
	}

	function update_title( $id, $title ) {
		if ( false === isset( $this->jobs[$id]) ) {
			trigger_error( 'Job not found. Unable to update_title.', E_USER_ERROR );
		}
		$this->jobs[$id]->add_command( "#title " . $title, 0, '' );
	}

	function get_progress( $cb, $id ) {
		if ( false !== isset( $this->jobs[$id]) ) {
			$cb( $this->jobs[$id]->progress() );
		}
		$cb( null );
	}

	function get_all_progress( $cb ) {
		$total = 0;
		foreach( $this->jobs as $job ) {
			$total = $total + $job->progress();
		}
		if ( count( $this->jobs ) > 0 ) {
			$total = $total / count( $this->jobs );
		}
		$cb( $total );
	}

	function error() {
		trigger_error( 'DS Workflows error.', E_USER_ERROR );
	}

	/**
	 * Provide ping-able "heartbeat" to verify viable connection.
	 *
	 * @param $pong Callback to validate connection.
	 */
	public function ping( $pong ) {
		trace( "pinged" );
		$this->idle = 0;
		$pong();
	}
}

global $ds_workflows;
$ds_workflows = new DS_Workflows();
