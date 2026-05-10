<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Discounts\Model\Discount;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataList;

/**
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

        if (($code = $this->findCouponCode()) !== null && ($code = $this->findCouponCode()) !== '' && ($code = $this->findCouponCode()) !== '0') {
            $code = Convert::raw2sql($code);
            return $dataList
                ->where(sprintf("(%s IS NULL) OR (%s = '%s')", $codeColumn, $codeColumn, $code));
        }

        return $dataList->where(sprintf('%s IS NULL', $codeColumn));
    }

    public function check(Discount $discount): bool
    {
        $code = strtolower($this->findCouponCode() ?? '');

        if ($discount->Code && ($code !== strtolower($discount->Code ?? ''))) {
            $this->error("Coupon code doesn't match " . $code);
            return false;
        }

        return true;
    }

    protected function findCouponCode(): ?string
    {
        return $this->context['CouponCode'] ?? null;
    }
}
