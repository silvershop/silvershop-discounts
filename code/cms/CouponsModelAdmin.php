<?php

/**
 * @package shop-discounts
 **/

class CouponsModelAdmin extends ModelAdmin {

	private static $menu_priority = 2;

	public static $collection_controller_class = "CouponsModelAdmin_CollectionController";
	public static $record_controller_class = "CouponsModelAdmin_RecordController";
	public static $managed_models = array("OrderCoupon");

	public static function set_managed_models(array $array) {
		self::$managed_models = $array;
	}
	public static function add_managed_model($item) {
		self::$managed_models[] = $item;
	}

	public static $url_segment = 'coupons';
	public static $menu_title = 'Coupons';
	public static $menu_icon = 'shop_discount/images/icon-coupons.png';

	public static $model_importers = array(
		'Product' => 'CouponBulkLoader',
	);

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
		return _t("CouponsModelAdmin.GENERATEDCOUPONS", "Generated $count coupons, now click 'Search' to see them");
	}

}