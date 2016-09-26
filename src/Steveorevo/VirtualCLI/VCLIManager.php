<?php
/**
 * The Virtual CLI Manager is a singleton instance that furnishes information about all running Virtual CLI instances.
 * The manager can be used to query or retrieve existing Virtual CLI instances that are currently running in memory
 * and centralizes common Virtual CLI functionality.
 */
namespace Steveorevo\VirtualCLI;
use Steveorevo\GString;

class VCLIManager {
	static $security_key;
	static $platform;
	static $port;

	/**
	 * Set runtime information.
	 */
	static function init($port = 7088) {

		// Allow override of default port
		VCLIManager::$port = $port;

		// Provide session support
		if (session_id() === "") {
			session_id('virtualcli');
			session_start();
		}

		// Provide unique security key
		if (! isset($_SESSION['vcli_security_key'])) {
			$_SESSION['vcli_security_key'] = uniqid() . dechex(rand(0, 32000));
		}
		VCLIManager::$security_key = $_SESSION['vcli_security_key'];

		// Determine platform
		$uname = strtolower( php_uname() );
		if ( strpos( $uname, "darwin" ) !== false ) {
			VCLIManager::$platform = 'darwin'; // OS X
		} else if ( strpos( $uname, "win" ) !== false ) {
			VCLIManager::$platform = 'win32'; // Windows
		} else if ( strpos( $uname, "linux" ) !== false ) {
			VCLIManager::$platform = 'linux'; // Linux
		} else {
			VCLIManager::$platform = 'unsupported'; // Unsupported
		}

		// Ensure vcli native binary is in memory
		$process_id = false;
		$cmd = '"' . __DIR__  . "/Builds - vcli.xojo_xml_project/";
		if (VCLIManager::$platform === 'win32') {
			exec("tasklist.exe", $ps);
			foreach($ps as $p) {
				if (false !== strpos($p, "vcli.exe")) {
					$p = new GString($p);
					$process_id = intval($p->delLeftMost("vcli.exe")->trim()->getLeftMost(" ")->__toString());
					break;
				}
			}
			$cmd .= 'Windows\vcli\vcli.exe" --port ' . VCLIManager::$port . ' --security_key ' . VCLIManager::$security_key;
			$cmd =  str_replace('/', '\\', $cmd);
			$cmd = 'start /b "vcli" ' . $cmd;
		}else{

			$process_id =  exec("ps -ax | awk '/[v]cli\\/vcli/{print $1}'") | false;
			if (!$process_id) {
				$process_id =  exec("ps -ax | awk '/[v]cli.debug\\/vcli.debug/{print $1}'") | false;
			}
			if (VCLIManager::$platform === 'darwin') {
				$cmd .= 'Mac OS X (Intel)/vcli/vcli" --port ' . VCLIManager::$port . ' --security_key ';
				$cmd .= VCLIManager::$security_key . ' > /dev/null 2>&1 &';
			}else{
				$cmd .= 'Linux/vcli/vcli" --port ' . VCLIManager::$port . ' --security_key ' . VCLIManager::$security_key;
				$cmd .= ' > /dev/null 2>&1 &';
			}
		}

		// Launch vcli instance
		if (!$process_id) {
			if (VCLIManager::$platform === 'win32') {
				pclose(popen($cmd, "r"));
			}else{
				exec($cmd);
			}
		}

		// Wait up to 15 seconds to see if socket is online
		$connected = @fsockopen("127.0.0.1", VCLIManager::$port);
		$timeup = 15;
		while (false === $connected && $timeup > 0) {
			$connected = @fsockopen("127.0.0.1", VCLIManager::$port);
			$timeup = $timeup - 1;
			sleep(1);
		}
		@fclose($connected);
	}

	/**
	 * Used to send a command with arguments to the virtual commandline interface and retrieve results.
	 *
	 * @param array $args The associated array containing the object parameters to serialize and send to the vcli
	 *
	 * @return string The results from the virtual commandline interface service.
	 */
	static function send($args = []) {
		$json = json_encode($args);
		$url = 'http://127.0.0.1:' . VCLIManager::$port . '/vcli?s=' . VCLIManager::$security_key;
		$url .= '&o=' . rawurlencode($json);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

	/**
	 * Used to retrieve an existing virtual command line interface object by the given id. The object can be used to
	 * add commands, get results, and query the status.
	 *
	 * @param $id The identifier used to create the initial instance of the VirtualCLI object.
	 *
	 * @return VirtualCLI The VirtualCLI object as referenced by the given id or false.
	 */
	static function get_cli($id) {
		if (VCLIManager::has_cli($id)) {
			return new VirtualCLI($id);
		}else{
			return false;
		}
	}

	/**
	 * Checks if the given virtual command line interface exists by id.
	 *
	 * @param $id The id used to create the original virtual command line interface.
	 *
	 * @return bool Returns true if the given $id exists otherwise false.
	 */
	static function has_cli($id) {
		$cli_list = VCLIManager::cli_list();
		return in_array($id, $cli_list);
	}

	/**
	 * Obtain a list of current virtual command line interface sessions.
	 *
	 * @return array An array containing a list of current sessions.
	 */
	static function cli_list() {
		$args = array(
			'action'        =>  'ids'
		);
		$results = new GString(VCLIManager::send($args));
		return explode("\r\n", $results->delRightMost("\r\n")->__toString());
	}

	/**
	 * Shutdown and quit all VirtualCLI objects and removes the vcli native binary from memory.
	 */
	static function shutdown() {
		$args = array(
			'action'        =>  'quit'
		);
		VCLIManager::send($args);
	}
}
