<?
namespace Pokeliga\User;

class Module_User extends Module implements ValueHost, Pathway
{
	use ValueHost_standard, Module_autoload_by_beginning
	{
		ValueHost_standard::ValueHost_request as std_ValueHost_request;
	}
	static $instance=null;
	
	public
		$name='User',
		$class_shorthands=
		[
			'Pokeliga\Data\ValueType'=>
			[
				'login', 'logins'
			],
		],
		$track_code='users',
		$quick_classes=
		[
			'User'					=>'User',
			'Prodive_user_by_login'	=>'User',
			
			'User_awards'			=>'Trophies/User_awards',
			
			'Value_login'			=>'User_Data',
			'Value_logins'			=>'User_Data',
			
			'UserLevel'				=>'UserLevel',
			'UserLog'				=>'UserLog',
			
			'Contribution'			=>'Posts/Contribution',
			'Contribution_identity'	=>'Posts/Contribution',
			'Contribution_signer'	=>'Posts/Contribution',
			'Select_by_availability'=>'Posts/Contribution',
			'Request_by_availability'		=>'Posts/Contribution',
			'Template_contribution_from_db'	=>'Posts/Contribution',
			
			'Page_user_api'			=>'User_API',
			
			'Trophy'				=>'Trophies/Trophy',
			
			'TrophyBlueprint'		=>'Trophies/TrophyBlueprint',
			
			'Trophy_owner'			=>'Trophies/Trophy_owner',
			
			'SiteSection'			=>'Posts/SiteSection',
			'Post'					=>'Posts/Post',
			'Art'					=>'Posts/Art',
			'PostUserlink'			=>'Posts/PostUserlink'
		],
		$classex='/^(?<file>User|Trophy_owner|TrophyBlueprint|Trophy|Form_trophy)[_$]/',
		$class_to_file=
		[
			'Trophy_owner'=>'Trophies/Trophy_owner',
			'TrophyBlueprint'=>'Trophies/TrophyBlueprint',
			'Trophy'=>'Trophies/Trophy',
			'Form_trophy'=>'Trophies/Form_trophy',
		],
		
		$type_slugs=
		[
			'user'				=>'User',
			'trophy'			=>'Trophy',
			'trophy_blueprint'	=>'TrophyBlueprint'
		];
	
	public function request($code) { return $this->ValueHost_request($code); }
	public function value($code) { return $this->ValueHost_value($code); }
	
	public function ValueHost_request($code)
	{
		if ($code==='current_user')
		{
			$current_user=User::current_user();
			if ( ($current_user instanceof \Pokeliga\Entity\Entity) && ($current_user->is_to_verify()) ) return $current_user->verify(false); // STUB
			return $this->sign_report(new \Report_resolution($current_user));
		}
		return $this->std_ValueHost_request($code);
	}
	
	public function follow_track($track, $line=[])
	{
		if ($track==='current_user') return User::current_user();
		return new \Report_unknown_track($track, $this);
	}
}

?>