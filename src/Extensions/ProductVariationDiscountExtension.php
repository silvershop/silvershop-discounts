<?php

namespace SilverShop\Discounts\Extensions;

use SilverStripe\Core\Extension;
use SilverShop\Model\Variation\Variation;

/**
 * @extends Extension<Variation&static>
 */
class ProductVariationDiscountExtension extends Extension
{
    private static array $casting = [
        'TotalReduction' => 'Currency'
    ];

    /**
     * Get the difference between the original price and the new price.
     */
    public function getTotalReduction($original = 'Price'): int|float
    {
        $reduction = $this->owner->{$original} - $this->owner->sellingPrice();
        //keep it above 0;
        $reduction = $reduction < 0 ? 0 : $reduction;
        return $reduction;
    }

    /**
     * Check if this variation has a reduced price.
     */
    public function IsReduced(): bool
    {
        return (bool)$this->getTotalReduction();
    }
}
