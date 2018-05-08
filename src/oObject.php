<?php
/**
 * @license MIT
 */

namespace obray;

/**
 * This class is the foundation of an obray based application
 */

Class oObject
{

    /** @var int Records the start time (time the object was created).  Cane be used for performance tuning */
    private $starttime;
    /** @var bool indicates if there was an error on this object */
    private $is_error = false;
    /** @var int Status code - used to translate to HTTP 1.1 status codes */
    private $status_code = 200;
    /** @var int Stores the content type of this class or how it should be represented externally */
    private $content_type = 'application/json';
    /** @var oDBOConnection Stores information about a connection or the connection itself for the purpose of establishing a connection to DB */
    protected $oDBOConnection;
    /** @var \Psr\Container\ContainerInterface Stores the objects container object for dependency injection */
    protected $container;
    /** @var \obray\oFactoryInterface Stores the objects factory object for the factory method */
    protected $factory;
    /** @var \obray\oInvokerInterface Stores the objects factory object for the factory method */
    protected $invoker;
    /** @var bool specify if we are in debug mode or not */
    protected $debug_mode = false;
    /** @var string the users table */
    protected $user_session_key = "oUser";

    /** @var string stores the name of the class */
    public $object = '';

    /**
     * The route method takes a path and converts it into an object/and or
     * method.
     *
     * @param string $path A path to an object/method
     * @param array $params An array of parameters to pass to the method
     * @param bool $direct Specifies if the route is being called directly
     *
     * @return \obray\oObject
     */

    public function route($path, $params = array(), $direct = true)
    {

        if (!$direct) {
            $params = array_merge($params, $_GET, $_POST);
        }

        $components = parse_url($path);
        $this->components = $components;
        if (isSet($components['query'])) {
            parse_str($components['query'], $tmp_params);
            $params = array_merge($tmp_params, $params);
        }

        $path_array = explode('/', $components['path']);
        $path_array = array_filter($path_array);
        $path_array = array_values($path_array);

        if (isSet($components['host']) && $direct) {
            if (!class_exists('obray\oCURL')) {
                throw new \Exception("obray\oCURL is not defined/installed.", 500);
            }
            $this->data = new obray\oCURL($components);
            return $this;
        }

        // set content type with these special parameters
        if (isset($params['ocsv'])) {
            $this->setContentType('text/csv');
            unset($params['ocsv']);
        }
        if (isset($params['otsv'])) {
            $this->setContentType('text/tsv');
            unset($params['otsv']);
        }
        if (isset($params['otable'])) {
            $this->setContentType('text/table');
            unset($params['otable']);
        }

        if (empty($path_array)) {
            $path_array[] = 'c';
            $path_array[] = 'cIndex';
        }

        // use the factory and invoker to create an object invoke its methods
        try {
            try {
                return $this->make($path_array, $params, $direct);
            } catch (\obray\exceptions\ClassNotFound $e) {
                $function = array_pop($path_array);
                return $this->make($path_array, $params, $direct, $function);
            }
        } catch (\obray\exceptions\ClassNotFound $e) {
            if (!empty($function)) {
                $path_array[] = $function;
            }
            return $this->searchForController($path_array, $params, $direct);
        }

        // if we're unsuccessful in anything above then throw error
        throw new \Exception("Could not find " . $components['path'], 404);
        return $this;

    }

    /**
     * This method creates an object based on the supplied parameters with
     * the classes factory object
     *
     * @param array $path_array Array containing the path
     * @param array $params Array containing the parameters to be passed the called method
     * @param bool $direct Specifies if the is is being called directly (skips permission check)
     * @param string $method The name of the method on the object we want to call
     *
     * @throws \obray\exceptions\ClassNotFound
     */

    private function make($path_array, $params, $direct, $method = '')
    {
        $obj = $this->factory->make('\\' . implode('\\', $path_array));
        $obj->object = '\\' . implode('\\', $path_array);
        $obj->factory = $this->factory;
        $obj->container = $this->container;
        $obj->invoker = $this->invoker;
        $this->checkPermissions($obj, null, $direct);
        if (!empty($method)) {
            $this->invoke($obj, $method, $params, $direct);
        } else {
            if (method_exists($obj, "index")) {
                $this->invoke($obj, 'index', $params, $direct);
            }
        }
        return $obj;
    }

    /**
     * The invoke method checks permission on the method we want to call and then uses
     * the class invoker to call the method
     *
     * @param mixed $obj The object containing the method we want to call
     * @param string $method The name of the method on the object we want to call
     * @param array $params Array of the parameters to be passed to our method
     * @param bool $direct Specifies if the is is being called directly (skips permission check)
     *
     */

    private function invoke($obj, $method, $params, $direct)
    {
        if (method_exists($obj, $method)) {
            $this->checkPermissions($obj, $method, $direct);
            return $this->invoker->invoke($obj, $method, $params);
        } else {
            throw new \obray\exceptions\ClassMethodNotFound("Unabel to find method " . $method, 404);
        }
    }

    /**
     * Searches recursively for a controller class based on the path_array and then
     * creates that controller and calls the specified method if any
     *
     * @param array $path_array Array containing the path
     * @param array $params Array containing the parameters to be passed the called method
     * @param bool $direct Specifies if the is is being called directly (skips permission check)
     * @param string $method The name of the method to be called on the created object
     * @param array $remaining An array of the remaining path (useful for dynamic page genration)
     * @param int $depth The depth of the recursive call.  Currenly has a hardcoded max
     *
     */

    private function searchForController($path_array, $params, $direct, $method = '', $remaining = array(), $depth = 0)
    {

        // prevent the posobility of an infinite loop (this should not happen, but is here just in case)
        if ($depth > 20) {
            throw new \Exception("Depth limit for controller search reached.", 500);
        }

        // setup path to controller class
        $object = array_pop($path_array);
        $path = 'c\\' . (!empty($path_array) ? implode('\\', $path_array) . '\\' : '') . 'c' . ucfirst($object);
        $index_path = 'c\\' . (!empty($path_array) ? implode('\\',
                    $path_array) . '\\' : '') . (!empty($object) ? $object . '\\' : '') . 'cIndex';

        // check if path to controller exists, if so create object
        if (class_exists('\\' . $path)) {
            $path_array = explode('\\', $path);
            $params["remaining"] = $remaining;
            try {
                $obj = $this->make($path_array, $params, $direct, $method);
            } catch (\obray\exceptions\ClassMethodNotFound $e) {
                $obj = $this->make($path_array, $params, $direct, '');
            }
            return $obj;

            // check if index path to contorller exists, if so create object
        } else {
            if (class_exists('\\' . $index_path)) {
                $path_array = explode('\\', $index_path);
                $params["remaining"] = $remaining;
                try {
                    $obj = $this->make($path_array, $params, $direct, $method);
                } catch (\obray\exceptions\ClassMethodNotFound $e) {
                    $obj = $this->make($path_array, $params, $direct, '');
                }
                return $obj;

                // if unable to objects specified by either path, throw exception
            } else {
                $remaining[] = $object;
                if (empty($path_array)) {
                    throw new \obray\exceptions\ClassNotFound("Path not found.", 404);
                }
            }
        }
        return $this->searchForController($path_array, $params, $direct, $object, $remaining, ++$depth);

    }

    /**
     * This method checks the pmerissions set on the object and allows permissions
     * accordingly
     *
     * @param mixed $obj The object we are going to check permissions on
     * @param bool $direct Specifies if the call is from a remote source
     *
     */

    protected function checkPermissions($obj, $fn = null, $direct)
    {
        if ($direct) {
            return;
        }
        $perms = $obj->getPermissions();
        if (($fn === null && !isSet($perms["object"])) || ($fn !== null && !isSet($perms[$fn]))) {
            throw new \obray\exceptions\PermissionDenied('You cannot access this resource.', 403);
        }
    }

    /**
     * Set the error state on the class and stores a serios of error messages.  This
     * function is useful if you want to throw a serios of errors without stopping
     * execution, and then report those errors back to the client.
     *
     * @param string $message Message to be stored in array of error messages
     * @param int $status_code The is the status code to report back out to the client
     * @param string $type This is the type of error, influences the output to client
     */

    public function throwError($message, $status_code = 500, $type = 'general')
    {
        $this->is_error = true;
        if (empty($this->errors) || !is_array($this->errors)) {
            $this->errors = [];
        }
        $this->errors[$type][] = $message;
        $this->status_code = $status_code;
    }

    /**
     * Simply returns if the error state on the class
     */

    public function isError()
    {
        return $this->is_error;
    }

    /**
     * Checks if a user has a specific role
     */

    public function hasRole($code)
    {
        if ((!empty($_SESSION['ouser']->roles) && in_array($code,
                    $_SESSION["ouser"]->roles)) || (!empty($_SESSION["ouser"]->roles) && in_array("SUPER",
                    $_SESSION["ouser"]->roles))) {
            return true;
        }
        return false;
    }

    /**
     * Check the user role, and if the user does not have the one specified throws an error
     */

    public function errorOnRole($code)
    {
        if (!$this->hasRole($code)) {
            throw new \obray\exceptions\PermissionDenied("Permission denied", 403);
        }
    }

    /**
     * Simply returns if the user has permission
     */

    public function hasPermission($code)
    {
        if ((!empty($_SESSION['ouser']->permissions) && in_array($code,
                    $_SESSION["ouser"]->permissions)) || (!empty($_SESSION["ouser"]->roles) && in_array("SUPER",
                    $_SESSION["ouser"]->roles))) {
            return true;
        }
        return false;
    }

    /**
     * Checks permissions, if the user doesn't have them it throws an error
     */

    public function errorOnPermission($code)
    {
        if (!$this->hasPermission($code)) {
            throw new \obray\exceptions\PermissionDenied("Permission denied", 403);
        }
    }

    /**
     * Simply returns the status code set on the object
     */

    public function getStatusCode()
    {
        return $this->status_code;
    }

    /**
     * Simply returns the content type set ont he object
     */

    public function getContentType()
    {
        return $this->content_type;
    }

    /**
     * Simply sets the content type on the object
     */

    public function setContentType($type)
    {
        if ($this->content_type != 'text/html') {
            $this->content_type = $type;
        }
    }

    /**
     * Gets the permissions array and returns it if exists
     */

    public function getPermissions()
    {
        return isset($this->permissions) ? $this->permissions : array();
    }

    /***********************************************************************
     *
     * RUN ROUTE IN BACKGROUND
     ***********************************************************************/

    public function routeBackground($route)
    {
        shell_exec("php -d memory_limit=-1 " . __SELF__ . "obray.php \"" . $route . "\" > /dev/null 2>&1 &");
    }

}
