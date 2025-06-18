<?php

namespace SilverShop\Discounts\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverShop\Model\OrderItem;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\ItemPriceInfo;

class DiscountedOrderItem extends DataExtension
{
    private static array $db = [
        'Discount' => 'Currency'
    ];

    private static array $many_many = [
        'Discounts' => Discount::class
    ];

    private static array $many_many_extraFields = [
        'Discounts' => [
            'DiscountAmount' => 'Currency'
        ]
    ];

    public function getDiscountedProductID(): int
    {
        $productKey = OrderItem::config()->buyable_relationship . 'ID';

        return $this->owner->{$productKey};
    }

    public function getPriceInfoClass(): string
    {
        $class = ItemPriceInfo::class;
        $this->owner->extend('updatePriceInfoClass', $class);
        return $class;
    }
}
