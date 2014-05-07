<?php

/**
 * @package shop-discounts
 **/

class DiscountModelAdmin extends ModelAdmin {

	private static $url_segment = 'discounts';
	private static $menu_title = 'Discounts';
	private static $menu_icon = 'shop_discount/images/icon-coupons.png';
	private static $menu_priority = 2;

	private static $managed_models = array(
		"OrderCoupon",
		"OrderDiscount"
	);
	public static $model_importers = array();
	
	public function GenerateCouponsForm() {
		$fields = Object::create('OrderCoupon')->scaffoldFormFields();
		$fields->insertBefore(new HeaderField('generatorhead', 'Generate Coupons'), 'Title');
		$fields->insertBefore(new NumericField('Number', 'Number of coupons to generate'), 'Title');
		$fields->removeByName('Code');

		$fields->fieldByName('StartDate')->getDateField()->setConfig('showcalendar', true);
		//$fields->fieldByName('StartDate')->getTimeField()->setConfig('showdropdown',true);
		$fields->fieldByName('EndDate')->getDateField()->setConfig('showcalendar', true);
		//$fields->fieldByName('EndDate')->getTimeField()->setConfig('showdropdown',true);

		$actions = new FieldList(
			new FormAction('generate', 'Generate')
		);
		$validator = new RequiredFields(array(
			'Title',
			'Number'
		));
		return new Form($this, "GenerateCouponsForm", $fields, $actions, $validator);
	}

	public function generate($data, $form) {
		$count = 1;
		if(isset($data['Number']) && is_numeric($data['Number'])){
			$count = (int)$data['Number'];
		}
		for($i = 0; $i < $count; $i++){
			$coupon = new OrderCoupon();
			$form->saveInto($coupon);
			$coupon->Code = OrderCoupon::generate_code();
			$coupon->write();
		}

		return _t(
			"CouponsModelAdmin.GENERATEDCOUPONS",
			"Generated $count coupons, now click 'Search' to see them"
		);
	}

}