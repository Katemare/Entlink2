<?
namespace Pokeliga\Nav;

interface Router
{
	public function route($route);
}

interface RouteMapper
{
	public function map_routes($hub);
	public function fill_router($hub, $route);
}

abstract class RouterHub implements Router
{
	use Page_spawner;
	abstract public function route($route);
}

?>