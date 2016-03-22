<?php
/**
 * A Virtual CLI instance provides programmable interactive access to the native OS command line interface. Commands
 * can be queued, executed, paused, or terminated. Methods can be used to retrieve queue progress and output.
 */
namespace Steveorevo;
use React\EventLoop\StreamSelectLoop;
use DNode\DNode;

class VirtualCLI
{
	public $connected = false;
	public $callbacks = [];
	public $commands = [];
	public $retries = -30;
	public $server = null;
	public $priority = 10;
	public $timeout = 60;
	public $timer = null;
	public $loop = null;
	public $title = "";
	public $boot = "";
	public $eol = "\n";
	public $id = null;
	public $port = 0;

	/**
	 * Creates an Virtual CLI (job) object to submit commands to the Virtual CLI Server.
	 *
	 * @param string $title The name of the job to add to the Virtual CLI Server.
	 * @param int $priority The priority for the job to execute. Lower numbers execute earlier.
	 * @param int $timeout The amount of time allocated for any given command to execute before a timeout occurs.
	 * @param int $port The optional port number for the client and server to communicate on.
	 * @param string $boot Optional startup command (for setting env. variables, etc.).
	 */
	public function __construct($title = "", $priority = 10, $timeout = 60, $port=7088, $boot = "")
	{
		// Connect to the server and submit the command
		$this->loop = new React\EventLoop\StreamSelectLoop();
		$this->server = new DNode\DNode($this->loop);
		$this->id = uniqid() . dechex(rand(0, 32000));
		$this->priority = $priority;
		$this->timeout = $timeout;
		$this->title = $title;
		$this->boot = $boot;
		$this->port = $port;
	}

	/**
	 * Add a command to be processed by our native shell.
	 *
	 * @param string $command The command to execute on the native CLI shell.
	 * @param null $wait Seconds (int) or the substring value to wait for from the command.
	 * @param null $eol Allows override to send "press key" events (sans line feed or carriage return), i.e. Press 'Y'
	 */
	public function add_command($command = "", $wait = null, $eol = null)
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

		// Create a command to send to the server
		$c = new stdClass();
		$c->command_id = uniqid() . dechex(rand(0, 32000));
		$c->timeout = $this->timeout;
		$c->priority = $this->priority;
		$c->command = $command . $eol;
		$c->title = $this->title;
		$c->id = $this->id;
		$c->wait = $wait;

		// Ensure the Virtual CLI Server is up and running
		$this->launch();

		// Invoke add_command on the server
		$this->server->connect($this->port, function ($remote, $connection) use ($c) {
			$remote->add_command($c, function() use ($connection) {
				$connection->end(); // Note: use callback to keep socket in sync.
			});
		} );
		$this->loop->run();
		$this->loop->stop();
	}

	public function get_results($cb)
	{
		// Invoke add_command on the server
		$this->server->connect($this->port, function ($remote, $connection) use($cb) {
			$remote->get_results($this->id, function($results) use ($connection, $cb) {
				$connection->end(); // Note: use callback to keep socket in sync.
				$cb($results);
			});
		} );
		$this->loop->run();
		$this->loop->stop();
	}
	public function close()
	{
		// Invoke close on the server
		$this->server->connect($this->port, function ($remote, $connection) {
			$remote->close($this->id, function() use ($connection) {
				$connection->end(); // Note: use callback to keep socket in sync.
			});
		} );
		$this->loop->run();
		$this->loop->stop();
	}
	public function closeAll()
	{
		// Invoke closeAll on the server
		$this->server->connect($this->port, function ($remote, $connection) {
			$remote->closeAll(function() use ($connection) {
				$connection->end(); // Note: use callback to keep socket in sync.
			});
		} );
		$this->loop->run();
		$this->loop->stop();
	}
	public function running()
	{
		echo "Running\n";
	}

	/**
	 * Launch the out-of-process Virtual CLI Server if it's not already running on the given port.
	 *
	 */
	public function launch()
	{
		$this->connected = @fsockopen("127.0.0.1", $this->port);
		if (false === $this->connected) {
			if ($this->retries === 0) {
				echo "Virtual CLI out-of-process timeout.\n";
				$this->loop->cancelTimer($this->timer);
				$this->timer = null;
				return;
			}else{

				// Be nice, launching on some CPUs take longer
				if (fmod(abs($this->retries),2)) {
					echo "Attempting to launch the Virtual CLI out-of-process server.\n";
					$boot = $this->boot . 'php ' . dirname( __FILE__ ) . '/virtual-cli-server.php -p' . $this->port;
					$pid = dirname( __FILE__ ) . '../virtual-cli-server.pid';
					$log = dirname( __FILE__ ) . '../virtual-cli-server.log';
					@unlink( $log );
					@unlink( $pid );
					exec( sprintf( "%s > %s 2>&1 & echo $! >> %s", $boot, $log, $pid ) );
				}

				// Retry/relaunch if need be over next 30 seconds
				if (null === $this->timer) {
					$this->timer = $this->loop->addPeriodicTimer(1, array($this, 'launch'));
					$this->loop->run();
				}
				$this->retries++;
			}
		}else {
			@fclose( $this->connected );
			if (null !== $this->timer) {
				$this->loop->cancelTimer($this->timer);
				$this->loop->stop();
				$this->timer = null;
			}
		}
	}
}
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
