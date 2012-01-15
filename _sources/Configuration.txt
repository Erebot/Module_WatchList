Configuration
=============

.. _`configuration options`:

Options
-------

This module provides only one configuration option.

..  table:: Options for |project|

    +---------------+--------+---------------+------------------------------+
    | Name          | Type   | Default value | Description                  |
    +===============+========+===============+==============================+
    | nicks         | string | ""            | A space-separated list of    |
    |               |        |               | nicknames for which the bot  |
    |               |        |               | will receive notifications.  |
    +---------------+--------+---------------+------------------------------+


Example
-------

The recommended way to use this module is to have it loaded at the general
configuration level and to disable it only for specific networks, if needed.

..  parsed-code:: xml

    <?xml version="1.0"?>
    <configuration
      xmlns="http://localhost/Erebot/"
      version="0.20"
      language="fr-FR"
      timezone="Europe/Paris">

      <modules>
        <!-- Other modules ignored for clarity. -->

        <!--
            The bot will receive notifications whenever "Foo" or "Bar"
            joins/quits the IRC server.
        -->
        <module name="Erebot_Module_WatchList">
          <param name="nicks" value="Foo Bar"/>
        </module>
      </modules>
    </configuration>


.. vim: ts=4 et
