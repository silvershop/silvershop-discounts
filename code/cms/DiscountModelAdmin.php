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

	private static $allowed_actions = array(
		"generatecoupons",
		"GenerateCouponsForm"
	);

	public function getEditForm($id = null, $fields = null){
		$form = parent::getEditForm($id, $fields);
		if($grid = $form->Fields()->fieldByName("OrderCoupon")){
			$grid->getConfig()
				->addComponent(
					$link = new GridField_LinkComponent("Generate Multiple Coupons", $this->Link()."/generatecoupons"),
					"GridFieldExportButton"
				);
			$link->addExtraClass("ss-ui-action-constructive");
		}

		return $form;
	}

	/**
	 * Update results list, to include custom search filters
	 */
	public function getList() {
		$context = $this->getSearchContext();
		$params = $this->request->requestVar('q');
		$list = $context->getResults($params);
		if(isset($params['HasBeenUsed'])) {
			$list = $list
				->leftJoin("Product_OrderItem_Discounts", "\"Product_OrderItem_Discounts\".\"DiscountID\" = \"Discount\".\"ID\"")
				->leftJoin("OrderDiscountModifier_Discounts", "\"OrderDiscountModifier_Discounts\".\"DiscountID\" = \"Discount\".\"ID\"")
				->innerJoin("OrderAttribute", implode(" OR ", array(
					"\"OrderAttribute\".\"ID\" = \"Product_OrderItem_Discounts\".\"Product_OrderItemID\"",
					"\"OrderAttribute\".\"ID\" = \"OrderDiscountModifier_Discounts\".\"OrderDiscountModifierID\""
				)));
		}
		if(isset($params['Products'])) {
			$list = $list
				->innerJoin("Discount_Products", "Discount_Products.DiscountID = Discount.ID")
				->filter("Discount_Products.ProductID", $params['Products']);
		}
		if(isset($params['Categories'])) {
			$list = $list
				->innerJoin("Discount_Categories", "Discount_Categories.DiscountID = Discount.ID")
				->filter("Discount_Categories.ProductCategoryID", $params['Categories']);
		}		
		$this->extend('updateList', $list);

		return $list;
	}

	public function GenerateCouponsForm() {
		$fields = Object::create('OrderCoupon')->getCMSFields();
		$fields->removeByName('Code');
		$fields->removeByName('GiftVoucherID');
		$fields->removeByName('SaveNote');

		$fields->addFieldsToTab("Root.Main", array(
			NumericField::create('Number', 'Number of Coupons'),
			FieldGroup::create("Code",
				TextField::create("Prefix", "Code Prefix")
					->setMaxLength(5),
				DropdownField::create("Length","Code Characters Length",
					array_combine(range(5,20),range(5,20)),
					OrderCoupon::config()->generated_code_length
				)->setDescription("This is in addition to the length of the prefix.")
			)
		), "Title");
		
		$actions = new FieldList(
			new FormAction('generate', 'Generate')
		);
		$validator = new RequiredFields(array(
			'Title',
			'Number',
			'Type'
		));
		$form = new Form($this, "GenerateCouponsForm", $fields, $actions, $validator);
		$form->addExtraClass("cms-edit-form cms-panel-padded center ui-tabs-panel ui-widget-content ui-corner-bottom");
		$form->setAttribute('data-pjax-fragment', 'CurrentForm');
		$form->setHTMLID('Form_EditForm');
		$form->loadDataFrom(array(
			'Number' => 1,
			'Active' => 1,
			'ForCart' => 1,
			'UseLimit' => 1
		));
		return $form;
	}

	public function generate($data, $form) {
		$count = 1;
		if(isset($data['Number']) && is_numeric($data['Number'])){
			$count = (int)$data['Number'];
		}
		$prefix = isset($data['Prefix']) ? $data['Prefix'] : "";
		$length = isset($data['Length']) ? (int)$data['Length'] : 10;
		for($i = 0; $i < $count; $i++){
			$coupon = new OrderCoupon();
			$form->saveInto($coupon);
			$coupon->Code = OrderCoupon::generate_code(
				OrderCoupon::config()->generated_code_length,
				$prefix
			);
			$coupon->write();
		}
		$this->redirect($this->Link());
	}

	function generatecoupons() {
		return array(
			'Title' => 'Generate Coupons',
			'EditForm' => $this->GenerateCouponsForm(),
			'SearchForm' => '',
			'ImportForm' => ''
		);
	}

}