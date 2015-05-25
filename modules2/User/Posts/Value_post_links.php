<?

// это значение содержит набор ссылок (именно ссылок!) в сочетании с правилом о том, как следует их читать.
class Value_post_links extends Value_linkset
{
	public
		$link_rule,		// сущность типа LinkRule
		$post,			// работа, с перспективы которого рассматриваются ссылки.
		$perspective;	// одна из констант из LinkRule. указывает, является ли корневая сущность значения объектом или субъектом в данном LinkRule.
}

class Value_post_linkset extends Value_array implements Value_unkept
{
	
}
?>