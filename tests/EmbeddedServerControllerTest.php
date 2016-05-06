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
 * Description of EmbeddedServerControllerTest.
 *
 * @author Thomas Praxl <thomas@macrominds.de>
 */
class EmbeddedServerControllerTest extends \PHPUnit_Framework_TestCase
{
    private $serverController;
    private $serverController2;

    public function tearDown()
    {
        if ($this->serverController !== null) {
            $this->serverController->stop();
        }
        if ($this->serverController2 !== null) {
            $this->serverController2->stop();
        }

        $this->serverController = null;
        $this->serverController2 = null;
    }
    /**
     * @test
     */
    public function phpShouldBeAccessible()
    {
        $output = array();
        exec('php -v', $output);
        $this->assertStringStartsWith('PHP', $output[0]);
    }
    /**
     * @test
     * @expectedException \RuntimeException
     */
    public function controllerShouldValidateDocumentRoot()
    {
        $dir = 'non-existing-directory-xx';
        $this->assertFalse(file_exists($dir));
        //throws \RuntimeException if docroot is not there.
        $this->serverController = new EmbeddedServerController(HOST, PORT, $dir);
    }
    /**
     * @test
     */
    public function shouldStartupAndShutdownProperly()
    {
        //HOST, PORT and DOCROOT are specified in phpunit.xml
        $this->serverController = new EmbeddedServerController(HOST, PORT, DOCROOT);
        $this->assertFalse($this->serverController->canConnect());
        $this->serverController->start();
        $this->assertTrue($this->serverController->canConnect());
    }

    /**
     * @test
     */
    public function shouldNotStartupWhenAnotherServiceRuns()
    {
        //HOST, PORT and DOCROOT are specified in phpunit.xml
        $this->serverController = new EmbeddedServerController(HOST, PORT, DOCROOT);
        $this->assertFalse($this->serverController->canConnect());
        $this->serverController2 = new EmbeddedServerController(HOST, PORT, DOCROOT);
        $this->serverController->start();
        $this->assertTrue($this->serverController->canConnect());
        try {
            $this->serverController2->start();
            $this->assertTrue(false);
        } catch (\RuntimeException $e) {
            //success
        }
    }
    /**
     * @test
     */
    public function controllerShouldReturnCorrectHost()
    {
        $this->serverController = new EmbeddedServerController('0.0.0.0',PORT, DOCROOT);
        $this->assertEquals($this->serverController->getHost(),'127.0.0.1');
        
        $this->serverController = new EmbeddedServerController('192.168.1.100',PORT,DOCROOT);
        $this->assertEquals($this->serverController->getHost(),'192.168.1.100');
        
        $this->assertEquals($this->serverController->getHost('0.0.0.0'),'127.0.0.1');
        $this->assertEquals($this->serverController->getHost('127.0.0.1'),'127.0.0.1');
        $this->assertEquals($this->serverController->getHost('192.168.1.100'),'192.168.1.100');
    }
}
