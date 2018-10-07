<?php

namespace obray;

/**
 * This class is used to invoke or call a method on a specified object
 */

Class oInvoker implements \obray\interfaces\oInvokerInterface
{

    /**
     * The invoke method attempts to call a specified method on an object
     *
     * @param mixed $object This is the object that contains the method we want to call
     * @param string $method The name of the function on the object you want to call
     * @param array $params This is an array of parameters to be passed to the method
     *
     * @return mixed
     */

    public function invoke($object, $method, $params = [])
    {

        // reflect the object 
        try {
            $reflector = new \ReflectionClass($object);
        } catch (\ReflectionException $e) {
            throw new \obray\exceptions\ClassNotFound("Unable to find object.", 404);
        }

        // reflect method and extract parameters
        try {
            $reflection_method = $reflector->getMethod($method);
            $parameters = $reflection_method->getParameters();
        } catch (\ReflectionException $e) {
            throw new \obray\exceptions\ClassMethodNotFound("Unable to find object method.", 404);
        }

        // support legacy style methods
        if (
            count($parameters) === 1 &&
            $parameters[0]->isDefaultValueAvailable() &&
            is_array($parameters[0]->getDefaultValue()) &&
            count($parameters[0]->getDefaultValue()) === 0
        ) {
            $object->$method($params);
            return $object;
        }

        // support fully parameratized methods with default values
        $method_parameters = [];
        forEach ($parameters as $parameter) {
            $method_parameters[] = self::getParameterValue($params, $parameter);
        }

        $object->$method(...$method_parameters);
        return $object;
    }

    /**
     * @param array $params
     * @param ReflectionParameter $parameter
     * @return mixed
     * @throws \Exception
     */
    private static function getParameterValue($params, $parameter)
    {
        if (!empty($params[$parameter->getName()])) {
            return $params[$parameter->getName()];
        }

        if ($parameter->isDefaultValueAvailable() && !$parameter->isDefaultValueConstant()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->isDefaultValueAvailable() && $parameter->isDefaultValueConstant()) {
            $constant = $parameter->getDefaultValueConstantName();
            return constant($constant);
        }

        if (!$parameter->isOptional() && !$parameter->isDefaultValueAvailable()) {
            throw new \Exception("Missing parameter " . $parameter->getName() . ".", 500);
        }
    }

}

?>