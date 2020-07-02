Eliksir arcanist plugins
========================

This contains custom arcanist plugins, for linting and unit tests.

For linting we implement a SASS/SCSS linter. There used to be a task to
implement support for this in core arcanist, but that has been abandoned. See
https://secure.phabricator.com/D10741

For unit tests we wrap the pytest engine to support running through Docker.
