<?php

namespace SilverShop\Discounts\Extensions;

use SilverStripe\Core\Extension;
use SilverShop\Page\Product;
/**
 * @extends Extension<Product&static>
 */
class ProductDiscountExtension extends Extension
{
    private static array $casting = [
        'TotalReduction' => 'Currency'
    ];

    /**
     * Get the difference between the original price and the new price.
     */
    public function getTotalReduction($original = 'BasePrice'): int|float
    {
        $reduction = $this->owner->{$original} - $this->owner->sellingPrice();
        //keep it above 0;
        $reduction = $reduction < 0 ? 0 : $reduction;
        return $reduction;
    }

    /**
     * Check if this product or variation has a reduced price.
     */
    public function IsReduced(): bool
    {
        return (bool) $this->getTotalReduction();
    }

    public function getDiscountedProductID(): int
    {
        return $this->owner->ID;
    }
}
