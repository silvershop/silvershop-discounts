<?php

namespace SilverShop\Discounts\Model\Modifiers;

use SilverShop\Model\Modifiers\OrderModifier;
use SilverStripe\Control\Controller;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\Calculator;

class OrderDiscountModifier extends OrderModifier
{
    private static $defaults = [
        "Type" => "Deductable"
    ];

    private static $many_many = [
        "Discounts" => Discount::class
    ];

    private static $many_many_extraFields = [
        'Discounts' => [
            'DiscountAmount' => 'Currency'
        ]
    ];

    private static $singular_name = 'Discount';

    private static $plural_name = "Discounts";

    private static $table_name = 'SilverShop_OrderDiscountModifier';

    public function value($incoming)
    {
        $this->Amount = $this->getDiscount();

        return $this->Amount;
    }

    public function getDiscount()
    {
        $context = [];

        if ($code = $this->getCode()) {
            $context['CouponCode'] = $code;
        }

        $order = $this->Order();
        $order->extend("updateDiscountContext", $context);

        $calculator = Calculator::create($order, $context);
        $amount = $calculator->calculate();

        $this->setField('Amount', $amount);

        return $amount;
    }

    public function getCode()
    {
        $code = Controller::curr()->getRequest()->getSession()->get("cart.couponcode");

        if (!$code && $this->Order()->exists()) {
            $discounts = $this->Order()->Discounts();

            foreach ($discounts as $discount) {
                if ($discount->Code) {
                    return $discount->Code;
                }
            }
        }

        return $code;
    }

    public function getSubTitle()
    {
        return $this->getUsedCodes();
    }

    public function getUsedCodes()
    {
        return implode(",",
            $this->Order()->Discounts()
                ->filter("Code:not", "")
                ->map('ID', 'Title')
                ->toArray()
        );
    }

    public function ShowInTable()
    {
        return $this->Amount() > 0;
    }
}
