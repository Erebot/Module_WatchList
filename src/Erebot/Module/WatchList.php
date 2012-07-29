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

    /// A timer used to emulate the WATCH command using the ISON command.
    protected $_timer;

    /// Number of nicks sent in an ISON command and avaiting a response.
    protected $_pending;

    /**
     * This method is called whenever the module is (re)loaded.
     *
     * \param int $flags
     *      A bitwise OR of the Erebot_Module_Base::RELOAD_*
     *      constants. Your method should take proper actions
     *      depending on the value of those flags.
     *
     * \note
     *      See the documentation on individual RELOAD_*
     *      constants for a list of possible values.
     */
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

            $handler = new Erebot_NumericHandler(
                new Erebot_Callable(array($this, 'handleISON')),
                $this->getNumRef('RPL_ISON')
            );
            $this->_connection->addNumericHandler($handler);

            $cls = $this->getFactory('!Callable');
            $this->registerHelpMethod(new $cls(array($this, 'getHelp')));
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
                $timerCls = $this->getFactory('!Timer');
                $this->_timer = new $timerCls(
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

    /**
     * Provides help about this module.
     *
     * \param Erebot_Interface_Event_Base_TextMessage $event
     *      Some help request.
     *
     * \param Erebot_Interface_TextWrapper $words
     *      Parameters passed with the request. This is the same
     *      as this module's name when help is requested on the
     *      module itself (in opposition with help on a specific
     *      command provided by the module).
     */
    public function getHelp(
        Erebot_Interface_Event_Base_TextMessage $event,
        Erebot_Interface_TextWrapper            $words
    )
    {
        if ($event instanceof Erebot_Interface_Event_Base_Private) {
            $target = $event->getSource();
            $chan   = NULL;
        }
        else
            $target = $chan = $event->getChan();

        $fmt        = $this->getFormatter($chan);
        $moduleName = strtolower(get_class());
        $nbArgs     = count($words);

        if ($nbArgs == 1 && $words[0] == $moduleName) {
            $msg = $fmt->_(
                "This module does not provide any command but can be used ".
                "to get notifications whenever a given user signs on/off an ".
                "IRC network."
            );
            $this->sendMessage($target, $msg);
            return TRUE;
        }
    }

    /**
     * Return a list of strings made of the nicknames
     * being tracked by this watch list.
     *
     * Each string is built so that it can be sent
     * to the IRC server without being truncated.
     *
     * \retval array
     *      List of strings containing tracked nicknames,
     *      separated by spaces.
     */
    protected function _splitNicks()
    {
        $nicks = array_keys($this->_watchedNicks);
        return explode("\n", wordwrap(implode(' ', $nicks), 400));
    }

    /**
     * Upon connection, requests that the server send notifications
     * for (dis)connection related to IRC nicknames this modules is
     * currently following.
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_ServerCapabilities $event
     *      Event containing information
     *      on server capabilities.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
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
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Connect $event
     *      Connection event.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleConnect(
        Erebot_Interface_EventHandler   $handler,
        Erebot_Interface_Event_Connect  $event
    )
    {
        if (!count($this->_watchedNicks))
            return;

        $collator = $this->_connection->getCollator();
        $watchedNicks = array_map(
            array($collator, 'normalizeNick'),
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

    /**
     * Handle the response to an ISON command.
     *
     * \param Erebot_Interface_EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot_Interface_Event_Numeric $numeric
     *      ISON response.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleISON(
        Erebot_Interface_NumericHandler $handler,
        Erebot_Interface_Event_Numeric  $numeric
    )
    {
        if (!$this->_pending)
            return;

        $nicksRows  = $this->_splitNicks();
        $index      = count($nicksRows) - $this->_pending;
        $nicksRow   = explode(' ', $nicksRows[$index]);
        $this->_pending--;
        $present = array();

        $eventsProducer = $this->_connection->getEventsProducer();
        if ((string) $numeric->getText() != '') {
            $collator = $this->_connection->getCollator();
            foreach ($numeric->getText() as $nick) {
                if (substr($nick, 0, 1) == ':')
                    $nick = (string) substr($nick, 1);
                $normalized = $collator->normalizeNick($nick);
                $present[]  = $normalized;

                // That user WAS NOT connected the last time
                // we polled the server. Flag him as logging in.
                if (!$this->_watchedNicks[$normalized]) {
                    $this->_watchedNicks[$normalized] = TRUE;
                    $event = $eventsProducer->makeEvent(
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
                $event = $eventsProducer->makeEvent(
                    '!UnNotify',
                    $normalized, NULL, NULL, new DateTime(), ''
                );
                $this->_connection->dispatch($event);
            }
        }
    }

    /**
     * Send ISON requests to see whether users
     * in the WATCH list recently logged on/off.
     *
     * \param Erebot_Interface_Timer $timer
     *      The (periodic) timer that triggered the request.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
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

