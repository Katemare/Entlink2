<?
namespace Pokeliga\Entlink;

class AdminRouter extends Router
{
	public
		$page_actions=
		[
			'auth'		=>'#entlink.unimplemented',
			'modules'	=>'#entlink.admin_modules',
			'config'	=>'#entlink.admin_config'
		],
		$type_slugs=
		[
			'module'	=>
			[
				'id_group'	=>'Pokeliga\Entlink\ModuleEntity',
				'provider'	=>'module_by_dir'
			],
			'config'		=>'Pokeliga\Entlink\ModuleConfig'
		];
}



?>