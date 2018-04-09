<?php

namespace SilverShop\Discounts;

use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\Model\Modifiers\OrderDiscountModifier;
use SilverShop\Discounts\Actions\ItemPercentDiscount;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverShop\Model\Order;
use SilverStripe\ORM\DataList;

class Calculator
{
    use Injectable;

    protected $order;

    protected $discounts;

    protected $modifier;

    protected $log = [];

    public function __construct(Order $order, $context = [])
    {
        $this->order = $order;

        // get qualifying discounts for this order
        $this->discounts = Discount::get_matching($this->order, $context);
    }

    /**
     * Work out the discount for a given order.
     *
     * @param Order $order
     * @return double - discount amount
     */
    public function calculate()
    {
        $this->modifier = $this->order->getModifier(
            OrderDiscountModifier::class, true
        );

        $total = 0;

        // clear any existing linked discounts
        $this->modifier->Discounts()->removeAll();

        // work out all item-level discounts, and load into infoitems
        $infoitems = $this->createPriceInfoList($this->order->Items());

        foreach ($this->getItemDiscounts() as $discount) {

            // item discounts will update info items
            $action = $discount->Type === "Percent" ?
                Injector::inst()->createWithArgs(ItemPercentDiscount::class, [$infoitems, $discount]) :
                Injector::inst()->createWithArgs(ItemFixedDiscount::class, [$infoitems, $discount]);

            $action->perform();
        }

        // select best item-level discounts
        foreach ($infoitems as $infoitem) {
            $bestadjustment = $infoitem->getBestAdjustment();

            if (!$bestadjustment) {
                continue;
            }

            $amount = $bestadjustment->getValue();

            // prevent discounting more than original price
            if ($amount > $infoitem->getOriginalTotal()) {
                $amount = $infoitem->getOriginalTotal();
            }

            $total += $amount;

            // remove any existing linked discounts
            $infoitem->getItem()->Discounts()->removeAll();
            $infoitem->getItem()->Discounts()->add(
                $bestadjustment->getAdjuster(),
                ['DiscountAmount' => $amount]
            );

            $this->logDiscountAmount("Item", $amount, $bestadjustment->getAdjuster());
        }

        // work out all cart-level discounts, and load into cartpriceinfo
        $cartpriceinfo = new PriceInfo($this->order->SubTotal());

        foreach ($this->getCartDiscounts() as $discount) {
            $action = new \SubtotalDiscountAction(
                $this->getDiscountableAmount($discount),
                $discount
            );

            $action->reduceRemaining($this->discountSubtotal($discount));

            $adjust = new Adjustment($action->perform(), $discount);
            $cartpriceinfo->adjustPrice($adjust);
        }

        $cartremainder = $cartpriceinfo->getOriginalPrice() - $total;
        // keep remainder sane, i.e above 0
        $cartremainder = $cartremainder < 0 ? 0 : $cartremainder;

        // select best cart-level discount
        if ($bestadjustment = $cartpriceinfo->getBestAdjustment()) {
            $discount = $bestadjustment->getAdjuster();
            $amount = $bestadjustment->getValue();
            // don't let amount be greater than remainder
            $amount = $amount > $cartremainder ? $cartremainder : $amount;
            $total += $amount;

            $this->modifier->Discounts()->add(
                $discount,
                ['DiscountAmount' => $amount]
            );

            $this->logDiscountAmount("Cart", $amount, $discount);
        }

        if (class_exists('ShippingFrameworkModifier') && $shipping = $this->order->getModifier("ShippingFrameworkModifier")) {
            // work out all shipping-level discounts, and load into shippingpriceinfo
            $shippingpriceinfo = new PriceInfo($shipping->Amount);

            foreach ($this->getShippingDiscounts() as $discount) {
                $action = new \SubtotalDiscountAction($shipping->Amount, $discount);
                $action->reduceRemaining($this->discountSubtotal($discount));
                $shippingpriceinfo->adjustPrice(
                    new Adjustment($action->perform(), $discount)
                );
            }
            //select best shipping-level disount
            if ($bestadjustment = $shippingpriceinfo->getBestAdjustment()) {
                $discount = $bestadjustment->getAdjuster();
                $amount = $bestadjustment->getValue();
                //don't let amount be greater than remainder
                $total += $amount;

                $this->modifier->Discounts()->add(
                    $discount,
                    ['DiscountAmount' => $amount]
                );

                $this->logDiscountAmount("Shipping", $amount, $discount);
            }
        }

        return $total;
    }

    /**
     * Work out the total discountable amount for a given discount
     *
     * @param Discount
     *
     * @return float
     */
    protected function getDiscountableAmount($discount)
    {
        $amount = 0;

        foreach ($this->order->Items() as $item) {
            if (ItemDiscountConstraint::match($item, $discount)) {
                $amount += method_exists($item, "DiscountableAmount") ?
                            $item->DiscountableAmount() :
                            $item->Total();
            }
        }

        return $amount;
    }

    /**
     * Work out how much the given discount has already
     * been used.
     */
    protected function discountSubtotal($discount)
    {
        return $this->modifier->Discounts()
                    ->filter("ID", $discount->ID)
                    ->sum("DiscountAmount");
    }

    /**
     * @param DataList $list
     *
     * @return array
     */
    protected function createPriceInfoList(DataList $list)
    {
        $output = [];

        foreach ($list as $item) {
            $output[] = new ItemPriceInfo($item);
        }

        return $output;
    }

    protected function getItemDiscounts()
    {
        return $this->discounts->filter("ForItems", true);
    }

    protected function getCartDiscounts()
    {
        return $this->discounts->filter("ForCart", true);
    }

    protected function getShippingDiscounts()
    {
        return $this->discounts->filter("ForShipping", true);
    }

    /**
     * Store details about discounts for loggging / debubgging
     * @param  string   $level
     * @param  double   $amount
     * @param  Discount $discount
     */
    public function logDiscountAmount($level, $amount, Discount $discount)
    {
        $this->log[] = [
            "Level" => $level,
            "Amount" => $amount,
            "Discount" => $discount->Title
        ];
    }

    public function getLog()
    {
        return $this->log;
    }
}
