<?php

namespace Shop\Discount;

/**
 * Stores the calculated adjustment,
 * and the associated object that made the adjustment.
 */
class Adjustment
{
    protected $value;
    protected $adjuster;

    public function __construct($val, $adjuster = null)
    {
        $this->value = $val;
        $this->adjuster = $adjuster;
    }

    public static function better_of(Adjustment $i, Adjustment $j)
    {
        return $i->compareTo($j) > 0 ? $i : $j;
    }

    //biggest adjustment = best
    public function compareTo(Adjustment $i)
    {
        return $this->getValue() - $i->getValue();
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getAdjuster()
    {
        return $this->adjuster;
    }

    public function __tostring()
    {
        try {
            return (string) $this->value;
        } catch (Exception $exception) {
            return '';
        }
    }
}
