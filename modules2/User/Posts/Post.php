<?
namespace Pokeliga\User;

/*
WIP
в отличие от Contribution, которое просто обеспечивает необходимость многих сущностей в единообразных описаниях и авторстве, это - настоящая публикация, предназначенная для демонстрации пользователям в лентах обновлений, с метками и так далее.

Посты (работы) могут быть двух видов:
- находящиеся в группе айди Post, чьим базовым аспектом является Post_basic. как правило, это типовое содержимое (рисунки, тексты...).
- находящиеся в своей группе айди. как правило, это сложные типы сущностей, к которым функциональность Post'а добавена позже.
В обоих случаях функциональность сущности как Post находится в аспекте Post_info или его наследнике. также требуется, чтобы среди аспектов был Contribution_indentity!

*/

class Post extends EntityType
{
	const
		TYPE_IMAGE		=1,	// авторское изображение
		TYPE_TEXT		=2,	// авторская статья или фанфик
		
		MOD_NOT_CHECKED		=0,	// на модерации, решение ещё не вынесено.
		MOD_APPROVED		=1, // решение вынесено - работа принята.
		MOD_REJECTED		=2, // решение вынесено - работа отклонена; редактирование не допускается, только чтобы автор мог сохранить копию перед удалением.
		MOD_WIP				=3,	// работа создана, но ещё не готова для модерации.
		MOD_STABILIZED		=4, // на модерации, решение ещё не вынесено, но редактирование уже отключено.
		MOD_POLISH			=5, // решение вынесено - работа принята, но ещё не преобразована модерами в итоговый формат.
		MOD_AUTO_APPROVED	=6, // автоматически утверждено, то есть лично модеры всё-таки не смотрели.
		MOD_REVISE			=7; // отправлено на доработку. автор может отредактировать и предложить снова.
	
	static
		$init=false,
		$module_slug='users',
		$data_model=[],
		$map=[],
		$pathway_tracks=[],
		$base_aspects=
		[
			'basic'		=>'Post_basic',
			'info'		=>'Post_info',
			'identity'	=>'Contribution_identity'
		],
		$by_post_type=
		[
			Post::TYPE_IMAGE	=>'Art',
			Post::TYPE_TEXT		=>'Article',
			Post::TYPE_SPECIES	=>'Species',
			Post::TYPE_ADVENTURE=>'Adventure',
			Post::TYPE_MISSION	=>'Mission' // FIX! требует миграции...
		],
		$default_table='posts',
		$id_group='Post';
	
	// пока обрабатывает только один случай: до верификации мы знаем только, что событие "Post", а после оно должно стать соответствующим типом работы. новые работы должны создаваться уже с указанным типом. FIX! потом должно обрабатывать все случая перетекания одного типа работ в другой.
	public function resolve_type()
	{
		if (get_class($this) !== get_class()) return $this; // эта реализация только для класса Post, а не его наследников.
		
		$get_refined_type=function()
		{
			$post_type=$this->entity->value('post_type');
			if (!array_key_exists($post_type, static::$by_post_type)) die('BAD POST TYPE');
			$type_code=static::$by_post_type[$post_type];
			return $this->refine_type($type_code);
		};
		$refine_type=function() use ($get_refined_type)
		{
			$new_type=$get_refined_type();
			$new_type->retype_entity();
		};
		if ($this->entity->is_to_verify())
		{
			$this->entity->add_call($refine_type, 'verified');
			return $this;
		}
		else return $get_refined_type();
	}
}

class Post_basic extends Aspect
{
	static
		$common_model=
		[
			'post_type'=>
			[
				// это дублирование информации из PostPool, но может помочь в случае, если параметры пула изменятся; а также может понадобиться для поиска.
				'type'=>'enum',
				'options'=>[Post::TYPE_IMAGE, Post::TYPE_TEXT, Post::TYPE_SPECIES, Post::TYPE_ADVENTURE]
			]
		],
		$basic=true,
		$init=false,
		$default_table='posts';
}

// этот аспект может быть включён в самые разные типы сущностей, делая их авторскими произведениями. в нём должен содержаться весь функционал, требуемый от работ, увы.
class Post_info extends Aspect
{
	static
		$common_model=
		[
			'post_pool'=>
			[
				'type'=>'entity',
				'id_group'=>'PostPool',
				'import'=>['link_rules']
			],
			'moderated'=>
			[
				'type'=>'enum',
				'options'=>
				[
					Post::MOD_NOT_CHECKED,	Post::MOD_APPROVED,		Post::MOD_REJECTED,
					Post::MOD_WIP,			Post::MOD_STABILIZED, 	Post::MOD_POLISH,
					Post::MOD_AUTO_APPROVED
				],
				'default'=>Post::MOD_AUTO_APPROVED
			],
			'links'=>
			[
				'type'=>'post_linkset',
				'auto'=>'Fill_entity_proceducal_value_with_callback',
				'pre_request'=>['link_model'],
				'call'=>['_aspect', 'basic', 'object_field']
			],
			'tags'=>
			[
				'type'=>'linkset',
				'id_group'=>'Tagged'
			],
			'collections'=>
			[
				'type'=>'linkset',
				'id_group'=>'Collection'
			],
			'popular'=>
			[
				'type'=>'bool',
				'default'=>false
			]
		],
		$init=false,
		$default_table='posts_info'; // эта таблица используется как первичными Постами, так и теми, у которых функционал Поста -  просто аспект.
		
	public static function init()
	{
		if (static::$init) return;
		Retriever()->register_common_table(static::$default_table);
		parent::init();
	}
}

/*
	это аспект для открытого набора ссылок, заполняющийся в соответствии с 'linkages' (правилами связей) в описании потока работ (PostPool). он решает следующие задачи:
	- Отображение открытого набора связей как гиперссылки на связанные сущности, сгруппированные по правилу связи.
	- Установка параметров отображения этих связей (в частности, заголовка) через админку.
	- Отображение некоторых из этих связей единобразно с метками (гиперссылка метки ведёт в поиск по данной метке и её описание; аналогично со связями).
	- Проверка на наличие связи того или иного рода для технических операций (например, может ли пользователь размещать работу в своей галерее - да, если он один из авторов; или она ему подарена; или ему посвящена; или стоит благодарность ему или помощь (например, бета); или если на работе его питомец.
	- Включение и отображение интерфейса для добавления и редактирования связей данной работы в формах редактирования.
	
*/

abstract class Post_type_specific extends Aspect
{
	static
		$common_model=
		[
		],
		$init=false,
		$default_table='override_me';
}

class UserLog_about_Post extends UserLog_standard
{
	const
		MODEL_MODIFIED=__CLASS__,
		
		ACTION_CREATE	='post',
		ACTION_APPROVE	='post_approved',
		ACTION_REJECT	='post_rejected',
		ACTION_DELETE	='post_deleted',
		ACTION_COMMENT	='post_commented',
		ACTION_RATING	='post_rated',
		ACTION_COLLECT	='post_collected',
		ACTION_REVIEW	='post_reviewed';

	static
		$common_model=null,
		$modify_model=
		[
			'subject'=>
			[
				'type'=>'entity',
				'id_group'=>'Post'
			],
		],
		$init=false;
}
?>