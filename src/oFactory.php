<?php

/**
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

namespace obray;

/**
 * This class implements the oFactoryInterface is uses the factory method design
 * patter to generate objects.  It also takes a container to generate and map
 * dependencies,
 */

Class oFactory implements \obray\interfaces\oFactoryInterface
{
    /** @var \Psr\Container\ContainerInterface Stores the available container */
    protected $container;
    
    /**
     * The constructor assigns the available container
     * 
     * @param \Psr\Container\ContainerInterface $container Variable that contains the container
     */

    public function __construct( \Psr\Container\ContainerInterface $container=NULL )
    {
        $this->container = $container;
    }

    /**
     * This function is the factory method and spits out objects based on the path that's pased
     * in
     * 
     * @param string $path The path that describes the object to create
     *
     * @throws \obray\exceptions\ClassNotFound
     */

    public function make($path,$index=0)
    {
        // handle errors
        if($path == '\\'){ throw new \obray\exceptions\ClassNotFound("Unable to find Class ".$path, 404); }
        if(!class_exists($path)){ throw new \obray\exceptions\ClassNotFound("Unable to find Class ".$path, 404); }
        
        $constructor_parameters = array();
        $reflector = new \ReflectionClass($path);
        $constructor = $reflector->getConstructor();
        if( !empty($constructor) ){
            $parameters = $constructor->getParameters();
            forEach( $parameters as $parameter ){
                if( !$parameter->hasType() ) continue;
                if($this->container !== NULL){
                    $constructor_parameters[] = $this->container->get($parameter->getType()->getName());
                } else {
                    // if we have a factory then make object and return it
                    try{
                        $constructor_parameters[] = $this->make("\\".$parameter->getType()->getName(),1);
                    } catch(\obray\exceptions\ClassNotFound $e){
                        throw new \obray\exceptions\DependencyNotFound("Unable to find class dependency. " . $e->getMessage(), 501);
                    }
                }
            }
        }
        return new $path(...$constructor_parameters);
    }

}
