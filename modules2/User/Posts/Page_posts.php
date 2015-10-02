<?

namespace Pokeliga\User;

abstract class Page_posts extends Page_view
{
	public
		$popular=false,
		$tags=[],
		$author;
	
	abstract public function url_posts_base();
	
	public function url()
	{
		$url=[];
		if (!empty($base=$this->url_posts_base())) $url=array_merge($url, (array)$base);
		if ($this->popular) $url[]='popular';
		if (!empty($this->author))
		{
			$url[]='author';
			$url=$this->author->id_and_hint_to_url($url);
		}
		if (count($this->tags)==1)
		{
			$url[]='tag';
			$url[]=$this->tags[0];
		}
		elseif (count($this->tags)>1)
		{
			$url[]='tags';
			$url=array_merge($url, $this->tags);
		}
		$url=$this->url_plus_get($url);
		return $url;
	}
}

class Page_posts_all extends Page_posts
{
	public function url_posts_base() { }
}

class Page_posts_section extends Page_posts
{
	public
		$section;
		
	public function url_posts_base()
	{
		return $this->section->value('slug');
	}
}

class Page_posts_pool extends Page_posts
{
	public
		$post_pool;
		
	public function url_posts_base()
	{
		return $this->post_pool->value('slug');
	}
}

/*
class Page_posts_favorites extends Page_posts
{
	public
		$user;
		
	public function url_posts_base()
	{
		$base=['fav'];
		$base=$this->user->id_and_hint_to_url($base); // даже для собственной страницы избранного, чтобы можнобыло легко делиться.
		return $base;
	}
}
*/
?>