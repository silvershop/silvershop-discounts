<div class="silvershop-gift-voucher-email">
    <p class="silvershop-gift-voucher-email__intro"><%t SilverShop\Discounts\Model\GiftVoucherOrderItem.EmailIntro "Here is your gift voucher" %></p>

    <% with $Coupon %>
        <div class="silvershop-gift-voucher" style="padding:10px; font-size:2em;">
            <p class="silvershop-gift-voucher__code"><%t SilverShop\Discounts\Model\GiftVoucherOrderItem.EmailCode "Code" %>: <strong class="silvershop-gift-voucher__code-value">$Code</strong></p>
            <% if $Type = 'Percent' %>
                <p class="silvershop-gift-voucher__amount silvershop-gift-voucher__amount--percent"><%t SilverShop\Discounts\Model\GiftVoucherOrderItem.EmailPercent "Percent" %>: $Percent.Nice</p>
            <% else %>
                <p class="silvershop-gift-voucher__amount silvershop-gift-voucher__amount--fixed"><%t SilverShop\Discounts\Model\GiftVoucherOrderItem.EmailAmount "Amount" %>: $Amount.Nice</p>
            <% end_if %>

            <% if $EndDate %>
                <p class="silvershop-gift-voucher__expiry"><%t SilverShop\Discounts\Model\GiftVoucherOrderItem.EmailExpiry "This voucher must be used by {date}" date=$EndDate.Long %></p>
            <% end_if %>
        </div>
    <% end_with %>
</div>
