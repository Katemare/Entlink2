<?
namespace Pokeliga\Data;

trait Logger_Filler
{
	use \Pokeliga\Entlink\Logger;
	
	public function log_domain() { return 'Data'; }
	
	public function log($msg_id, $details=[])
	{
		if ($msg_id==='successful_fill') $this->debug('SUCCESSFUL FILLER: '.$this->human_readable());
		else parent::log($msg_id, $details);
	}
}

?>