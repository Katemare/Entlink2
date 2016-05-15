<?

// отвечает за отображение авторства сообщений. гарантирует лишь что объект может как-то обозначить себя и подписаться.
interface Identity extends Templater
{
	public function handle();
	public function identity_data();
}

trait IdentityTemplater
{
	public function template($code, $line=[])
	{
		if ($code==='handle') return $this->handle();
		$data=$this->identity_data();
		if (array_key_exists($code, $data)) return $data[$code];
	}
}

trait StandardIdentity
{
	use IdentityTemplater;
	
	public function identity_data() { return ['handle'=>$this->handle()]; }
}

// предалгается, что каждый объект Persona в своём пруду уникален и имеет неповторяющиеся данные Identity.
interface Persona extends Identity, HasIdent
{
	// повторяют ли эти данные авторства те, которые присущи персоне?
	public function collides_with(Identity $identity);
	
	public function collides_with_handle($handle);
}

trait StandardPersona
{
	use StandardIdentity
	{
		StandardIdentity::identity_data as Identity_identity_data;
	}
	
	public function identity_data()
	{
		$data=$this->Identity_identity_data();
		$data['ident']=$this->ident();
		return $data;
	}
	
	public function collides_with(Identity $identity) { return $this->collides_with_handle($identity->handle()); }
	
	public function collides_with_handle($handle) { return $this->handle()===$handle; }
}

class IdentityException extends \Exception implements ChatException
{
	use StandardChatException;
	const ERROR_TEMPLATE_KEY='chat.identity_error';
}

?>