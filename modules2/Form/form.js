
function send_once(form_id)
{
	var form=document.getElementById(form_id);
	if (form.sent) return false;
	
	form.sent=true;
	setTimeout(5000, function() { form.sent=false; } );
}