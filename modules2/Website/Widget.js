Entlink.Widget=function construct()
{
	construct.superclass.apply(this, arguments);
	this.init();
}

extend(Entlink.Widget, Entlink.Component,
{
	init: function()
	{
		this.fill_skin();
		this.fill_element();
	},
	
	fill_skin: function()
	{
		this.skin=this.get_initial_skin();
	},
	get_initial_skin: function()
	{
		return this.engine.skin;
	},

	element: null,
	fill_element: function()
	{
		if (this.element) return;
		this.element=this.create_element();
		this.init_element();
	},
	create_element: function()
	{
		return document.createElement('div');
	},
	init_element: function()
	{
		this.element.className='widget';
		this.update_element_css();
	},
	update_element_css: function()
	{
		this.update_element_width();
		this.update_element_height();
	},
	
	_skin: null,
	get skin() { return this._skin; },
	set skin(val)
	{
		if (this._skin==val) return;
		this._skin=val;
		this.skin_updated();
	},	
	skin_updated: function() { }, // override me!
	
	_height: null,
	get height() { return this._height; },
	set height(val)
	{
		val=parseInt(val);
		if (height==val) return;
		this._height=val;
		this.update_element_height();
	},
	update_element_height: function()
	{
		if (this._height===null) this.element.style.height=null;
		else this.element.style.height=this._height+'px'
	},

	_width: null,
	get width() { return this._width; },
	set width(val)
	{
		val=parseInt(val);
		if (width==val) return;
		this._width=val;
		this.update_element_width();
	},
	update_element_width: function()
	{
		if (this._weight===null) this.element.style.weight=null;
		else this.element.style.width=this._width+'px';
	},

});

/*
Этот виджет - верхний заголовок сайта.
1) Он находится сверху, занимая верхние ХХ пикселей по всей ширине. Он скроллируется (не закрепляется).
2) Картинка может быть взята с общего скина или выбранного отдельно. Выбранная отдельно может быть из специального изображения для заголовков или из другого скина.
3) Картинка для заголовка состоит из следующих частей: левой части, правой части, средней части (опционально) и зацикленной части. Зацикленная часть обычно имеет в ширину 1 пиксель. Картинка преобразуется из изображения, загруженного в ФанАрт (перестраивается, чтобы CSS мог зациклить его как следует).
4) Картинка может иметь разную высоту, что определяется опциональным файлом CSS. Боковые и средняя часть могут иметь разный размер. Высота у всех элементов одна.
5) Картинку можно менять на лету: на одну из последних, избранных, вернуть по умолчанию; есть ссылки, переводящие в ФанАрт на поиск скинов и заголовков.
*/

Entlink.Widget.Header=function construct()
{
	construct.superclass.apply(this, arguments);
}

extend(Entlink.Widget.Header, Entlink.Widget,
{

});

/*
Этот виджет - часть боковой панели, правой или левой.
1) Он находится сбоку, прилепляясь к правому или левому краю. Виджеты этого типа располагаются вертикально друг за другом.
2) Картинка может быть взята с общего скина или выбрана отдельно из другого скина или специального набора.
3) Картинка состоит из следующих частей ???: четыре угловых части, горизонтально зацикленных сверху и снизу и вертикально зацикленной. Зацикленная часть имеет в толщину 1 пиксель. Картинка преобразуется из изображения, загруженного в ФанАрт.
4) Размерности картинок берутся из ФанАрта.
5) Картинку можно менять на лету: на другую из того же набора или скина; среди последних и любимых наборов; есть ссылки, переводящие в ФанАрт на поиск подходящихся скинов.
*/

Entlink.Widget.Sidebar=function construct()
{
	construct.superclass.apply(this, arguments);
}

extend(Entlink.Widget.Sidebar, Entlink.Widget,
{

});

/*
Этот виджет - часть верхнего горизонтального меню.
1) Он находится сверху, прямо под заголовком.
2) Картинка?
3) Части картинки?
4) Размерности картинок?
5) Картинку можно менять на лету?
*/

Entlink.Widget.Menu=function construct()
{
	construct.superclass.apply(this, arguments);
}

extend(Entlink.Widget.Menu, Entlink.Widget,
{

});