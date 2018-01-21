<?php

/**
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

namespace obray;

/**
 * Describes the interface of an obray invoker
 */
interface oInvokerInterface
{
        /**
         * Finds an entry of the container by its identifier and returns it.
         *
         * @param string $path function name of a valid class.
         * @param array $parameters The parameters to be passed into the function
         *
         * @return mixed whatever is chose by implementer.
         */

        public function invoke($object, $path, $parameters=[]);
}
