(function($) {
$(document).ready(function() {
   var doList = function() {
     var currentModel = $('#ModelClassSelector').children('select');
     var currentModelName = $('option:selected', currentModel).val();
     var strFormname = "#Form_SearchForm" + currentModelName.replace('Form','');
     $(strFormname).submit();
     return false;
   }

   $('#ModelClassSelector').live("change",doList);
   $('button[name=action_clearsearch]').click(doList);
   $('#list_view').live("click",doList);

   if($('#list_view_loading').length) {
     doList();
   }
});
})(jQuery);