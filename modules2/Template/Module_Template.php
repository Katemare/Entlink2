<?
namespace Pokeliga\Template;

class Module_Template extends \Pokeliga\Entlink\Module
{
	use \Pokeliga\Entlink\Module_autoload_by_beginning;
	static $instance=null;
	
	public
		$name='Template',
		$quick_classes=
		[
			'Templater'=>'Template_interfaces',
			
			'Template'=>'Template',
			
			'Context'=>'Context',
			'Pathway'=>'Context',
			'Template_context'=>'Context',
			
			'Template_period'=>'Template_date',
			
			'CodeFragment'=>'CodeFragment',
			
			'Paged'=>'Paginator',
			'Template_pages'=>'Paginator',
			
			'Task_retrieve_cache'=>'Cache',
			'Task_save_cache'=>'Cache',
			'Task_reset_cache'=>'Cache',
		],
		$classex='(?<file>Template|CodeFragment)[_$]';
		
	static
		$conditionex='/{{#if:(?<condition>\s*(?<param>[a-z_\.])\s*(?<operation>(?<op>=|>|<|>=|<=|!=)\s*(?<value>.+?)\s*)?)\|(?<on_true>.+?)(\|(?<on_false>.+?))?}}/', // примитивное условие, не поддерживающее командной строки у шаблонов в on_true и on_false
		$elementex='/{{(?<master_pointer>#)?(?<track>[a-z_\d\.]+)(\|(?<commandline>.+?))?}}/'; // распознавание кодов в тексте шаблона для компиляции. STUB: в будущем master_pointer должен указывать на константу Template.
	
	public function template($code, $line=[])
	{
		if ($code==='paginator')
		{
			$template=Template_pages::with_line($line);
			return $template;
		}
		return parent::template($code, $line);
	}
	
	public static function compile_template($text, &$has_php=true)
	{
		$element_callback=function ($m)
		{
			if (!empty($m['master_pointer'])) $track=[$m['master_pointer'], $m['track']];
			else $track=explode('.', $m['track']);
			if (count($track)==1) $track=reset($track);
			
			if (empty($m['commandline'])) $commandline=null;
			else
			{
				$commandline=explode('|', $m['commandline']);
				if (empty($commandline)) $commandline=null;
				else
				{
					$parsed=[];
					foreach($commandline as $argument)
					{
						if (preg_match('/^(?<name>.*?)=(?<value>.+)$/', $argument, $m)) $parsed[$m['name']]=$m['value']; // STUB: не позволяет экранировать знак "равно".
						else $parsed[]=$argument;
					}
					$commandline=$parsed;
				}
			}
			
			if (is_null($commandline))
				$result='<?php $this->keyword('.var_export($track, true).'); ?>';
			else
				$result='<?php $this->keyword('.var_export($track, true).', '.var_export($commandline, true).'); ?>';
			return $result;
		};
		
		$condition_callback=function($m)
		{
			$param='$this->entity()->'.$m['param'].'()';		
			if (!empty($m['operation']))
			{
				if ( ($m['value']==='null') && ($m['op']==='=') ) $condition="is_null($param)";
				elseif ( ($m['value']==='null') && ($m['op']==='!=') ) $condition="!is_null($param)";
				elseif ($m['value']==='null') die ('BAD NULL OP');
				else
				{
					if (is_numeric($m['value'])) $value=(float)($m['value']);
					elseif (in_array($m['value'], ['true', 'false'], 1)) $value=$m['value'];
					else $value=var_export($m['value'], true);
					
					if (in_array($value, ['true', 'false']))
					{
						if ($m['op']==='=') $op='===';
						elseif ($m['op']==='!=') $op='!==';
						else $op=$m['op'];
					}
					elseif ($m['op']==='=') $op='==';
					else $op=$m['op'];
					$condition=$param.$op.$value;
				}
			}
			else $condition=$param;
			
			$result=
				'<?php if ('.$condition.') '.
				'echo '.var_export($m['on_true'], true).';'.
				( (!empty($m['on_false'])) ? (' else echo '.var_export($m['on_false'], true).';') : ('') ).
				' ?>'
			;
			
			return $result;
		};
		
		$count=0; $total=0;
		$text=preg_replace('/(<\?|\?>)/', '<? echo \'$1\'; ?>', $text); // экранирование < ? и ? >
		
		$compiled_text=preg_replace_callback(static::$conditionex, $condition_callback, $text, -1, $total);
		
		$compiled_text=preg_replace_callback(static::$elementex, $element_callback, $compiled_text, -1, $count);
		$total+=$count;
		
		$has_php=$total>0;
		/* if ($has_php) */
		$compiled_text='?>'.$compiled_text;
		return $compiled_text;
	}
}

?>