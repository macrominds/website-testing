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
     * Create instance. DocumentRoot and routerscript will be validated directly.
     * 
     * @param string $host         host on which the server shall run. For localhost, best practice 
     *                             is to use 0.0.0.0. That enables access from outside and especially from
     *                             java-tools. If you set host to 0.0.0.0, the server will be 
     *                             bound to 0.0.0.0 while connection tests are performed against 127.0.0.1.
     * @param int    $port         port to which the server shall listen.
     * @param string $documentRoot path to the servers' document root 
     * @param string $router       path to router script
     *
     * @throws \ŖuntimeException if the documentRoot-directory or routerscript does not exist
     *                            or the script doesn't have executable permissions on all directories
     *                            in the hierarchy of it's realpath. You will receive a detailed message in that case.
     *                            Also thrown if the functions `shell_exec` or `proc_open` are disabled. You will receive
     *                            an according error message.
     *                            Also thrown if the wrong php variant is used. Any variant other than php cli will cause a RuntimeException to be thrown.
     */
    public function __construct($host, $port, $documentRoot, $router = null)
    {
        self::verifyCorrectPhpVariant();
        self::verifyThatEssentialFunctionsAreEnabledOrThrowException();
        $this->host = $host;
        $this->port = $port;
        $this->documentRoot = $documentRoot;
        if (!file_exists($this->documentRoot)) {
            //the directory doesn't exist
            throw new \RuntimeException('DocumentRoot directory '.(realpath('.').'/'.$documentRoot).' does not exist');
        }
        $this->router = $router;
        if ($this->router !== null && !file_exists($this->router)) {
            //the routerscript doesn't exist
            throw new \RuntimeException('Routerscript '.(realpath('.').'/'.$router).' does not exist');
        }
    }
    /**
     * Verifies that we are running php cli and not any variant, because only
     * php cli provides the built-in-server.
     *
     * @throws \RuntimeException if the wrong php variant is used
     */
    private static function verifyCorrectPhpVariant()
    {
        $sapiName = php_sapi_name();
        if ($sapiName !== 'cli') {
            throw new \RuntimeException("Wrong php variant '$sapiName' used. Please make sure to run php 'cli'.");
        }
    }
    /**
     * Verifies that the functions `shell_exec`, `proc_open` and `fsockopen` are
     * enabled. These functions are necessary to start, check and stop the server.
     *
     * @throws \RuntimeException if any of the essential functions is not available. You will receive a detailed error message.
     */
    private static function verifyThatEssentialFunctionsAreEnabledOrThrowException()
    {
        if (!self::isFunctionEnabled('shell_exec')) {
            throw new \RuntimeException('The php function `shell_exec` is disabled. Without this function we would not be able to kill the server after it has been started. You need to turn it on in your php.ini. While you\'re there, also make sure that `proc_open` and `fsockopen` are enabled.');
        }
        if (!self::isFunctionEnabled('proc_open')) {
            throw new \RuntimeException('The php function `proc_open` is disabled. Without this function we would not be able to start the server. You need to turn it on in your php.ini. While you\'re there, make sure that `fsockopen` is enabled.');
        }
        if (!self::isFunctionEnabled('fsockopen')) {
            throw new \RuntimeException('The php function `fsockopen` is disabled. Without this function we would not be able to check if the server is running. You need to turn it on in your php.ini.');
        }
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
        $host = $this->getHost($host);
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
    /**
     * Returns the host to request for $host or this server.
     *
     * @param string $host optional host. If null or not given, then this 
     *                     servers host is used. If host is 0.0.0.0, then 127.0.0.1 is returned, 
     *                     otherwise host will be returned without modifications.
     *
     * @return string the host. E.g. 127.0.0.1 for 0.0.0.0 or 192.168.1.100 for
     *                192.168.1.100.
     */
    public function getHost($host = null)
    {
        if ($host === null) {
            $host = $this->host;
        }
        if ($host === '0.0.0.0') {
            //fsockopen on 0.0.0.0 does not work on windows.
            $host = '127.0.0.1';
        }

        return $host;
    }
    /**
     * Returns the port this server listens to.
     *
     * @return int the port. E.g. 80
     */
    public function getPort()
    {
        return $this->port;
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
        return $this->waitUntil(function () {
            //callback function. Will be requested until it returns true or the timeout is reached.
            return $this->canConnect();
        }, $timeoutInSeconds);
    }

    /**
     * Waits until a the $callback function returns true or the timeout is reached.
     *
     * @param callable $callback
     * @param int      $timeoutInSeconds
     *
     * @return bool condition finally met
     */
    private function waitUntil($callback, $timeoutInSeconds)
    {
        $start = microtime(true);
        $condition = $callback();
        if (!$condition) {
            // Try to connect until the time spent exceeds the timeout
            while (microtime(true) - $start <= (int) $timeoutInSeconds) {
                if ($callback()) {
                    $condition = true;
                    break;
                }
            }
        }

        return $condition;
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
        //get the parent pid of the process we want to kill
        $pid = $pstatus['pid'];
        if ($this->isWindows()) {
            //windows kill
          shell_exec("taskkill /F /T /PID $pid >nul 2>&1");
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

            //use ps to get all the children of this process, and kill them
            $pids = preg_split('/\s+/', shell_exec("ps -o pid --no-heading --ppid $pid"));
            foreach ($pids as $id) {
                if (is_numeric($id)) {
                    echo "Killing $id\n";
                    shell_exec("kill -9 $id"); //9 is the SIGKILL signal
                }
            }
            shell_exec("kill -9 $pid");
        }
    }

    /**
     * stop the server (kill it), if it is running. 
     * Also wait, until we cannot connect anymore.
     * It is save to call this even if the server has not been started.
     *
     * @param int $timeoutInSeconds timeout in seconds.
     */
    public function stopAndWaitForConnectionLoss($timeoutInSeconds = 10)
    {
        $this->stop();
        // wait until we can't connect to the server anymore
        $this->waitUntilServerIsDown($timeoutInSeconds);
    }

    private function waitUntilServerIsDown($timeoutInSeconds)
    {
        return $this->waitUntil(function () {
            //callback function. Will be requested until it returns true or the timeout is reached.
            return !$this->canConnect();
        }, $timeoutInSeconds);
    }

    /**
     * Checks if a php function is available and enabled.
     *
     * @param string $functionName name of the function (e.g. 'shell_exec')
     *
     * @return bool true, if the function is enabled (thus it can be used), false if not.
     */
    private static function isFunctionEnabled($functionName)
    {
        //bishops suggestion on http://stackoverflow.com/questions/21581560/php-how-to-know-if-server-allows-shell-exec#21581873
        return is_callable($functionName) && false === stripos(ini_get('disable_functions'), $functionName);
    }
}
