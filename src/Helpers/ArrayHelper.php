<?php

namespace Ecode\CRUDBundle\Helpers;

use Doctrine\Common\Collections\ArrayCollection;

class ArrayHelper
{
    /**
     * group array by value of some key of each element
     *
     * @param array $array array with values
     * @param string $group key name by which will group
     * @return array result
     */
    public static function groupArray(array $array, $group): array {
        $result = [];
        foreach ($array as $key=>$val) {
            $result[$val[$group]][$key] = $val;
        }
        return $result;
    }

    public static function indexArray(array $array, $col): array {
        $result = [];
        foreach ($array as $key=>$val) {
            $result[$val[$col]] = $val;
        }
        return $result;
    }

    public static function arrayClone(array $array) {
        return array_map(function($element) {
            if (is_array($element)) {
                return ArrayHelper::arrayClone($element);
            } elseif ($element instanceof ArrayCollection) {
                $arrVal = new ArrayCollection();
                foreach ($element as $el) {
                    $arrVal->add(clone $el);
                }
                return $arrVal;
            } elseif (is_object($element)) {
                return clone $element;
            } else {
                return $element;
            }
        }, $array);
    }
}
