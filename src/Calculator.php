<?php

namespace SilverShop\Discounts;

use SilverStripe\Model\List\ArrayList;
use SilverShop\Discounts\Actions\ItemDiscountAction;
use SilverShop\Discounts\Actions\SubtotalDiscountAction;
use SilverShop\Discounts\Extensions\Constraints\ItemDiscountConstraint;
use SilverShop\Discounts\Model\Discount;
use SilverShop\Discounts\Model\Modifiers\OrderDiscountModifier;
use SilverShop\Discounts\Actions\ItemPercentDiscount;
use SilverShop\Discounts\Actions\ItemFixedDiscount;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverShop\Model\Order;
use SilverStripe\ORM\DataList;

class Calculator
{
    use Injectable;

    protected Order $order;

    /** @var ArrayList<Discount> */
    protected ArrayList $discounts;

    protected OrderDiscountModifier $modifier;

    /** @var array<int, array{Level: string, Amount: int|float, Discount: string}> */
    protected array $log = [];

    /** @param array<string, mixed> $context */
    public function __construct(Order $order, array $context = [])
    {
        $this->order = $order;

        if (!empty($context['use_applied_discounts'])) {
            // Reprice using the discounts already applied to this order (e.g. recalculating a
            // completed or refunded order) instead of re-matching currently-active discounts —
            // so a discount that has since expired/been disabled still applies to that order.
            $discounts = $order->Discounts();
        } else {
            // get qualifying discounts for this order
            $discounts = Discount::get_matching($this->order, $context);
        }

        // stacked discounts compound, so the order they are applied in matters
        $this->discounts = $discounts->sort(['Priority' => 'DESC', 'ID' => 'ASC']);
    }

    /**
     * Work out the discount for a given order.
     *
     * At each level (each item, the cart subtotal, and shipping) either the
     * single best discount is used, or - if it saves more - all discounts that
     * allow stacking, applied in priority order.
     */
    public function calculate(): int|float
    {
        $this->modifier = $this->order->getModifier(
            OrderDiscountModifier::class,
            true
        );

        $total = 0;

        // clear any existing linked discounts
        $this->modifier->Discounts()->removeAll();

        // work out all item-level discounts, and load into infoitems
        $infoitems = $this->createPriceInfoList($this->order->Items());

        foreach ($this->getItemDiscounts() as $discount) {
            // item discounts will update info items
            $this->createItemAction($infoitems, $discount)->perform();
        }

        $stackable = $this->getStackableDiscounts($this->getItemDiscounts());

        if ($stackable->count() > 1) {
            foreach ($stackable as $discount) {
                $this->createItemAction($infoitems, $discount)
                    ->setStacking(true)
                    ->perform();
            }
        }

        // select best item-level discounts
        foreach ($infoitems as $infoitem) {
            $adjustments = $this->selectAdjustments(
                $infoitem->getBestAdjustment(),
                $infoitem->getStackedAdjustments(),
                $infoitem->getOriginalTotal()
            );

            // remove any existing linked discounts
            $infoitem->getItem()->Discounts()->removeAll();

            foreach ($adjustments as $adjustment) {
                $amount = $adjustment->getValue();
                $total += $amount;

                $infoitem->getItem()->Discounts()->add(
                    $adjustment->getAdjuster(),
                    ['DiscountAmount' => $amount]
                );

                $this->logDiscountAmount('Item', $amount, $adjustment->getAdjuster());
            }
        }

        $cartremainder = $this->order->SubTotal() - $total;
        // keep remainder sane, i.e above 0
        $cartremainder = $cartremainder < 0 ? 0 : $cartremainder;

        // work out all cart-level discounts, and load into cartpriceinfo
        $cartpriceinfo = new PriceInfo($this->order->SubTotal());

        foreach ($this->getCartDiscounts() as $discount) {
            $action = new SubtotalDiscountAction(
                $this->getDiscountableAmount($discount),
                $discount
            );

            $action->reduceRemaining($this->discountSubtotal($discount));

            $adjust = new Adjustment($action->perform(), $discount);
            $cartpriceinfo->adjustPrice($adjust);
        }

        $stackable = $this->getStackableDiscounts($this->getCartDiscounts());

        if ($stackable->count() > 1) {
            foreach ($stackable as $discount) {
                // each stacked discount applies to what is left after the ones before it
                $base = min(
                    $this->getDiscountableAmount($discount),
                    $cartremainder - $cartpriceinfo->getStackedDiscount()
                );

                $this->addStackedSubtotalAdjustment($cartpriceinfo, (float) $base, $discount);
            }
        }

        // select best cart-level discount(s)
        $adjustments = $this->selectAdjustments(
            $cartpriceinfo->getBestAdjustment(),
            $cartpriceinfo->getStackedAdjustments(),
            $cartremainder
        );

        foreach ($adjustments as $adjustment) {
            $discount = $adjustment->getAdjuster();
            $amount = $adjustment->getValue();
            $total += $amount;

            $this->modifier->Discounts()->add(
                $discount,
                ['DiscountAmount' => $amount]
            );

            $this->logDiscountAmount('Cart', $amount, $discount);
        }

        if (class_exists('SilverShop\Shipping\ShippingFrameworkModifier') && $shipping = $this->order->getModifier('SilverShop\Shipping\ShippingFrameworkModifier')) {
            $shippingamount = (float) $shipping->Amount;

            // work out all shipping-level discounts, and load into shippingpriceinfo
            $shippingpriceinfo = new PriceInfo($shippingamount);

            foreach ($this->getShippingDiscounts() as $discount) {
                $action = new SubtotalDiscountAction($shippingamount, $discount);
                $action->reduceRemaining($this->discountSubtotal($discount));
                $shippingpriceinfo->adjustPrice(
                    new Adjustment($action->perform(), $discount)
                );
            }

            $stackable = $this->getStackableDiscounts($this->getShippingDiscounts());

            if ($stackable->count() > 1) {
                foreach ($stackable as $discount) {
                    $this->addStackedSubtotalAdjustment(
                        $shippingpriceinfo,
                        (float) $shippingpriceinfo->getStackedPrice(),
                        $discount
                    );
                }
            }

            // select best shipping-level discount(s)
            $adjustments = $this->selectAdjustments(
                $shippingpriceinfo->getBestAdjustment(),
                $shippingpriceinfo->getStackedAdjustments(),
                $shippingamount
            );

            foreach ($adjustments as $adjustment) {
                $discount = $adjustment->getAdjuster();
                $amount = $adjustment->getValue();
                $total += $amount;

                $this->modifier->Discounts()->add(
                    $discount,
                    ['DiscountAmount' => $amount]
                );

                $this->logDiscountAmount('Shipping', $amount, $discount);
            }
        }

        return $total;
    }

    /**
     * Choose between the single best adjustment, and the stacked
     * adjustments - whichever saves more. The chosen adjustments are capped
     * so that together they never exceed $max.
     *
     * @param array<int, Adjustment> $stacked
     * @return array<int, Adjustment>
     */
    protected function selectAdjustments(?Adjustment $best, array $stacked, int|float $max): array
    {
        $stackedtotal = array_sum(array_map(
            fn (Adjustment $adjustment): int|float => $adjustment->getValue(),
            $stacked
        ));
        $besttotal = $best instanceof Adjustment ? $best->getValue() : 0;

        if (count($stacked) > 1 && $stackedtotal > $besttotal) {
            $adjustments = $stacked;
        } elseif ($best instanceof Adjustment) {
            $adjustments = [$best];
        } else {
            return [];
        }

        $output = [];
        $remaining = $max;

        foreach ($adjustments as $adjustment) {
            if ($remaining <= 0) {
                break;
            }

            $amount = min($adjustment->getValue(), $remaining);
            $remaining -= $amount;

            $output[] = $amount === $adjustment->getValue() ?
                $adjustment :
                new Adjustment($amount, $adjustment->getAdjuster());
        }

        return $output;
    }

    /**
     * Calculate a stacked subtotal-level (cart or shipping) discount on the
     * given remaining amount, and record it against the price info.
     */
    protected function addStackedSubtotalAdjustment(PriceInfo $priceInfo, float $base, Discount $discount): void
    {
        if ($base <= 0) {
            return;
        }

        $action = new SubtotalDiscountAction($base, $discount);
        $action->reduceRemaining($this->discountSubtotal($discount));

        $amount = $action->perform();

        if ($amount > 0) {
            $priceInfo->addStackedAdjustment(new Adjustment($amount, $discount));
        }
    }

    /**
     * @param array<int, ItemPriceInfo> $infoitems
     */
    protected function createItemAction(array $infoitems, Discount $discount): ItemDiscountAction
    {
        return $discount->Type === 'Percent' ?
            Injector::inst()->createWithArgs(ItemPercentDiscount::class, [$infoitems, $discount]) :
            Injector::inst()->createWithArgs(ItemFixedDiscount::class, [$infoitems, $discount]);
    }

    /**
     * @param ArrayList<Discount> $discounts
     * @return ArrayList<Discount>
     */
    protected function getStackableDiscounts(ArrayList $discounts): ArrayList
    {
        return $discounts->filter('AllowStacking', true);
    }

    /**
     * Work out the total discountable amount for a given discount
     */
    protected function getDiscountableAmount(Discount $discount): int|float
    {
        $amount = 0;

        foreach ($this->order->Items() as $hasManyList) {
            if (ItemDiscountConstraint::match($hasManyList, $discount)) {
                $amount += $hasManyList->hasMethod('DiscountableAmount') ?
                            $hasManyList->DiscountableAmount() * $hasManyList->Quantity : $hasManyList->Total();
            }
        }

        return $amount;
    }

    /**
     * Work out how much the given discount has already
     * been used
     */
    protected function discountSubtotal(Discount $discount): float
    {
        return (float) $this->modifier->Discounts()
            ->filter('ID', $discount->ID)
            ->sum('DiscountAmount');
    }

    /**
     * @param iterable<\SilverStripe\ORM\DataObject> $dataList
     * @return array<int, ItemPriceInfo>
     */
    protected function createPriceInfoList(iterable $dataList): array
    {
        $output = [];

        foreach ($dataList as $item) {
            $priceInfoClass = $item->hasMethod('getPriceInfoClass') ? $item->getPriceInfoClass() : null;
            if (!is_string($priceInfoClass) || $priceInfoClass === '') {
                $priceInfoClass = ItemPriceInfo::class;
            }

            $output[] = Injector::inst()->createWithArgs($priceInfoClass, [$item]);
        }

        return $output;
    }

    /** @return ArrayList<Discount> */
    protected function getItemDiscounts(): ArrayList
    {
        return $this->discounts->filter('ForItems', true);
    }

    /** @return ArrayList<Discount> */
    protected function getCartDiscounts(): ArrayList
    {
        return $this->discounts->filter('ForCart', true);
    }

    /** @return ArrayList<Discount> */
    protected function getShippingDiscounts(): ArrayList
    {
        return $this->discounts->filter('ForShipping', true);
    }

    /**
     * Store details about discounts for loggging / debubgging
     */
    public function logDiscountAmount(string $level, int|float $amount, Discount $discount): void
    {
        $this->log[] = [
            'Level' => $level,
            'Amount' => $amount,
            'Discount' => $discount->Title
        ];
    }

    /** @return array<int, array{Level: string, Amount: int|float, Discount: string}> */
    public function getLog(): array
    {
        return $this->log;
    }
}
