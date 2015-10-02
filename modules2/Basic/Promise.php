<?
namespace Pokeliga\Entlink;

// Promise �������� �������� ���-������ ��������� ��� �������� ���� ��� ������. ��� ���������, ��� ��� ����������� ������ ��������� ��������, �������� �����, ��������� ��� ����������� ��������� ������ ��. � �����, ������, �������� ������ �� ������� � ����������� ������� ������ ��������� ��� ��������.

// � ������� �� ������������ ���������� Promise � ������ �������, ������ �� �����������, ��� �������� �� ����� ���� "��������" �� ���� � ���������. ��� � ������������� ����������� ��� �����, ��������, �������� � �� (Request), ������� �� ���������� �� ���������� ������ ����������. ����� �� �������� �� ��������� �� ��� ������� - ��������� � ������.
interface Promise
{
	public function completed();	// �� ��� ��� - ��������� �� ��������?
	public function successful();	// ���������� ��, ���� �������� ��������� � �������.
	public function failed();		// ���������� ��, ���� �������� ��������� � ���������.
	public function resolution();	// ���������� ��������� ���� ����� � ������������� ������ �������. ���� �������� �� ���������, �� ������ ������.
	public function now();			// ����������� ��������� ��������� � ��������� �������� �����������, ���� ���������� � ��������.
	public function register_dependancy_for($task, $identifier=null); // ������������ �����������, � ���������� ���������� ������� completed() ����� �������� true. � ��� ����� ������������ �������� ���������� ��� ����������� �����������, ������ ��� ������ ��������� �� ����� ���� ������ � ����� ������� ��� ���� �����-�� ������.
	// ���� �������� �� ���������, �� ��� ������ ���������������� ������ Task, ���������� ��������� ��� ������������� ������� ��������. � ��������� ������ ������, ��� ������� �������������� �����������, �� ����� �����, ��� �� ������.
	public function to_task(); // ��� ������� ���������� ������ � ������������� ��������! � ����� ����������� �����������.
}

// ����� ���������� ������������� ��������, ��������������� ��� ��������� ��������� ����������, � ��, � �������, ������ ������������� ������.
interface FinalPromise extends Promise { }

interface PromiseLink
{
	public function get_promise();
}

trait Promise_from_link
{
	public function completed()		{ return $this->get_promise()->completed(); }
	public function successful()	{ return $this->get_promise()->successful(); }
	public function failed()		{ return $this->get_promise()->failed(); }
	public function resolution()	{ return $this->get_promise()->resolution(); }
	public function now()			{ return $this->get_promise()->now(); }
	public function register_dependancy_for($task, $identifier=null)
	{
		return $this->get_promise()->register_dependancy_for($task, $identifier);
	}
	public function to_task()
	{
		if ($this->completed()) throw new \Exception('completed Promise to task');
		$linked=$this->get_promise();
		if ($linked instanceof \Pokeliga\Task\Task) return $linked;
		else return $linked->to_task();
	}
}

?>