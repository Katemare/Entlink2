<?
namespace Pokeliga\Interactive;

abstract class Interactive_fiction extends Interactive
{
	public
		$things=[]; // соответствия айди => данные о штуке (место, объект, предмет, игрок, таймер, процедура...)
	
	public function make_character($user)
	{
	}
	
	public function apply_parsed($parsed)
	{
		return true;
	}
	
	public function apply_params($params)
	{
		return true;
	}
	
	public function apply_save($save)
	{
		return true;
	}
}

?>