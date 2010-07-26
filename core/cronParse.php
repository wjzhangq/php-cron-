<?php

class cronParse{
	function next($cronStr, $start=null){
		if (empty($start)){
			$start = time();
		}
		
		$range = self::parseCronStr($cronStr);
		
		$from = getdate($start);

		if ( ! in_array($from['mon'], $range['months']))
			return self::_next_crontab_month($from,  $range);

		if (count($range['weekdays']) === 7)
		{
			// Day of Week is unrestricted, defer to Day of Month
			if ( ! in_array($from['mday'], $range['monthdays']))
				return self::_next_crontab_monthday($from,  $range);
		}
		elseif (count($range['monthdays']) === 31)
		{
			// Day of Month is unrestricted, use Day of Week
			if ( ! in_array($from['wday'], $range['weekdays']))
				return self::_next_crontab_weekday($from,  $range);
		}
		else
		{
			// Both Day of Week and Day of Month are restricted
			if ( ! in_array($from['mday'], $range['monthdays']) AND ! in_array($from['wday'], $range['weekdays']))
				return self::_next_crontab_day($from,  $range);
		}

		if ( ! in_array($from['hours'], $range['hours']))
			return self::_next_crontab_hour($from,  $range);

		return self::_next_crontab_minute($from,  $range);
	}
	
	function parseCronStr($cronStr){
		list($minutes, $hours, $monthdays, $months, $weekdays) = explode(' ', $cronStr);

		$months = strtr(strtolower($months), array(
			'jan' => 1,
			'feb' => 2,
			'mar' => 3,
			'apr' => 4,
			'may' => 5,
			'jun' => 6,
			'jul' => 7,
			'aug' => 8,
			'sep' => 9,
			'oct' => 10,
			'nov' => 11,
			'dec' => 12,
		));

		$weekdays = strtr(strtolower($weekdays), array(
			'sun' => 0,
			'mon' => 1,
			'tue' => 2,
			'wed' => 3,
			'thu' => 4,
			'fri' => 5,
			'sat' => 6,
		));

		$range = array(
			'minutes'   => self::_parse_crontab_field($minutes, 0, 59),
			'hours'     => self::_parse_crontab_field($hours, 0, 23),
			'monthdays' => self::_parse_crontab_field($monthdays, 1, 31),
			'months'    => self::_parse_crontab_field($months, 1, 12),
			'weekdays'  => self::_parse_crontab_field($weekdays, 0, 7)
		);

		// Ensure Sunday is zero
		if (end($range['weekdays']) === 7)
		{
			array_pop($range['weekdays']);

			if (reset($range['weekdays']) !== 0)
			{
				array_unshift($range['weekdays'], 0);
			}
		}
		
		return $range;
	}
	
	/**
	 * Returns a sorted array of all the values indicated in a Crontab field
	 * @link http://linux.die.net/man/5/crontab
	 *
	 * @param   string  Crontab field
	 * @param   integer Minimum value for this field
	 * @param   integer Maximum value for this field
	 * @return  array
	 */
	protected function _parse_crontab_field($value, $min, $max)
	{
		$result = array();

		foreach (explode(',', $value) as $value)
		{
			if ($slash = strrpos($value, '/'))
			{
				$step = (int) substr($value, $slash + 1);
				$value = substr($value, 0, $slash);
			}

			if ($value === '*')
			{
				$result = array_merge($result, range($min, $max, $slash ? $step : 1));
			}
			elseif ($dash = strpos($value, '-'))
			{
				$result = array_merge($result, range(max($min, (int) substr($value, 0, $dash)), min($max, (int) substr($value, $dash + 1)), $slash ? $step : 1));
			}
			else
			{
				$value = (int) $value;

				if ($min <= $value AND $value <= $max)
				{
					$result[] = $value;
				}
			}
		}

		sort($result);

		return array_unique($result);
	}
	
	/**
	 * Calculates the first timestamp in the next day of this period when both
	 * Day of Week and Day of Month are restricted
	 *
	 * @uses    _next_crontab_month()
	 *
	 * @param   array   Date array from getdate()
	 * @return  integer Timestamp of next restricted Day
	 */
	protected function _next_crontab_day(array $from, &$range)
	{
		// Calculate effective Day of Month for next Day of Week

		if ($from['wday'] >= end($range['weekdays']))
		{
			$next = reset($range['weekdays']) + 7;
		}
		else
		{
			foreach ($range['weekdays'] as $next)
			{
				if ($from['wday'] < $next)
					break;
			}
		}

		$monthday = $from['mday'] + $next - $from['wday'];

		if ($monthday <= (int) date('t', mktime(0, 0, 0, $from['mon'], 1, $from['year'])))
		{
			// Next Day of Week is in this Month

			if ($from['mday'] >= end($range['monthdays']))
			{
				// No next Day of Month, use next Day of Week
				$from['mday'] = $monthday;
			}
			else
			{
				// Calculate next Day of Month
				foreach ($range['monthdays'] as $next)
				{
					if ($from['mday'] < $next)
						break;
				}

				// Use earliest day
				$from['mday'] = min($monthday, $next);
			}
		}
		else
		{
			if ($from['mday'] >= end($range['monthdays']))
			{
				// No next Day of Month, use next Month
				return self::_next_crontab_month($from, $range);
			}

			// Calculate next Day of Month
			foreach ($range['monthdays'] as $next)
			{
				if ($from['mday'] < $next)
					break;
			}

			// Use next Day of Month
			$from['mday'] = $next;
		}

		// Use first Hour and first Minute
		return mktime(reset($range['hours']), reset($range['minutes']), 0, $from['mon'], $from['mday'], $from['year']);
	}

	/**
	 * Calculates the first timestamp in the next hour of this period
	 *
	 * @uses    _next_crontab_day()
	 * @uses    _next_crontab_monthday()
	 * @uses    _next_crontab_weekday()
	 *
	 * @param   array   Date array from getdate()
	 * @return  integer Timestamp of next Hour
	 */
	protected function _next_crontab_hour(array $from,  &$range)
	{
		if ($from['hours'] >= end($range['hours']))
		{
			// No next Hour

			if (count($range['weekdays']) === 7)
			{
				// Day of Week is unrestricted, defer to Day of Month
				return self::_next_crontab_monthday($from, $range);
			}

			if (count($range['monthdays']) === 31)
			{
				// Day of Month is unrestricted, use Day of Week
				return self::_next_crontab_weekday($from, $range);
			}

			// Both Day of Week and Day of Month are restricted
			return self::_next_crontab_day($from, $range);
		}

		// Calculate next Hour
		foreach ($range['hours'] as $next)
		{
			if ($from['hours'] < $next)
				break;
		}

		// Use next Hour and first Minute
		return mktime($next, reset($range['minutes']), 0, $from['mon'], $from['mday'], $from['year']);
	}

	/**
	 * Calculates the timestamp of the next minute in this period
	 *
	 * @uses    _next_crontab_hour()
	 *
	 * @param   array   Date array from getdate()
	 * @return  integer Timestamp of next Minute
	 */
	protected function _next_crontab_minute(array $from,  &$range)
	{
		if ($from['minutes'] >= end($range['minutes']))
		{
			// No next Minute, use next Hour
			return self::_next_crontab_hour($from, $range);
		}

		// Calculate next Minute
		foreach ($range['minutes'] as $next)
		{
			if ($from['minutes'] < $next)
				break;
		}

		// Use next Minute
		return mktime($from['hours'], $next, 0, $from['mon'], $from['mday'], $from['year']);
	}

	/**
	 * Calculates the first timestamp in the next month of this period
	 *
	 * @param   array   Date array from getdate()
	 * @return  integer Timestamp of next Month
	 */
	protected function _next_crontab_month(array $from,  &$range)
	{
		if ($from['mon'] >= end($range['months']))
		{
			// No next Month, increment Year and use first Month
			++$from['year'];
			$from['mon'] = reset($range['months']);
		}
		else
		{
			// Calculate next Month
			foreach ($range['months'] as $next)
			{
				if ($from['mon'] < $next)
					break;
			}

			// Use next Month
			$from['mon'] = $next;
		}

		if (count($range['weekdays']) === 7)
		{
			// Day of Week is unrestricted, use first Day of Month
			$from['mday'] = reset($range['monthdays']);
		}
		else
		{
			// Calculate Day of Month for the first Day of Week
			$indices = array_flip($range['weekdays']);

			$monthday = 1;
			$weekday = (int) date('w', mktime(0, 0, 0, $from['mon'], 1, $from['year']));

			while ( ! isset($indices[$weekday % 7]) AND $monthday < 7)
			{
				++$monthday;
				++$weekday;
			}

			if (count($range['monthdays']) === 31)
			{
				// Day of Month is unrestricted, use first Day of Week
				$from['mday'] = $monthday;
			}
			else
			{
				// Both Day of Month and Day of Week are restricted, use earliest one
				$from['mday'] = min($monthday, reset($range['monthdays']));
			}
		}

		// Use first Hour and first Minute
		return mktime(reset($range['hours']), reset($range['minutes']), 0, $from['mon'], $from['mday'], $from['year']);
	}

	/**
	 * Calculates the first timestamp in the next day of this period when only
	 * Day of Month is restricted
	 *
	 * @uses    _next_crontab_month()
	 *
	 * @param   array   Date array from getdate()
	 * @return  integer Timestamp of next Day of Month
	 */
	protected function _next_crontab_monthday(array $from,  &$range)
	{
		if ($from['mday'] >= end($range['monthdays']))
		{
			// No next Day of Month, use next Month
			return self::_next_crontab_month($from, $range);
		}

		// Calculate next Day of Month
		foreach ($range['monthdays'] as $next)
		{
			if ($from['mday'] < $next)
				break;
		}

		// Use next Day of Month, first Hour, and first Minute
		return mktime(reset($range['hours']), reset($range['minutes']), 0, $from['mon'], $next, $from['year']);
	}

	/**
	 * Calculates the first timestamp in the next day of this period when only
	 * Day of Week is restricted
	 *
	 * @uses    _next_crontab_month()
	 *
	 * @param   array   Date array from getdate()
	 * @return  integer Timestamp of next Day of Week
	 */
	protected function _next_crontab_weekday(array $from,  &$range)
	{
		// Calculate effective Day of Month for next Day of Week

		if ($from['wday'] >= end($range['weekdays']))
		{
			$next = reset($range['weekdays']) + 7;
		}
		else
		{
			foreach ($range['weekdays'] as $next)
			{
				if ($from['wday'] < $next)
					break;
			}
		}

		$monthday = $from['mday'] + $next - $from['wday'];

		if ($monthday > (int) date('t', mktime(0, 0, 0, $from['mon'], 1, $from['year'])))
		{
			// Next Day of Week is not in this Month, use next Month
			return self::_next_crontab_month($from, $range);
		}

		// Use next Day of Week, first Hour, and first Minute
		return mktime(reset($range['hours']), reset($range['minutes']), 0, $from['mon'], $monthday, $from['year']);
	}	
}

if (true){
	$cronStr = '0 12 * * 1';
	$num = 5;
	$next = mktime(8, 40, 0);
	for($i = 0; $i < $num; $i++){
		$next = cronParse::next($cronStr, $next);
		echo "\n", date('Y-m-d H:i:s',$next);
	}	
}

?>