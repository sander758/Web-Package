<?php

namespace Wizard\Kernel;

use Wizard\App\Session;
use Wizard\Exception\WizardRuntimeException;
use Wizard\Kernel\Http\HttpKernel;
use Wizard\Modules\Config\Config;
use Wizard\Modules\Database\Database;
use Wizard\Modules\Database\DatabaseException;
use Wizard\Sessions\BaseSessionHandler;
use Wizard\Sessions\SessionException;
use Wizard\Sessions\SessionHandler;
use Wizard\Templating\TemplateLoader;

class App
{
    /**
     * @var string
     * Keeps the response.
     */
    static $response;

    /**
     * @var string
     * Holds the path to the Response that is send to the user.
     * Null if it is plain html.
     */
    static $response_path = '';

    /**
     * @var string
     * The root directory of the project.
     */
    static $root = '';

    /**
     * @var static array
     * Keeps all the debug messages to output at the end of the response
     */
    static $debug = array();

    /**
     * @var string
     * Holds the base uri of the request.
     */
    static $base_uri;

    /**
     * @var null|\PDO
     * The global database connection from config.
     */
    static $db_connection = null;

    /**
     * @var null|BaseSessionHandler
     * The session handler.
     */
    static $session_handler = null;

    /**
     * @var string
     * The requested uri by the user
     */
    private $uri;

    /**
     * @var string
     * The request method
     */
    private $method;


    /**
     * App constructor.
     * @param string $uri
     * @param string $method
     *
     * This is the base of the app, triggering the database connection
     * and starting session handler.
     */
    public function __construct(string $uri, string $method)
    {
        $this->uri = $uri;
        $this->method = $method;
        self::$base_uri = substr($_SERVER['REQUEST_URI'], 0, strlen($_SERVER['REQUEST_URI']) - strlen(substr($_SERVER['REQUEST_URI'], strpos($_SERVER['PHP_SELF'], '/index.php'))));
    }

    /**
     * @throws DatabaseException
     *
     * Sets up the database and session handler.
     */
    public function prepare()
    {
        $this->setupDatabase();
        $this->setupSession();
    }

    /**
     * Start the app
     */
    public function start()
    {
        $http_kernel = new HttpKernel();

        $http_kernel->handleRequest($this->uri, $this->method);
    }

    /**
     * @param string $absolute_path
     * @param array $parameters
     * @return bool
     * @throws WizardRuntimeException
     *
     * Sets the response that is soon to be send to the user.
     * Also collecting the output that is echo'ed before this method
     */
    public static function setResponse(string $absolute_path, array $parameters = array())
    {
        $path = str_replace('\\', '/', $absolute_path);
        if (file_exists($path) === false) {
            throw new WizardRuntimeException($path. ' response file not found');
        }
        self::$response_path = $path;

        $loader = new TemplateLoader(App::$root.'/Storage/Cache/template.php');
        $content = $loader->loadTemplate($path);

        $parameters = $loader->filterParameters($parameters);

        $asset_name = HttpKernel::$route['assets'];
        $params = $loader->addAssets($content, $asset_name);
        $parameters['links'] = $params['links'];
        $parameters['images'] = $params['images'];

        $cache = self::$root.'/Storage/Cache/template.php';
        App::$response = self::loadResponseFile($cache, $parameters);

        return true;
    }

    /**
     * @param string $path
     * @param array $parameters
     * @return mixed
     *
     * Load the App::$ResponsePath and return its contents.
     * Make sure that the path exists before using this method
     */
    public static function loadResponseFile(string $path, array $parameters)
    {
        ob_start();
        extract($parameters, EXTR_SKIP);

        require $path;

        $content = ob_get_clean();
        return $content;
    }

    /**
     * Sending the fresh forged app to the users browser
     */
    public static function send()
    {
        echo html_entity_decode(self::$response);
    }

    /**
     * @param $message
     * Closing the app.
     * TODO shutting down database connections and sessions.
     */
    public static function terminate(string $message = '')
    {
        self::$session_handler->updateData(self::$session_handler->getId());
        die($message);
    }

    /**
     * @param string $uri
     * Send the user to another location.
     */
    public static function sendRequest(string $uri)
    {
        header('Location: '. self::$base_uri . $uri);
    }

    /**
     * Starting the session with the custom made session handler
     */
    private function setupSession()
    {
        try {
            $handler = new SessionHandler();
            self::$session_handler = $handler->setup();
        } catch (SessionException $e) {
            $e->showErrorPage();
        }
    }

    /**
     * @throws DatabaseException
     * Checking the database config file and when the connect_on_load key is set
     * to true it will automatically connect to the database.
     */
    private function setupDatabase()
    {
        $config = Config::getFile('database');
        if ($config === null) {
            throw new DatabaseException("Couldn't find database config file");
        }
        if (($config['connect_on_load'] ?? false) === false) {
            return;
        }
        if (!is_array($config)) {
            throw new DatabaseException("Database config file didn't return an array");
        }
        if (!array_key_exists('driver', $config)) {
            throw new DatabaseException("Couldn't find database driver");
        }
        $driver = $config['driver'];
        if ($driver != 'mysql') {
            throw new DatabaseException('Unknown database driver');
        }
        $driver_config = $config[$driver];
        if (!array_key_exists('host', $driver_config) || !is_string($driver_config['host'])) {
            throw new DatabaseException('Couldnt find database host or host isnt a string');
        }
        if (!array_key_exists('database', $driver_config) || !is_string($driver_config['database'])) {
            throw new DatabaseException('Couldnt find database database or database isnt a string');
        }
        if (!array_key_exists('user', $driver_config) || !is_string($driver_config['user'])) {
            throw new DatabaseException('Couldnt find database user or user isnt a string');
        }
        if (!array_key_exists('password', $driver_config) || !is_string($driver_config['password'])) {
            throw new DatabaseException('Couldnt find database password or password isnt a string');
        }
        $db = new Database();
        App::$db_connection = $db->connect($driver_config['database'], $driver_config['host'], $driver_config['user'], $driver_config['password']);
    }
}
