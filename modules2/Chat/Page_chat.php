<?

class Page_chat extends Page_pokeliga_from_db
{
	public
		$db_key_base='chat',
		$db_key='main';
	
	public function pgtitle()
	{
		return 'Тестовый чат';
	}
	
	public function set_requirements()
	{
		$this->register_requirement('css', Engine()->module_url('Chat', 'frontend/chat.css'));
		$this->register_requirement('js', Engine()->module_url('Chat', 'frontend/chat.js'));
		$this->register_requirement('js', Engine()->module_url('Chat', 'frontend/fancywebsocket.js'));
	}
}

?>