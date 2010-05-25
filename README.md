README
======

This is a Mongrel/WEBrick inspired HTTP webserver for Symfony 2.

The goal is to implement the cool and just missing features that Mongrel/WEBrick
do provide for RoR developers. If you're interested in making this Bundle usable
for production, feel free to fork and improve it - I'd appreciate pull requests
and helping hands.

Right now it can deliver Symfony requests, static files, directory listings and
errors (404, 500). Expect to get more features continuously as the hardest work
is almost done.

> **BEWARE:** Everything is **HIGHLY EXPERIMENTAL** - things **WILL** change!


REQUIREMENTS
------------

 * PHP 5.3.0 and up
 * ext/[pecl_http][1]
 * [Symfony 2][2]


INSTALLATION
------------

Clone the ServerBundle git repository in the `src/Bundle` directory of your
Symfony project:

    [bash]
    $> cd src/Bundle
    $> git clone git://github.com/pminnieur/ServerBundle.git


Open up the applications kernel file in your application folder and add the
necessary ServerBundle line to the `registerBundles()` function:

    [php]
    // hello/HelloKernel.php
    public function registerBundles()
    {
      return array(
        // core bundles and your application bundles are lined up here
        new Bundle\ServerBundle\Bundle(),
      );
    }


Finally we need to enable the Server and Daemon service in our applications
`config.yml` for the dependency injection container to create them:

    [yaml]
    # hello/config/config.yml
    server.daemon: ~
    server.server: ~

> **NOTE:** You can leave `server.daemon` out if you do not plan to run the
> server in background / daemon mode.


CONFIGURATION
-------------

If you want to change the port, change it in your applications configuration:

    [yaml]
    # hello/config/config.yml
    server.server:
      port: 80


> **NOTE:** All configuration options available will be made available in the
> `README` as soon as possible.


### Server Info and Status

If you want to use the Apache like `/server-info` and `/server-status` pages,
simply include the `routing.yml` file in your applications routing configuration.

    [yaml]
    # hello/config/routing.yml
    server:
      resource: ServerBundle/Resources/config/routing.yml


Now point your browser to `localhost/server-info` or `localhost/server-info`. 


USAGE
-----

### Start

Start your server via the console:

    [bash]
    $> sudo php hello/console server:start


The default configuration of ServerBundle starts a server listening on `*:1962`
- I have chosen port `1962` because it is the alphabetic representation
(latin alphabet) of the Symfony shortcut `sf2` (s:19 f:6 2) :)

Now, just point your browser to `localhost:1962`, or try to load another
controller, e.g. `http://localhost:1962/hello/Pierre`.


### Start in background (daemon)
You can detach the process from the console to run in background. Just append
the `-d` option to the `server:start` command and the process will be detached
from the console and runs in background:

    [bash]
    $> sudo php hello/console server:start -d


### Stop

You can stop your server with the following command (in daemon mode only):

    [bash]
    $> sudo php hello/console server:stop


### Restart

You can restart your server with the following command (in daemon mode only):

    [bash]
    $> sudo php hello/console server:restart


### Commands

Available (and self explaining) console commands are:

    [bash]
    $> sudo php hello/console server:start
    $> sudo php hello/console server:stop
    $> sudo php hello/console server:restart
    $> sudo php hello/console server:reload
    $> sudo php hello/console server:status


> **BEWARE:** The `server:reload` and `server:status` commands are not yet
> implement.

> **NOTE:** I'd appreciate if you use the `--help` to find out more about the
> concrete usage of each command.


LICENSE
-------

For the full copyright and license information, please view the `LICENSE` file
that was distributed with this source code.


[1]: http://pecl.php.net/package/pecl_http
[2]: http://symfony-reloaded.org/
