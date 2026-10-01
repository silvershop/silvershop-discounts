<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Discounts\Checkout\AppliedCouponCodes;
use SilverShop\Discounts\Model\Discount;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataList;

/**
 * Restricts a discount to orders where its code has been entered.
 *
 * The context may provide a single `CouponCode`, a list of `CouponCodes`, or
 * both. A coded discount matches when its code is any of the provided codes.
 *
 * @property ?string $Code
 */
class CodeDiscountConstraint extends DiscountConstraint
{
    private static array $db = [
        'Code' => 'Varchar(25)'
    ];

    public function filter(DataList $dataList): DataList
    {
        $codeColumn = sprintf('"%s"."Code"', Discount::config()->get('table_name'));
        $codes = $this->findCouponCodes();

        if ($codes !== []) {
            $codes = implode(', ', array_map(
                fn (string $code): string => "'" . Convert::raw2sql($code) . "'",
                $codes
            ));

            return $dataList
                ->where(sprintf('(%s IS NULL) OR (%s IN (%s))', $codeColumn, $codeColumn, $codes));
        }

        return $dataList->where(sprintf('%s IS NULL', $codeColumn));
    }

    public function check(Discount $discount): bool
    {
        $codes = $this->findCouponCodes();

        if ($discount->Code && !in_array(strtoupper($discount->Code), $codes, true)) {
            $this->error("Coupon code doesn't match " . implode(', ', $codes));
            return false;
        }

        return true;
    }

    /**
     * @return array<int, string> uppercase codes
     */
    protected function findCouponCodes(): array
    {
        $codes = $this->context['CouponCodes'] ?? [];
        $codes = is_array($codes) ? $codes : [$codes];

        if (!empty($this->context['CouponCode'])) {
            $codes[] = $this->context['CouponCode'];
        }

        return AppliedCouponCodes::normalise($codes);
    }
}
