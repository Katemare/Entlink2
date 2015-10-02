<?

// в глобальном пространстве имён, потому что это одни из самых используемых классов, и писать повсюду \что-нибудь\что-нибудь\Report_такой-то было бы ужасно неудобно!

// всякий раз, когда нам нужно вернуть данные о прошедшей операции вместо обычного ответа, мы возвращаем объект класса, унаследованного от этого. Если вернулся объект класса Report, то это всегда отчёт об операции и никогда - не посредственный результат выполнения метода. Это избавляет от неоднозначности таких ответов как, например, false (результат - false или же операция не удалась?).
// другие классы, объекты которых никогда не могут быть результатом: \Generator и \Pokeliga\Task\Need.
class Report implements \Pokeliga\Entlink\Mediator
{
	public
		$source=null;
	
	public function __construct($by=null)
	{
		if ($by!==null) $this->sign($by);
	}
	
	public function human_readable()
	{
		return get_class($this);
	}
	
	public function sign($by)
	{
		global $debug;
		if (!$debug) return $this;
		if ($this->source!==null) {vdump($this); die ('SIGNED REPORT'); } // в будущем, возможно, будет клонировать и переподписывать отчёт.
		$this->source=$by;
		return $this;
	}
}

// большая часть обмена данными - это отчёты класса Report_resolution, Report_impossible, Report_dependant и Report_promise. В частных случаях (касающихся обеспечения выполнения задач) может обрабатываться Report_in_progress.

// его возвращают задачи, находящиеся в процессе выполнения и не имеющие зависимостей.
// используется в основном как затычка в случае, если Process'у зачем-нибудь понадобится запросить отчёт в неурочное время.
class Report_in_progress extends Report {}