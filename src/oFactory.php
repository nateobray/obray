<?php

/**
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

namespace obray;

Class oFactory implements oFactoryInterface
{

    protected $container;
    
    public function __construct( \Psr\Container\ContainerInterface $container )
    {
        $this->container = $container;
    }

    public function make($path, $parameters=[])
    {
        if(!class_exists($path)){ throw new \obray\ClassNotFound("Unable to find Class ".$path, 404); }
        
        $reflector = new \ReflectionClass($path);
        $constructor = $reflector->getConstructor();
        $parameters = $constructor->getParameters();
        
        forEach( $parameters as $parameter ){
            $constructor_parameters[] = $this->container->get( $parameter->getType()->__toString() );
        }
        return new $path(...$constructor_parameters);
        
    }

}
