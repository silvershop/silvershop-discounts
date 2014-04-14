<?php

class CouponReport extends ShopPeriodReport{

	protected $title = "Coupon Usage";
	protected $dataClass = "OrderCoupon";
	protected $periodfield = "Order.Paid";
	protected $description = "See the total savings for coupons. Note that the 'Entered' field may not be
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
		$query->setSelect("OrderCoupon.*")
			->selectField($this->periodfield, 'FilterPeriod')
			->selectField('Title', 'Name')
			->selectField('COUNT("OrderCouponModifier"."ID")', 'Entered')
			->selectField('SUM(if(' . $this->periodfield . ' IS NOT NULL, 1, 0))', 'Uses')
			->selectField('SUM(if(' . $this->periodfield . ' IS NOT NULL, "OrderModifier"."Amount", 0))', 'Savings');

		$query->addInnerJoin("OrderCouponModifier", "OrderCoupon.ID = OrderCouponModifier.CouponID");
		$query->addInnerJoin("OrderAttribute", "OrderCouponModifier.ID = OrderAttribute.ID");
		$query->addInnerJoin("OrderModifier", "OrderCouponModifier.ID = OrderModifier.ID");
		$query->addInnerJoin("Order", "OrderAttribute.OrderID = Order.ID");

		$query->setGroupBy("OrderCoupon.ID");
		if(!$query->getOrderBy()){
			$query->setOrderBy("Savings DESC,Title ASC");
		}
		$query->setLimit("50");

		return $query;
	}

}
