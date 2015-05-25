<?
// обеспечивает показ страниц, карту сайта.
class Module_Nav extends Module
{
	use Module_autoload_by_beginning;
	static $instance=null;
	
	public
		$name='Nav',
		$quick_classes=
		[			
			'Page'=>'Page',
			'Template_page'=>'Page',
			
			'Page_xml'			=>'Page_xml',
			'Page_view'			=>'Page_view',
			'Page_view_from_db'	=>'Page_view',
			'Page_operation'	=>'Page_operation',
			'PageProcessor'		=>'PageProcessor',
			'PageTitle'			=>'PageTitle',
			'PageLocator'		=>'PageLocator',
			
			'Router'=>'Router',
			'SiteNode'=>'SiteNode'
		],
		$classex='/^(?<file>Page_entity|Page_view|Page)[_$]/';
}

?>