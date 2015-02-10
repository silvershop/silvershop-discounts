<?php

/**
 * Shop Discount Migration 
 */
class ShopDiscountMigrationTask extends BuildTask{

	protected $title = "Shop Discounts Migration";
	
	public function run($request) {

		//check if OrderCouponModifier exists
		if(DB::getConn()->hasTable("OrderCouponModifier")){
			$this->log("OrderCouponModifier table exists");

			$query = DB::query(
				"SELECT *
				FROM OrderModifier
				INNER JOIN `OrderAttribute` ON `OrderModifier`.`ID` = `OrderAttribute`.`ID`
				INNER JOIN `OrderCouponModifier` ON `OrderModifier`.`ID` = `OrderCouponModifier`.`ID`
				INNER JOIN `Order` ON `OrderAttribute`.`OrderID` = `Order`.`ID`;"
			);
			foreach($query as $row){
				$newdata = $row;
				$newdata["ClassName"] = "OrderDiscountModifier";
				$newdata["ID"] = 0;
				if(
					!OrderDiscountModifier::get()
						->filter("OrderID", $newdata["OrderID"])
						->exists()
				){
					$newmodifier = new OrderDiscountModifier($newdata);
					$newmodifier->write();
					$discount = Discount::get()->byID($newdata["CouponID"]);
					//set up discount values
					if($discount && $discount->exists()){
						$newmodifier->Discounts()->add($discount, array(
							"DiscountAmount" => $newmodifier->Amount
						));
					}
					$this->log("Migrated modifier for Order ".$newdata["OrderID"]." ".$newmodifier->ID);
				}
			}
		}else{
			$this->log("OrderCouponModifier table does not exist");
		}
	}

	private function log($message){
		echo $message."\n";
	}

}
