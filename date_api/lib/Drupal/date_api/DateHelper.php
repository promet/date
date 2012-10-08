<?php
/**
 * @file
 * Definition of self.
 *
 * Lots of helpful functions for use in massaging dates.
 * @TODO remove t() and use IntlDateFormatter instead.
 */
namespace Drupal\date_api;

use Drupal\Core\Datetime\DrupalDateTime;

class DateHelper {


  /**
   * A regex string that will extract date and time parts from either
   * a datetime string or an iso string, with or without missing date
   * and time values.
   */
  public static $regex_loose = '/(\d{4})-?(\d{1,2})-?(\d{1,2})([T\s]?(\d{2}):?(\d{2}):?(\d{2})?(\.\d+)?(Z|[\+\-]\d{2}:?\d{2})?)?/';

  /**
   * Constructs an untranslated array of month names.
   *
   * Needed for CSS, translation functions, strtotime(), and other places
   * that use the English versions of these words.
   *
   * @return array
   *   An array of month names.
   */
  public static function month_names_untranslated() {

    // Force the key to use the correct month value, rather than
    // starting with zero.
    return array(
      1 => 'January',
      2 => 'February',
      3 => 'March',
      4 => 'April',
      5 => 'May',
      6 => 'June',
      7 => 'July',
      8 => 'August',
      9 => 'September',
      10 => 'October',
      11 => 'November',
      12 => 'December',
    );
  }

  /**
   * Constructs an untranslated array of week days.
   *
   * Needed for CSS, translation functions, strtotime(), and other places
   * that use the English versions of these words.
   *
   * @param bool $refresh
   *   (optional) Whether to refresh the list. Defaults to TRUE.
   *
   * @return array
   *   An array of week day names
   */
  public static function week_days_untranslated() {
    return array(
      'Sunday',
      'Monday',
      'Tuesday',
      'Wednesday',
      'Thursday',
      'Friday',
      'Saturday',
    );
  }

  /**
   * Returns a translated array of month names.
   *
   * @param bool $required
   *   (optional) If FALSE, the returned array will include a blank value.
   *   Defaults to FALSE.
   *
   * @return array
   *   An array of month names.
   */
  public static function month_names($required = FALSE) {
    $month_names = array();
    foreach (self::month_names_untranslated() as $key => $month) {
      $month_names[$key] = t($month, array(), array('context' => 'Long month name'));
    }
    $none = array('' => '');
    return !$required ? $none + $month_names : $month_names;
  }
  
  /**
   * Constructs a translated array of month name abbreviations
   *
   * @param bool $required
   *   (optional) If FALSE, the returned array will include a blank value.
   *   Defaults to FALSE.
   * @param int $length
   *   (optional) The length of the abbreviation. Defaults to 3.
   *
   * @return array
   *   An array of month abbreviations.
   */
  public static function month_names_abbr($required = FALSE, $length = 3) {
    $month_names = array();
    foreach (self::month_names_untranslated() as $key => $month) {
      if ($length == 3) {
        $month_names[$key] = t(substr($month, 0, $length), array());
      }
      else {
        $month_names[$key] = t(substr($month, 0, $length), array(), array('context' => 'month_abbr'));
      }
    }
    $none = array('' => '');
    return !$required ? $none + $month_names : $month_names;
  }
  
  /**
   * Returns a translated array of week names.
   *
   * @param bool $required
   *   (optional) If FALSE, the returned array will include a blank value.
   *   Defaults to FALSE.
   *
   * @return array
   *   An array of week day names
   */
  public static function week_days($required = FALSE, $refresh = TRUE) {
    $weekdays = array();
    foreach (self::week_days_untranslated() as $key => $day) {
      $weekdays[$key] = t($day, array(), array('context' => ''));
    }
    $none = array('' => '');
    return !$required ? $none + $weekdays : $weekdays;
  }
  
  /**
   * Constructs a translated array of week day abbreviations.
   *
   * @param bool $required
   *   (optional) If FALSE, the returned array will include a blank value.
   *   Defaults to FALSE.
   * @param bool $refresh
   *   (optional) Whether to refresh the list. Defaults to TRUE.
   * @param int $length
   *   (optional) The length of the abbreviation. Defaults to 3.
   *
   * @return array
   *   An array of week day abbreviations
   */
  public static function week_days_abbr($required = FALSE, $refresh = TRUE, $length = 3) {
    $weekdays = array();
    switch ($length) {
      case 1:
        $context = 'day_abbr1';
        break;
      case 2:
        $context = 'day_abbr2';
        break;
      default:
        $context = '';
        break;
    }
    foreach (self::week_days_untranslated() as $key => $day) {
      $weekdays[$key] = t(substr($day, 0, $length), array(), array('context' => $context));
    }
    $none = array('' => '');
    return !$required ? $none + $weekdays : $weekdays;
  }
  
  /**
   * Reorders weekdays to match the first day of the week.
   *
   * @param array $weekdays
   *   An array of weekdays.
   *
   * @return array
   *   An array of weekdays reordered to match the first day of the week.
   */
  public static function week_days_ordered($weekdays) {
    $first_day = variable_get('date_first_day', 0);
    if ($first_day > 0) {
      for ($i = 1; $i <= $first_day; $i++) {
        $last = array_shift($weekdays);
        array_push($weekdays, $last);
      }
    }
    return $weekdays;
  }
  
  /**
   * Constructs an array of years.
   *
   * @param int $min
   *   The minimum year in the array.
   * @param int $max
   *   The maximum year in the array.
   * @param bool $required
   *   (optional) If FALSE, the returned array will include a blank value.
   *   Defaults to FALSE.
   *
   * @return array
   *   An array of years in the selected range.
   */
  public static function years($min = 0, $max = 0, $required = FALSE) {
    // Ensure $min and $max are valid values.
    if (empty($min)) {
      $min = intval(date('Y', REQUEST_TIME) - 3);
    }
    if (empty($max)) {
      $max = intval(date('Y', REQUEST_TIME) + 3);
    }
    $none = array(0 => '');
    return !$required ? $none + drupal_map_assoc(range($min, $max)) : drupal_map_assoc(range($min, $max));
  }
  
  /**
   * Constructs an array of days in a month.
   *
   * @param bool $required
   *   (optional) If FALSE, the returned array will include a blank value.
   *   Defaults to FALSE.
   * @param int $month
   *   (optional) The month in which to find the number of days.
   * @param int $year
   *   (optional) The year in which to find the number of days.
   *
   * @return array
   *   An array of days for the selected month.
   */
  public static function days($required = FALSE, $month = NULL, $year = NULL) {
    // If we have a month and year, find the right last day of the month.
    if (!empty($month) && !empty($year)) {
      $date = new DrupalDateTime($year . '-' . $month . '-01 00:00:00', 'UTC');
      $max = $date->format('t');
    }
    // If there is no month and year given, default to 31.
    if (empty($max)) {
      $max = 31;
    }
    $none = array(0 => '');
    return !$required ? $none + drupal_map_assoc(range(1, $max)) : drupal_map_assoc(range(1, $max));
  }
  
  /**
   * Constructs an array of hours.
   *
   * @param string $format
   *   A date format string.
   * @param bool $required
   *   (optional) If FALSE, the returned array will include a blank value.
   *   Defaults to FALSE.
   *
   * @return array
   *   An array of hours in the selected format.
   */
  public static function hours($format = 'H', $required = FALSE) {
    $hours = array();
    if ($format == 'h' || $format == 'g') {
      $min = 1;
      $max = 12;
    }
    else {
      $min = 0;
      $max = 23;
    }
    for ($i = $min; $i <= $max; $i++) {
      $hours[$i] = $i < 10 && ($format == 'H' || $format == 'h') ? "0$i" : $i;
    }
    $none = array('' => '');
    return !$required ? $none + $hours : $hours;
  }
  
  /**
   * Constructs an array of minutes.
   *
   * @param string $format
   *   A date format string.
   * @param bool $required
   *   (optional) If FALSE, the returned array will include a blank value.
   *   Defaults to FALSE.
   *
   * @return array
   *   An array of minutes in the selected format.
   */
  public static function minutes($format = 'i', $required = FALSE, $increment = 1) {
    $minutes = array();
    // Ensure $increment has a value so we don't loop endlessly.
    if (empty($increment)) {
      $increment = 1;
    }
    for ($i = 0; $i < 60; $i += $increment) {
      $minutes[$i] = $i < 10 && $format == 'i' ? "0$i" : $i;
    }
    $none = array('' => '');
    return !$required ? $none + $minutes : $minutes;
  }
  
  /**
   * Constructs an array of seconds.
   *
   * @param string $format
   *   A date format string.
   * @param bool $required
   *   (optional) If FALSE, the returned array will include a blank value.
   *   Defaults to FALSE.
   *
   * @return array
   *   An array of seconds in the selected format.
   */
  public static function seconds($format = 's', $required = FALSE, $increment = 1) {
    $seconds = array();
    // Ensure $increment has a value so we don't loop endlessly.
    if (empty($increment)) {
      $increment = 1;
    }
    for ($i = 0; $i < 60; $i += $increment) {
      $seconds[$i] = $i < 10 && $format == 's' ? "0$i" : $i;
    }
    $none = array('' => '');
    return !$required ? $none + $seconds : $seconds;
  }
  
  /**
   * Constructs an array of AM and PM options.
   *
   * @param bool $required
   *   (optional) If FALSE, the returned array will include a blank value.
   *   Defaults to FALSE.
   *
   * @return array
   *   An array of AM and PM options.
   */
  public static function ampm($required = FALSE) {
    $none = array('' => '');
    $ampm = array(
      'am' => t('am', array(), array('context' => 'ampm')),
      'pm' => t('pm', array(), array('context' => 'ampm')),
    );
    return !$required ? $none + $ampm : $ampm;
  }

  /**
   * Identifies the number of days in a month for a date.
   */
  public static function days_in_month($year, $month) {
    // Pick a day in the middle of the month to avoid timezone shifts.
    $datetime = DrupalDateTime::datePad($year, 4) . '-' . DrupalDateTime::datePad($month) . '-15 00:00:00';
    $date = new DrupalDateTime($datetime);
    return $date->format('t');
  }
  
  /**
   * Identifies the number of days in a year for a date.
   *
   * @param mixed $date
   *   (optional) The current date object, or a date string. Defaults to NULL.
   *
   * @return integer
   *   The number of days in the year.
   */
  public static function days_in_year($date = NULL) {
    if (empty($date)) {
      $date = new DrupalDateTime();
    }
    elseif (!is_object($date)) {
      $date = new DrupalDateTime($date);
    }
    if (is_object($date)) {
      if ($date->format('L')) {
        return 366;
      }
      else {
        return 365;
      }
    }
    return NULL;
  }
  
  /**
   * Identifies the number of ISO weeks in a year for a date.
   *
   * December 28 is always in the last ISO week of the year.
   *
   * @param mixed $date
   *   (optional) The current date object, or a date string. Defaults to NULL.
   *
   * @return integer
   *   The number of ISO weeks in a year.
   */
  public static function iso_weeks_in_year($date = NULL) {
    if (empty($date)) {
      $date = new DrupalDateTime();
    }
    elseif (!is_object($date)) {
      $date = new DrupalDateTime($date);
    }
  
    if (is_object($date)) {
      date_date_set($date, $date->format('Y'), 12, 28);
      return $date->format('W');
    }
    return NULL;
  }
  
  /**
   * Returns day of week for a given date (0 = Sunday).
   *
   * @param mixed $date
   *   (optional) A date, default is current local day. Defaults to NULL.
   *
   * @return int
   *   The number of the day in the week.
   */
  public static function day_of_week($date = NULL) {
    if (empty($date)) {
      $date = new DrupalDateTime();
    }
    elseif (!is_object($date)) {
      $date = new DrupalDateTime($date);
    }
  
    if (is_object($date)) {
      return $date->format('w');
    }
    return NULL;
  }
  
  /**
   * Returns translated name of the day of week for a given date.
   *
   * @param mixed $date
   *   (optional) A date, default is current local day. Defaults to NULL.
   * @param string $abbr
   *   (optional) Whether to return the abbreviated name for that day.
   *   Defaults to TRUE.
   *
   * @return string
   *   The name of the day in the week for that date.
   */
  public static function day_of_week_name($date = NULL, $abbr = TRUE) {
    if (!is_object($date)) {
      $date = new DrupalDateTime($date);
    }
    $dow = self::day_of_week($date);
    $days = $abbr ? self::week_days_abbr() : self::week_days();
    return $days[$dow];
  }
  
  /**
   * Calculates the start and end dates for a calendar week.
   *
   * The dates are adjusted to use the chosen first day of week for this site.
   *
   * @param int $week
   *   The week value.
   * @param int $year
   *   The year value.
   *
   * @return array
   *   A numeric array containing the start and end dates of a week.
   */
  public static function calendar_week_range($week, $year) {
    if (config('date_api.settings')->get('iso8601')) {
      return DateHelper::iso_week_range($week, $year);
    }
    $min_date = new DrupalDateTime($year . '-01-01 00:00:00');
    $min_date->setTimezone(date_default_timezone_object());
  
    // Move to the right week.
    date_modify($min_date, '+' . strval(7 * ($week - 1)) . ' days');
  
    // Move backwards to the first day of the week.
    $first_day = variable_get('date_first_day', 0);
    $day_wday = date_format($min_date, 'w');
    date_modify($min_date, '-' . strval((7 + $day_wday - $first_day) % 7) . ' days');
  
    // Move forwards to the last day of the week.
    $max_date = clone($min_date);
    date_modify($max_date, '+7 days');
  
    if (date_format($min_date, 'Y') != $year) {
      $min_date = new DrupalDateTime($year . '-01-01 00:00:00');
    }
    return array($min_date, $max_date);
  }
  
  /**
   * Calculates the start and end dates for an ISO week.
   *
   * @param int $week
   *   The week value.
   * @param int $year
   *   The year value.
   *
   * @return array
   *   A numeric array containing the start and end dates of an ISO week.
   */
  public static function iso_week_range($week, $year) {
    // Get to the last ISO week of the previous year.
    $min_date = new DrupalDateTime(($year - 1) . '-12-28 00:00:00');
    date_timezone_set($min_date, date_default_timezone_object());
  
    // Find the first day of the first ISO week in the year.
    date_modify($min_date, '+1 Monday');
  
    // Jump ahead to the desired week for the beginning of the week range.
    if ($week > 1) {
      date_modify($min_date, '+ ' . ($week - 1) . ' weeks');
    }
  
    // Move forwards to the last day of the week.
    $max_date = clone($min_date);
    date_modify($max_date, '+7 days');
    return array($min_date, $max_date);
  }
  
  /**
   * The number of calendar weeks in a year.
   *
   * PHP week functions return the ISO week, not the calendar week.
   *
   * @param int $year
   *   A year value.
   *
   * @return int
   *   Number of calendar weeks in selected year.
   */
  public static function weeks_in_year($year) {
    $date = new DrupalDateTime(($year + 1) . '-01-01 12:00:00', 'UTC');
    date_modify($date, '-1 day');
    return DateHelper::calendar_week($date->format('Y-m-d'));
  }
  
  /**
   * The calendar week number for a date.
   *
   * PHP week functions return the ISO week, not the calendar week.
   *
   * @param string $date
   *   A date string in the format Y-m-d.
   *
   * @return int
   *   The calendar week number.
   */
  public static function calendar_week($date) {
    $date = substr($date, 0, 10);
    $parts = explode('-', $date);
  
    $date = new DrupalDateTime($date . ' 12:00:00', 'UTC');
  
    // If we are using ISO weeks, this is easy.
    if (config('date_api.settings')->get('iso8601')) {
      return intval($date->format('W'));
    }
  
    $year_date = new DrupalDateTime($parts[0] . '-01-01 12:00:00', 'UTC');
    $week = intval($date->format('W'));
    $year_week = intval(date_format($year_date, 'W'));
    $date_year = intval($date->format('o'));
  
    // Remove the leap week if it's present.
    if ($date_year > intval($parts[0])) {
      $last_date = clone($date);
      date_modify($last_date, '-7 days');
      $week = date_format($last_date, 'W') + 1;
    }
    elseif ($date_year < intval($parts[0])) {
      $week = 0;
    }
  
    if ($year_week != 1) {
      $week++;
    }
  
    // Convert to ISO-8601 day number, to match weeks calculated above.
    $iso_first_day = 1 + (variable_get('date_first_day', 0) + 6) % 7;
  
    // If it's before the starting day, it's the previous week.
    if (intval($date->format('N')) < $iso_first_day) {
      $week--;
    }
  
    // If the year starts before, it's an extra week at the beginning.
    if (intval(date_format($year_date, 'N')) < $iso_first_day) {
      $week++;
    }
  
    return $week;
  }

}