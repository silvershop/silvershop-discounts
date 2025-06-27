<?php

namespace SilverShop\Discounts;

use Exception;

/**
 * Stores the calculated adjustment,
 * and the associated object that made the adjustment
 */
class Adjustment
{
    protected int|float $value;

    protected $adjuster;

    public function __construct(int|float $val, $adjuster = null)
    {
        $this->value = $val;
        $this->adjuster = $adjuster;
    }

    public static function better_of(Adjustment $i, Adjustment $j): Adjustment
    {
        return $i->compareTo($j) > 0 ? $i : $j;
    }

    //biggest adjustment = best
    public function compareTo(Adjustment $adjustment): int|float
    {
        return $this->getValue() - $adjustment->getValue();
    }

    public function getValue(): int|float
    {
        return $this->value;
    }

    public function getAdjuster()
    {
        return $this->adjuster;
    }

    public function __tostring(): string
    {
        return strval($this->value);
    }
}
