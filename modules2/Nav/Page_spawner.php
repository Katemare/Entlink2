<?

namespace Pokeliga\Nav;

trait Page_spawner
{
	public static function spawn_page_by_data($page_data, $parts=null, $route=null, $standard_class='Page_view_from_db')
	{
		$class=null;
		$db_key=null;
		if (is_array($page_data))
		{
			if (array_key_exists('db_key', $page_data)) $db_key=$page_data['db_key'];
			if (array_key_exists('page_class', $page_data)) $class=$page_data['page_class'];
			$generic_keys=[0, 1];
			if ($db_key===null)
			{
				foreach ($generic_keys as $index)
				{
					if (!array_key_exists($index, $page_data)) continue;
					if ($page_data[$index]{0}==='#')
					{
						$db_key=substr($page_data[$index], 1);
						$page_data['db_key']=$db_key;
						unset($page_data[$index]);
						break;
					}
				}
			}
			if ($class===null)
			{
				foreach ($generic_keys as $index)
				{
					if ($page_data[$index]{0}!=='#')
					{
						$class=$page_data[$index];
						$page_data['page_class']=$class;
						unset($page_data[$index]);
						break;
					}
				}
			}
		}
		elseif ($page_data{0}==='#')
		{
			$db_key=substr($page_data, 1);
			$page_data=['db_key'=>$db_key];
		}
		else
		{
			$class=$page_data;
			$page_data=[];
		}
		if ($class===null) $class=$standard_class;
		$page_data['page_class']=$class;
		
		if ($db_key!==null) $page=$class::with_db_key($db_key);
		else $page=new $class();
		if ($route!==null) $page->record_route($route);
		$page->apply_model($page_data);
		if ($parts!==null) $page->apply_query($parts);
		return $page;
	}
}

?>