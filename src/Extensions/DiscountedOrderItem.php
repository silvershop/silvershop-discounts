<?php

namespace SilverShop\Discounts\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverShop\Model\OrderItem;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\ItemPriceInfo;

class DiscountedOrderItem extends DataExtension
{
    private static $db = [
        'Discount' => 'Currency'
    ];

    private static $many_many = [
        'Discounts' => Discount::class
    ];

    private static $many_many_extraFields = [
        'Discounts' => [
            'DiscountAmount' => 'Currency'
        ]
    ];

    /**
     * @return int
     */
    public function getDiscountedProductID()
    {
        $productKey = OrderItem::config()->buyable_relationship . 'ID';

        return $this->owner->{$productKey};
    }

    /**
     * @return string
     */
    public function getPriceInfoClass()
    {
        $class = ItemPriceInfo::class;
        $this->owner->extend('updatePriceInfoClass', $class);
        return $class;
    }
}
