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

/**
 * \brief
 *      A module that can be used to manage the bot's IRC WATCH list.
 *
 * This module adds support for the WATCH/ISON commands to Erebot.
 * It can be used to monitor (dis)connections of some IRC users.
 *
 * \attention
 *      The WATCH command is an extension to the official IRC protocol
 *      and is supported only by some IRC networks.
 *      Most IRC servers that implement it have strict restrictions
 *      on the number of users someone may track using this command.
 *      The ISON command however is safe (and is part of the official
 *      IRC protocol).
 *      If you plan to track a lot of users, don't use this module.
 *
 * \note
 *      In the future, this module may use the most appropriate
 *      command to monitor (dis)connections, depending on the
 *      number of nicks it has to track.
 */
class   Erebot_Module_WatchList
extends Erebot_Module_Base
{
    /// List of nicknames currently being followed by this module.
    protected $_watchedNicks;

    /// \copydoc Erebot_Module_Base::_reload()
    public function _reload($flags)
    {
        if ($flags & self::RELOAD_HANDLERS) {
            $handler    =   new Erebot_EventHandler(
                array($this, 'handleConnect'),
                new Erebot_Event_Match_InstanceOf('Erebot_Interface_Event_Connect')
            );
            $this->_connection->addEventHandler($handler);
        }

        if ($flags & self::RELOAD_MEMBERS) {
            $watchedNicks = $this->parseString('nicks', '');
            $watchedNicks = str_replace(',', ' ', $watchedNicks);
            $this->_watchedNicks = array_filter(array_map('trim',
                                    explode(' ', $watchedNicks)));
        }
    }

    /// \copydoc Erebot_Module_Base::_unload()
    protected function _unload()
    {
    }

    /**
     * Upon connection, requests that the server send notifications
     * for (dis)connection related to IRC nicknames this modules is
     * currently following.
     *
     * \param Erebot_Interface_Event_Connect $event
     *      Connection event.
     */
    public function handleConnect(Erebot_Interface_Event_Connect $event)
    {
        if (!count($this->_watchedNicks))
            return;

        $this->sendCommand('WATCH +'.implode(' +', $this->_watchedNicks));
    }
}

