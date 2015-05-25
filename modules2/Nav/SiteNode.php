<?

interface SiteNode
{
	public function node_url_template();
	
	public function node_title_template();
}

trait SiteNode_proxy
{
	public function node_url_template()
	{
		return $this->proxied_node()->node_url_template();
	}
	
	public function node_title_template()
	{
		return $this->proxied_node()->node_title_template();
	}
	
	public abstract function proxied_node();
}

?>