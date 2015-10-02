<?
namespace Pokeliga\Nav;

// обеспечивает показ страниц, карту сайта.
class Module_Nav extends \Pokeliga\Entlink\Module
{
	use \Pokeliga\Entlink\Module_autoload_by_beginning;

	const
		FRONT_CLASS_NAME='\Pokeliga\Nav\NavFront';
	
	static
		$default_config=
		[
			'router_cache_key'=>'Nav:RootHub'
		];
	
	public
		$name='Nav',
		$quick_classes=
		[			
			'Page'				=>'Page',
			'Template_page'		=>'Page',
			'Page_spawner'		=>'Page_spawner',
			
			'Page_xml'			=>'Page_xml',
			'Page_view'			=>'Page_view',
			'Page_view_from_db'	=>'Page_view',
			'Page_operation'	=>'Page_operation',
			'Page_bad'			=>'Page_bad',
			
			'PageProcessor'		=>'PageProcessor',
			'PageTitle'			=>'PageTitle',
			'PageLocator'		=>'PageLocator',
			
			'Router'			=>'Router',
			'RouterHub'			=>'Router',
			'RootHub'			=>'RootHub',
			'SiteNode'			=>'SiteNode'
		],
		$classex='(?<file>Page_entity|Page_view|Page)[_$]';
}

class NavFront extends \Pokeliga\Entlink\ModuleFront
{
	public
		$root_router;
		
	public function get_root_router()
	{
		if ($this->root_router===null) $this->root_router=$this->create_root_router();
		return $this->root_router;
	}
	
	public function create_root_router()
	{
		return RootHub::for_module($this);
	}
}
?>