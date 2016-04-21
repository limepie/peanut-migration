<?php
/**
 * Peanut\Migration
 *
 * @package    Peanut\Migration
 */
namespace Peanut\Migration;

/**
 * Migration Utility Class
 *
 * @author Max <kwon@yejune.com>
 */
class Utils
{

    /**
     * Gets largest length of the array.
     * @param unknown $array
     */
    public static function arrayKeyLargestLength($array)
    {
        return max(array_map('strlen', array_keys($array)));
    }

    public static function isSQL($str)
    {
        return true === is_string($str) && 1 === preg_match('#^insert|update|delete|select#i', trim($str)) ? true : false;
    }

    public static function arrayKeyFlatten($array, $namespace = null)
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array));

        $flat = [];
        foreach ($iterator as $key => $value)
        {
            $keys = null !== $namespace ? [$namespace] : [];
            for ($i=0, $j=$iterator->getDepth(); $i<=$j; $i++)
            {
                $keys[] = $iterator->getSubIterator($i)->key();
            }
            $flat[implode('/',$keys)] = $value;
        }
        return $flat;
    }

    /*
    The Following Methods are copied from symfony.
    https://github.com/symfony/DependencyInjection/blob/master/Container.php
    Copyright (c) 2004-2014 Fabien Potencier
    Permission is hereby granted, free of charge, to any person obtaining a copy
    of this software and associated documentation files (the "Software"), to deal
    in the Software without restriction, including without limitation the rights
    to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
    copies of the Software, and to permit persons to whom the Software is furnished
    to do so, subject to the following conditions:
    The above copyright notice and this permission notice shall be included in all
    copies or substantial portions of the Software.
    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
    IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
    FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
    AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
    LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
    OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
    THE SOFTWARE.
    */

    public static function camelize($id)
    {
        return strtr(ucwords(strtr($id, array('_' => ' ', '.' => '_ ', '\\' => '_ '))), array(' ' => ''));
    }

    public static function underscore($id)
    {
        return strtolower(preg_replace(array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('\\1_\\2', '\\1_\\2'), strtr($id, '_', '.')));
    }

}
