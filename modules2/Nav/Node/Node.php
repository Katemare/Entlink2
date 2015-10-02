<?

namespace Pokeliga\Nav;

interface Router
{
	public function route($route, $step=0);
}

trait Router_standard
{
	public function route($route, $step=0)
	{
		$result=$this->route_by_track($this->route_track($route, $step), $track_count);
		if ($result instanceof \Report_impossible or $result instanceof Page) return $result;
		if ($result instanceof Router) return $result->route($route, $step+$track_count);
		die('BAD ROUTE RESULT');
	}
	
	public function route_by_track($track, &$track_count=1)
	{
		return $this->no_route();
	}

	public function no_route()
	{
		return $this->sign_report(new \Report_impossible('no_route'));
	}
	
	public function route_track($route, $step=0)
	{
		return $route[$step];
	}
}

abstract class Node implements Router
{
	use Router_standard;
	
	public
		$nodeset,
		$nodeset_model=
		[
			'url'=>
			[
				'type'=>'url',
				'node_call'=>'generate_url'
			],
			'parent_node'=>
			[
				'type'=>'object',
				'class'=>'Pokeliga\Nav\Node',
				'null'=>true,
				'node_call'=>'get_parent_object'
			],
			'track'=>
		