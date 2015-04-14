<?
class Module_File extends Module implements Templater
{
	use Module_autoload_by_beginning;
	static $instance=null;
	
	public
		$name='File',
		$quick_classes=
		[
			'File'=>'File',
			'Image'=>'Image',
			
			'ImageLocation'=>'ImageLocation',
			'Select_image_locations'=>'ImageLocation',
			
			'Validator_contains_point'=>'File_Data',
			'Validator_PiP'=>'File_Data',
			
			'Template_img'=>'Template_file',
			'Page_file'=>'Page_file'
		],
		$form_slugs=
		[
			'file_new_image_point'		=>'Form_image_new_point',
			'file_edit_image_point'		=>'Form_image_edit_point',
			'file_new_image_fragment'	=>'Form_image_new_fragment',
			'file_edit_image_fragment'	=>'Form_image_edit_fragment'
		],
		$classex='/^(?<file>Page_file|Template_file|Form_image|Page_image_location|Value_coord)[_$]/',
		$class_to_file=['Form_image'=>'File_Form', 'Page_image_location'=>'Page_file', 'Value_coord'=>'File_Data'];
		
	public function template($code, $line=[])
	{
		if ($code==='form_new_image_point')
		{
			$form=Form_image_new_point::create_for_display();
			$template=$form->main_template($line);
			return $template;
		}
		if ($code==='form_new_image_fragment')
		{
			$form=Form_image_new_fragment::create_for_display();
			$template=$form->main_template($line);
			return $template;
		}
	}
}

?>