<% require javascript(sapphire/thirdparty/tabstrip/tabstrip.js) %>
<% require css(sapphire/thirdparty/tabstrip/tabstrip.css) %>

<% require javascript(shop_discount/javascript/CouponsModelAdmin.js) %>

<div id="LeftPane">
	<div id="SearchForm_holder" class="leftbottom">		

		<% if SearchClassSelector = dropdown %>
			<p id="ModelClassSelector">
				<% _t('ModelAdmin.SEARCHFOR','Search for:') %>
				<select>
					<% control ModelForms %>
						<option value="{$Form.Name}_$ClassName">$Title</option>
					<% end_control %>
				</select>
			</p>
		<% end_if %>
		
		<% control ModelForms %>
			<div class="tab" id="{$Form.Name}_$ClassName">
				$Content
			</div>
		<% end_control %>
		
		<div class="tab">
			$GenerateCouponsForm
		</div>		
	</div>
</div>