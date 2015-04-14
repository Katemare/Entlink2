<?
class Module_User extends Module implements ValueHost, Pathway
{
	use ValueHost_standard, Module_autoload_by_beginning
	{
		ValueHost_standard::ValueHost_request as std_ValueHost_request;
	}
	static $instance=null;
	
	public
		$name='User',
		$track_code='users',
		$quick_classes=
		[
			'User'					=>'User',
			'Prodive_user_by_login'	=>'User',
			
			'User_awards'			=>'User_awards',
			
			'Value_login'			=>'User_Data',
			'Value_logins'			=>'User_Data',
			
			'Contribution'			=>'Contribution',
			'Contribution_identity'	=>'Contribution',
			'Contribution_signer'	=>'Contribution',
			'Select_by_availability'=>'Contribution',
			'Request_by_availability'		=>'Contribution',
			'Template_contribution_from_db'	=>'Contribution',
			
			'Page_user_api'			=>'User_API',
			
			'Trophy'				=>'Trophy',
			
			'TrophyBlueprint'		=>'TrophyBlueprint',
			
			'Trophy_owner'			=>'Trophy_owner'
		],
		$classex='/^(?<file>User|Trophy_owner|TrophyBlueprint|Trophy|Form_trophy)[_$]/';
	
	public function request($code) { return $this->ValueHost_request($code); }
	public function value($code) { return $this->ValueHost_value($code); }
	
	public function ValueHost_request($code)
	{
		if ($code==='current_user')
		{
			$current_user=User::current_user();
			if ( ($current_user instanceof Entity) && ($current_user->is_to_verify()) ) return $current_user->verify(false); // STUB
			return $this->sign_report(new Report_resolution($current_user));
		}
		return $this->std_ValueHost_request($code);
	}
	
	public function follow_track($track)
	{
		if ($track==='current_user')
		{
			return User::current_user();
		}
	}
}

?>