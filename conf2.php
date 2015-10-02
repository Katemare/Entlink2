<?
return
[
	'modules_dir'=>'modules2',
	'timezone'=>'Europe/Moscow',
	'language'=>'Russian',
	'pre_load'=>
	[
		'retriever'=> //false
		[
			'name'			=>'Retriever',
			'db_host'		=>'localhost',
			'db_login'		=>'root',
			'db_password'	=>'',
			'db_database'	=>'pokeliga_test',
			// 'table_prefix'=> 'smtg_'
		]
	],
	
	'imagemagick'	=>false,
	'development'	=>true,
];
?>