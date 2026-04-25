<?php

namespace SilverShop\Discounts\Extensions\Constraints;

use SilverShop\Discounts\Model\Discount;
use SilverShop\Model\OrderItem;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\ListboxField;

/**
 * @property ?string $ProductTypes
 */
class ProductTypeDiscountConstraint extends ItemDiscountConstraint
{
    private static array $db = [
        'ProductTypes' => 'Text'
    ];

    public function updateCMSFields(FieldList $fieldList): void
    {
        $owner = $this->getOwner();
        //multiselect subtypes of orderitem
        if ($owner->isInDB() && $owner->ForItems) {
            $fieldList->addFieldsToTab(
                'Root.Constraints.ConstraintsTabs.Product',
                [
                    HeaderField::create(
                        'ProductTypesHeading',
                        _t(__CLASS__ . '.PRODUCTTYPES', 'Product types'),
                        2
                    )->addExtraClass('grid-field__title'),
                    ListBoxField::create(
                        'ProductTypes',
                        '',
                        $this->getTypes(false, $owner) ?? []
                    )
                ]
            );
        }
    }

    public function check(Discount $discount): bool
    {
        $types = $this->getTypes(true, $discount);
        //valid if no categories defined
        if ($types === null || $types === []) {
            return true;
        }

        $incart = $this->itemsInCart($discount);
        if (!$incart) {
            $this->error(_t(__CLASS__ . '.PRODUCTTYPESNOTINCART', 'The required product type(s), are not in the cart.'));
        }

        return $incart;
    }

    /**
     * This function is used by ItemDiscountAction, and the check function above.
     */
    public function itemMatchesCriteria(OrderItem $orderItem, Discount $discount): bool
    {
        $types = $this->getTypes(true, $discount);
        if ($types === null || $types === []) {
            return true;
        }

        $buyable = $orderItem->Buyable();
        if (!is_object($buyable) || !method_exists($buyable, 'getClassName')) {
            return false;
        }

        return isset($types[$buyable->getClassName()]);
    }

    /** @return array<string, string>|null */
    protected function getTypes(bool $selected, Discount $discount): ?array
    {
        $types = $selected ? array_filter(explode(',', $discount->ProductTypes ?? '')) : $this->BuyableClasses();
        if (count($types) > 0) {
            $types = array_combine($types, $types);
            foreach (array_keys($types) as $type) {
                $name = singleton($type)->i18n_singular_name();
                $types[$type] = is_string($name) && $name !== '' ? $name : $type;
            }

            return $types;
        }

        return null;
    }

    /** @return array<class-string, class-string> */
    protected function BuyableClasses(): array
    {
        $implementors = ClassInfo::implementorsOf('Buyable');
        $classes = [];
        foreach ($implementors as $implementor) {
            $classes = array_merge($classes, array_values(ClassInfo::subclassesFor($implementor)));
        }

        $classes = array_combine($classes, $classes);

        if (array_key_exists(\SilverShop\Model\Variation\Variation::class, $classes)) {
            unset($classes[\SilverShop\Model\Variation\Variation::class]);
        }

        return $classes;
    }
}
