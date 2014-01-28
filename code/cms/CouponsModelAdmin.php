<?php

/**
 * @package shop-discounts
 **/

class CouponsModelAdmin extends ModelAdmin {

	static $menu_priority = 2;

	public static $collection_controller_class = "CouponsModelAdmin_CollectionController";
	public static $record_controller_class = "CouponsModelAdmin_RecordController";
	public static $managed_models = array("OrderCoupon");

	public static function set_managed_models(array $array) {
		self::$managed_models = $array;
	}
	public static function add_managed_model($item) {self::$managed_models[] = $item;}
	
	public static $url_segment = 'coupons';
	public static $menu_title = 'Coupons';
	
	public static $model_importers = array(
		'Product' => 'CouponBulkLoader',
	);
	
	function GenerateCouponsForm(){
		$fields = Object::create('OrderCoupon')->scaffoldFormFields();
		$fields->insertBefore(new HeaderField('generatorhead','Generate Coupons'),'Title');
		$fields->insertBefore(new NumericField('Number','Number of coupons to generate'),'Title');
		$fields->removeByName('Code');
		
		$fields->fieldByName('StartDate')->getDateField()->setConfig('showcalendar',true);
		//$fields->fieldByName('StartDate')->getTimeField()->setConfig('showdropdown',true);
		$fields->fieldByName('EndDate')->getDateField()->setConfig('showcalendar',true);
		//$fields->fieldByName('EndDate')->getTimeField()->setConfig('showdropdown',true);
		
		$actions = new FieldList(
			new FormAction('generate','Generate')
		);
		$validator = new RequiredFields(array(
			'Title',
			'Number'
		));
		return new Form($this,"GenerateCouponsForm",$fields,$actions,$validator);
	}
	
	function generate($data,$form){
		$count = 1;
		if(isset($data['Number']) && is_numeric($data['Number']))
			$count = (int)$data['Number'];
		for($i = 0; $i < $count; $i++){
			$coupon = new OrderCoupon();
			$form->saveInto($coupon);
			$coupon->Code = OrderCoupon::generateCode();
			$coupon->write();
		}
		return _t("CouponsModelAdmin.GENERATEDCOUPONS","Generated $count coupons, now click 'Search' to see them");
	}
	

}
/**
 * Removes empty before import option
 * @package shop-discount
 */
//class CouponsModelAdmin_CollectionController extends ModelAdmin_CollectionController {
//
//	 //note that these are called once for each $managed_models
//
//	function ImportForm(){
//		$form = parent::ImportForm();
//		if($form){
//			//EmptyBeforeImport checkbox does not appear to work for SiteTree objects, so removed for now
//			$form->Fields()->removeByName('EmptyBeforeImport');
//		}
//		return $form;
//	}
//
//	//TODO: Half-started attempt at modifying the way products are deleted - they should be deleted from both stages
//	function ResultsForm($searchCriteria){
//		$form = parent::ResultsForm($searchCriteria);
//		if($tf = $form->Fields()->fieldByName($this->modelClass)){
//			/*$tf->actions['create'] = array(
//				'label' => 'delete',
//				'icon' => null,
//				'icon_disabled' => 'cms/images/test.gif',
//				'class' => 'testlink'
//			);*/
//
//			/*$tf->setPermissions(array(
//				'create'
//			));*/
//		}
//		return $form;
//	}
//
//}

/**
 * @package shop-discount
 */
//class CouponsModelAdmin_RecordController extends ModelAdmin_RecordController{
//
//}
