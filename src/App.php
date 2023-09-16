<?php

/**
 * @package Mp
 * This is main file of Mpanel
 * do not edit this file or any of its contents
 * by editing this file, you are breaking the Application
 */

namespace Mp;

use Throwable;

$GLOBALS['time_start'] = microtime(true);

class App extends BoxApp
{

    const app_config = __DIR__ . "/config/app.yml";
    /**
     * Constructor function for initializing the class.
     * 
     * It creates instances of the EventSystem, MiddlewareStack,
     * Router, Config, Logger, and PluginManager classes.
     *
     * @return void
     */
    public function __construct()
    {

       // ob_start();

        $this->exception();
       // $this->database_db();
        $this->view();
        $this->setConfig(self::app_config);

        if ($this->getConfig()->get("service")) {

            $service = $this->getConfig()->get("service");

            $mod = $this->mod();
            $mod->$service();

        }
    }




    function getDirectory()
    {

        return __DIR__;
    }


    /**
     * Runs the application.
     */
    public function run(): void
    {


        try {

             $request = $this->utility_http_request();
            // Send the response
            echo $this->utility_router()->dispatch($request);
             
        } catch (Throwable $e) {

             $this->exception()->catch_error($e);
        }
    }
}
