<?php namespace App\Core;
/**
 * Main App Class
 * 
 * The main Silver Application class is responsible for setting up the
 * whole app environment, app modes and any boostrapping needed.
 * This class also provides several functions for easily knowing where
 * the application runs (live or production through SilverApp::mode())
 * 
 * @package Silver
 * @author  Brux
 * @since   0.1.0
 */
final class SilverApp extends \Slim\Slim
{

    const VERSION = '0.1.0';

    const TIMEZONE = 'Asia/Manila';

    public function __construct()
    {
        // Set PHP default timezone & ENV variables from .env
        date_default_timezone_set(self::TIMEZONE);
        \Dotenv::load(ROOT);

        // Setup default app settings
        $settings = [
            'templates.path'        => APP . '/Templates',
            'cookies.encrypt'       => true,
            'cookies.httponly'      => true,
            'cookies.secret_key'    => getenv('COOKIE_SECRET')
        ];

        // Change settings depending on the mode
        if ( $_SERVER['SERVER_NAME'] == 'localhost' )
        {
            $settings['mode'] = 'development';
            $settings['debug'] = true;
        }
        else
        {
            $settings['mode'] = 'production';
            $settings['debug'] = false;
        }

        // Initialize the app
        parent::__construct($settings);

        // Setup the logger
        $this->container->singleton('logger', function($c) {
            $log = new \Monolog\Logger('silver');
            if ( $settings['mode'] == 'development' )
            {
                $log->pushHandler(new \Monolog\Handler\RotatingFileHandler(ROOT . '/storage/logs/silver'));
            }
            else
            {
                $log->pushHandler(new \Monolog\Handler\SyslogHandler('app'));
            }
            return $log;
        });
        $this->getLog()->setWriter(new LogAdapter($this->logger));
    }

    public function mode($mode = null)
    {
        $app_mode = $this->container['mode'];
        if ( $mode === null )
        {
            return $app_mode;
        }
        else
        {
            return $mode === $app_mode;
        }
    }

}