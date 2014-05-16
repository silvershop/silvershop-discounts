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
			"Uses" => "Uses",
			"Savings" => "Savings"
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

	public function getReportField() {
		$field = parent::getReportField();
//		$field->addSummary("Total",array(
//			"Entered"=>"sum",
//			"Uses"=>"sum",
//			"Savings"=> array("sum","Currency->Nice")
//		));
		return $field;
	}

	public function query($params) {
		$query = parent::query($params);
		$query->setSelect("Discount.*")
			->selectField($this->periodfield, 'FilterPeriod')
			->selectField('Title', 'Name')
			->selectField('COUNT("OrderAttribute"."ID")', 'Entered')
			->selectField('SUM(CASE WHEN ' . $this->periodfield . ' IS NOT NULL THEN 1 ELSE 0 END)', 'Uses')
			->selectField('SUM(CASE WHEN ' . $this->periodfield . ' IS NOT NULL THEN "OrderDiscountModifier_Discounts"."Amount" ELSE 0 END)', 'Savings');
		$query->addInnerJoin("OrderDiscountModifier_Discounts", "OrderDiscountModifier_Discounts.ID = Discount.ID");
		$query->addInnerJoin("OrderAttribute", "\"OrderDiscountModifier_Discounts\".\"OrderDiscountModifierID\" = \"OrderAttribute\".\"ID\"");
		$query->addInnerJoin("Order", "\"OrderAttribute\".\"OrderID\" = \"Order\".\"ID\"");
		$query->setGroupBy("\"Discount\".\"ID\"");
		if(!$query->getOrderBy()){
			$query->setOrderBy("Savings DESC,Title ASC");
		}
		$query->setLimit("50");

		return $query;
	}

}
