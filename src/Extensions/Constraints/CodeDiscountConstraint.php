<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Discounts\Model\Discount;
use SilverStripe\ORM\DataList;

class CodeDiscountConstraint extends DiscountConstraint
{
    private static $db = [
        'Code' => 'Varchar(25)'
    ];

    public function filter(DataList $list)
    {
        if ($code = $this->findCouponCode()) {
            $list = $list
                ->where("(\"Code\" IS NULL) OR (\"Code\" = '$code')");
        } else {
            $list = $list->where('"Code" IS NULL');
        }

        return $list;
    }

    public function check(Discount $discount)
    {
        $code = strtolower($this->findCouponCode());

        if ($discount->Code && ($code != strtolower($discount->Code))) {
            $this->error("Coupon code doesn't match $code");
            return false;
        }

        return true;
    }

    protected function findCouponCode()
    {
        return isset($this->context['CouponCode']) ? $this->context['CouponCode'] : null;
    }
}
