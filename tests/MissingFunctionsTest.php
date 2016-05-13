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
 * Description of MissingFunctionsTest.
 *
 * @author Thomas Praxl <thomas@macrominds.de>
 */
class MissingFunctionsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * This tests expects to be run in an environment, where the functions
     * are not available. It must be executed with the following
     * command (where fsockopen may be replaced with proc_open and shell_exec)
     * php -d disable_functions=fsockopen vendor/phpunit/phpunit/phpunit --no-configuration tests/MissingFunctionsTest.php.
     *
     * @test
     * @expectedException \RuntimeException
     */
    public function shouldThrowExceptionWhenEssentialFunctionsAreNotAvailable()
    {
        $actuallyDisabledFunctions = explode(',', ini_get('disable_functions'));
        $expectedDisabledFunctions = array('fsockopen', 'proc_open', 'shell_exec');

        //make sure at least one function is disabled
        $this->assertGreaterThan(0, count($actuallyDisabledFunctions), 'At least one of the functions '.implode(', ', $expectedDisabledFunctions).' must be disabled for this test to work. Make sure to call this test with the command described in the comment of this method. (hint: php -d disable_functions=fsockopen ...)');

        //make sure that at least one _essential_ function is disabled
        $foundAtLeastOneEssentialFunction = false;
        foreach ($actuallyDisabledFunctions as $functionName) {
            if (in_array($functionName, $expectedDisabledFunctions)) {
                $foundAtLeastOneEssentialFunction = true;
                break;
            }
        }
        $this->assertTrue($foundAtLeastOneEssentialFunction, 'At least one of the functions '.implode(', ', $expectedDisabledFunctions).' must be disabled for this test to work. Make sure to call this test with the command described in the comment of this method. (hint: php -d disable_functions=fsockopen ...)');

        //test requirements are all set. Now do the actual test. (We expect a \RuntimeException)
        new EmbeddedServerController('0.0.0.0', 1234, 'tests/web/');
    }
}
