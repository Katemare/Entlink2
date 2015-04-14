<?

// "вклад" пока что подцепляется к другим типам данных, а не является сущностью сам по себе.

// пока используется только для хранения констант.
class Contribution extends EntityType
{
	const
		MOD_NOT_CHECKED	=0,	// на модерации, решение ещё не вынесено.
		MOD_APPROVED	=1, // решение вынесено - работа принята.
		MOD_REJECTED	=2, // решение вынесено - работа отклонена.
		MOD_WIP			=3,	// работа создана, но ещё не готова для модерации.
		MOD_STABILIZED	=4, // на модерации, решение ещё не вынесено, но редактирование уже отключено.
		MOD_POLISH		=5, // решение вынесено - работа принята, но ещё не преобразована модерами в итоговый формат.
		MOD_AUTO_APPROVED=6; // автоматически утверждено, то есть лично модеры всё-таки не смотрели.
	
	static
		$default_table='contributions';
}

class Contribution_identity extends Aspect
{	
	static
		$common_model=
		[
			'title'=>
			[
				'type'=>'title'
			],
			'annotation'=>
			[
				'type'=>'text',
				'null'=>true,
				'default'=>null
			],
			'description'=>
			[
				'type'=>'text',
				'null'=>true,
				'default'=>null
			],
			'cover_image'=>
			[
				'type'=>'id',
				'id_group'=>'Image',
				'auto_valid'=>[null],
				'null'=>true,
				'default'=>null
			],
			'contributor'=>
			[
				'type'=>'id',
				'id_group'=>'User',
				'pathway_track'=>true
			],
			'contribution_date'=>
			[
				'type'=>'time'
			],
			'available'=>
			[
				'type'=>'bool',
				'default'=>true
			],
			'moderated'=>
			[
				'type'=>'enum',
				'options'=>
				[
					Contribution::MOD_NOT_CHECKED,	Contribution::MOD_APPROVED,		Contribution::MOD_REJECTED,
					Contribution::MOD_WIP,			Contribution::MOD_STABILIZED, 	Contribution::MOD_POLISH,
					Contribution::MOD_AUTO_APPROVED
				],
				'default'=>Contribution::MOD_AUTO_APPROVED
			],
			'credits'=>
			[
				'type'=>'linkset',
				'id_group'=>'User',
				'select'=>'generic_linked',
				'position'=>Request_generic_links::FROM_SUBJECT,
				'opposite_id_group'=>'Contribution',
				'relation'=>'credit'
			]
		],
		$templates=
		[
			'descriptions'		=>['Template_contribution_from_db', '#contribution.descriptions'],
				// аннотация + разворачиваемое описание, если есть; в рамке.
			'descriptions_ajax'	=>['Template_contribution_from_db', '#contribution.descriptions_ajax'],
				// аннотация + запрашиваемое описание, если есть; в рамке.
			
			'description_framed'=>['Template_contribution_from_db',	'#contribution.description_framed'],
				// сворачиваемое описание в рамке.
			'description_collapsed'=>['Template_contribution_from_db', '#contribution.description_collapsed'],
				// свёрнутое описание в рамке
			'description_ajax'	=>['Template_contribution_from_db', '#contribution.description_ajax'],
				// запрашиваемое описание в рамке.
		],
		$init=false,
		$default_table='contributions';
		
	public static function init()
	{
		if (static::$init) return;
		Retriever()->register_common_table(static::$default_table);
		parent::init();
	}
}

trait Contribution_signer
{

	public function sign_new_contribution($entity, $moderated=null)
	{
		$entity->set('contributor', User::current_user_id());
		$entity->set('contribution_date', time());
		$entity->set('moderated', $moderated);
	}
}

class Provide_contribution_by_id extends Provide_by_single_request
{
	public
		$id,
		$id_group;
		
	public function setup_by_args($args)
	{
		$this->id=reset($args);
		$this->id_group=next($args);
	}

	public function get_data_set()
	{
		return $this->get_request()->get_data_set($this->id);
	}
	
	public function create_request()
	{
		return Request_by_id_and_group::instance(Contribution_identity::$default_table, $this->id_group);
	}
	
}

class Template_contribution_from_db extends Template_from_db
{
	public function initiated()
	{
		$this->page->register_requirement('js', Engine()->module_url('User', 'contribution.js'));
		$this->page->register_requirement('css', Engine()->module_url('User', 'contribution.css'));
		parent::initiated();
	}
}

// STUB! это работа для критериев в квадратных скобках или для какого-нибудь другого универсального подхода.
class Select_by_availability extends Select_all
{
	public function create_request()
	{
		return new RequestTicket('Request_by_availability', [$this->id_group(), $this->value_model_now('available')]);
	}
}

class Request_by_availability extends Request_all
{
	static
		$instances=[];
		
	public
		$available,
		$id_group;
	
	public function __construct($id_group=null, $available=true)
	{
		$this->id_group=$id_group;
		$this->available=(int)$available;
		parent::__construct($id_group::$default_table);
	}
	
	public function make_query()
	{
		$query=parent::make_query();
		$query=Query::from_array($query);
		$query->add_table(Contribution_identity::$default_table, 'contribution', ['field'=>'id', 'value_field'=>['contribution', 'id']], ['field'=>['contribution', 'id_group'], 'value'=>$this->id_group]);
		$query->add_complex_condition(['field'=>['contribution', 'available'], 'value'=>$this->available]);
		return $query;
	}
}
?>