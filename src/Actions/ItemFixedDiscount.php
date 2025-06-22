<?php

namespace SilverShop\Discounts\Actions;

use SilverShop\Discounts\Adjustment;

class ItemFixedDiscount extends ItemDiscountAction
{
    public function perform(): void
    {
        foreach ($this->infoitems as $infoitem) {
            if (!$this->itemQualifies($infoitem)) {
                continue;
            }

            $amount = $this->discount->getDiscountValue($infoitem->getOriginalPrice());
            $amount *= $infoitem->getQuantity();
            $amount = $this->limit($amount);

            $infoitem->adjustPrice(new Adjustment($amount, $this->discount));

            //break the loop if there is no discountable amount left
            if (!$this->hasRemainingDiscount()) {
                break;
            }
        }
    }
}
