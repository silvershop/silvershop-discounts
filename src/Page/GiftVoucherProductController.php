<?php

namespace SilverShop\Discounts\Page;

use SilverShop\Discounts\Form\GiftVoucherFormValidator;
use SilverShop\Page\ProductController;
use SilverStripe\Forms\CurrencyField;
use SilverStripe\Forms\Form;

/**
 * @extends ProductController<GiftVoucherProduct>
 */
class GiftVoucherProductController extends ProductController
{
    private static array $allowed_actions = [
        'Form'
    ];

    public function Form(): Form
    {
        $form = parent::Form();

        if ($this->VariableAmount) {
            $form->setSaveableFields(
                [
                    'UnitPrice'
                ]
            );
            $form->Fields()->push(
                $giftamount = CurrencyField::create('UnitPrice', _t('GiftVoucherProduct.Amount', 'Amount'), $this->BasePrice)
            );
            $giftamount->setForm($form);
        }
        $form->setValidator(
            $validator = new GiftVoucherFormValidator(
                [
                'Quantity',
                'UnitPrice'
                ]
            )
        );
        return $form;
    }
}
