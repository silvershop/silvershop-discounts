<?php

class GroupDiscountConstraint extends DiscountConstraint{

	private static $has_one = array(
		"Group" => "Group"
	);

	public function updateCMSFields(FieldList $fields) {
		$fields->addFieldToTab("Root.Main",
			DropdownField::create("GroupID",
				"Member Belongs to Group",
				Group::get()->map('ID', 'Title')
			)->setHasEmptyDefault(true)
			->setEmptyString('Any or no group')
		);
	}
	
	public function filter(DataList $list) {
		$groupids = array(0);
		if($member = Member::currentUser()){
			$groupids = $groupids + $member->Groups()
										->map('ID', 'ID')
										->toArray();
		}

		return $list->filter("GroupID", $groupids);
	}

	public function check(Discount $discount) {
		$group = $discount->Group();
		$member = (Member::currentUser()) ? Member::currentUser() : $this->order->Member(); //get member
		if($group->exists() && (!$member || !$member->inGroup($group))){
			$this->error(_t(
				"Discount.GROUPED", 
				"Only specific members can use this discount."
			));

			return false;
		}

		return true;
	}
	
}