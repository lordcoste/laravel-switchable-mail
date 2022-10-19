<?php

namespace KVZ\Laravel\SwitchableMail;

use Illuminate\Mail\MailServiceProvider as ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/switchable-mail.php' => config_path('switchable-mail.php'),
        ], 'switchable-mail');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/switchable-mail.php', 'switchable-mail');

        $this->registerSwiftMailer();

        $this->registerSwiftMailerManager();

        $this->registerMailer();
    }

    /**
     * Register the swift mailer manager.
     *
     * @return void
     */
    protected function registerSwiftMailerManager()
    {
        $this->app->singleton('swift.mailer.manager', function ($app) {
            return (new SwiftMailerManager($app))
                ->setTransportManager($app['swift.transport']);
        });
    }

    /**
     * Register the Mailer instance.
     *
     * @return void
     */
    protected function registerMailer()
    {
        $this->app->singleton('mailer', function ($app) {
            // Once we have create the mailer instance, we will set a container instance
            // on the mailer. This allows us to resolve mailer classes via containers
            // for maximum testability on said classes instead of passing Closures.
            $mailer = new Mailer(
                $app['view'], $app['swift.mailer'], $app['events']
            );

            $this->setMailerDependencies($mailer, $app);

            // If a "from" address is set, we will set it on the mailer so that all mail
            // messages sent by the applications will utilize the same "from" address
            // on each one, which makes the developer's life a lot more convenient.
            $from = $app['config']['mail.from'];

            if (is_array($from) && isset($from['address'])) {
                $mailer->alwaysFrom($from['address'], $from['name']);
            }

            $to = $app['config']['mail.to'];

            if (is_array($to) && isset($to['address'])) {
                $mailer->alwaysTo($to['address'], $to['name']);
            }

            return $mailer;
        });

        $this->app->alias('mailer', Mailer::class);
    }

    /**
     * Set a few dependencies on the mailer instance.
     *
     * @param  \KVZ\Laravel\SwitchableMail\Mailer  $mailer
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function setMailerDependencies($mailer, $app)
    {
        // parent::setMailerDependencies($mailer, $app);
        if ($app->bound('queue')) {
            $mailer->setQueue($app['queue']);
        }

        $mailer->setSwiftMailerManager($app['swift.mailer.manager']);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array_merge(parent::provides(), ['swift.mailer.manager']);
    }

    /**
     * Register the Swift Mailer instance.
     *
     * @return void
     */
    public function registerSwiftMailer()
    {
        $this->registerSwiftTransport();

        // Once we have the transporter registered, we will register the actual Swift
        // mailer instance, passing in the transport instances, which allows us to
        // override this transporter instances during app start-up if necessary.
        $this->app->singleton('swift.mailer', function ($app) {
            if ($domain = $app->make('config')->get('mail.domain')) {
                Swift_DependencyContainer::getInstance()
                                ->register('mime.idgenerator.idright')
                                ->asValue($domain);
            }

            return new Swift_Mailer($app['swift.transport']->driver());
        });
    }

    /**
     * Register the Swift Transport instance.
     *
     * @return void
     */
    protected function registerSwiftTransport()
    {
        $this->app->singleton('swift.transport', function ($app) {
            return new TransportManager($app);
        });
    }
}
