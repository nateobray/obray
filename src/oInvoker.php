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
            if (!empty($params[$parameter->getName()])) {
                $method_parameters[] = $params[$parameter->getName()];
            } else if ($parameter->isDefaultValueAvailable() && !$parameter->isDefaultValueConstant()) {
                $method_parameters[] = $parameter->getDefaultValue();
            } else if ($parameter->isDefaultValueAvailable() && $parameter->isDefaultValueConstant()) {
                $constant = $parameter->getDefaultValueConstantName();
                $method_parameters[] = constant($constant);
            } else if (!$parameter->isOptional() && !$parameter->isDefaultValueAvailable()) {
                throw new \Exception("Missing parameter " . $parameter->getName() . ".", 500);
            }
        }

        $object->$method(...$method_parameters);
        return $object;
    }

}

?>