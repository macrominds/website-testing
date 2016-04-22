<?php

/*
 * The MIT License
 *
 * Copyright 2016 Thomas Praxl <thomas@macrominds.de>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace macrominds\website\testing;

/**
 * Enables you to start the built-in php webserver from a php script. 
 * 
 * Especially useful for functional testing.
 * 
 * A lot of the code of EmbeddedServerController is inspired from 
 * https://github.com/vgno/tech.vg.no-1812/blob/master/features/bootstrap/FeatureContext.php
 *
 * @author Thomas Praxl <thomas@macrominds.de>
 */
class EmbeddedServerController
{
    /**
     * process handle that is used to query for the pid and kill the process 
     * later on. Is null until started.
     * 
     * @var resource
     */
    private $processHandle;
    /**
     * host on which the server shall run. For localhost, best practice 
     * is to use 0.0.0.0. That enables access from outside and especially from
     * java-tools. If you set host to 0.0.0.0, the server will be 
     * bound to 0.0.0.0 while connection tests are performed against 127.0.0.1.
     * 
     * @var string
     */
    private $host;

    /**
     * port to which the server shall listen.
     *
     * @var int
     */
    private $port;
    /**
     * @var string path to the servers' document root
     */
    private $documentRoot;
    /**
     * @var string path to router script
     */
    private $router;
    /**
     * Create instance. documentRoot will be validated directly.
     * 
     * @param string $host         host on which the server shall run. For localhost, best practice 
     *                             is to use 0.0.0.0. That enables access from outside and especially from
     *                             java-tools. If you set host to 0.0.0.0, the server will be 
     *                             bound to 0.0.0.0 while connection tests are performed against 127.0.0.1.
     * @param int    $port         port to which the server shall listen.
     * @param string $documentRoot string path to the servers' document root 
     * @param string $router       path to router script
     *
     * @throws \ŖuntimeException if the documentRoot-directory does not exist
     *                            or the script doesn't have executable permissions on all directories
     *                            in the hierarchy of it's realpath. You will receive a detailed message in that case.
     */
    public function __construct($host, $port, $documentRoot, $router = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->documentRoot = realpath(__DIR__.'/../'.$documentRoot);
        if ($this->documentRoot === false) {
            //the directory doesn't exist
            throw new \RuntimeException('DocumentRoot directory '.$documentRoot.' does not exist or '
                    .'we dont have executable permissions to all directories in'
                    .'the hierarchy: .'.(__DIR__.'/../'.$documentRoot));
        }
        $this->router = $router;
    }

    /**
     * Start the server.
     * 
     * @param int $timeoutInSeconds the maximum amount of seconds, we wait for the 
     *                              server to get up and be available. Default is 10.
     *
     * @throws \RuntimeException if another service blocks [host:port] or if
     *                           the server was not available after $timeoutInSeconds seconds.
     */
    public function start($timeoutInSeconds = 10)
    {
        if ($this->canConnect()) {
            throw new \RuntimeException('Some service is blocking '.
                    $this->host.':'.$this->port.'. Aborting tests.');
        }
        $connected = $this->startAndWaitUntilServerIsUp($timeoutInSeconds);
        if (!$connected) {
            $this->killServer();
            throw new \RuntimeException(
                sprintf(
                    'Could not connect to the web server within the given timeout (%d second(s))',
                    $timeoutInSeconds
                )
            );
        }
    }
    /**
     * Tests if we can connect to the server.
     *
     * @param string $host optional ip or hostname. Default is null,
     *                     which means that the field host is used (which has been passed
     *                     to the constructor).
     *
     * @return bool true if we can connect, false if not
     */
    public function canConnect($host = null)
    {
        if ($host === null) {
            if ($this->host === '0.0.0.0') {
                //fsockopen on 0.0.0.0 does not work on windows.
                $host = '127.0.0.1';
            } else {
                $host = $this->host;
            }
        }
        // Disable error handler for now
        set_error_handler(function () { return true; });
        // Try to open a connection 
        $sp = fsockopen(''.$host, $this->port);
        // Restore the handler
        restore_error_handler();
        if ($sp === false) {
            return false;
        }
        fclose($sp);

        return true;
    }

    private function startAndWaitUntilServerIsUp($timeoutInSeconds)
    {
        $this->processHandle = $this->startServer();
        if ($this->processHandle === null) {
            throw new \RuntimeException('Couldn\'t start the server');
        }
        $connected = $this->waitUntilServerIsUp($timeoutInSeconds);

        return $connected;
    }

    private function startServer()
    {
        //linux command:
        $rawCommand = 'exec php -S %s:%d -t %s %s';

        if ($this->isWindows()) {
            //windows command:
            //-> the alternative "start /b php ..." spawns in background, 
            //but pid returned by proc_get_status wouldn't be reliable.
            //forking the process using '&' seems to work though
            $rawCommand = 'php -S %s:%d -t %s %s >nul 2>&1 &';
        }
        $command = sprintf($rawCommand,
                            $this->host,
                            $this->port,
                            str_replace('\\', '/', $this->documentRoot),
                            str_replace('\\', '/', $this->router));
        $pipes = array();
        $this->processHandle = proc_open($command,
        array(
          array('pipe', 'r'),
          array('pipe', 'w'),
          array('pipe', 'w'),
        ),
        $pipes);

        return $this->processHandle;
    }
    private function isWindows()
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    private function waitUntilServerIsUp($timeoutInSeconds)
    {
        $start = microtime(true);
        $connected = false;
        // Try to connect until the time spent exceeds the timeout
        while (microtime(true) - $start <= (int) $timeoutInSeconds) {
            if ($this->canConnect()) {
                $connected = true;
                break;
            }
        }

        return $connected;
    }

    /**
     * stop the server (kill it), if it is running.
     * It is save to call stop even if the server has not been started.
     */
    public function stop()
    {
        if ($this->processHandle !== null) {
            $this->killServer();
        }
    }

    private function killServer()
    {
        //use OS specific kill instead of pclose / proc_terminate. Because
        //the latter two options wouldn't kill child processes.
        //taken from: php.net/manual/en/function.proc-terminate.php#113918
        $pstatus = proc_get_status($this->processHandle);
        $pid = $pstatus['pid'];
        if ($this->isWindows()) {
            //windows kill
          exec("taskkill /F /T /PID $pid >nul 2>&1");
        } else {
            //linux kill alone is not enough.
            //Furthermore: php sadly returns the pid of the sh that started the 
            //process. And: We cannot prefix the command with exec, because
            //we need it to run it in the background. 
            //Forking while using exec prefix will also lead to wrong pid. 
            //The correct pid is only returned, if you prefix with exec and
            //if you don't fork. So it's absolutely useless for background-
            //processes.
            //I won't consider killing pid+1 as suggested by some, it would
            //work in many cases, but it's just not reliable.
            //
            //Additionally: The spawned processes are somehow not really
            //identified as child-processes in my test-scenarios.
            //
            //we provide a script that kills all child processes as well.
            //get the parent pid of the process we want to kill

            //use ps to get all the children of this process, and kill them
            $pids = preg_split('/\s+/', `ps -o pid --no-heading --ppid $pid`);
            foreach ($pids as $id) {
                if (is_numeric($id)) {
                    echo "Killing $id\n";
                    exec("kill -9 $id"); //9 is the SIGKILL signal
                }
            }
            exec("kill -9 $pid");
            proc_close($this->processHandle);
        }
    }
}
