<?
namespace Pokeliga\Pokeliga;

global $content_requirements;
$content_requirements=[];
// COMP

class Module_Pokeliga extends Module
{
	static $instance=null;
	public
		$name='Pokeliga',
		$quick_classes=
		[
			'Template_page_pokeliga'=>'Pokeliga_Template',
			
			'Tournament'=>'Tournament/Tournament',
			'Tournament_elimination'=>'Tournament/Tournament_elimination',
			
			'TournamentEntry'=>'Tournament/TournamentEntry',
			'TournamentBattle'=>'Tournament/TournamentBattle',
			'TournamentChar'=>'Tournament/TournamentChar'
		],
		$classex='/^(?<file>Tournament|TournamentEntry|TournamentBattle|TournmentChar)_/',
		$class_to_file=
		[
			'Tournament'=>'Tournament/Tournament',
			'TournamentEntry'=>'Tournament/TournamentEntry',
			'TournamentBattle'=>'Tournament/TournamentBattle',
			'TournamentChar'=>'Tournament/TournamentChar'
		],
		
		$type_slugs=
		[
			'tournament'		=>'Tournament',
			'tournament_entry'	=>'TournamentEntry',
			'tournament_battle'	=>'TournamentBattle',
			'tournament_char'	=>'TournamentChar'
		];
}
?>