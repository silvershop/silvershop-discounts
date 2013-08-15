<?php

class CouponReport extends ShopPeriodReport{
	
	protected $title = "Coupon Usage";
	protected $dataClass = "OrderCoupon";
	protected $periodfield = "Order.Paid";
	protected $description = "See the total savings for coupons. Note that the 'Entered' field may not be
										accurate if old/expired carts have been deleted from the database.";
	
	function columns(){
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
	function sortColumns() {
		$cols = parent::sortColumns();
		unset($cols['DiscountNice']);
		return $cols;
	}
	
	function getReportField(){
		$field = parent::getReportField();
		$field->addSummary("Total",array(
			"Entered"=>"sum",
			"Uses"=>"sum",
			"Savings"=> array("sum","Currency->Nice")
		));
		return $field;
	}
	
	function query($params){
		$query = parent::query($params);
		$query->setSelect(array(
			"$this->periodfield AS FilterPeriod",
			"OrderCoupon.*",
			"\"Title\" AS \"Name\"",
			"COUNT(OrderCouponModifier.ID) AS Entered",
			"SUM(if($this->periodfield IS NOT NULL, 1, 0)) AS Uses",
			"SUM(if($this->periodfield IS NOT NULL,OrderModifier.Amount,0)) AS Savings"
		));
		$query->innerJoin("OrderCouponModifier", "OrderCoupon.ID = OrderCouponModifier.CouponID");
		$query->innerJoin("OrderAttribute", "OrderCouponModifier.ID = OrderAttribute.ID");
		$query->innerJoin("OrderModifier", "OrderCouponModifier.ID = OrderModifier.ID");
		$query->innerJoin("Order", "OrderAttribute.OrderID = Order.ID");
		$query->setGroupBy("OrderCoupon.ID");
		if(!$query->getOrderBy()){
			$query->setOrderBy("Savings DESC,Title ASC");
		}
		$query->setLimit("50");
		return $query;
	}
	
}