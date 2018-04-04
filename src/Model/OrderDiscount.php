<?php

namespace SilverShop\Discounts\Model;

use SilverShop\Discounts\Model\Discount;

/**
 * Order discounts.
 *
 * This class is needed to clearly distinguish between coupons and generic
 * discounts.
 */
class OrderDiscount extends Discount
{

    private static $table_name = 'SilverShop_OrderDiscount';
}
