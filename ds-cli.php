<?php
/**
 * DesktopServer CLI object creates our DesktopServer Workflows as a
 * persistent auto-starting remote out-of-process object.
 *
 */
require 'vendor/autoload.php';

class DS_CLI {
	public $connection = null;
	public $workflows = null;
	public $job_id =  null;
	public $priority = 10;
	public $status = 0;
	public $title = '';
	public $timer = null;
	public $dnode = null;
	public $loop = null;
	public $retry = 0;
	public $cb = null;

	/**
	 * Constants to define connection state
	 */
	Const DISCONNECTED = 0;
	Const LAUNCHING = 1;
	Const CONNECTED = 2;

	/**
	 * Initialize and attempt connection
	 */
	function __construct() {
		$this->loop = new React\EventLoop\StreamSelectLoop();
		$this->dnode = new DNode\DNode( $this->loop );
		$this->timer = $this->loop->addPeriodicTimer( 5, array( $this, 'running' ) );
		$this->dnode->on( 'error', array( $this, 'error') );
		$this->status = Self::DISCONNECTED;
		$this->running();
		$this->loop->run();
	}

	/**
	 * Our running process to our remote workflows object.
	 */
	function running() {
		if ( $this->status !== Self::CONNECTED ) {
			$this->dnode->connect( 6567, function ( $workflows, $connection ) {
				$this->status = Self::CONNECTED;
				$this->connection = $connection;
				$this->workflows = $workflows;
				$this->ping();
			} );
		}else{
			//$this->ping();
		}
	}

	/**
	 * Ping function to validate connection.
	 */
	function ping() {
		echo "ping\n";
		$this->retry++;
		$this->workflows->ping( function() {
			$this->loop->stop();
			$this->retry = 0;
			echo "pong\n";
		} );

		// Watchdawg, signal disconnect on failed pings
		if ( $this->retry > 1 ) {
			$this->status = Self::DISCONNECTED;
			$this->retry = 0;
		}
	}

	/**
	 * Launch DesktopServer Workflows as an out-of-process server.
	 */
	function launch() {
		echo "launch\n";
		if ( $this->status === Self::LAUNCHING ) return;
		$this->status = Self::LAUNCHING;
		$boot         = dirname( __FILE__ ) . '/platform/mac/boot.sh';
		$log          = '/Applications/XAMPP/xamppfiles/logs/ds-cli.log';
		$pid          = '/Applications/XAMPP/xamppfiles/logs/ds-cli.pid';
		if ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ) {
			$boot = dirname( __FILE__ ) . '/platform/win32/boot.bat';
			$log  = '/xampplite/apache/logs/ds-cli.log';
			$pid  = '/xampplite/apache/logs/ds-cli.pid';
		}
		$boot .= ' php ' . dirname( __FILE__ ) . '/ds-workflows.php';
		@unlink( $log );
		@unlink( $pid );
		exec( sprintf( "%s > %s 2>&1 & echo $! >> %s", $boot, $log, $pid ) );
	}

	/**
	 * Catch connection error and attempt to launch/connect.
	 */
	function error() {
		$this->status = Self::DISCONNECTED;
		$this->launch();
	}

	function create_job( $cb, $title = '', $priority = 10 ) {
		echo "line 107\n";
		$this->loop->run();
		echo "line 109\n";
		$this->workflows->create_job( function($id) {
			echo "back from create_job\n";
			call_user_func( $cb );
			$this->loop->stop();
		}, $title, $priority );
	}
}
global $ds_cli;
$ds_cli = new DS_CLI();
$ds_cli->create_job( function( $id ) {
	echo $id . " called back\n";
}, 'test', 10 );
echo "line 100\n";


//$ds_cli->workflows->ping( function() use ($ds_cli) {
//	echo "reply";
//	$ds_cli->loop->stop();
//} );
//$ds_cli->loop->run();
//$ds_cli->workflows->test();
//$ds_cli->workflows->create_job('test', )
//$ds_cli->pung();
//$ds_cli->pang( function() {
//	echo "replied!\n";
//} );
echo "continue\n";
