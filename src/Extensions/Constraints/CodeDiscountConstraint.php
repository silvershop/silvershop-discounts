<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Discounts\Model\Discount;
use SilverStripe\ORM\DataList;

class CodeDiscountConstraint extends DiscountConstraint
{
    private static array $db = [
        'Code' => 'Varchar(25)'
    ];

    public function filter(DataList $list): DataList
    {
        if ($code = $this->findCouponCode()) {
            $list = $list
                ->where("(\"Code\" IS NULL) OR (\"Code\" = '$code')");
        } else {
            $list = $list->where('"Code" IS NULL');
        }

        return $list;
    }

    public function check(Discount $discount): bool
    {
        $code = strtolower($this->findCouponCode() ?? '');

        if ($discount->Code && ($code !== strtolower($discount->Code ?? ''))) {
            $this->error("Coupon code doesn't match $code");
            return false;
        }

        return true;
    }

    protected function findCouponCode(): ?string
    {
        return isset($this->context['CouponCode']) ? $this->context['CouponCode'] : null;
    }
}
