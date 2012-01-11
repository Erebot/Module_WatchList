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

    protected $_timer;

    protected $_pending;

    /// \copydoc Erebot_Module_Base::_reload()
    public function _reload($flags)
    {
        if ($flags & self::RELOAD_HANDLERS) {
            $handler = new Erebot_EventHandler(
                new Erebot_Callable(array($this, 'handleConnect')),
                new Erebot_Event_Match_InstanceOf(
                    'Erebot_Interface_Event_Connect'
                )
            );
            $this->_connection->addEventHandler($handler);

            $handler = new Erebot_EventHandler(
                new Erebot_Callable(array($this, 'handleCapabilities')),
                new Erebot_Event_Match_InstanceOf(
                    'Erebot_Interface_Event_ServerCapabilities'
                )
            );
            $this->_connection->addEventHandler($handler);

            $handler = new Erebot_RawHandler(
                new Erebot_Callable(array($this, 'handleISON')),
                $this->getRawRef('RPL_ISON')
            );
            $this->_connection->addRawHandler($handler);
        }

        if ($flags & self::RELOAD_MEMBERS) {
            $watchedNicks = $this->parseString('nicks', '');
            $watchedNicks = str_replace(',', ' ', $watchedNicks);
            $watchedNicks = array_filter(
                array_map('trim', explode(' ', $watchedNicks))
            );
            if (!count($watchedNicks))
                $this->_watchedNicks = array();
            else
                $this->_watchedNicks = array_combine(
                    $watchedNicks,
                    array_fill(0, count($watchedNicks), FALSE)
                );

            if ($flags & self::RELOAD_INIT) {
                $this->_pending = 0;
                $this->_timer = new Erebot_Timer(
                    new Erebot_Callable(array($this, '_sendRequest')),
                    $this->parseInt('poll_delay', 15),
                    TRUE
                );
            }
        }
    }

    /// \copydoc Erebot_Module_Base::_unload()
    protected function _unload()
    {
    }

    protected function _splitNicks()
    {
        $nicks = array_keys($this->_watchedNicks);
        return explode("\n", wordwrap(implode(' ', $nicks), 400));
    }

    public function handleCapabilities(
        Erebot_Interface_EventHandler               $handler,
        Erebot_Interface_Event_ServerCapabilities   $event
    )
    {
        $module = $event->getModule();
        if ($module->hasCommand('WATCH'))
            $this->_timer = NULL;
    }

    /**
     * Upon connection, requests that the server send notifications
     * for (dis)connection related to IRC nicknames this modules is
     * currently following.
     *
     * \param Erebot_Interface_Event_Connect $event
     *      Connection event.
     */
    public function handleConnect(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_Connect  $event
    )
    {
        if (!count($this->_watchedNicks))
            return;

        $watchedNicks = array_map(
            array($this->_connection, 'normalizeNick'),
            array_keys($this->_watchedNicks)
        );
        $this->_watchedNicks = array_combine(
            $watchedNicks,
            array_fill(0, count($watchedNicks), FALSE)
        );

        if ($this->_timer !== NULL) {
            $this->addTimer($this->_timer);
            $this->_sendRequest($this->_timer);
            return;
        }

        foreach ($this->_splitNicks() as $nicksRow)
            $this->sendCommand('WATCH +'.str_replace(' ', ' +', $nicksRow));
    }

    public function handleISON(
        Erebot_Interface_RawHandler $handler,
        Erebot_Interface_Event_Raw  $raw
    )
    {
        if (!$this->_pending)
            return;

        $nicksRows  = $this->_splitNicks();
        $index      = count($nicksRows) - $this->_pending;
        $nicksRow   = explode(' ', $nicksRows[$index]);
        $this->_pending--;
        $present = array();

        if ((string) $raw->getText() != '') {
            foreach ($raw->getText() as $nick) {
                $normalized = $this->_connection->normalizeNick($nick);
                $present[]  = $normalized;

                // That user WAS NOT connected the last time
                // we polled the server. Flag him as logging in.
                if (!$this->_watchedNicks[$normalized]) {
                    $this->_watchedNicks[$normalized] = TRUE;
                    $event = $this->_connection->makeEvent(
                        '!Notify',
                        $nick, NULL, NULL, new DateTime(), ''
                    );
                    $this->_connection->dispatch($event);
                }
            }
        }

        $absent = array_diff($nicksRow, $present);
        foreach ($absent as $normalized) {
            // That user WAS connected the last time we polled
            // the server. Flag him as signing out.
            if ($this->_watchedNicks[$normalized]) {
                $this->_watchedNicks[$normalized] = FALSE;
                $event = $this->_connection->makeEvent(
                    '!UnNotify',
                    $normalized, NULL, NULL, new DateTime(), ''
                );
                $this->_connection->dispatch($event);
            }
        }
    }

    public function _sendRequest(Erebot_Interface_Timer $timer)
    {
        if ($this->_pending)
            return;

        $nicksRows = $this->_splitNicks();
        $this->_pending = count($nicksRows);
        foreach ($nicksRows as $nicksRow)
            $this->sendCommand('ISON '.$nicksRow);
    }
}

