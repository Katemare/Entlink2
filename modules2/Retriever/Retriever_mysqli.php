<?
// namespace Entlink;

class Retriever_mysqli extends Retriever_operator
{
	use Logger_Retriever;
	
	public
		$db;

	// STUB
	public function connect()
	{
		$config=Engine()->config;
		
		$this->db=new mysqli($config['db_host'], $config['db_login'], $config['db_password'], $config['db_database']);
		$this->db->set_charset('utf8');
	}

	public function get_insert_id()
	{
		return $this->db->insert_id;
	}

	public function affected_rows()
	{
		return $this->db->affected_rows;
	}
	
	public function query($query)
	{
		$this->log('query', ['query'=>htmlspecialchars($query)]);
		return $this->db->query($query);
	}
	
	public function fetch($result)
	{
		return $result->fetch_assoc();
	}
	
	public function fetch_all($result)
	{
		// COMP!
		// return $result->fetch_all(MYSQLI_ASSOC);
		
		$rows=[];
		while ($row=$result->fetch_assoc())
		{
			$rows[]=$row;
		}
		return $rows;
	}
	
	public function safe_text($text)
	{
		return $this->db->real_escape_string($text);
	}
	
	public function start_transaction()
	{
		die('IMIMPLEMENTED YET: '.__METHOD__);
	}
	
	public function commit()
	{
		die('IMIMPLEMENTED YET: '.__METHOD__);
	}
	
	public function rollback()
	{
		die('IMIMPLEMENTED YET: '.__METHOD__);
	}
}
?>