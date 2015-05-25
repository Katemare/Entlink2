<?

class PageBreadcrumbs extends PageProcessor
{
	public function process()
	{
		$nodes=$this->page->generate_breadcrumbs();
		if (empty($nodes)) return;
		return new NodeSet(...$nodes);
	}
}

class NodeSet extends MonoSet
{
	public function __construct(...$nodes)
	{
		foreach ($nodes as $node)
		{
			$this->add($node);
		}
	}
}

?>