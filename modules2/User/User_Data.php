<?

class Value_login extends Value_title
{
	public
		$max=50;
	
	public function filter_out_bad_characters($content)
	{
		$result=trim(preg_replace('/['.static::BAD_TEXT_SYMBOLS.'\v,]/', '', $content));
	}
}

class Value_logins extends Value_title
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