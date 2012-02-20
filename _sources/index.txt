Welcome to the documentation for Erebot_Module_WatchList!
=========================================================

|project| is a module for `Erebot`_ that can be used to handle a WATCH list,
ie. track when some user (dis)connects.

The WATCH list is implemented using either the
``ISON`` command (see :rfc:`2812#section-4.9`) or the
``WATCH`` extension (see http://docs.dal.net/docs/misc.html#4).
Exactly which mechanism is used depends on what the IRC server supports.

Contents:

..  toctree::
    :maxdepth: 2

    Prerequisites
    generic/Installation
    Configuration
    Usage

Current status on http://travis-ci.org/:

..  image:: https://secure.travis-ci.org/fpoirotte/Erebot_Module_WatchList.png
    :alt: unknown
    :target: http://travis-ci.org/#!/fpoirotte/Erebot_Module_WatchList/


..  _`Erebot`:
    https://www.erebot.net/

.. vim: ts=4 et

