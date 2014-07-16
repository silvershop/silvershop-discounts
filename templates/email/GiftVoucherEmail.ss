<p>Here is your gift voucher</p>

<% with Coupon %>
<div class="giftvoucher" style="padding:10px; font-size:2em;">
	<p>Code: <strong>$Code</strong></p>
	<% if Type = Percent %>
		<p>Percent: $Percent.Nice</p>
	<% else %>
		<p>Amount: $Amount.Nice</p>
	<% end_if %>
	
	<% if EndDate %>
		<p>This voucher must be used by $EndDate.Long</p>
	<% end_if %>
</div>
<% end_with %>