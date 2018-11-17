<?php

namespace SilverShop\Discounts\Page;

use SilverShop\Page\ProductController;
use SilverStripe\Forms\CurrencyField;
use SilverShop\Discounts\Form\GiftVoucherFormValidator;

class GiftVoucherProductController extends ProductController
{
    private static $allowed_actions = [
        'Form'
    ];

    public function Form()
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
