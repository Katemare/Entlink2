<?
namespace Pokeliga\Entlink;

// пока никакого общего функционала не требуется.

class Module_Basic extends Module
{
	static
		$instance=null,
		$default_config=
		[
			'admin_route'=>'admin'
		];
	
	public
		$class_shorthands=
		[
			'Pokeliga\Entity\Select'=>
			[
				'installed_modules'
			],
		],
		$quick_classes=
		[
			'ModuleEntity'=>true,
			'ModuleConfig'=>true,
			'Select_installed_modules'=>'ModuleEntity'
			/*
			'Error'=>'Error',
			'ErrorHandler'=>'Error',
			'ErrorLogger'=>'Error'
			*/
		];
}
/*
class EntlinkFront extends ModuleFront implements \Pokeliga\Nav\RouteMapper
{
	public
		$admin_router;
		
	public function map_routes($hub)
	{
		if ($hub===Router()) $hub->add_keyword_route($this->get_config('admin_route', $this));
	}
	
	public function fill_router($hub, $route)
	{
		if ($hub===Router() and $route===$this->get_config('admin_route')) return $this->get_admin_router();
	}
	
	public function get_admin_router()
	{
		if ($this->admin_router===null) $this->admin_router=$this->create_admin_router();
		return $this->admin_router;
	}
	
	public function create_admin_router()
	{
		return AdminHub::for_module($this);
	}
}
*/
?>