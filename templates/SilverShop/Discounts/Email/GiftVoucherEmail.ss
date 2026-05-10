<div class="silvershop-gift-voucher-email">
    <p class="silvershop-gift-voucher-email__intro">Here is your gift voucher</p>

    <% with $Coupon %>
        <div class="silvershop-gift-voucher" style="padding:10px; font-size:2em;">
            <p class="silvershop-gift-voucher__code">Code: <strong class="silvershop-gift-voucher__code-value">$Code</strong></p>
            <% if $Type = 'Percent' %>
                <p class="silvershop-gift-voucher__amount silvershop-gift-voucher__amount--percent">Percent: $Percent.Nice</p>
            <% else %>
                <p class="silvershop-gift-voucher__amount silvershop-gift-voucher__amount--fixed">Amount: $Amount.Nice</p>
            <% end_if %>

            <% if $EndDate %>
                <p class="silvershop-gift-voucher__expiry">This voucher must be used by $EndDate.Long</p>
            <% end_if %>
        </div>
    <% end_with %>
</div>
