<?php

class DiscountedOrderExtension extends DataExtension{
		
	/**
	 * Get all discounts that have been applied to an order.
	 */
	function Discounts(){
		return Discount::get()
			->leftJoin("OrderDiscountModifier_Discounts", "\"Discount\".\"ID\" = \"OrderDiscountModifier_Discounts\".\"DiscountID\"")
			->leftJoin("Product_OrderItem_Discounts", "\"Discount\".\"ID\" = \"Product_OrderItem_Discounts\".\"DiscountID\"")
			->innerJoin("OrderAttribute", 
				"(\"OrderDiscountModifier_Discounts.\"OrderDiscountModifierID\" = \"OrderAttribute\".\"ID\") OR 
				(\"Product_OrderItem_Discounts\".\"Product_OrderItemID\" = \"OrderAttribute\".\"ID\")"
			)
			->filter("OrderAttribute.OrderID", $this->owner->ID);
	}

}