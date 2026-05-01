Here is your gift voucher

<% with $Coupon %>
Code: $Code
<% if $Type = 'Percent' %>
Percent: $Percent.Nice
<% else %>
Amount: $Amount.Nice
<% end_if %>
<% if $EndDate %>
This voucher must be used by $EndDate.Long
<% end_if %>
<% end_with %>
