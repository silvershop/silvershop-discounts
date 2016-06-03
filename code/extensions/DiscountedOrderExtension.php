<?php

/**
 * @package silvershop-discounts
 */
class DiscountedOrderExtension extends DataExtension {

    public function updateCMSFields(FieldList $fields) {
        $fields->addFieldsToTab('Root.Discounts',
           $grid = new GridField(
                'Discounts',
                'Discount',
                $this->Discounts(),
                new GridFieldConfig_RecordViewer()
            )
        );

        $grid->setModelClass('Discount');
    }

	/**
	 * Get all discounts that have been applied to an order.
     *
     * @return ArrayList
	 */
	public function Discounts() {
        $discounts = Discount::get()
            ->leftJoin("OrderDiscountModifier_Discounts", "\"Discount\".\"ID\" = \"OrderDiscountModifier_Discounts\".\"DiscountID\"")
            ->innerJoin("OrderAttribute",
                "(\"OrderDiscountModifier_Discounts\".\"OrderDiscountModifierID\" = \"OrderAttribute\".\"ID\")"
            )
            ->filter("OrderAttribute.OrderID", $this->owner->ID);

        $finalDiscounts = new ArrayList();

        foreach($discounts as $discount) {
            $finalDiscounts->push($discount);
        }

        foreach($this->owner->Items() as $item) {
            foreach($item->Discounts() as $discount) {
                $finalDiscounts->push($discount);
            }
        }

        $finalDiscounts->removeDuplicates();

        return $finalDiscounts;
	}

	/**
	 * Remove any partial discounts
	 */
	public function onPlaceOrder() {
		foreach($this->owner->Discounts()->filter("ClassName", "PartialUseDiscount") as $discount) {
			//only bother creating a remainder discount, if savings have been made
			if($savings = $discount->getSavingsForOrder($this->owner)){
				$discount->createRemainder($savings);
				//deactivate discounts
				$discount->Active = false;
				$discount->write();
			}
		}
	}

    /**
     * Remove discounts
     */
    public function removeDiscounts() {
        foreach($this->owner->Items() as $item) {
            $item->Discounts()->removeAll();
        }
    }
}
