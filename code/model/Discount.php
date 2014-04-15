<?php

class Discount extends DataObject{
	
	private static $db = array(
		"Title" => "Varchar(255)", //store the promotion name, or whatever you like
		"Type" => "Enum('Percent,Amount','Percent')",
		"Amount" => "Currency",
		"Percent" => "Percentage",
		"Active" => "Boolean",

		"ForItems" => "Boolean",
		"ForShipping" => "Boolean",

		//Item / order validity criteria
		//"Cumulative" => "Boolean",
		"MinOrderValue" => "Currency",
		"UseLimit" => "Int",
		"StartDate" => "Datetime",
		"EndDate" => "Datetime"
	);

	private static $has_one = array(
		"Group" => "Group"
	);

	private static $many_many = array(
		"Products" => "Product", //for restricting to product(s)
		"Categories" => "ProductCategory",
		"Zones" => "Zone"
	);

	private static $defaults = array(
		"Type" => "Percent",
		"Active" => true,
		"UseLimit" => 0,
		//"Cumulative" => 1,
		"ForItems" => 1
	);

	private static $field_labels = array(
		"DiscountNice" => "Discount",
		"UseLimit" => "Maximum number of uses",
		"MinOrderValue" => "Minimum subtotal of order"
	);

	private static $summary_fields = array(
		"Title",
		"DiscountNice",
		"StartDate",
		"EndDate"
	);

	private static $singular_name = "Discount";
	private static $plural_name = "Discounts";

	private static $default_sort = "EndDate DESC, StartDate DESC";

	public function getCMSFields($params = null) {
		$fields = new FieldList(array(
			$tabset = new TabSet("Root",
				$maintab = new Tab("Main",
					TextField::create("Title"),
					CheckboxField::create("Active", "Active")
						->setDescription("Enable/disable all use of this discount."),
					new FieldGroup("This discount applies to:",
						CheckboxField::create("ForItems", "Item values"),
						CheckboxField::create("ForShipping", "Shipping cost")
					),
					HeaderField::create("Criteria", "Order and Item Criteria", 4),
					LabelField::create(
						"CriteriaDescription",
						"Configure the requirements an order must meet for this coupon to be used with it:"
					),
					new FieldGroup("Valid date range:",
						CouponDatetimeField::create("StartDate", "Start Date / Time"),
						CouponDatetimeField::create(
							"EndDate",
							"End Date / Time (you should set the end time to 23:59:59, if you want to include the entire end day)"
						)
					),
					CurrencyField::create("MinOrderValue", "Minimum order subtotal"),
					NumericField::create("UseLimit", "Limit number of uses")
						->setDescription("Note: 0 = unlimited")
				)
			)
		));
		if($this->isInDB()){
			if($this->ForItems){
				$tabset->push(new Tab("Products",
					LabelField::create("ProductsDescription", "Select specific products that this discount applies to"),
					GridField::create("Products", "Products", $this->Products(), new GridFieldConfig_RelationEditor())
				));
				$tabset->push(new Tab("Categories",
					LabelField::create("CategoriesDescription", "Select specific product categories that this discount applies to"),
					GridField::create("Categories", "Categories", $this->Categories(), new GridFieldConfig_RelationEditor())
				));
//				$products->setPermissions(array('show'));
//				$categories->setPermissions(array('show'));
			}

			$tabset->push(new Tab("Zones",
				$zones = new GridField("Zones", "Zones", $this->Zones(), new GridFieldConfig_RelationEditor())
			));

			$maintab->Fields()->push(
				DropdownField::create("GroupID",
					"Member Belongs to Group",
					Group::get()->map('ID', 'Title')
				)->setHasEmptyDefault(true)
					->setEmptyString('-- Any Group --')
			);

			if($this->Type == "Percent"){
				$fields->insertBefore(
					NumericField::create("Percent", "Percentage discount")
						->setDescription("e.g. 0.05 = 5%, 0.5 = 50%, and 5 = 500%"), 
					"Active"
				);
			}elseif($this->Type == "Amount"){
				$fields->insertBefore(
					NumericField::create("Amount", "Discount value"),
					"Active"
				);
			}
		}else{
			$fields->insertBefore(
				new OptionsetField("Type", "Type of discount",
					array(
						"Percent" => "Percentage of subtotal (eg 25%)",
						"Amount" => "Fixed amount (eg $25.00)"
					)
				),
				"Active"
			);
			$fields->insertAfter(
				LiteralField::create(
					"warning", 
					"<p class=\"message good\">
						More criteria options can be set after an intial save
					</p>"
				),
				"Criteria"
			);
		}
		$this->extend("updateCMSFields", $fields);

		return $fields;
	}


}