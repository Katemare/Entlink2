var Entlink={};

Entlink.Engine=function construct(config)
{
	this.config=merge_objects(construct.DEFAULT_CONFIG, config);
	this.init();
}

Entlink.Engine.DEFAULT_CONFIG=
{
	default_skin: 'entlink'
};

extend(Entlink.Engine,
{
	config: null,
	init: function()
	{
		this.apply_config();
		this.load_prefs();
	},
	
	apply_config: function()
	{
	},
	
	skin: null,
	load_prefs: function()
	{
		var skin_cookie=readCookie('skin');
		if (skin) this.skin=Entlink.Skin.from_cookie(skin);
		if (!this.skin) this.skin=this.get_default_skin();
	},
	
	get_default_skin: function()
	{
		return Entlink.Skin.from_config(this.config.deault_skin);
	}
});

Entlink.Component=function construct(engine)
{
	this.engine=engine;
}
extend(Entlink.Component,
{
	engine: null;
});
