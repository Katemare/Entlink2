
function navigate_search_results_page($new_page, $form_id, $page_tag_name)
{
	var $form=document.getElementById($form_id);
	if (!$form) return;
	
	var $page_tag=$form.children[$page_tag_name];
	if (!$page_tag) return;
	$page_tag.value=$new_page;
	
	var $button=document.getElementById($form_id+'_search');
	if (!$button) return;
	$button.click();
}