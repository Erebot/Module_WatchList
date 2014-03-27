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

namespace Erebot\Module;

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
class WatchList extends \Erebot\Module\Base implements \Erebot\Interfaces\HelpEnabled
{
    /// List of nicknames currently being followed by this module.
    protected $watchedNicks;

    /// A timer used to emulate the WATCH command using the ISON command.
    protected $timer;

    /// Number of nicks sent in an ISON command and avaiting a response.
    protected $pending;

    /**
     * This method is called whenever the module is (re)loaded.
     *
     * \param int $flags
     *      A bitwise OR of the Erebot::Module::Base::RELOAD_*
     *      constants. Your method should take proper actions
     *      depending on the value of those flags.
     *
     * \note
     *      See the documentation on individual RELOAD_*
     *      constants for a list of possible values.
     */
    public function reload($flags)
    {
        if ($flags & self::RELOAD_HANDLERS) {
            $handler = new \Erebot\EventHandler(
                \Erebot\CallableWrapper::wrap(array($this, 'handleConnect')),
                new \Erebot\Event\Match\Type(
                    '\\Erebot\\Interfaces\\Event\\Connect'
                )
            );
            $this->connection->addEventHandler($handler);

            $handler = new \Erebot\EventHandler(
                \Erebot\CallableWrapper::wrap(array($this, 'handleCapabilities')),
                new \Erebot\Event\Match\Type(
                    '\\Erebot\\Interfaces\\Event\\ServerCapabilities'
                )
            );
            $this->connection->addEventHandler($handler);

            $handler = new \Erebot\NumericHandler(
                \Erebot\CallableWrapper::wrap(array($this, 'handleISON')),
                $this->getNumRef('RPL_ISON')
            );
            $this->connection->addNumericHandler($handler);
        }

        if ($flags & self::RELOAD_MEMBERS) {
            $watchedNicks = $this->parseString('nicks', '');
            $watchedNicks = str_replace(',', ' ', $watchedNicks);
            $watchedNicks = array_filter(
                array_map('trim', explode(' ', $watchedNicks))
            );
            if (!count($watchedNicks)) {
                $this->watchedNicks = array();
            } else {
                $this->watchedNicks = array_combine(
                    $watchedNicks,
                    array_fill(0, count($watchedNicks), false)
                );
            }

            if ($flags & self::RELOAD_INIT) {
                $this->pending = 0;
                $timerCls = $this->getFactory('!Timer');
                $this->timer = new $timerCls(
                    \Erebot\CallableWrapper::wrap(array($this, 'sendRequest')),
                    $this->parseInt('poll_delay', 15),
                    true
                );
            }
        }
    }

    /**
     * Provides help about this module.
     *
     * \param Erebot::Interfaces::Event::Base::TextMessage $event
     *      Some help request.
     *
     * \param Erebot::Interfaces::TextWrapper $words
     *      Parameters passed with the request. This is the same
     *      as this module's name when help is requested on the
     *      module itself (in opposition with help on a specific
     *      command provided by the module).
     */
    public function getHelp(
        \Erebot\Interfaces\Event\Base\TextMessage $event,
        \Erebot\Interfaces\TextWrapper $words
    ) {
        if ($event instanceof \Erebot\Interfaces\Event\Base\PrivateMessage) {
            $target = $event->getSource();
            $chan   = null;
        } else {
            $target = $chan = $event->getChan();
        }

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
            return true;
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
    protected function splitNicks()
    {
        $nicks = array_keys($this->watchedNicks);
        return explode("\n", wordwrap(implode(' ', $nicks), 400));
    }

    /**
     * Upon connection, requests that the server send notifications
     * for (dis)connection related to IRC nicknames this modules is
     * currently following.
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::ServerCapabilities $event
     *      Event containing information
     *      on server capabilities.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleCapabilities(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\ServerCapabilities $event
    ) {
        $module = $event->getModule();
        if ($module->hasCommand('WATCH')) {
            $this->timer = null;
        }
    }

    /**
     * Upon connection, requests that the server send notifications
     * for (dis)connection related to IRC nicknames this modules is
     * currently following.
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Connect $event
     *      Connection event.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleConnect(
        \Erebot\Interfaces\EventHandler $handler,
        \Erebot\Interfaces\Event\Connect $event
    ) {
        if (!count($this->watchedNicks)) {
            return;
        }

        $collator = $this->connection->getCollator();
        $watchedNicks = array_map(
            array($collator, 'normalizeNick'),
            array_keys($this->watchedNicks)
        );
        $this->watchedNicks = array_combine(
            $watchedNicks,
            array_fill(0, count($watchedNicks), false)
        );

        if ($this->timer !== null) {
            $this->addTimer($this->timer);
            $this->sendRequest($this->timer);
            return;
        }

        foreach ($this->splitNicks() as $nicksRow) {
            $this->sendCommand('WATCH +'.str_replace(' ', ' +', $nicksRow));
        }
    }

    /**
     * Handle the response to an ISON command.
     *
     * \param Erebot::Interfaces::EventHandler $handler
     *      Handler that triggered this event.
     *
     * \param Erebot::Interfaces::Event::Numeric $numeric
     *      ISON response.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleISON(
        \Erebot\Interfaces\NumericHandler $handler,
        \Erebot\Interfaces\Event\Numeric $numeric
    ) {
        if (!$this->pending) {
            return;
        }

        $nicksRows  = $this->splitNicks();
        $index      = count($nicksRows) - $this->pending;
        $nicksRow   = explode(' ', $nicksRows[$index]);
        $this->pending--;
        $present = array();

        $eventsProducer = $this->connection->getEventsProducer();
        if ((string) $numeric->getText() != '') {
            $collator = $this->connection->getCollator();
            foreach ($numeric->getText() as $nick) {
                if (substr($nick, 0, 1) == ':') {
                    $nick = (string) substr($nick, 1);
                }
                $normalized = $collator->normalizeNick($nick);
                $present[]  = $normalized;

                // That user WAS NOT connected the last time
                // we polled the server. Flag him as signing on.
                if (!$this->watchedNicks[$normalized]) {
                    $this->watchedNicks[$normalized] = true;
                    $event = $eventsProducer->makeEvent(
                        '!Notify',
                        $nick,
                        null,
                        null,
                        new \DateTime(),
                        ''
                    );
                    $this->connection->dispatch($event);
                }
            }
        }

        $absent = array_diff($nicksRow, $present);
        foreach ($absent as $normalized) {
            // That user WAS connected the last time we polled
            // the server. Flag him as signing out.
            if ($this->watchedNicks[$normalized]) {
                $this->watchedNicks[$normalized] = false;
                $event = $eventsProducer->makeEvent(
                    '!UnNotify',
                    $normalized,
                    null,
                    null,
                    new \DateTime(),
                    ''
                );
                $this->connection->dispatch($event);
            }
        }
    }

    /**
     * Send ISON requests to see whether users
     * in the WATCH list recently logged on/off.
     *
     * \param Erebot::TimerInterface $timer
     *      The (periodic) timer that triggered the request.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function sendRequest(\Erebot\TimerInterface $timer)
    {
        if ($this->pending) {
            return;
        }

        $nicksRows = $this->splitNicks();
        $this->pending = count($nicksRows);
        foreach ($nicksRows as $nicksRow) {
            $this->sendCommand('ISON '.$nicksRow);
        }
    }
}
