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


if (!defined('__DIR__')) {
  class __FILE_CLASS__ {
    function  __toString() {
      $X = debug_backtrace();
      return dirname($X[1]['file']);
    }
  }
  define('__DIR__', new __FILE_CLASS__);
} 

include_once(__DIR__.'/../../../../autoloader.php');
include_once(__DIR__.'/testenv/bootstrap.php');

class   WatchListTestHelper
extends Erebot_Module_WatchList
{
    public function setWatchedNicks($nicks)
    {
        $this->_watchedNicks = $nicks;
    }
}

class   WatchListTest
extends ErebotModuleTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->_module = new WatchListTestHelper(
            $this->_connection,
            NULL
        );
        $this->_module->reload(Erebot_Module_Base::RELOAD_MEMBERS);
    }

    public function tearDown()
    {
        parent::tearDown();
        unset($this->_module);
    }

    public function testWatchNicks()
    {
        $this->_module->setWatchedNicks(array('foo', 'bar', 'baz'));
        $event = new Erebot_Event_Connect($this->_connection);
        $this->_module->handleConnect($event);
        $this->assertEquals(1, count($this->_outputBuffer));
        $this->assertEquals(
            "WATCH +foo +bar +baz",
            $this->_outputBuffer[0]
        );
    }
}
