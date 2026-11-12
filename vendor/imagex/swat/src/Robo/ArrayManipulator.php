<?php

namespace Imagex\Swat\Robo;

/**
 * Utility class for manipulating arrays.
 */
class ArrayManipulator {

  /**
   * Merges arrays recursively while preserving.
   *
   * @param array $array1
   *   The first array.
   * @param array $array2
   *   The second array.
   *
   * @return array
   *   The merged array.
   *
   * @see http://php.net/manual/en/function.array-merge-recursive.php#92195
   */
  public static function arrayMergeRecursiveDistinct(
    array &$array1,
    array &$array2
  ) {
    $merged = $array1;
    foreach ($array2 as $key => &$value) {
      if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
        $merged[$key] = self::arrayMergeRecursiveDistinct($merged[$key],
          $value);
      }
      else {
        $merged[$key] = $value;
      }
    }
    return $merged;
  }

  /**
   * Flattens a multidimensional array to a flat array, using custom glue.
   *
   * This is the inverse of expandFromDotNotatedKeys(), e.g.,
   * ['drush' => ['alias' => 'self']] would be flattened to
   * [drush.alias => 'self'].
   *
   * @param array $array
   *   The multidimensional array.
   * @param string $glue
   *   The character(s) to use for imploding keys.
   *
   * @return array
   *   The flattened array.
   */
  public static function flattenMultidimensionalArray(array $array, $glue) {
    $iterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array));
    $result = [];
    foreach ($iterator as $leafValue) {
      $keys = [];
      foreach (range(0, $iterator->getDepth()) as $depth) {
        $keys[] = $iterator->getSubIterator($depth)->key();
      }
      $result[implode($glue, $keys)] = $leafValue;
    }

    return $result;
  }

}
