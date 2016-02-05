<?php

/**
 * @package shop_discount
 */
class DiscountedOrderExtension extends DataExtension {

    public function updateCMSFields(FieldList $fields) {
        $fields->addFieldsToTab('Root.Discounts',
           new GridField(
                'Discounts',
                'Discount',
                $this->Discounts(),
                new GridFieldConfig_RecordViewer()
            )
        );
    }

	/**
	 * Get all discounts that have been applied to an order.
	 */
	function Discounts() {
		return Discount::get()
			->leftJoin("OrderDiscountModifier_Discounts", "\"Discount\".\"ID\" = \"OrderDiscountModifier_Discounts\".\"DiscountID\"")
			->leftJoin("Product_OrderItem_Discounts", "\"Discount\".\"ID\" = \"Product_OrderItem_Discounts\".\"DiscountID\"")
			->innerJoin("OrderAttribute",
				"(\"OrderDiscountModifier_Discounts\".\"OrderDiscountModifierID\" = \"OrderAttribute\".\"ID\") OR
				(\"Product_OrderItem_Discounts\".\"Product_OrderItemID\" = \"OrderAttribute\".\"ID\")"
			)
			->filter("OrderAttribute.OrderID", $this->owner->ID);
	}

	/**
	 * Remove any partial discounts
	 */
	function onPlaceOrder() {
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

}
