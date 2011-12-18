Welcome to the documentation for Erebot_Module_WatchList!
=========================================================

Erebot_Module_WatchList is a module for `Erebot`_ that can be used to handle
a WATCH list, ie. track when some user (dis)connects.

The WATCH list is implemented using either the
``ISON`` command (see :rfc:`2812#section-4.9`) or the
``WATCH`` (see http://docs.dal.net/docs/misc.html#4) extension.
Exactly which mechanism is used depends on what the IRC server supports.

Contents:

..  toctree::
    :maxdepth: 2

    generic/Installation


..  _`Erebot`:
    https://www.erebot.net/

.. vim: ts=4 et
