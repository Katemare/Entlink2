<?
namespace Pokeliga\User;

class ValueType_login extends ValueType_title
{
	public
		$max=50;
	
	public function filter_out_bad_characters($content)
	{
		$result=trim(preg_replace('/['.static::BAD_TEXT_SYMBOLS.'\v,]/', '', $content));
	}
}

class ValueType_logins extends ValueType_title
{
	public
		$max=null;
		
	public function filter_out_bad_characters($content)
	{
		return trim(preg_replace('/['.static::BAD_TEXT_SYMBOLS.'\v]/', '', $content));
	}
	
	public function to_array()
	{
		$logins=explode(',', $this->content);
		foreach ($logins as &$login)
		{
			$login=trim($login);
		}
		return $logins;
	}
}
?>