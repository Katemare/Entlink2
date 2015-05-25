<?

class PageTitle extends PageProcessor
{
	public function process()
	{
		return $this->page->immediate_title();
	}
}

?>