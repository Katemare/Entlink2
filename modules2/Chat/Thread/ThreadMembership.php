<?

interface MemberableLink
{
	public function get_memberable();
}

// object that implement this interface can be thread members.
interface Memberable extends Persona, MemberableLink { }

trait StandardMemberable
{
	use StandardPersona;
	
	public function get_memberable() { return $this; }
}

// represents presense, rights and status of a Memberable in relation to thread. a Memberable can be a member of the same thread serval times if thread permits, and a Memberable will ofter be a member of several threads.
class ThreadMembership implements MessageNode, MemberableLink
{
	protected
		$member,	// Memberable
		$thread;	// Thread
		
	public function get_memberable() { return $this->member; }
	
	// member can distinguish between memberships of the same thread by message's target.
	public function process_incoming_message(Message $message) { $this->member->process_incoming_message($message); }
	public function on_message_bounce(Message $message, \Exception $e) { $this->member->on_message_bounce($message, $e); }
}

?>