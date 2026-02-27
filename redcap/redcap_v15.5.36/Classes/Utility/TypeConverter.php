<?php
namespace Vanderbilt\REDCap\Classes\Utility;

class TypeConverter
{
    /**
     * Convert a value to a number (float).
     *
     * @param mixed $value
     * @return float|null
     */
    public static function toNumber($value)
    {
        if (is_numeric($value)) {
            return (float)$value;
        }
        return null;
    }

    /**
     * Convert a value to an integer number (int).
     *
     * @param mixed $value
     * @return int|null
     */
    public static function toInt($value)
    {
        if (is_numeric($value)) {
            return intval($value);
        }
        return null;
    }

    /**
     * Convert a value to a string.
     *
     * @param mixed $value
     * @return string|null
     */
    public static function toString($value)
    {
        if (is_scalar($value)) {
            return (string)$value;
        }
        return null;
    }

    /**
     * Convert a value to a DateTime object.
     *
     * @param mixed $value
     * @param string|null $format
     * @return \DateTime|null
     */
    public static function toDateTime($value, $format = null)
    {
        if (is_null($value)) return null;
        if ($value instanceof \DateTime) return $value;

        try {
            if ($format) {
                return \DateTime::createFromFormat($format, $value) ?: null;
            } else {
                return new \DateTime($value);
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Convert a value to a boolean.
     *
     * @param mixed $value
     * @return bool
     */
    public static function toBoolean($value)
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
