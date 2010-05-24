TODO
====

 * keep in mind that this is a HTTP Webserver and nothing more and nothing less
 * check APC, Memcached, XCache, ZendPlatform & ZendServer usage possibilities
 * check PHP SPL features for various circumstances (Queues, Heaps, etc...)
 * write PHPUnit tests


Command
-------
 * implement mongrel_rails like options (work in progress)
 * implement mongrel_rails like runtime configuration
 * implement process daemonizing (work in progress, almost finished)


Controller
----------
 * add security check (localhost only)
 * gather and display server infos
 * gather and display server status


Exception
---------
 * improve error handling in general
 * implement own ErrorException handler
 * make better use of different SplExceptions


Logging
-------
 * implement access.log capabilities
 * implement error.log capabilities
 * implement STDOUT logging (if process is not daemonized)
 * add server status/info to WebDebugToolbar


Server
------
 * move socket logic to ServerBundle\Socket (work in progress, almost finished)
 * add interprocess communication (IPC)
 * add process forking (spawn children) and management (work in progress)
 * check multi-threading in child processes
 * separate dispatching into its own classes (symfony, static file, error) (work in progress)
 * improve dispatching logic to be more granular and user friendly


Symfony
-------
 * start Symfony in provided environment (in child processes / threads)
 * improve $_SERVER emulation
 * implement $_GET, $_POST, $_COOKIE and $_FILES emulation
 * check if $_REQUEST, $_SESSION and $_ENV superglobals must be "emulated"
 * improve Request, Kernel and Response usage (and the rest of Symfony, too) ;)
 * add more headers
