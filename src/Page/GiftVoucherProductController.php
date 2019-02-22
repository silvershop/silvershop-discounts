<?php

namespace SilverShop\Discounts\Page;

use SilverShop\Page\ProductController;
use SilverStripe\Forms\CurrencyField;

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
                "UnitPrice"
                ]
            );
            $form->Fields()->push(
                //TODO: set minimum amount
                $giftamount = new CurrencyField("UnitPrice", _t('GiftVoucherProduct.Amount', 'Amount'), $this->BasePrice)
            );
            $giftamount->setForm($form);
        }
        $form->setValidator(
            $validator = new GiftVoucherFormValidator(
                [
                "Quantity", "UnitPrice"
                ]
            )
        );
        return $form;
    }
}
