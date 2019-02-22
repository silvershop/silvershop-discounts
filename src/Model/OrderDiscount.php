<?php

namespace SilverShop\Discounts\Model;


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
