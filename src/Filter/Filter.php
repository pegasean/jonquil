<?php

declare(strict_types=1);

namespace Jonquil\Filter;

use InvalidArgumentException;

/**
 * Class Filter
 * @package Jonquil\Filter
 */
abstract class Filter
{

    /**
     * @var array Error messages
     */
    protected static $errors = [
        'invalid_rule' => 'Invalid filter rule',
    ];

    /**
     * Applies a set of filters on a collection of values.
     *
     * @param array $data
     * @param array $rules
     */
    public function apply(array &$data, array $rules)
    {
        foreach ($rules as $rule) {
            if (count($rule) < 2) {
                throw new InvalidArgumentException(
                    static::$errors['invalid_rule']
                );
            }
            list($key, $filter) = $rule;
            $parameters = array_slice($rule, 2);

            if (is_array($key)) {
                if (empty($key)) {
                    $key = array_keys($data);
                }
                foreach ($key as $k) {
                    $rule[0] = $k;
                    $this->apply($data, [$rule]);
                }
            } elseif (array_key_exists($key, $data)) {
                if (is_array($data[$key])) {
                    foreach ($data[$key] as $k => $v) {
                        $rule[0] = $k;
                        $this->apply($data[$key], [$rule]);
                    }
                } else {
                    $data[$key] = $this->filter(
                        $data[$key],
                        $filter,
                        $parameters
                    );
                }
            }
        }
    }

    /**
     * Filters a value.
     *
     * @param $value
     * @param string $filter
     * @param array $parameters
     * @return mixed
     */
    abstract protected function filter(
        $value,
        string $filter,
        array $parameters
    );
}
