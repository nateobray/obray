<?php

namespace obray;

/**
 * This class is used to invoke or call a method on a specified object
 */

Class oInvoker implements oInvokerInterface
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
    
    public function invoke($object, $method, $params=[]){

        // reflect the object and method to retreive parameter data
        $reflector = new \ReflectionClass($object);
        $reflection_method = $reflector->getMethod($method);
        $parameters = $reflection_method->getParameters();

        // support legacy style methods
        if( count($parameters) === 1 && is_array($parameters[0]->getDefaultValue()) && count($parameters[0]->getDefaultValue()) === 0 ){
            $object->$method($params);
            return $object;
        }

        // support fully parameratized methods with default values
        $method_parameters = [];
        forEach( $parameters as $parameter ){
            if (!empty($params[$parameter->getName()])) {
                $method_parameters[] = $params[$parameter->getName()];
            } else if ($parameter->isDefaultValueAvailable() && !$parameter->isDefaultValueConstant()) {
                $method_parameters[] = $parameter->getDefaultValue();
            } else if ($parameter->isDefaultValueAvailable() && $parameter->isDefaultValueConstant()) {
                $constant = $parameter->getDefaultValueConstantName();
                $method_parameters[] = constant($constant);
            } else if (!$parameter->isOptional() && !$parameter->isDefaultValueAvailable()) {
                throw new \Exception("Missing parameter ".$parameter->getName().".",500);
            }
        }

        $object->$method(...$method_parameters);
        return $object;
    }

}

?>