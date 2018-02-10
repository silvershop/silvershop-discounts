<?php

namespace SilverShop\Discounts\Extensions;

use SilverStripe\ORM\DataExtension;

class ProductDiscountExtension extends DataExtension
{
    private static $casting = [
        'TotalReduction' => 'Currency'
    ];

    /**
     * Get the difference between the original price and the new price.
     *
     * @param string $original
     *
     * @return float
     */
    public function getTotalReduction($original = "BasePrice")
    {
        $reduction = $this->owner->{$original} - $this->owner->sellingPrice();
        //keep it above 0;
        $reduction = $reduction < 0 ? 0 : $reduction;
        return $reduction;
    }

    /**
     * Check if this product has a reduced price.
     *
     * @return bool
     */
    public function IsReduced()
    {
        return (bool)$this->getTotalReduction();
    }

    /**
     * @return int
     */
    public function getDiscountedProductID()
    {
        return $this->owner->ID;
    }
}
