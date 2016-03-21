<?php

// Include Composer-generated autoloader
require('trace.php');

require('vendor/autoload.php');
require('virtual-cli-console.php');

class VirtualCLIServer
{
    private $callbacks = [];
    private $consoles = [];
    public $server = null;
    public $loop = null;
    public $port = 0;

    public function __construct()
    {
        $this->loop = new React\EventLoop\StreamSelectLoop();
        $this->loop->addPeriodicTimer(0.01, array($this, 'processing'));
        $this->server = new DNode\DNode($this->loop, $this);
        $this->port = intval(@getopt('p:')['p']) | 7088;
        $this->server->listen($this->port);
        $this->loop->run();
    }

    public function add_command($c, $cb)
    {
        if (false === isset($this->consoles[$c->id])) {
            $this->consoles[$c->id] = new VirtualCLIConsole();
        }
        $this->consoles[$c->id]->start();
        $this->consoles[$c->id]->add_command($c);
        $cb(); // Note: invoke callback to keep socket in sync.
    }

    public function get_results($id, $cb)
    {
        $this->callbacks[$id] = $cb;
    }
    public function close($id, $cb)
    {
        if (isset($this->consoles[$id])) {
            unset($this->consoles[$id]);
            $cb();
        }
    }
    public function closeAll($cb)
    {
        $this->consoles = [];
        $cb();
    }
    /**
     * Processes the Virtual CLI Console; lower priority numbers execute first.
     */
    public function processing()
    {
        $prior = false;
        for ($n = 0; $n < 21; $n++) {
            foreach($this->consoles as $console) {
                if ($console->priority === $n) {
                    $r = $console->process();
                    if (null !== $r) {
                        if (isset($this->callbacks[$console->id])) {
                            @call_user_func($this->callbacks[$console->id], $r);
                            unset($this->callbacks[$console->id]);
                        }
                    }
                    $prior = true;
                }
            }
            if (true === $prior) break;
        }
    }
}
global $virtual_cli_server;
$virtual_cli_server = new VirtualCLIServer();

