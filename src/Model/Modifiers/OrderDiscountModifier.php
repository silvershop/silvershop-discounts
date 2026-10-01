<?php

namespace SilverShop\Discounts\Model\Modifiers;

use SilverStripe\ORM\ManyManyList;
use SilverStripe\Model\List\ArrayList;
use SilverShop\Model\Modifiers\OrderModifier;
use SilverShop\Discounts\Checkout\AppliedCouponCodes;
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

        if ($codes = $this->getCodes()) {
            $context['CouponCodes'] = $codes;
            // single code kept for constraints / extensions that predate stacking
            $context['CouponCode'] = end($codes);
        }

        $order = $this->Order();
        $order->extend('updateDiscountContext', $context);

        $calculator = Calculator::create($order, $context);
        $amount = $calculator->calculate();

        $this->setField('Amount', $amount);

        return $amount;
    }

    /**
     * The most recently applied coupon code.
     */
    public function getCode(): ?string
    {
        $codes = $this->getCodes();

        return $codes === [] ? null : end($codes);
    }

    /**
     * All coupon codes applied to this order. Codes entered in the current
     * session take precedence, otherwise falls back to the codes of coupons
     * already linked to the order (e.g. when recalculating outside a request).
     *
     * @return array<int, string>
     */
    public function getCodes(): array
    {
        $codes = AppliedCouponCodes::get();

        if ($codes === [] && $this->Order()->exists()) {
            /** @var ArrayList<Discount> $discounts */
            $discounts = $this->Order()->Discounts();

            foreach ($discounts as $discount) {
                if ($discount->Code) {
                    $codes[] = $discount->Code;
                }
            }
        }

        return AppliedCouponCodes::normalise($codes);
    }

    public function getSubTitle(): string
    {
        return $this->getUsedCodes();
    }

    public function getUsedCodes(): string
    {
        if (!$this->Order()->exists()) {
            return '';
        }

        $discounts = $this->Order()->Discounts()->filter("Code:not", "");

        if (!$discounts->count()) {
            return '';
        }

        return implode(
            (string) $this->config()->get('subtitle_separator'),
            $discounts->map('ID', 'Title')->toArray()
        );
    }

    public function ShowInTable(): bool
    {
        return $this->Amount > 0;
    }
}
