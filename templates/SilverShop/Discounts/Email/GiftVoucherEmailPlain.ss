<%t SilverShop\Discounts\Model\GiftVoucherOrderItem.EmailIntro "Here is your gift voucher" %>

<% with $Coupon %>
<%t SilverShop\Discounts\Model\GiftVoucherOrderItem.EmailCode "Code" %>: $Code
<% if $Type = 'Percent' %>
<%t SilverShop\Discounts\Model\GiftVoucherOrderItem.EmailPercent "Percent" %>: $Percent.Nice
<% else %>
<%t SilverShop\Discounts\Model\GiftVoucherOrderItem.EmailAmount "Amount" %>: $Amount.Nice
<% end_if %>
<% if $EndDate %>
<%t SilverShop\Discounts\Model\GiftVoucherOrderItem.EmailExpiry "This voucher must be used by {date}" date=$EndDate.Long %>
<% end_if %>
<% end_with %>
