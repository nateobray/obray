<?php
/**
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

namespace obray\interfaces;

/**
 * Describes the interface of an obray factory
 */
interface oFactoryInterface
{
    /**
     * Factory takes a container implementing PSR ContainerInterface.  This
     * uses a service locator design pattern, which is generally discouraged,
     * however its considered acceptable practice for factory objects to
     * use this patter: http://www.php-fig.org/psr/psr-11/meta/
     *
     * @param \Psr\Container\ContainerInterface $container Contains any container 
     * implementing PSR 11 ContainerInterface.
     *
     */
    public function __construct(\Psr\Container\ContainerInterface $container);
    
    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $path namespace\object of a valid class.
     * @param array $parameters The parameters to be passed into the constructor
     *
     * @return mixed Object made from path.
     */
    public function make($path);
}
