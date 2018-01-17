<?php

namespace LaravelFly\Coroutine;

use LaravelFly\Coroutine\Illuminate\EventServiceProvider;
use LaravelFly\Coroutine\Illuminate\RoutingServiceProvider;
use Illuminate\Log\LogServiceProvider;

use Illuminate\Filesystem\Filesystem;
use LaravelFly\One\ProviderRepository;
use Illuminate\Contracts\Container\Container as ContainerContract;

class Application extends \LaravelFly\Application
{

    /**
     * @var bool
     */
    protected $bootedOnWorker = false;

    /**
     * @var bool
     */
    protected $bootedInRequest = false;

    /**
     * @var array
     */
    protected $providersToBootOnWorker=[];

    /**
     * @var array
     */
    protected $acrossServiceProviders = [];

    /**
     * The id of coroutine which this instance is in
     *
     * @var int
     */
    protected $coid;

    /**
     * if this application instance is a worker app or a request app.
     *
     * the worker app is always $appInstance->instance or Container::$instance
     *
     * @var bool
     */
    protected $isRequestApp;

    public function __construct($basePath = null)
    {
        parent::__construct($basePath);
        $this->isRequestApp = false;
        $this->coid = \Swoole\Coroutine::getuid();
        static::$instance = $this;
    }

    /*
     * Override
     * use new providers for
     * 1. new services with __clone
     * 2. compiled all routes which are made before request
     */
    protected function registerBaseServiceProviders()
    {
        $this->register(new EventServiceProvider($this));

        $this->register(new LogServiceProvider($this));

        $this->register(new RoutingServiceProvider($this));
    }

    function __clone()
    {
        $this->isRequestApp = true;
        $this->coid = \Swoole\Coroutine::getuid();

        /**
         * following is implementing part of  parent __construct
         */

        // replace $this->registerBaseBindings();
        static::setInstance($this);
        $this->instance('app', $this);
        $this->instance(Container::class, $this);
        //todo:
//        $this->instance(PackageManifest::class, new PackageManifest(
//            new Filesystem, $this->basePath(), $this->getCachedPackagesPath()
//        ));

        /**
         * replace $this->register(new EventServiceProvider($this));
         */
        $this->instance('events', clone $this->make('events'));
        /**
         * replace $this->register(new RoutingServiceProvider($this));
         *
         * in most cituations, routes clone is not needed, but it's possbile that
         * in a request a service may add more routes.
         * If so , the array content of routes vars will grow and grow.
         *
         * order is important, because dependencies:
         *  router : events routes
         *  url : routes
         */
        $this->instance('routes', clone $this->make('routes'));
        $this->instance('router', clone $this->make('router'));
        $this->instance('url', clone $this->make('url'));
    }

    static function delRequestApplication($coroutineID)
    {
        unset(static::$self_instances[$coroutineID]);
    }

    public function setProvidersToBootOnWorker($providers)
    {
        $this->providersToBootOnWorker = $providers;
    }

    public function registerAcrossProviders()
    {
        $config = $this->make('config');
        $providers = array_diff(
        // providers in request have remove from 'app.providers' by CleanProviders
            $config->get('app.providers'),
            $this->providersToBootOnWorker
        );

        $serviceProvidersBack = $this->serviceProviders;
        $this->serviceProviders = [];

        if ($providers) {
            if ($config->get('app.debug')) {
                echo PHP_EOL, 'start to reg Providers across', PHP_EOL, __CLASS__, PHP_EOL;
                var_dump($providers);
            }

            //todo update code
            (new ProviderRepository($this, new Filesystem, $this->getCachedServicesPathAcross()))
                ->load($providers);

        }

        $this->acrossServiceProviders = $this->serviceProviders;
        //todo merge? nest?
        $this->serviceProviders = $serviceProvidersBack;
    }

    public function getCachedServicesPathAcross()
    {
        return $this->bootstrapPath() . '/cache/laravelfly_services_across.json';
    }

    public function registerConfiguredProvidersBootOnWorker()
    {

        //todo study official registerConfiguredProviders
        (new ProviderRepository($this, new Filesystem, $this->getCachedServicesPathBootOnWorker()))
            ->load($this->providersToBootOnWorker);

        //todo
        $this->loadDeferredProviders();
    }

    public function getCachedServicesPathBootOnWorker()
    {
        return $this->bootstrapPath() . '/cache/laravelfly_services_on_worker.json';
    }

    public function bootOnWorker()
    {

        if ($this->bootedOnWorker) {
            return;
        }

        $this->fireAppCallbacks($this->bootingCallbacks);

        array_walk($this->serviceProviders, function ($p) {
            $this->bootProvider($p);
        });

        $this->bootedOnWorker = true;

        /**
         * moved to {@link bootInRequest()}
         */
        // $this->fireAppCallbacks($this->bootedCallbacks);
    }

    public function resetServiceProviders()
    {
        $this->serviceProviders = [];
    }

    public function bootInRequest()
    {
        if ($this->bootedInRequest) {
            return;
        }

        $this->registerConfiguredProvidersInRequest();

        /**
         * moved to {@link bootOnWorker()}
         */
        // $this->fireAppCallbacks($this->bootingCallbacks);

        array_walk($this->acrossServiceProviders, function ($p) {
            $this->bootProvider($p);
        });
        array_walk($this->serviceProviders, function ($p) {
            $this->bootProvider($p);
        });

        $this->bootedInRequest = $this->booted = true;

        $this->fireAppCallbacks($this->bootedCallbacks);
    }
    public function make($abstract, array $parameters = [])
    {
        if (in_array($abstract, ['app', \Illuminate\Foundation\Application::class, \Illuminate\Contracts\Container\Container::class, \Illuminate\Contracts\Foundation\Application::class, \Psr\Container\ContainerInterface::class])) {
            return static::getInstance();
        }

        return parent::make($abstract, $parameters);
    }

}