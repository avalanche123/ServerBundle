TODO
====

 * keep in mind that this is a HTTP Webserver and nothing more and nothing less
 * check APC, Memcached, XCache, ZendPlatform & ZendServer usage possibilities
 * check PHP SPL features for various circumstances (Queues, Heaps, etc...)
 * write PHPUnit tests


pecl_http
---------
 * make pecl_http optional and re-implement HttpMessage functionality
 * create a Request and Response class around HttpMessage
 * move Request/Response creation from Handlers/Filters/Socket to HttpServer


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


Database
--------
 * check if and how background process, forking etc ... affects
   database connections (e.g. Doctrine) -- I think they'll lose
   connection after forking or during the long lifetime of such
   a background process (daemon). Maybe we must re-initialise
   those bundles, that's why I was thinking about booting and
   shutting down the kernel after every request. A more elegant
   solution may be rebooting only database-using bundles.


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
 * separate dispatching into its own classes (symfony, static file, error) (work in progress, almost finished)
 * improve dispatching logic to be more granular and user friendly


Socket
------
 * improve API
 * check class functionality and usage
 * check stream_socket_* usage


Symfony
-------
 * start Symfony in provided environment (in child processes / threads) (work in progress, almost finished)
 * improve $_SERVER emulation (work in progress)
 * implement $_GET, $_POST, $_COOKIE and $_FILES emulation
 * check if $_REQUEST, $_SESSION and $_ENV superglobals must be "emulated"
 * improve Request, Kernel and Response usage (and the rest of Symfony, too) (work in progress)
 * add more headers
