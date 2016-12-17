<?php

namespace Jaxon\Module\Traits;

use Jaxon\Jaxon;

trait Module
{
    protected $setupCalled = false;

    protected $preCallback = null;
    protected $postCallback = null;
    protected $initCallback = null;

    // Requested class and method
    private $reqObject = null;
    private $reqMethod = null;

    protected $configFile = '';
    protected $configLibKey = '';
    protected $configAppKey = '';

    protected $appConfig = array();
    protected $libConfig = array();

    /**
     * Set the module specific options for the Jaxon library.
     *
     * @return void
     */
    abstract protected function setup();

    /**
     * Set the module specific options for the Jaxon library.
     *
     * @return void
     */
    abstract protected function check();

    /**
     * Return the view renderer.
     *
     * @return void
     */
    abstract protected function view();

    /**
     * Send the Jaxon response back to the browser.
     *
     * @param  $code        The HTTP Response code
     *
     * @return HTTP Response
     */
    abstract public function httpResponse($code = '200');

    /**
     * Set the config file path.
     *
     * @return void
     */
    protected function setConfigOptions($configFile, $configLibKey, $configAppKey)
    {
        $this->configFile = $configFile;
        $this->configLibKey = $configLibKey;
        $this->configAppKey = $configAppKey;
    }

    /**
     * Set the directory and namespace of the Jaxon classes.
     *
     * @return void
     */
    protected function setClassOptions($directory, $namespace, $controllerClass, array $excludedMethods = [])
    {
        $this->classDirectory = $directory;
        $this->classNamespace = $namespace;
        $this->controllerClass = $controllerClass;
        $this->excludedMethods = $excludedMethods;
        $this->appConfig['controllerClass'] = $controllerClass;
        $this->appConfig['excludedMethods'] = $excludedMethods;
    }

    /**
     * Set the directory and namespace of the Jaxon classes.
     *
     * @return void
     */
    protected function setExportOptions($extern, $minify, $jsUri, $jsDir)
    {
        $this->libConfig['js.app.extern'] = $extern;
        $this->libConfig['js.app.minify'] = $minify;
        $this->libConfig['js.app.uri'] = $jsUri;
        $this->libConfig['js.app.dir'] = $jsDir;
    }

    /**
     * Set the default Jaxon request URI.
     *
     * @return void
     */
    protected function setRequestOptions($requestUri)
    {
        $this->libConfig['core.request.uri'] = $requestUri;
    }

    /**
     * Wraps the module/package/bundle setup method.
     *
     * @return void
     */
    private function _setup()
    {
        if(($this->setupCalled))
        {
            return;
        }

        // Set the module/package/bundle specific specific options
        $this->setup();
        // Set the missing application config options
        if(!array_key_exists('controllerClass', $this->appConfig))
        {
            $this->appConfig['controllerClass'] = '\\Jaxon\\Module\\Controller';
        }
        if(!array_key_exists('excludedMethods', $this->appConfig))
        {
            $this->appConfig['excludedMethods'] = array();
        }

        $jaxon = jaxon();
        // Use the Composer autoloader
        $jaxon->useComposerAutoloader();
        // Read config
        $appConfig = $jaxon->readConfigFile($this->configFile, $this->configLibKey, $this->configAppKey);

        // Set the default values for mandatory application config options
        $appOptions = array('directory', 'namespace');
        $missingOptions = array();
        foreach($appOptions as $appOption)
        {
            if(!$appConfig->hasOption($appOption) && array_key_exists($appOption, $this->appConfig))
            {
                $appConfig->setOption($appOption, $this->appConfig[$appOption]);
            }
            // Check if the mandatory options are defined
            if(!$appConfig->hasOption($appOption))
            {
                $missingOptions[] = $appOption;
            }
        }
        if(count($missingOptions) > 0)
        {
            // Todo: throw an exception
        }

        // Jaxon application config
        $excluded = array_merge($appConfig->getOption('excluded', array()), $this->appConfig['excludedMethods']);
        // The public methods of the Controller base class must not be exported to javascript
        $controllerClass = new \ReflectionClass($this->appConfig['controllerClass']);
        foreach ($controllerClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $xMethod)
        {
            $excluded[] = $xMethod->getShortName();
        }

        // Register the default Jaxon class directory
        $jaxon->addClassDir($appConfig->getOption('directory'), $appConfig->getOption('namespace'), $excluded);
        $libOptions = array('core.request.uri', 'js.app.extern', 'js.app.minify', 'js.app.uri', 'js.app.dir');
        foreach($libOptions as $libOption)
        {
            if(!$jaxon->hasOption($libOption) && array_key_exists($libOption, $this->libConfig))
            {
                $jaxon->setOption($libOption, $this->libConfig[$libOption]);
            }
        }
        if(!$jaxon->hasOption('core.request.uri'))
        {
            $jaxon->setOption('core.request.uri', 'jaxon');
        }

        $this->appConfig = $appConfig;
        $this->check();
        $this->setupCalled = true;
    }

    /**
     * Register the Jaxon classes.
     *
     * @return void
     */
    public function register()
    {
        $this->_setup();
        $jaxon = jaxon();
        $jaxon->registerClasses();
    }

    /**
     * Register a specified Jaxon class.
     *
     * @return void
     */
    public function registerClass($sClassName)
    {
        $this->_setup();
        $jaxon = jaxon();
        $jaxon->registerClass($sClassName);
    }

    /**
     * Get the javascript code to be sent to the browser.
     *
     * @return string  the javascript code
     */
    public function script($bIncludeJs = false, $bIncludeCss = false)
    {
        $this->_setup();
        $jaxon = jaxon();
        return $jaxon->getScript($bIncludeJs, $bIncludeCss);
    }

    /**
     * Get the HTML tags to include Jaxon javascript files into the page.
     *
     * @return string  the javascript code
     */
    public function js()
    {
        $this->_setup();
        $jaxon = jaxon();
        return $jaxon->getJs();
    }

    /**
     * Get the HTML tags to include Jaxon CSS code and files into the page.
     *
     * @return string  the javascript code
     */
    public function css()
    {
        $this->_setup();
        $jaxon = jaxon();
        return $jaxon->getCss();
    }

    /**
     * Set the init callback, used to initialise controllers.
     *
     * @param  callable  $callable the callback function
     * @return void
     */
    public function setInitCallback($callable)
    {
        $this->initCallback = $callable;
    }

    /**
     * Set the pre-request processing callback.
     *
     * @param  callable  $callable the callback function
     * @return void
     */
    public function setPreCallback($callable)
    {
        $this->preCallback = $callable;
    }

    /**
     * Set the post-request processing callback.
     *
     * @param  callable  $callable the callback function
     * 
     * @return void
     */
    public function setPostCallback($callable)
    {
        $this->postCallback = $callable;
    }

    /**
     * Initialise a controller.
     *
     * @return void
     */
    protected function initController(Controller $controller)
    {
        // Return if the controller has already been initialised.
        if(!($controller) || ($controller->module))
        {
            return;
        }
        // Init the controller
        $controller->module = $this;
        $controller->response = $this->response;
        if(($this->initCallback))
        {
            $cb = $this->initCallback;
            $cb($controller);
        }
        $controller->init();
        // The default view is used only if there is none already set
        if(!$controller->view)
        {
            $controller->view = $this->view();
        }
    }

    /**
     * Get a controller instance.
     *
     * @param  string  $classname the controller class name
     * 
     * @return object  The registered instance of the controller
     */
    public function controller($classname)
    {
        $this->_setup();
        $jaxon = jaxon();
        $controller = $jaxon->registerClass($classname, true);
        if(!$controller)
        {
            return null;
        }
        $this->initController($controller);
        return $controller;
    }

    /**
     * This is the pre-request processing callback passed to the Jaxon library.
     *
     * @param  boolean  &$bEndRequest if set to true, the request processing is interrupted.
     * 
     * @return object  the Jaxon response
     */
    public function preProcess(&$bEndRequest)
    {
        // Validate the inputs
        $class = $_POST['jxncls'];
        $method = $_POST['jxnmthd'];
        if(!$jaxon->validateClass($class) || !$jaxon->validateMethod($method))
        {
            // End the request processing if the input data are not valid.
            // Todo: write an error message in the response
            $bEndRequest = true;
            return $this->response;
        }
        // Instanciate the controller. This will include the required file.
        $this->reqObject = $this->controller($class);
        $this->reqMethod = $method;
        if(!$this->reqObject)
        {
            // End the request processing if a controller cannot be found.
            // Todo: write an error message in the response
            $bEndRequest = true;
            return $this->response;
        }

        // Call the user defined callback
        if(($this->preCallback))
        {
            $cb = $this->preCallback;
            $cb($this->reqObject, $method, $bEndRequest);
        }
        return $this->response;
    }

    /**
     * This is the post-request processing callback passed to the Jaxon library.
     *
     * @return object  the Jaxon response
     */
    public function postProcess()
    {
        if(($this->postCallback))
        {
            $cb = $this->postCallback;
            $cb($this->reqObject, $this->reqMethod);
        }
        return $this->response;
    }

    /**
     * Check if the current request is a Jaxon request.
     *
     * @return boolean  True if the request is Jaxon, false otherwise.
     */
    public function canProcessRequest()
    {
        $this->_setup();
        $jaxon = jaxon();
        return $jaxon->canProcessRequest();
    }

    /**
     * Process the current Jaxon request.
     *
     * @return void
     */
    public function processRequest()
    {
        $this->_setup();
        // Process Jaxon Request
        $jaxon = jaxon();
        $jaxon->register(Jaxon::PROCESSING_EVENT, Jaxon::PROCESSING_EVENT_BEFORE, array($this, 'preProcess'));
        $jaxon->register(Jaxon::PROCESSING_EVENT, Jaxon::PROCESSING_EVENT_AFTER, array($this, 'postProcess'));
        if($jaxon->canProcessRequest())
        {
            // Traiter la requete
            $jaxon->processRequest();
        }
    }
}