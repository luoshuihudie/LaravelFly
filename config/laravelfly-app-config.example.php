<?php

if (!defined('LARAVELFLY_MODE')) return [];

$IN_PRODUCTION = env('APP_ENV') === 'production' || env('APP_ENV') === 'product';

return [
    /**
     * If use cache file for config/laravel.php always.
     *
     * If true, Laravelfly will always use cache file laravelfly_ps_simple.php or
     * laravelfly_ps_map.php and laravelfly_aliases.php
     * under bootstrap/cache/ when the files exist. If not exist, Laravelfly will create them.
     *
     * It's better to set it to false in dev env , set true and run `php artisan config:clear` before starting LaravelFly in production env
     */
    // 'config_cache_always' => $IN_PRODUCTION,
    'config_cache_always' => true,

    /**
     * For each worker, if a view file is compiled max one time. Only For Mode Map
     *
     * If true, Laravel not know a view file changed until the swoole workers restart.
     * It's good for production env.
     */
    'view_compile_1' => LARAVELFLY_SERVICES['view.finder'] && $IN_PRODUCTION,

    /**
     * useless providers. For Mode Simple, Map
     *
     * There providers will be only removed from config('app.providers')
     * not from  config('laravelfly.providers_on_worker') or  config('laravelfly.providers_in_request')
     */
    'providers_ignore' => array_merge([

        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Laravel\Tinker\TinkerServiceProvider::class,
        Fideloper\Proxy\TrustedProxyServiceProvider::class,
        LaravelFly\Providers\CommandsServiceProvider::class,
        'Barryvdh\\LaravelIdeHelper\\IdeHelperServiceProvider',

    ], LARAVELFLY_SERVICES['broadcast'] ? [] : [
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Broadcasting\BroadcastManager::class,
        Illuminate\Contracts\Broadcasting\Broadcaster::class,
        App\Providers\BroadcastServiceProvider::class
    ]
    ),

    /**
     * Providers to reg and boot in each request.For Mode Simple, Map
     *
     * There providers will be removed from app('config')['app.providers'] on worker, before any requests
     */
    'providers_in_request' => [
    ],


    /**
     * Which properties of base services need to backup. Only for Mode Simple
     *
     * See: Illuminate\Foundation\Application::registerBaseServiceProviders
     */
    'BaseServices' => [

        \Illuminate\Contracts\Http\Kernel::class => [

            /** depends
             * put new not safe properties here
             */
            // 'newProp1', 'newProp2',

            /** depends
             * Uncomment it if it's not always same in multiple request. They may be changed by Route::middleware
             * No need worry about same middlewares are added multiple times,
             * because there's a check in Illuminate\Foundation\Http::pushMiddleware or prependMiddleware:
             *          if (array_search($middleware, $this->middleware) === false)
             */
            // 'middleware',

        ],
        /* Illuminate\Events\EventServiceProvider::class : */
        'events' => [
            'listeners', 'wildcards', 'queueResolver',
        ],

        /* Illuminate\Routing\RoutingServiceProvider::class : */
        'router' => [
            /** depends
             * Uncomment them if it's not same on each request. They may be changed by Route::middleware
             */
            // 'middleware','middlewareGroups','middlewarePriority',

            /** not necessary to backup,
             * it will be changed during next request
             * // 'current',
             */

            /** not necessary to backup,
             * the ref to app('request') will be released during next request
             * //'currentRequest',
             */

            /* Illuminate\Routing\RouteCollection */
            'obj.routes' => [
                /** depends
                 *
                 * Uncomment them if routes created during requests are different.
                 * Most cases, Service Providers add same routes, like DebugBar(Barryvdh\Debugbar\ServiceProvider),
                 * so it's not needed to comment them because they are of associate array.
                 */
                // 'routes' , 'allRoutes' , 'nameList' , 'actionList' ,
            ],
        ], /* end 'router' */

        'url' => [
            /* depends */
            // 'forcedRoot', 'forceSchema',
            // 'cachedRoot', 'cachedSchema',

            /** not necessary to backup,
             *
             * the ref to app('request') will be released during next request;
             * and no need set request for `url' on every request , because there is a $app->rebinding for request:
             *      $app->rebinding( 'request', $this->requestRebinder() )
             * //'request'
             */
        ],


        /** nothing need to backup
         *
         * // 'redirect' => false,
         * // 'routes' => false,
         * // 'log' => false,
         */
    ],

    /**
     * providers to reg and boot on worker, before any request. only for Mode Map
     *
     * format:
     *      proverder_name => [],
     *
     * you can also supply singleton services to made on worker
     * only singleton services are useful and valid here.
     * and the singleton services must not be changed during any request,
     * otherwise they should be made in request, no on worker.
     *
     * a singeton service is like this:
     *     *   $this->app->singleton('hash', function ($app) { ... });
     *
     * formats:
     *      proverder,                   // this provider will be booted on worker
     *      proverder2=> true,           // this provider will be booted on worker
     *      proverder1=> [],             // this provider will be booted on worker
     *      proverder3=> [
     *        '_replace' => 'provider1', // the provider1 will be replaced by provider2 and deleted from app['config']['app.providers']
     *        'singleton_service_1' => true,  //  service will be made on worker
     *        'singleton_service_2' => false, //  service will not be made on worker,
     *                                            even if the service has apply if using coroutineFriendlyServices()
     *      ],
     *
     *      proverder4=> false,           // this provider will not be booted on worker
     *      proverder5=> null,           // this provider will not be booted on worker too.
     *      proverder6=> 'across',           // this provider will not be booted on worker too.
     *      proverder7=> 'request',           // just like config('laravelfly.providers_in_request')
     *      proverder8=> 'ignore',           // just like config('laravelfly.providers_ignore')
     */
    'providers_on_worker' => [

        // this is not in config('app.providers') and registered in Application:;registerBaseServiceProviders
        Illuminate\Log\LogServiceProvider::class => [
            'log' => true,
        ],

        LaravelFly\Map\Illuminate\Auth\AuthServiceProvider::class => [
            '_replace' => Illuminate\Auth\AuthServiceProvider::class,
        ],

        Illuminate\Broadcasting\BroadcastServiceProvider::class =>
            LARAVELFLY_SERVICES['broadcast'] ? [
                Illuminate\Broadcasting\BroadcastManager::class,
                Illuminate\Contracts\Broadcasting\Broadcaster::class,
            ] : 'ignore',

        Illuminate\Bus\BusServiceProvider::class => [],

        Illuminate\Cache\CacheServiceProvider::class => [
            'cache' => true,
            'cache.store' => true,
            /* depends */
            // if memcached is used, enable it
            // 'memcached.connector' => true,

        ],

        /**
         * CookieJar's path, domain, secure and sameSite  are not rewriten to be a full COROUTINE-FRIENDLY SERVICE.
         * so this provider requires there values always be same in all requests.
         */
        LaravelFly\Map\Illuminate\Cookie\CookieServiceProvider::class => [
            '_replace' => Illuminate\Cookie\CookieServiceProvider::class,
        ],

        LaravelFly\Map\Illuminate\Database\DatabaseServiceProvider::class => [
            '_replace' => Illuminate\Database\DatabaseServiceProvider::class,
        ],

        Illuminate\Encryption\EncryptionServiceProvider::class => [
            'encrypter' => true,
        ],

        Illuminate\Filesystem\FilesystemServiceProvider::class => [
            'files' => true,
            'filesystem.disk' => true,
            'filesystem.cloud' => LARAVELFLY_SERVICES['filesystem.cloud'],
        ],

        /* This reg FormRequestServiceProvider, whose boot is related to request */
        Illuminate\Foundation\Providers\FoundationServiceProvider::class => 'across',

        Illuminate\Hashing\HashServiceProvider::class => [
            'hash' => LARAVELFLY_SERVICES['hash']
        ],

        Illuminate\Mail\MailServiceProvider::class => [],

        Illuminate\Notifications\NotificationServiceProvider::class => 'across',

        /**
         * some static props like currentPathResolver, ... in Illuminate\Pagination\AbstractPaginator
         * in most cases they keep same.
         * if not same, `use StaticDict` is needed to convert AbstractPaginator in Map Mode.
         */
        Illuminate\Pagination\PaginationServiceProvider::class => [],

        Illuminate\Pipeline\PipelineServiceProvider::class => [],

        Illuminate\Queue\QueueServiceProvider::class => [],

        Illuminate\Redis\RedisServiceProvider::class => [
            'redis' => LARAVELFLY_SERVICES['redis'],
        ],

        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class => [],

        LaravelFly\Map\Illuminate\Session\SessionServiceProvider::class => [
            '_replace' => Illuminate\Session\SessionServiceProvider::class,
        ],

        Illuminate\Translation\TranslationServiceProvider::class => [
            'translation.loader' => true,
            'translator' => true,
        ],

        Illuminate\Validation\ValidationServiceProvider::class => [
            'validator' => true,
            'validation.presence' => true,
        ],

        \LaravelFly\Map\Illuminate\View\ViewServiceProvider::class => [
            '_replace' => Illuminate\View\ViewServiceProvider::class,
        ],


        /*
         * Application Service Providers...
         */


        /* depends */
        /**
         * if it's register and boot need executing in each request, remove it to 'providers_in_request'
         * if it's boot can execute on worker (before any requests), change false to true or [].
         */
        App\Providers\AppServiceProvider::class => 'across',

        /* depends */
        /**
         * if some executions always same in each request,
         * suggest to create a new AppServiceProvider whoes reg and boot are both executed on worker.
         */
        // App\Providers\WorkerAppServiceProvider::class => [],

        /* depends */
        /**
         * its boot relates Illuminate\Auth\Access\Gate
         * which would better not to be made on worker
         * because it has props like afterCallbacks relating memory leak
         */
        App\Providers\AuthServiceProvider::class => 'across',

        App\Providers\BroadcastServiceProvider::class => LARAVELFLY_SERVICES['broadcast'] ? [] : 'ignore',

        /* depends */
        App\Providers\EventServiceProvider::class => [],

        /* depends */
        App\Providers\RouteServiceProvider::class => [],


        /*
         * Third Party
         */

        // Collision is an error handler framework for console/command-line PHP applications such as laravelfly
        NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider::class => [
            Illuminate\Contracts\Debug\ExceptionHandler::class => true,
        ],

    ],

];

