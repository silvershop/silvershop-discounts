<?php

class CouponReport extends ShopPeriodReport{
	
	protected $title = "Coupon Usage";
	protected $dataClass = "OrderCoupon";
	protected $periodfield = "Order.Paid";
	
	function columns(){
		return array(
			"Name" => "Title",
			"Code" => "Code",
			"DiscountNice" => "Discount",
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
			"Uses"=>"sum",
			"Savings"=> array("sum","Currency->Nice")
		));
		return $field;
	}
	
	function query($params){
		$query = parent::query($params);
		$query->select(
			"$this->periodfield AS FilterPeriod",
			"OrderCoupon.*",
			"\"Title\" AS \"Name\"",
			"COUNT(OrderCouponModifier.ID) AS Uses",
			"SUM(OrderModifier.Amount) AS Savings");
		$query->innerJoin("OrderCouponModifier", "OrderCoupon.ID = OrderCouponModifier.CouponID");
		$query->innerJoin("OrderAttribute", "OrderCouponModifier.ID = OrderAttribute.ID");
		$query->innerJoin("OrderModifier", "OrderCouponModifier.ID = OrderModifier.ID");
		$query->innerJoin("Order", "OrderAttribute.OrderID = Order.ID");
		$query->groupby("OrderCoupon.ID");
		if(!$query->orderby){
			$query->orderby("Uses DESC,Title ASC");
		}
		$query->limit("50");
		return $query;
	}
	
}