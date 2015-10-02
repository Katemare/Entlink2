<?
namespace Pokeliga\User;

// это настройки типов постов (записей). некоторые типы постов не отличаются технически (авторская картинка, авторский текст...), но отличаются по тому, где они размещаются, как и где показываются, какие имеют настройки по умолчанию, кто имеет право редактировать... Не хочется для каждого случая делать свой тип (EntityType) постов. к тому же так модеры смогут настраивать посты.

class PostPool extends EntityType
{
	const
		NOTIFY_NONE		=0,	// оповещения отключены.
		NOTIFY_ALL		=1, // оповещения включены.
		NOTIFY_EVENTS	=2, // включены только оповещения о создании (не о создании и утверждении)
		
		COMMENTS_OFF	=0,
		COMMENTS_SINGLE	=1,
		COMMENTS_THREADS=2,
		COMMENTS_FORUM	=3,
		
		RATINGS_OFF		=0,
		RATINGS_SCHOOL	=1,
		
		SCOPE_PUBLIC	=1, // проверенная работа становится доступна широкой публике.
		SCOPE_MODER		=2, // модеры видять проверенную работу во всяких лентах, но обычные пользователи нет.
		SCOPE_CUSTOM	=3, // проверенная работа используется как-то иначе, а в публичных лентах всплывать не должна.
		
		// независимо от авторства, наибольшие пользовательские права на работу у распорядителя (contributor)
		AUTHORLESS		=0, // для данного типа работ нет такого понятия как "автор"
		AUTHOR_SINGLE	=1, // строго один автор,
		AUTHORS_MULTIPLE=2, // возможно соавторство.
		
		CONTRIB_ONLY	=0, // загружающий - просто загрузил и не более того.
		CONTRIB_AUTO	=1, // загружающий по умолчанию автор, но допустимо иное.
		CONTRIB_AUTHOR	=2, // загружающий обязан быть автором.
		
		EDIT_FREE		=1,	// утверждённая работа может редактироваться распорядителем свобоно.
		EDIT_REQUEST	=2, // утверждённая работа может редактироваться распорядителем, под модерацию
		EDIT_OFF		=3, // редактирование утверждённой работы только у модеров.
		
		DELETE_FREE		=1, // утверждённая работа может свободно удаляться распорядителем.
		DELETE_REQUEST	=2, // утверждённая работа может удаляться или скрываться по запросу, решение за модератором.
		DELETE_OFF		=3; // утверждённая работа может быть удалена только модератором.
	
	static
		$init=false,
		$module_slug='users',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'		=>'PostPool_basic',
			'linking'	=>'PostPool_linking',
			'features'	=>'PostPool_features',
			'publish'	=>'PostPool_publishing'
		],
		$default_table='posts_pools';
}

class PostPool_basic extends Aspect
{
	static
		$common_model=
		[
			'post_type'=>
			[
				'type'=>'enum',
				'options'=>[Post::TYPE_IMAGE, Post::TYPE_TEXT, Post::TYPE_SPECIES, Post::TYPE_ADVENTURE, Post::TYPE_MISSION]
			],
			'section'=>
			[
				'type'=>'entity',
				'id_group'=>'SiteSection'
			],
		]
		$basic=true,
		$init=false,
		$default_table='posts_pools';
}

class PostPool_linking extends Aspect
{
	static
		$common_model=
		[
			// эти метки играют две функции: во-первых, доступны для данного потока несмотря на установку "general"; во-вторых, считаются приоритетными и показываются выше по списку.
			// метки, имеющие особый эффект, реализуемый типом поста (Post, Art и так далее), должны иметь general=false и должны быть доступны только в разделах, где они имеют смысл. также они не могут быть поставлены как персональные.
			'affiliated_tag_groups'=>
			[
				'type'=>'linkset',
				'id_group'=>'TagGroup',
				'select'=>'generic_linked',
				'position'=>Request_generic_links::FROM_OBJECT,
				'opposite_id_group'=>'PostPool',
				'relation'=>'affiliated'
			],
			'affiliated_tags'=>
			[
				// заполняется автоматически из предыдущего поля.
				'type'=>'linkset',
				'id_group'=>'Tag',
				'select'=>'flatten_groups',
				'groups_source'=>'affiliated_tag_groups'
			],
			
			// для каждой работы в момент создания из каждой из этих группы должна быть выбрана одна и строго одна метка. старые работы, создававшиеся при других категориях, могут не соблюдать это, но при редактировании для каждой категории может быть выбрано не большой одной метки.
			'categories'=>
			[
				'type'=>'linkset',
				'id_group'=>'TagGroup',
				'select'=>'generic_linked',
				'position'=>Request_generic_links::FROM_OBJECT,
				'opposite_id_group'=>'PostPool',
				'relation'=>'has_category'
			],
			'active_likes'=>
			[
				// "лайки", которые можно ставить сейчас.
				'type'=>'entity',
				'id_group'=>'TagGroup',
				'null'=>true,
				'auto_valid'=>[null],
				'default'=>null
			],
			
			'user_tags'=>
			[
				// могут ли пользователи предлагать новые метки, не являющиеся официальными?
				'type'=>'bool',
				'default'=>true
			],
			'personal_tags'=>
			[
				// могут ли пользователи ставить персональные метки?
				'type'=>'bool',
				'default'=>true
			],
			'link_rules'=>
			[
				'type'=>'linkset',
				'id_group'=>'LinkRule',
				'select'=>'generic_linked',
				'position'=>Request_generic_links::FROM_OBJECT,
				'opposite_id_group'=>'PostPool',
				'relation'=>'uses_link_rule'
			]
		],
		$init=false,
		$default_table='posts_pools';
}

class PostPool_features extends Aspect
{
	static
		$common_model=
		[
			'notifications'=>
			[
				// отсылает ли публикация, редактирование и другие события нотификацию?
				'type'=>'enum',
				'options'=>[PostPool::NOTIFY_NONE, PostPool::NOTIFY_ALL, PostPool::NOTIFY_EVENTS],
				'default'=>PostPool::NOTIFY_ALL
			],
			'comments'=>
			[
				// есть ли лента комментариев?
				'type'=>'enum',
				'options'=>[PostPool::COMMENTS_OFF, PostPool::COMMENTS_SINGLE, PostPool::COMMENTS_THREADS, PostPool::COMMENTS_FORUM],
				'default'=>PostPool::COMMENTS_SINGLE
			],
			'ratings'=>
			[
				// можно ли ставить оценки?
				'type'=>'enum',
				'options'=>[PostPool::RATINGS_OFF, PostPool::RATINGS_SCHOOL],
				'default'=>PostPool::RATINGS_SCHOOL
			],
			'reviews'=>
			[
				// можно ли писать обзоры?
				'type'=>'bool',
				'default'=>true
			],
			'collections'=>
			[
				// можно ли добавлять в избранное?
				'type'=>'bool',
				'default'=>true
			],
			'gallery'=>
			[
				// можно ли выставлять в галерее?
				'type'=>'bool',
				'default'=>true
			],
			'series'=>
			[
				'type'=>'keyword', // класс сущности серии.
				'null'=>true,
				'auto_valid'=>[null],
				'default'=>null
			],
			'follow'=>
			[
				// можно ли подписаться на события?
				'type'=>'bool',
				'default'=>true
			],
			'popularity'=>
			[
				// ведётся ли подсчёт популярности?
				'type'=>'bool',
				'default'=>true
			],
			'views'=>
			[
				// ведётся ли подсчёт просмотров?
				'type'=>'bool',
				'default'=>true
			],
			'trends'=>
			[
				// ведутся ли показываются ли тренды?
				'type'=>'bool',
				'default'=>true
			]
		],
		$init=false,
		$default_table='posts_pools';
}

class PostPool_publishing extends Aspect
{
	static
		$common_model=
		[
			'new_page_slug'=>
			[
				'type'=>'keyword',
				'null'=>true,
				'auto_valid'=>[null],
				'default'=>null
			],
			'external_author'=>
			[
				// может ли автор или соавтор работы быть неким внешним лицом, не зарегистрированным на сайте?
				'type'=>'bool',
				'default'=>false
			],
			'external_post'=>
			[
				// может ли работа вообще не иметь авторов среди пользователей Лиги? если она не AUTHORLESS, то указать хотя бы внешнего автора всё равно надо.
				'type'=>'bool',
				'default'=>false
			],
			'author_mode'=>
			[
				// какое количество авторов может или должно быть у работы?
				'type'=>'enum',
				'options'=>[PostPool::AUTHORLESS, PostPool::AUTHOR_SINGLE, PostPool::AUTHORS_MULTIPLE]
				'default'=>PostPool::AUTHOR_SINGLE
			],
			'contributor_mode'=>
			[
				// авторский статус загружающего
				'type'=>'enum',
				'options'=>[PostPool::CONTRIB_ONLY, PostPool::CONTRIB_AUTO, PostPool::CONTRIB_AUTHOR],
				'default'=>PostPool::CONTRIB_AUTHOR
			],
			
			'edit_page_slug'=>
			[
				'type'=>'keyword'
			],
			'edit_mode'=>
			[
				// кто имеет право редактировать утверждённую работу? все лица, перечисленные ниже (соавторы, бета), имеют право не выше этого;
				'type'=>'enum',
				'options'=>[PostPool::EDIT_FREE, PostPool::EDIT_REQUEST, PostPool::EDIT_OFF],
				'default'=>PostPool::EDIT_OFF
			],
			'coauthors_edit_mode'=>
			[
				// имеют ли право соавторы (если есть) редактировать работу? это также относится к редактированию работы "в работе" или "в доработке". если право распорядителя - FREE, а соавтора - REQUEST, то запрос утверждает распорядитель; иначе - модер. право соавтора не может быть выше права распорядителя.
				'type'=>'enum',
				'options'=>[PostPool::EDIT_FREE, PostPool::EDIT_REQUEST, PostPool::EDIT_OFF],
				'default'=>PostPool::EDIT_OFF
			],
			'allow_beta'=>
			[
				// может ли предлагать правки бета-читатель? его правки всегда проходят в режиме EDIT_REQUEST, и он не добавляется в соавторы даже при утверждении правок.
				'type'=>'bool',
				'default'=>false
			],
			'editor_group'=>
			[
				// можно поставить ответственную за раздел редакторскую группу, имеющую право редактировать и попадать в соавторы. её правки всегда идут в свободном режиме.
				'type'=>'entity',
				'id_group'=>'UserGroup',
				'null'=>true,
				'auto_valid'=>[null],
				'default'=>null
			],
			'delete_mode'=>
			[
				'type'=>'enum',
				'options'=>[PostPool::DELETE_FREE, PostPool::DELETE_REQUEST, PostPool::DELETE_OFF],
				'default'=>PostPool::DELETE_OFF
			],
			
			'scope'=>
			[
				// кому будет видна утверждённая работа?
				'type'=>'enum',
				'options'=>[PostPool::SCOPE_PUBLIC, PostPool::SCOPE_MODER, PostPool::SCOPE_CUSTOM],
				'default'=>PostPool::SCOPE_PUBLIC
			],
			'pending_public'=>
			[
				// видны ли публике неутверждённые работы?
				'type'=>'bool',
				'default'=>false
			],
			
			'desk'=>
			[
				// размер рабочего стола - работы "в работе", "на проверке" и "на доработке".
				'type'=>'unsigned_int',
				'max'=>100,
				'default'=>0
			],
			'wip'=>
			[
				// можно ли публиковать работы на рабочий стол, а не сразу на проверку?
				'type'=>'bool',
				'default'=>false
			],
			'desk_expiration'=>
			[
				// срок существования работы на рабочем столе, после которого она становится доступна модерам для просмотра и удаления.
				'type'=>'timespan',
				'null'=>true,
				'auto_valid'=>[null],
				'default'=>null
			],
			'approve'=>
			[
				// требуется ли проверка модером (премодерация)?
				'type'=>'bool',
				'default'=>true
			],
			'delayed_publishing'=>
			[
				// можно ли настроить отложенную публикацию?
				'type'=>'bool',
				'default'=>false
			],
			'delayed_publishing_gap'=>
			[
				// насколько максимально можно отложить публикацию?
				'type'=>'timespan',
				'default'=>315532800 // 10 лет
			],
		],
		$init=false,
		$default_table='posts_pools';
}

?>