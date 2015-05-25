Entlink.Skin=function construct(engine, css_file)
{
	construct.superclass.apply(this, [engine]);
	this.css_file=css_file;
	this.apply();
}

Entlink.Skin.from_cookie=function(keyword)
{
}

Entlink.Skin.from_config=function(keyword)
{
}

extend(Entlink.Skin, Entlink.Component,
{
	css_file: null,
	header_css_file: null,
	
	_element: null,
	get element()
	{
		if (!this._element) this._element=this.create_css_element(this.css_file);
		return this._element;
	},
	_header_element: null,
	get header_element()
	{
		if (!this.header_css_file) return;
		if (!this._header_element) this._header_element=this.create_css_element(this.heade_css_file);
		return this._header_element;
	},
	create_css_element: function(filename)
	{
		var element=document.createElement('link');
		element.setAttribute('rel', 'stylesheet');
		element.setAttribute('type', 'text/css');
		element.setAttribute('href', filename);
		return element;
	},
	
	apply: function()
	{
		if ((this.element) && (!this.element.parentNode)) this.engine.css_container.appendChild(this.element);
		if ((this.header_element) && (!this.header_element.parentNode)) this.engine.css_container.appendChild(this.header_element);
	}
});