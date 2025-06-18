<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Model\OrderItem;
use SilverShop\Discounts\Model\Discount;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;

/**
 * Discount constraint that restricts to specific items.
 *
 * @package silvershop-discounts
 */
abstract class ItemDiscountConstraint extends DiscountConstraint
{

    /**
     * Checks that an item can be discounted for configured constraints.
     * If any constraint check fails, the entire function returns false;
     */
    public static function match(OrderItem $item, Discount $discount): bool
    {
        $singletons = [];
        $itemconstraints = ClassInfo::subclassesFor(self::class);

        array_shift($itemconstraints); //exclude abstract base class

        $configuredconstraints = Injector::inst()->get(Discount::class)->getConstraints();

        //get only the configured item constraints
        $classes = array_intersect($itemconstraints, $configuredconstraints);

        foreach ($classes as $constraint) {
            if (!singleton($constraint)->itemMatchesCriteria($item, $discount)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns true if the given item sits within this constraint.
     * If there is no constraint set, then it should return true.
     */
    abstract public function itemMatchesCriteria(OrderItem $item, Discount $discount): bool;

    /**
     * Check if at least one item in cart matches this criteria.
     */
    public function itemsInCart(Discount $discount): bool
    {
        $items = $this->order->Items();

        foreach ($items as $item) {
            if ($this->itemMatchesCriteria($item, $discount)) {
                return true;
            }
        }

        return false;
    }
}
