<?php

namespace SilverShop\Discounts\Model\Modifiers;

use SilverStripe\ORM\ManyManyList;
use SilverShop\Model\Modifiers\OrderModifier;
use SilverStripe\Control\Controller;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\Calculator;

/**
 * @method ManyManyList<Discount> Discounts()
 */
class OrderDiscountModifier extends OrderModifier
{
    private static string $subtitle_separator = ', ';

    private static array $defaults = [
        'Type' => 'Deductable'
    ];

    private static array $many_many = [
        'Discounts' => Discount::class
    ];

    private static array $many_many_extraFields = [
        'Discounts' => [
            'DiscountAmount' => 'Currency'
        ]
    ];

    private static string $singular_name = 'Discount';

    private static string $plural_name = 'Discounts';

    private static string $table_name = 'SilverShop_OrderDiscountModifier';

    private static array $casting = [
        'SubTitle' => 'HTMLFragment',
        'UsedCodes' => 'HTMLFragment'
    ];

    public function value($incoming): int|float
    {
        $this->Amount = $this->getDiscount();

        return $this->Amount;
    }

    public function getDiscount(): int|float
    {
        $context = [];

        if ($code = $this->getCode()) {
            $context['CouponCode'] = $code;
        }

        $order = $this->Order();
        $order->extend('updateDiscountContext', $context);

        $calculator = Calculator::create($order, $context);
        $amount = $calculator->calculate();

        $this->setField('Amount', $amount);

        return $amount;
    }

    public function getCode()
    {
        $code = Controller::curr()->getRequest()->getSession()->get('cart.couponcode');

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

    public function getSubTitle(): string
    {
        return $this->getUsedCodes();
    }

    /**
     * @return string
     */
    public function getUsedCodes(): string
    {
        $discounts = $this->Order()->Discounts()->filter("Code:not", "");

        if (!$discounts->count()) {
            return '';
        }

        return implode(
            $this->config()->subtitle_separator,
            $discounts->map('ID', 'Title')->toArray()
        );
    }

    public function ShowInTable(): bool
    {
        return $this->Amount > 0;
    }
}
