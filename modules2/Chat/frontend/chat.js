"use strict"

function PersistentServer(wait, freq)
{
	this.map=new Map();
	this.access=new Map();
	this.servers=new Set();
	this.wait=wait || PersistentServer.DEFAULT_WAIT;
	this.freq=freq || PersistentServer.DEFAULT_FREQUENCY;
	setTimeout(this.check.bind(this), Math.max(this.freq*1000, this.wait*1000));
}

PersistentServer.DEFAULT_WAIT=3600; // 1 час
PersistentServer.DEFAULT_FREQUENCY=600; // 10 минут

PersistentServer.prototype.check=function()
{
	var it=this.map.keys(), now=new Date().getTime();
	for (var key=it.next().value; key!==undefined; key=it.next().value)
	{
		if (now-this.access.get(key).getTime() > this.wait*1000)
		{
			if (this.isUsed_callback && this.isUsed_callback(this.map.get(key))) continue;
			this.forget(key);
		}
	}
	this.resumeInterval();
}

PersistentServer.prototype.resumeInterval=function()
{
	if (this.map.length==0)
	{
		if (this.interval!==undefined) clearInterval(this.interval);
		this.interval=undefined;
		return;
	}
	if (this.interval!==undefined) return;
	this.interval=this.interval=setInterval(this.check.bind(this), this.freq*1000);
}

PersistentServer.prototype.set=function(key, val)
{
	if (!(val instanceof Object)) throw new Error();
	this.map.set(key, val);
	this.accessed(key);
	this.resumeInterval();
}
PersistentServer.prototype.has=function(key) { return this.map.has(key); }
PersistentServer.prototype.accessed=function(key)
{
	this.access.set(key, new Date());
}
PersistentServer.prototype.forget=function(key)
{
	this.map.delete(key);
	this.access.delete(key);
	this.resumeInterval();
	chat.debug('Забыты за ненадобростью данные '+key);
}
PersistentServer.prototype.get=function(key, create_callback)
{
	var val;
	if (this.map.has(key))
	{
		val=this.map.get(key);
		this.accessed(val);
	}
	if (!val)
	{
		val=this.trySubservers(key);
		if (val) this.set(key, val);
	}
	if (!val && create_callback)
	{
		val=create_callback();
		if (val) this.set(key, val);
	}
	return val;
}
PersistentServer.prototype.registerSubserver=function(server)
{
	this.servers.add(server);
}
PersistentServer.prototype.deregisterSubserver=function(server)
{
	this.servers.delete(server);
}
PersistentServer.prototype.trySubservers=function(key)
{
	var it=this.servers.values();
	for (var server=it.next().value; server; server=it.next().value)
	{
		var val=server.get(key);
		if (val) return val;
	}
}

function Chat(target)
{
	this.debugMode=true;
	this.target=target;
	this.createElement();
}

Chat.MAX_WAITING_TIME=5000;

Chat.prototype.createElement=function()
{
	this.element=document.createElement('div');
	this.element.className='chat';
}

Chat.prototype.init=function()
{
	this.identities=new PersistentServer();
	this.tabs=new Tabs(this);
	this.console=this.tabs.console;
	this.initServer(this.target);
	this.initProcessors();
}

Chat.prototype.initServer=function(target)
{
	this.server = new FancyWebSocket(target);
	this.server.bind('open', this.onConnected.bind(this));
	this.server.bind('close', this.onDisconnected.bind(this));
	this.server.bind('message', this.onMessage.bind(this));
}

Chat.prototype.initProcessors=function()
{
	this.processors=new Map();
	
	this.processors.priority=new Map();
	this.processors.priority.set('join', this.processJoin.bind(this));
	
	this.processors.set('info', this.processInfo.bind(this));
	this.processors.set('auth', this.processAuth.bind(this));
	this.processors.set('close', this.processClose.bind(this));
	this.processors.set('error', this.processError.bind(this));
	this.processors.set('ident', this.processIdent.bind(this));
	this.processors.set('threadMessage', this.processThreadMessage.bind(this));
}

Chat.prototype.start=function()
{
	this.init();
	this.server.connect();
}

Chat.prototype.onConnected=function() {}
Chat.prototype.onDisconnected=function(data)
{
	this.log('Соединение закрыто.', 'notify');
}
Chat.prototype.onMessage=function(content)
{
	try
	{
		this.debug('Получено: '+content);
		var message=Message.fromServerData(content);
		var method=this.getProcessorByMessage(message);
		if (method) method(message);
		else this.onUnknownMessage(message);
	}
	catch (e) { this.onException(e); }
}

Chat.prototype.getProcessorByMessage=function(message)
{
	if (this.processors.priority.has(message.code)) return this.processors.priority.get(message.code);
	if (message.thread!==undefined) return this.processors.get('threadMessage');
	else return this.processors.get(message.code);
}
Chat.prototype.onUnknownMessage=function(message)
{
	this.logError('Сервер прислал неизвестное сообщение. Возможно, механизм чата был обновлён: попробуйте обновить страницу.');
}
Chat.prototype.onException=function(e)
{
	this.logError('В работе чата произошла ошибка: '+e.toString()+'. Попробуйте обновить страницу.');
	throw e;
}

Chat.prototype.debug=function(text, type)
{
	if (this.debugMode) console.log(text);
}

Chat.prototype.log=function(text, type)
{
	if (this.console) this.console.print(text, type);
	else this.debug(text);
}

Chat.prototype.logError=function(text)
{
	this.log(text, 'error');
}

Chat.prototype.send=function(data)
{
	this.debug('Отправлено: '+data);
	this.server.send('message', data);
}

Chat.prototype.getTab=function(id)
{
	return this.tabs.getTab(id);
}

Chat.prototype.processInfo=function(message)
{
	this.info=message.content;
	this.info.ts_precision=Math.pow(10, this.info.ts_precision);
	this.log('Данные сервера получены.');
}
Chat.prototype.processAuth=function(message)
{
	this.log('Запрошены данные авторизации.');
	var token=message.getContent('token');
	if (token!==undefined)
	{
		createCookie('poketoken', token);
		return;
	}
	
	var authData={};
	var session=readCookie('favhue');
	if (session!==null) authData.session=session;
	else
	{
		token=readCookie('poketoken');
		if (token!==null) authData.token=token;
		else authData.blank=true;
	}
	var response=new Message('auth', authData);
	this.log('Отправлены данные авторизации.');
	response.send();
}
Chat.prototype.processClose=function(message)
{
	this.log('Сервер закрывает соединение: '+message.text);
}
Chat.prototype.processError=function(message)
{
	this.log('Сервер сообщает об ошибке: '+message.text);
}

Chat.prototype.processThreadMessage=function(message)
{
	var tab=message.tab;
	if (!tab)
	{
		this.debug('Сообщение для несуществующей вкладки: '+message.thread);
		return;
	}
	tab.processMessage(message);
}

Chat.prototype.processIdent=function(message)
{
	this.applyIdentityData(message.content);
}

Chat.prototype.processJoin=function(message)
{
	message.getContent('members').forEach(this.applyIdentityData.bind(this));
	var tab=this.tabs.newTab(message.thread);
	tab.title=message.getContent('title');
	this.tabs.activateTab(tab);
}

Chat.prototype.applyIdentityData=function(data)
{
	var ident=data.ident;
	var identity=this.tryGlobalIdentity(ident);
	if (!identity)
	{
		identity=new Identity(ident);
		this.registerGlobalIdentity(identity);
	}
	identity.applyData(data);
}

Chat.prototype.tryGlobalIdentity=function(ident)
{
	return this.identities.get(ident);
}

Chat.prototype.registerGlobalIdentity=function(identity)
{
	this.identities.set(identity.ident, identity);
}

function Message(code, content, ts, unparsedContent)
{
	this.code=code;
	this.ts=ts;
	if (content===undefined) this._content=null;
	else if (unparsedContent) this._unparsedContent=content;
	else if (!(content instanceof Object)) this._content={text: content};
	else this._content=content;
}

Object.defineProperty(Message.prototype, 'content',
{
    get: function()
	{
		if (this._content!==undefined) return this._content;
		if (this._unparsedContent!==undefined)
		{
			this._content=JSON.parse(this._unparsedContent);
			delete this._unparsedContent;
			return this._content;
		}
		throw new Error('no Message content');
	},
	set: function(val)
    {
		delete this._unparsedContent;
		this._content=val;
	}
});

Object.defineProperty(Message.prototype, 'ts',
{
    get: function()
	{
		if (this._ts===undefined || this._ts===null) return;
		if (this._ts instanceof Date) return this._ts;
		try
		{
			this._ts = parseInt(this._ts, 36)/chat.info.ts_precision + chat.info.start;
			if (isNaN(this._ts)) throw new Error('bad Message ts');
			this._ts=new Date(this._ts*1000);
		}
		catch (e)
		{
			this._ts=null;
			chat.onException(r);
		}
		return this._ts;
	},
	set: function(val)
    {
		this._ts=val;
	}
});

Object.defineProperty(Message.prototype, 'identity',
{
    get: function()
	{
		if (this._identity===null) return undefined;
		if (this._identity!==undefined) return this._identity;
		if (this.code!=='chat') return undefined;
		
		this._identity=this.getContent('identity');
		
		if (this._identity===undefined) this._identity=null;
		else if (!this.tab) this._identity=null;
		else this._identity=this.tab.serveIdentity(this._identity);
		return this.identity; // рекурсия
	}
});

Message.prototype.usesIdentity=function(identity)
{
	if (this._identity instanceof Identity) return this._identity===identity;
	var ident=this.getContent('identity');
	if (ident===undefined) return false;
	return identity.ident===ident;
}

Message.fromServerData=function(data)
{
	var m=/^([a-z\d]{1,6}):([a-z\d]+)(:(.+))?$/.exec(data);
	if (!m) throw new Error('bad server message');
	return new Message(m[1], m[4], m[2], true);
}

Message.forThread=function(tab, code, content, ts)
{
	if (content===undefined) content={};
	var message=new Message(code, content, ts)
	message.thread=tab;
	return message;
}

Message.prototype.getContent=function(code, def)
{
	if (this.content instanceof Object && this.content.hasOwnProperty(code)) return this.content[code];
	return def;
}

Object.defineProperty(Message.prototype, 'thread',
{
    get: function()
	{
		return this.getContent('thread');
	},
	set: function(val)
    {
		if (val instanceof Tab) val=val.id;
		this.content.thread=val;
	}
});

Object.defineProperty(Message.prototype, 'tab',
{
    get: function()
	{
		var thread=this.thread;
		if (thread===undefined) return;
		return chat.getTab(thread);
	},
	set: function(val)
    {
		this.thread=val;
	}
});

Object.defineProperty(Message.prototype, 'text',
{
    get: function()
	{
		return this.getContent('text');
	},
	set: function(val)
    {
		this.content.text=val;
	}
});

Message.prototype.getIdentityDataOrPromise=function(code, def)
{
	var identity=this.identity;
	if (identity===undefined) return def;
	if (!identity.isReady())
	{
		return this.promiseIdentity().then(function(identity) { return identity.getData(code, def); }, function() { return def; });
	}
	return this.identity.getData(code, def);
}

Message.prototype.promiseIdentity=function()
{
	var identity=this.identity;
	if (identity===undefined) throw new Error();
	if (identity.isReady()) return Promise.resolve(identity);
	return this.tab.promiseIdentity(identity);
}

Message.prototype.getTemplateElementOrPromise=function(code)
{
	if (code==='timestamp')
	{
		var ts=this.ts;
		if (ts===null) return;
		return ts.getHours()+':'+ts.getMinutes()+':'+ts.getSeconds();
	}
}

Message.prototype.compose=function()
{
	return this.code+':'+JSON.stringify(this.content);
}

Message.prototype.send=function()
{
	chat.send(this.compose());
}

function Identity(ident)
{
	this.ident=ident;
	this.usage=0;
}
has_custom_events(Identity);

Identity.prototype.request=function(tab)
{
	tab.send('ident', {id: this.ident});
}
Identity.prototype.promise=function(tab)
{
	if (this.isReady()) return Promise.resolve(this);
	if (!this._promise)
	{
		var me=this;
		this.request(tab);
		this._promise=new Promise(function(resolve, reject)
		{
			me.add_custom_listener('ready', resolve.bind(null, me));
			setTimeout(reject.bind(null, me), Chat.MAX_WAITING_TIME);
		});
	}
	return this._promise;
}
Identity.prototype.init=function(data)
{
	if (this.isReady()) throw new Error();
	this.data=new Map();
	this.copyData(data);
	if (this.isGlobal()) chat.registerGlobalIdentity(this);
	this.fire_custom_event('ready');
}
Identity.prototype.applyData=function(new_data)
{
	if (!this.isReady()) this.init(new_data);
	else if (this.isBad()) throw new Error();
	else
	{
		this.copyData(new_data);
		this.fire_custom_event('changed');
	}
}
Identity.prototype.copyData=function(new_data)
{
	for (var key in new_data)
	{
		if (key==='ident') continue;
		this.setData(key, new_data[key]);
	}
}
Identity.prototype.makeBad=function()
{
	if (this.isReady()) throw new Error();
	this.data=false;
	this.fire_custom_event('ready');
	this.fire_custom_event('bad');
}
Identity.prototype.isBad=function() { return this.data===false; }
Identity.prototype.isReady=function() { return this.data!==undefined; }
Identity.prototype.getData=function(code, def)
{
	if (this.isBad()) return def;
	if (this.isReady() && this.data.has(code)) return this.data.get(code);
	return def;
}
Identity.prototype.getDataOrPromise=function(code, def)
{
	if (!this.isReady()) return this.promise().then(function(identity) { return identity.getData(code, def); }, function() { return def; });
	return this.getData(code, def);
}
Identity.prototype.setData=function(code, val)
{
	if (!this.isReady() || this.isBad()) throw new Error();
	this.data.set(code, val);
}
Object.defineProperty(Tab.prototype, 'handle',
{
    get: function()
	{
		this.getData('handle');
	},
	set: function(val)
    {
		this.setData('handle', val);
	}
});

Identity.prototype.isGlobal=function()
{
	if (!this.isReady()) throw new Error();
	var group=this.getData('group');
	if (!group) return false;
	if (group==='persona') return false;
	return true;
}

Identity.prototype.registerUse=function() { this.usage++; }
Identity.prototype.deregisterUse=function() { this.usage--; }
Identity.prototype.isUsed=function() { this.usage>0; }

function Tabs(chat)
{
	this.chat=chat;
	this.defaultStyler=TabStyler_IRC;
	this.tabs={};
	this.createElement();
	this.console=this.createConsoleTab();
	this.activateTab(this.console);
}

Tabs.prototype.createElement=function()
{
	this.element=document.createElement('div');
	this.element.className='tabs';
	this.chat.element.appendChild(this.element);
	
	this.createHeadsElement();
	this.createContentElement();
}

Tabs.prototype.createHeadsElement=function()
{
	this.headsElement=document.createElement('ul');
	this.headsElement.className='heads';
	this.element.appendChild(this.headsElement);
}

Tabs.prototype.createContentElement=function()
{
	this.contentElement=document.createElement('div');
	this.contentElement.className='content';
	this.element.appendChild(this.contentElement);
}

Tabs.prototype.createConsoleTab=function()
{
	var tab=this.newTab(0);
	tab.title='Чат';
	return tab;
}

Tabs.prototype.newTab=function(id)
{
	if (this.tabs.hasOwnProperty(id)) throw new Error();
	return this.tabs[id]=new Tab(this, id, this.defaultStyler);
}
Tabs.prototype.removeTab=function(id)
{
	if (!this.tabs.hasOwnProperty(id)) return;
	this.headsElement.removeChild(this.tabs[id].headElement);
	this.contentElement.removeChild(this.tabs[id].contentElement);
	this.tabs[id].dispose();
	delete this.tabs[id];
}
Tabs.prototype.getTab=function(id)
{
	return this.tabs[id];
}

Tabs.prototype.activateTab=function(id)
{
	if (id instanceof Tab) id=id.id;
	if (!this.tabs[id]) throw new Error();
	if (this.activeTab) this.activeTab.deactivate();
	this.activeTab=this.tabs[id];
	this.activeTab.activate();
}

function Tab(master, id, styler)
{
	this.master=master;
	this.active=false;
	this.id=id;
	this._title='Канал';
	this.createElements();
	this.initStyler(styler);
	this.initProcessors();
	
	this.identities=new PersistentServer();
	this.identities.isUsed_callback=this.isIdentityUsed.bind(this);
	chat.identities.registerSubserver(this.identities);
}

Object.defineProperty(Tab.prototype, 'title',
{
    get: function()
	{
		return this._title;
	},
	set: function(val)
    {
		this._title=val;
		this.updateHeadElement();
	}
});

Tab.prototype.createElements=function()
{
	this.createHeadElement();
	this.createContentElement();
	this.log=[];
}

Tab.prototype.createHeadElement=function()
{
	this.headElement=document.createElement('li');
	this.headElement.addEventListener('click', this.onHeadClick.bind(this));
	this.master.headsElement.appendChild(this.headElement);
}

Tab.prototype.updateHeadElement=function()
{
	this.headElement.innerHTML=this.title;
}

Tab.prototype.onHeadClick=function()
{
	this.master.activateTab(this);
}

Tab.prototype.createContentElement=function()
{
	this.contentElement=document.createElement('div');
	this.contentElement.className='tab';
	this.master.contentElement.appendChild(this.contentElement);
}

Tab.prototype.initStyler=function(styler)
{
	if (styler instanceof Function) styler=new styler(this);
	this.styler=styler;
	this.styler.init();
}

Tab.prototype.initProcessors=function()
{
	this.processors={};
	this.processors.clear=this.processClear.bind(this);
	this.processors.notify=this.processNotify.bind(this);
	this.processors.chat=this.processChat.bind(this);
	this.processors.ident=this.processIdent.bind(this);
}

Tab.prototype.isIdentityUsed=function(identity)
{
	for (var x=0; x<this.log.length; x++)
	{
		if (this.log[x].usesIdentity(identity)) return true;
	}
	return this.styler.usesIdentity(identity);
}

Tab.prototype.serveIdentity=function(ident)
{
	if (!this.identities.has(ident)) this.identities.set(ident, chat.tryGlobalIdentity(ident) || new Identity(ident));
	return this.identities.get(ident);
}

Tab.prototype.promiseIdentity=function(identity)
{
	if (this.identities.get(identity.ident)!==identity) throw new Error();
	return identity.promise(this);
}

Tab.prototype.createMessage=function(code, content)
{
	if (content===undefined) content={};
	else if (!(content instanceof Object)) content={text: content};
	content.thread=this.id;
	return new Message(code, content);
}

Tab.prototype.activate=function()
{
	if (this.active) return;
	this.headElement.classList.add('active');
	this.contentElement.classList.add('active');
	this.active=true;
	
	this.createMessage('active');
}

Tab.prototype.deactivate=function()
{
	if (!this.active) return;
	this.headElement.classList.remove('active');
	this.contentElement.classList.remove('active');
	this.active=false;
}

Tab.prototype.clear=function()
{
	this.log.length=0;
	this.styler.clear();
}

Tab.prototype.print=function(text, type)
{
	this.styler.print(text, type);
}

Tab.prototype.processMessage=function(message)
{
	var method=this.getProcessorByMessage(message);
	if (!method) chat.onUnknownMessage(message);
	else method(message);
}

Tab.prototype.getProcessorByMessage=function(message)
{
	return this.processors[message.code];
}

Tab.prototype.processClear=function(message)
{
	this.clear();
}

Tab.prototype.processNotify=function(message)
{
	this.print(message.text, 'notify');
}

Tab.prototype.processChat=function(message)
{
	this.styler.logMessage(message);
}

Tab.prototype.processIdent=function(message)
{
	var ident=message.getContent('ident');
	var identity=this.serveIdentity(ident);
	if (message.getContent('bad_identity')) identity.makeBad();
	else identity.applyData(message.content);
}

Tab.prototype.send=function(code, content)
{
	if (!(content instanceof Object)) content={text: content};
	var message=new Message(code, content);
	message.tab=this;
	message.send();
}

Tab.prototype.dispose=function()
{
	chat.identities.deregisterSubserver(this.identities);
}

function TabStyler(tab)
{
	this.tab=tab;
	this.render_chain=[];
	this.render_busy=false;
	this.styleClass='standard';
}

TabStyler.prototype.init=function()
{
	if (this.styleClass!==null) this.tab.contentElement.classList.add(this.styleClass);
	this.createElements();
}

TabStyler.prototype.createElements=function()
{
	this.createLogElement();
	this.createInputElement();
}

TabStyler.prototype.createInputElement=function()
{
	this.inputElement=document.createElement('input');
	this.inputElement.className='message_input';
	this.inputElement.addEventListener('keypress', this.onKeypress.bind(this));
	this.tab.contentElement.appendChild(this.inputElement);
}

TabStyler.prototype.createLogElement=function()
{
	this.logElement=document.createElement('div');
	this.logElement.className='chatlog';
	this.tab.contentElement.appendChild(this.logElement);
}

TabStyler.prototype.onKeypress=function(e)
{
	if (e.keyCode==13 && this.inputElement.value!='') this.send();
}

TabStyler.prototype.usesIdentity=function(identity) { return false; }

TabStyler.prototype.send=function()
{
	this.tab.send('chat', this.inputElement.value);
	this.inputElement.value='';
}

TabStyler.prototype.clear=function()
{
	this.logElement.children.length=0;
}

TabStyler.prototype.drawLog=function(messages)
{
	messages.walk(this.chainMessage);
	this.afterDrawLog();
}

TabStyler.prototype.logMessage=function(message)
{
	this.chainMessage(message);
	this.afterDrawLog();
}

TabStyler.prototype.afterDrawLog=function()
{
	while (this.logElement.children.length>this.tab.maxLength) this.logElement.removeChild(this.tab.contentElement.firstChild);
}

TabStyler.prototype.chainMessage=function(message)
{
	if (this.render_busy) this.render_chain.push(message);
	else this.drawMessage(message);
}

TabStyler.prototype.drawMessage=function(message)
{
	var render=this.renderMessage(message);
	if (render instanceof Promise)
	{
		var me=this;
		this.render_busy=true;
		render.then(function(result)
		{
			me.chainProceed();
		});
	}
	else return true;
}

TabStyler.prototype.chainProceed=function()
{
	this.render_busy=false;
	var message=this.render_chain.pop();
	while (message && this.drawMessage(message)) message=this.render_chain.pop();
}

TabStyler.prototype.renderMessage=function(message)
{
	var element=this.createMessageElement(message), me=this;
	if (element instanceof Promise) return element.then(function(element) { me.placeMessage(element); });
	this.placeMessage(element);
}

TabStyler.prototype.placeMessage=function(rendered_message)
{
	this.logElement.appendChild(rendered_message);
}

TabStyler.prototype.createMessageElement=function(message)
{
	throw new Error('style not implemented');
}

TabStyler.prototype.print=function(text, type)
{
	var message=new Message(type || 'notify', text, new Date());
	this.logMessage(message);
}

TabStyler.prototype.dispose=function() { }

function TabStyler_template(tab, template)
{
	TabStyler.call(this, tab);
	this.keywordClass=TemplateKeyword;
	this.usePlaceholders=true;
	
	this.templates={};
	if (template===undefined) template=TabStyler_template.TEMPLATE_DEFAULT;
	this.addTemplate(template, 'default');
}
extend(TabStyler_template, TabStyler);

TabStyler_template.TEMPLATE_DEFAULT='%timestamp%: %text%';

TabStyler_template.prototype.addTemplate=function(template, type)
{
	this.templates[type]=this.compile(template);
}

TabStyler_template.prototype.compile=function(text)
{
	var arr=text.split('%'), template=[], keyword=false;
	for (var x=0; x<arr.length; x++, keyword=!keyword)
	{
		if (!keyword && arr[x]==='') continue;
		if (!keyword) template.push(arr[x]);
		else if (x==arr.length-1) template.push('%'+arr[x]);
		else template.push(new this.keywordClass(this, arr[x]));
	}
	return template;
}

TabStyler_template.prototype.createMessageElement=function(message)
{
	var parsed=this.parseTemplate(message), me=this;
	if (parsed instanceof Promise) return parsed.then(function(result) { return me.frameRenderedMessage(message, result); });
	else return this.frameRenderedMessage(message, parsed);
}

TabStyler_template.prototype.frameRenderedMessage=function(message, rendered_message)
{
	var element=document.createElement('div');
	element.className=message.code;
	if (rendered_message instanceof Array)
	{
		for (var x=0; x<rendered_message.length; x++) element.appendChild(rendered_message[x]);
	}
	else element.innerHTML=rendered_message;
	return element;
}

TabStyler_template.prototype.parseTemplate=function(message)
{
	var template;
	if (this.templates.hasOwnProperty(message.code)) template=this.templates[message.code];
	else template=this.templates.default;
	
	var result=template.map(function(template_element)
	{
		if (template_element instanceof TemplateKeyword) return template_element.parse(message, true);
		else return template_element;
	});
	var me=this;
	if (result.some(function(element) { return element instanceof Promise; }))
		return Promise.all(result).then(function(result) { return me.joinTemplate(result); });
	else return this.joinTemplate(result);
}

TabStyler_template.prototype.joinTemplate=function(result)
{
	if (!result.some(function(element) { return element instanceof Node; })) return result.join('');
	
	var join=[], temp;
	for (var x=0; x<result.length; x++)
	{
		if (result[x] instanceof Node) join.push(result[x]);
		else
		{
			temp=document.createElement('div');
			temp.innerHTML=result[x];
			while (temp.firstChild)
			{
				join.push(temp.firstChild);
				temp.removeChild(temp.firstChild);
			}
		}
	}
	return join;
}

function TemplateKeyword(styler, keyword)
{
	this.styler=styler;
	this.keyword=keyword;
}
TemplateKeyword.prototype.parse=function(message, def)
{
	var result, resolve=function(res) { result=res; return true; }
	
	def=this.defaultResult(def);
	this.tryResult(message.getContent(this.keyword), resolve) ||
		this.tryResult(message.getTemplateElementOrPromise(this.keyword), resolve) ||
		this.tryResult(message.getIdentityDataOrPromise(this.keyword, def), resolve);
	
	if (result instanceof Promise)
	{
		if (this.styler.usePlaceholders)
		{
			var element=this.createPlaceholderElement();
			result.then(function(result) { element.innerHTML=result; }, function(reason) { this.innerHTML=reason; });
			return element;
		}
		else return result.catch(function(reason) { return reason; });
	}
	return result;
}

TemplateKeyword.prototype.createPlaceholderElement=function()
{
	var element=document.createElement('div');
	element.className='placeholder';
	element.appendChild(document.createTextNode('(Загрузка)'));
	return element;
}

TemplateKeyword.prototype.tryResult=function(result, resolve)
{
	if (result instanceof Promise) return resolve(result);
	else if (result!==undefined && result!==null) return resolve(result);
	return false;
}

TemplateKeyword.prototype.defaultResult=function(def)
{
	if (def===true) return '%'+this.keyword+'%';
	return def;
}

function TabStyler_IRC(tab)
{
	TabStyler_template.call(this, tab);
	this.addTemplate(TabStyler_IRC.TEMPLATE_CHAT, 'chat')
}
extend(TabStyler_IRC, TabStyler_template);
TabStyler_IRC.TEMPLATE_CHAT='<span class="ts">%timestamp%</span> <span class="handle">%handle%<span>: <span class="message_text">%text%</span>';