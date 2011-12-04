<?php
/*
    This file is part of Erebot.

    Erebot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Erebot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Erebot.  If not, see <http://www.gnu.org/licenses/>.
*/

class   WatchListTestHelper
extends Erebot_Module_WatchList
{
    public function setWatchedNicks($nicks)
    {
        $finalNicks = array();
        foreach ($nicks as $nick)
            $finalNicks[strtoupper($nick)] = FALSE;
        $this->_watchedNicks = $finalNicks;
    }

    public function simulateTimer($timer)
    {
        $this->_timer = $timer;
    }
}

class   ServerCapsTestHelper
extends Erebot_Module_Base
{
    protected function _reload($flags)
    {
    }

    protected function _unload()
    {
    }

    public function hasCommand($cmd)
    {
        throw new RuntimeException('Not implemented');
    }
}

class   WatchListTest
extends Erebot_Testenv_Module_TestCase
{
    public function setUp()
    {
        $this->_module = new WatchListTestHelper(NULL);
        parent::setUp();
        $this->_module->reload($this->_connection, 0);
    }

    public function tearDown()
    {
        $this->_module->unload();
        parent::tearDown();
    }

    public function testUsingWATCH()
    {
        $this->_module->setWatchedNicks(array('foo', 'bar', 'baz'));

        // Mock a server that supports the WATCH commands.
        $caps = $this->getMock(
            'ServerCapsTestHelper',
            array(),
            array(NULL),
            '',
            FALSE,
            FALSE
        );

        $caps
            ->expects($this->any())
            ->method('hasCommand')
            ->will($this->returnValue(TRUE));

        $event = $this->getMock(
            'Erebot_Interface_Event_ServerCapabilities',
            array(), array(), '', FALSE, FALSE
        );
        $event
            ->expects($this->any())
            ->method('getConnection')
            ->will($this->returnValue($this->_connection));
        $event
            ->expects($this->any())
            ->method('getModule')
            ->will($this->returnValue($caps));
        $this->_module->handleCapabilities($this->_eventHandler, $event);

        $event = $this->getMock(
            'Erebot_Interface_Event_Connect',
            array(), array(), '', FALSE, FALSE
        );
        $event
            ->expects($this->any())
            ->method('getConnection')
            ->will($this->returnValue($this->_connection));

        $this->_module->handleConnect($this->_eventHandler, $event);
        $this->assertEquals(1, count($this->_outputBuffer));
        $this->assertEquals(
            "WATCH +FOO +BAR +BAZ",
            $this->_outputBuffer[0]
        );
    }

    public function testUsingISON()
    {
        $this->_module->setWatchedNicks(array('foo', 'bar', 'baz'));
        // Simulate a network where the WATCH command isn't supported and
        // where a timer was launched to poll the presence of those nicks.
        $timer = $this->getMock(
            'Erebot_Interface_Timer',
            array(), array(), '', FALSE, FALSE
        );
        $this->_module->simulateTimer($timer);

        $event = $this->getMock(
            'Erebot_Interface_Event_Connect',
            array(), array(), '', FALSE, FALSE
        );
        $event
            ->expects($this->any())
            ->method('getConnection')
            ->will($this->returnValue($this->_connection));

        $this->_module->handleConnect($this->_eventHandler, $event);
        $this->assertEquals(1, count($this->_outputBuffer));
        $this->assertEquals(
            "ISON FOO BAR BAZ",
            $this->_outputBuffer[0]
        );
    }
}
