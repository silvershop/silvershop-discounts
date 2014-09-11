<?php

class DiscountReport extends ShopPeriodReport{

	protected $title = "Discounts";
	protected $dataClass = "Discount";
	protected $periodfield = "\"Order\".\"Paid\"";
	protected $description = "See the total savings for discounts. Note that the 'Entered' field may not be
										accurate if old/expired carts have been deleted from the database.";

	public function columns() {
		return array(
			"Name" => "Title",
			"Code" => "Code",
			"DiscountNice" => "Discount",
			"Entered" => "Entered",
			"UseCount" => "Uses",
			"SavingsTotal" => "Total Savings"
		);
	}

	/**
	 * Remove unsortable columns
	 */
	public function sortColumns() {
		$cols = parent::sortColumns();
		unset($cols['DiscountNice']);
		return $cols;
	}

	public function query($params) {
		$query = parent::query($params);
		$query->addSelect("\"Discount\".*")
			->selectField("\"Title\"", "Name")
			->selectField("COUNT(DISTINCT \"Order\".\"ID\")", 'Entered')
			->addLeftJoin("Product_OrderItem_Discounts", "\"Product_OrderItem_Discounts\".\"DiscountID\" = \"Discount\".\"ID\"")
			->addLeftJoin("OrderDiscountModifier_Discounts", "\"OrderDiscountModifier_Discounts\".\"DiscountID\" = \"Discount\".\"ID\"")
			->addInnerJoin("OrderAttribute",(implode(" OR ",array(
				"\"Product_OrderItem_Discounts\".\"Product_OrderItemID\" = \"OrderAttribute\".\"ID\"",
				"\"OrderDiscountModifier_Discounts\".\"OrderDiscountModifierID\" = \"OrderAttribute\".\"ID\""
			))))
			->addInnerJoin("Order", "\"OrderAttribute\".\"OrderID\" = \"Order\".\"ID\"");
		$query->setGroupBy("\"Discount\".\"ID\"");
		$query->setLimit("50");

		return $query;
	}

}
