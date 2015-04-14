<?

abstract class Value_coord extends Value_unsigned_int
{
}

class Value_coord_x extends Value_coord
{
}

class Value_coord_y extends Value_coord
{
}

class Value_coords extends Value_int_array
{
	// STUB: тут могут быть всякие проверки, что координаты, представленные внутри, хорошие, но пока опасных ситуаций нет.
}

// применяется к значениям, содержащим фрагмент изображения (ImageLocation с location_type==Image::LOCATION_FRAGMENT), чтобы проверить, содержится в нём ли точка, координаты которой получаются из содержимого сестёр значения.
class Validator_contains_point extends Validator_for_entity_value
{
	use Task_steps;
	
	const
		STEP_PRIMARY_REQUESTS=0, // запрашиваются все необходимые значения.
		STEP_SECONDARY_REQUESTS=1, // два шага не потребуются, когда get_entity() будет возвращать сущность с настроенным провайдером или Report_impossible в любом случае.
		STEP_COMPARE=2;
	
	public
		$fragment;
	
	public function run_step()
	{
		if ($this->step===static::STEP_PRIMARY_REQUESTS)
		{
			$tasks=[];
			
			if (! ($this->value instanceof Value_provides_entity) ) return $this->sign_report(new Report_impossible('bad_value'));
			$fragment=$this->value->get_entity();
			if ($fragment instanceof Report_tasks) $tasks=array_merge($tasks, $fragment->tasks);
			
			$coords=$this->value_model('point_coords');
			if ($coords instanceof Report_tasks) $tasks=array_merge($tasks, $coords->tasks);
			if (empty($tasks)) return $this->advance_step();
			return $this->sign_report(new Report_tasks($tasks));
			
		}
		elseif ($this->step===static::STEP_SECONDARY_REQUESTS)
		{
			$fragment=$this->value->get_entity();
			if ($fragment instanceof Report_impossible) return $fragment;
			$this->fragment=$fragment;
			
			$report=$fragment->request('coords');
			if ($report instanceof Report_success) return $this->advance_step();
			return $report;
		}
		elseif ($this->step===static::STEP_COMPARE)
		{
			$fragment_coords=$this->fragment->value('coords');
			$point_coords=$this->value_model_now('point_coords');
			
			$good=true;
			foreach (['x', 'y'] as $axis)
			{
				if ( ($point_coords[$axis]<$fragment_coords[$axis]) || ($point_coords[$axis]>$fragment_coords[$axis.'2']) )
				{
					$good=false;
					break;
				}
			}
			
			if ($good) return $this->sign_report(new Report_success());
			return $this->sign_report(new Report_impossible('out_of_fragment'));
		}
	}
}

// проверяет набор координат: указанная картинка должна целиком влезать в другую указанную картинку, если её центр имеет такой набор координат.
class Validator_PiP extends Validator_for_entity_value
{
	use Task_steps;
	
	const
		STEP_PRIMARY_REQUESTS=0,
		STEP_SECONDARY_REQUESTS=1,
		STEP_COMPARE=2;
		
	public
		$big_image,
		$big_width,
		$bid_height,
		$small_image,
		$dimensions=[],
		$x, $y;
	
	public function run_step()
	{
		if ($this->step===static::STEP_PRIMARY_REQUESTS)
		{
			if (!($this->value instanceof Value_coords)) die ('BAD COORDS VALUE');
			$this->x=$this->value->subvalue('x')->content();
			$this->y=$this->value->subvalue('y')->content();
			
			$tasks=[];

			if ($this->in_value_model('image_source'))
			{
				$big_image=$this->value_model('image_source');
				if (!($big_image instanceof Report)) $big_image=$this->entity->request($big_image);
				if ($big_image instanceof Report_impossible) return $big_image;
				elseif ($big_image instanceof Report_task)
				{
					$tasks[]=$big_image->task;
					$this->big_image=$big_image->task;
				}
				elseif ($big_image instanceof Report_resolution) $this->big_image=$big_image->resolution;
				else die('BAD REPORT');
			}
			
			$small_image=$this->value_model('inner_image_source');
			if (!($small_image instanceof Report)) $small_image=$this->entity->request($small_image);
			
			if ($small_image instanceof Report_impossible) return $small_image;
			elseif ($small_image instanceof Report_task)
			{
				$tasks[]=$small_image->task;
				$this->small_image=$small_image->task;
			}
			elseif ($small_image instanceof Report_resolution) $this->small_image=$small_image->resolution;
			else die('BAD REPORT');
			
			if (empty($tasks)) return $this->advance_step();
			return $this->sign_report(new Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_SECONDARY_REQUESTS)
		{
			if ($this->big_image instanceof Task)
			{
				if ($this->big_image->failed()) return $this->big_image->report();
				$this->big_image=$this->big_image->resolution;
				if (is_numeric($this->big_image)) $this->big_image=$this->pool()->entity_from_db_id($this->big_image, 'Image');
			}
			else
			{
				if ($this->in_value_model('width')) $this->dimensions['big_image']['width']=$this->value_model_now('width');
				else die('NO PiP WIDTH');
				if ($this->in_value_model('height')) $this->dimensions['big_image']['height']=$this->value_model_now('height');
				else die('NO PiP HEIGHT');
			}
			
			if ($this->small_image instanceof Task)
			{
				if ($this->small_image->failed()) return $this->small_image->report();
				$this->small_image=$this->small_image->resolution;
				if (is_numeric($this->small_image)) $this->small_image=$this->pool()->entity_from_db_id($this->small_image, 'Image');
			}
			
			$images=['big_image'=>$this->big_image, 'small_image'=>$this->small_image];
			$to_request=['width', 'height'];
			$tasks=[];
			foreach ($images as $image_code=>$image)
			{
				if (array_key_exists($image_code, $this->dimensions)) continue;
				$this->dimensions[$image_code]=[];
				foreach ($to_request as $code)
				{
					$report=$image->request($code);
					if ($report instanceof Report_impossible) return $report;
					elseif ($report instanceof Report_task)
					{
						$this->dimensions[$image_code][$code]=$report->task;
						$tasks[]=$report->task;
					}
					else $this->dimensions[$image_code][$code]=$report->resolution;
				}
			}
			if (empty($tasks)) return $this->advance_step();
			return $this->sign_report(new Report_tasks($tasks));
		}
		elseif ($this->step===static::STEP_COMPARE)
		{
			foreach ($this->dimensions as $image_code=>&$dims)
			{
				foreach ($dims as &$dim)
				{
					if ($dim instanceof Task)
					{
						if ($dim->failed()) return $dim->report();
						$dim=$dim->resolution;
					}
				}
			}
			$good=
				$this->x >= floor($this->dimensions['small_image']['width']/2) &&
				$this->x <= $this->dimensions['big_image']['width']-floor($this->dimensions['small_image']['width']/2) &&
				
				$this->y >= floor($this->dimensions['small_image']['height']/2) &&
				$this->y <= $this->dimensions['big_image']['width']-floor($this->dimensions['small_image']['height']/2);
			
			if ($good) return $this->sign_report(new Report_success());
			return $this->sign_report(new Report_impossible('bad_coords'));
		}
	}
}
?>