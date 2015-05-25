<?

class PageLocator extends PageProcessor
{
	public function process()
	{
		return $this->page->url();
	}
}

?>