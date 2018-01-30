<?php
/**
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

namespace obray\interfaces;

/**
 * Describes the interface of an obray factory
 */
interface oEncoderInterface
{
    /**
     * Takes some data and encodes that data
     *
     * @param mixed $data the data to be encoded
     *
     * @return mixed encoded data.
     */
    public function encode($data, $start_time);

    /**
     * Takes some data and decodes that data
     *
     * @param mixed $data the data to be decoded
     *
     * @return mixed decoded data.
     */
    public function decode($data);

    /**
     * Takes some encoded data and displays it appropariately
     *
     * @param mixed $data the data to be displayed
     *
     * @return null
     */
    public function out($data);

}