<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Discounts\Model\Discount;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Versioned\Versioned;
use SilverShop\Model\OrderItem;
use SilverShop\Page\Product;

class ProductsDiscountConstraint extends ItemDiscountConstraint
{
    private static $db = [
        'ExactProducts' => 'Boolean'
    ];

    private static $many_many = [
        "Products" => Product::class
    ];

    public function updateCMSFields(FieldList $fields)
    {
        if ($this->owner->isInDB()) {
            $fields->addFieldsToTab(
                "Root.Constraints.ConstraintsTabs.Product",
                [
                    GridField::create(
                        "Products",
                        _t(__CLASS__.'SPECIFICPRODUCTS', "Specific products"),
                        $this->owner->Products(),
                        GridFieldConfig_RelationEditor::create()
                            ->removeComponentsByType(GridFieldAddNewButton::class)
                            ->removeComponentsByType(GridFieldEditButton::class)
                    ),
                    CheckboxField::create(
                        "ExactProducts",
                        _t(__CLASS__.'.ALLPRODUCTSINCART', "All the selected products must be present in cart.")
                    ),
                ]
            );
        }
    }

    public function check(Discount $discount)
    {
        $products = $discount->Products();

        // if no products in the discount even
        if (!$products->exists()) {
            $curr = Versioned::get_stage();

            Versioned::set_stage('Stage');
            $products = $discount->Products();

            if (!$products->exists()) {
                return true;
            }

            $constraintproductids = $products->map('ID', 'ID')->toArray();
            Versioned::set_stage($curr);
        } else {
            $constraintproductids = $products->map('ID', 'ID')->toArray();
        }

        // uses 'DiscountedProductID' so that subclasses of projects (say a custom nested set of products) can define the
        // underlying DiscountedProductID.
        $cartproductids = $this->order->Items()->map('ProductID', 'DiscountedProductID')->toArray();
        $intersection = array_intersect($constraintproductids, $cartproductids);

        $incart = $discount->ExactProducts ?
            array_values($constraintproductids) === array_values($intersection) :
            count($intersection) > 0;

        if (!$incart) {
            $this->error(
                _t('ProductsDiscountConstraint.MISSINGPRODUCT', "The required products are not in the cart.")
            );
        }

        return $incart;
    }

    public function itemMatchesCriteria(OrderItem $item, Discount $discount)
    {
        $products = $discount->Products();
        $itemproduct = $item->Product(true); // true forces the current version of product to be retrieved.

        if ($products->exists()) {
            foreach ($products as $product) {
                // uses 'DiscountedProductID' since some subclasses of buyable could be used as the item product (such as
                // a bundle) rather than the product stored.
                if ($product->ID == $itemproduct->DiscountedProductID) {
                    return true;
                }
            }

            $this->error(
                _t('ProductsDiscountConstraint.MISSINGPRODUCT', "The required products are not in the cart.")
            );

            return false;
        }

        return true;
    }
}
