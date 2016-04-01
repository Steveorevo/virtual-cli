<?php
/**
 * A Virtual CLI instance provides programmable interactive access to the native OS command line interface. Commands
 * can be queued, prioritized, sequentially executed, paused, and terminated. Methods can be used to retrieve queue
 * progress and output.
 */
namespace Steveorevo\VirtualCLI;
use Steveorevo\String;

class VirtualCLI {
    static $security_key = '';
    public $platform = '';
    public $priority = 10;
    public $timeout = 60;
    public $port = 7088;
    public $eol = "\n";

    /**
     * Creates an Virtual CLI (job) object to submit commands to the native vcli service.
     *
     * @param int $priority The priority for the job to execute. Lower numbers execute earlier.
     * @param int $timeout The amount of time allocated for any given command to execute before a timeout occurs.
     * @param string $shell The initial shell to use for the command line interface. Defaults to native.
     * @param int $port The optional port number for the client and server to communicate on.
     */
    public function __construct($priority = 10, $timeout = 60, $shell = null, $port=7088)
    {
        // Fill out console settings
        $this->priority = $priority;
        $this->timeout = $timeout;
        $this->port = $port;

        // Determine platform
        $uname = strtolower( php_uname() );
        if ( strpos( $uname, "darwin" ) !== false ) {
            $this->platform = 'darwin'; // OS X
        } else if ( strpos( $uname, "win" ) !== false ) {
            $this->platform = 'win32'; // Windows
        } else if ( strpos( $uname, "linux" ) !== false ) {
            $this->platform = 'linux'; // Linux
        } else {
            $this->platform = 'unsupported'; // Unsupported
        }

        // Create unique security key for all VirtualCLI instances to use
        if (VirtualCLI::$security_key === '') {
            VirtualCLI::$security_key = uniqid() . dechex(rand(0, 32000));
        }

        // Check for existing vcli instance
        $process_id = false;
        $cmd = '"' . __DIR__  . "/Builds - vcli.xojo_xml_project/";
        if ($this->platform === 'win32') {
            exec("tasklist.exe", $ps);
            foreach($ps as $p) {
                if (false !== strpos($p, "vcli.exe --port")) {
                    $p = new String($p);
                    $process_id = intval($p->delLeftMost("vcli.exe")->trim()->getLeftMost(" ")->__toString());
                    break;
                }
            }
            $cmd .= 'Windows\vcli\vcli.exe" --port ' . $this->port . ' --security_key ' . VirtualCLI::$security_key;
            $cmd =  str_replace('/', '\\', $cmd);
            $cmd = 'start /b "vcli" ' . $cmd;

            // Windows default is usually c:\windows\System32\cmd.exe
            if ($shell === null) {
                $shell = getenv("ComSpec");
            }
        }else{
            $process_id =  exec("ps -a | awk '/[v]cli\\/vcli/{print $1}'") | false;
            if ($this->platform === 'linux') {
                $cmd .= 'Mac OS X (Intel)/vcli/vcli --port ' . $this->port . ' --security_key ';
                $cmd .= VirtualCLI::$security_key . '" > /dev/null 2>&1 &';
            }else{
                $cmd .= 'Linux/vcli/vcli --port ' . $this->port . ' --security_key ' . VirtualCLI::$security_key;
                $cmd .= '" > /dev/null 2>&1 &';
            }

            // Linux, Darwin default is usually /bin/bash
            if ($shell === null) {
                $shell = getenv("SHELL");
            }
        }

        // Launch vcli instance
        if (false === $process_id) {
            if ($this->platform === 'win32') {
                pclose(popen($cmd, "r"));
            }else{
                exec($cmd);
            }
        }

        // Create unique id for this VirtualCLI instance
        $this->id = uniqid() . dechex(rand(0, 32000));

        // Start the session
        $url = 'http://127.0.0.1:' . $this->port . '/vcli?s=' . VirtualCLI::$security_key . '&id=' . $this->id;
        $url .= '&c=' . rawurlencode($shell);
        $url . "\n";
        file_get_contents($url);
    }

    /**
     * Add a command to be processed by our native shell.
     *
     * @param string $command The command to execute on the native CLI shell.
     * @param null $wait Seconds (int) or the substring value to wait for from the command.
     * @param null $callback An optional callback to invoke when the #wait parameter has been met.
     * @param null $eol Allows override to send "press key" events (sans line feed or carriage return), i.e. Press 'Y'
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


    }
}