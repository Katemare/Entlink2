<?

class TopicThread extends GroupThread
{
	const
		WELCOME_KEY='chat.topic_welcome';
		
	protected
		$topic;	// Entity[Topic]
		
	protected function get_welcome_nofitication(Client $client)
	{
		$template=parent::get_welcome_nofitication($client);
		$template->context->append($this->topic, 'topic');
		return $template;
	}
}
?>