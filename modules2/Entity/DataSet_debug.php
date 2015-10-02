<?
namespace Pokeliga\Entity;

trait Logger_DataSet
{
	use \Pokeliga\Entlink\Logger;
	
	public function log_domain() { return 'Data'; }
	
	public function log($msg_id, $details=[])
	{
		if ($msg_id==='create_value') $this->debug('CREATE VALUE <b>'.$details['code'].'</b> FOR '.$this->entity->human_readable());
		elseif ($msg_id==='filling_value') $this->debug('FILLING VALUE: '.$details['value']->code);
	}
}

?>