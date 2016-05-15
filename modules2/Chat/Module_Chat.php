<?
class Module_Chat extends Module implements Templater
{
	use Module_autoload_by_beginning;
	static $instance=null;
	
	public
		$name='Chat',
		$quick_classes=
		[
			'PHPWebSocket'	=>true,
			'Page_chat'		=>true,
			'ChatServer'	=>true,
			'ChatException'	=>true,
			'ChatContext'	=>true,
			
			'Client'		=>'Client/Client',
			
			'ClientAgent'	=>'Client/ClientAgent',
			'UserAgent'		=>'Client/ClientAgent',
			'AnonAgent'		=>'Client/ClientAgent',
			
			'Identity'		=>'Identity/Identity',
			'Persona'		=>'Identity/Identity',
			'IdentityTemplater'=>'Identity/Identity',
			'StandardIdentity'=>'Identity/Identity',
			'StandardPersona'=>'Identity/Identity',
			'IdentityException'=>'Identity/Identity',
			
			'Agent'			=>'Identity/Agent',
			
			'HandleManager'	=>'Identity/HandleManager',
			'ScrictHandleManager'=>'Identity/HandleManager',
			
			'Message'		=>'Message/Message',
			'MessageException'=>'Message/Message',
			
			'MessageOriginator' =>'Message/Message_interfaces',
			'MessageTarget' 	=>'Message/Message_interfaces',
			'MessageNode' 		=>'Message/Message_interfaces',
			
			'Thread'		=>'Thread/Thread',
			'MemberableLink'=>'Thread/Thread',
			'ThreadType'	=>'Thread/Thread',
			'TopicThread'	=>'Thread/Thread',
			'PrivateThread'	=>'Thread/Thread',
			'ThreadException'=>'Thread/Thread',
			
			'ThreadMessage'	=>'Thread/ThreadMessage',
			'LoggedMessage'	=>'Thread/ThreadMessage',
			
			'ThreadMembership'=>'Thread/ThreadMembership',
			'Memberable'	=>'Thread/ThreadMembership',
			'MemberableLink'=>'Thread/ThreadMembership',
			'StandardMemberable'=>'Thread/ThreadMembership',
			
			'Bot'			=>'Bot/Bot',
			
			'ConsoleBot'	=>'Bot/ConsoleBot',
			'ConsoleThread'	=>'Bot/ConsoleBot',
			
			'Command'		=>true,
			'BoundCommand'	=>'Command',
			
			'Topic'			=>'Thread/Topic'
		],
		$form_slugs=
		[
		],
		$classex='/^(?<file>Page_file|Template_file|Form_image|Page_image_location|Value_coord)[_$]/',
		$class_to_file=['Form_image'=>'File_Form', 'Page_image_location'=>'Page_file', 'Value_coord'=>'File_Data'];
		
	public function template($code, $line=[])
	{
	}
}

// FIXME: должно быть в каком-то общем файле и написанное по правилам полифилла.
function array_some(Iterator $array, Callable $callback)
{
	foreach ($array as $k=>$v) if ($callback($v, $k)) return true;
	return false;
}

function array_every(Iterator $array, Callable $callback)
{
	foreach ($array as $k=>$v) if (!$callback($v, $k)) return false;
	return true;
}
?>