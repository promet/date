<?php

/**
 * @file
 * Definition of Drupal\date_api\DateObject.
 */
namespace Drupal\date_api;

use DateTime;
use DateTimezone;
use Exception;

/**
 * This class is a Drupal independent extension of the PHP DateTime class.
 *
 * It extends the PHP DateTime class with more flexible initialization parameters,
 * allowing a date to be created from a timestamp, a string with an unknown
 * format, a string with a known format, or an array of date parts.
 * It also adds an errors array to the date object.
 *
 * As with the base class, we return a date object even if it has errors.
 * It has an errors array attached to it that explains what the errors are.
 * The calling script can decide what to do with any errors reported.
 *
 */
class DateObject extends DateTime {

  // Static values used in massaging this date.
  public static $date_parts = array('year', 'month', 'day', 'hour', 'minute', 'second');
  public static $default_format = 'Y-m-d H:i:s';
  public static $default_timezone_name = '';
  public static $invalid_date_message = 'The date is invalid.';

  // The input format, if known.
  public $format = '';

  // The input time value, before it is altered.
  public $input_original = '';

  // The time, without timezone, of this date.
  public $input_adjusted = '';

  // The desired timezone for this date.
  public $timezone_name = 'UTC';

  // The timezone object for this date.
  public $timezone_object = '';

  // An array of errors encounted when creating this date.
  public $errors = array();

  /**
   * Constructs a date object.
   *
   * @param string $time
   *   A date/time string or array. Defaults to 'now'.
   * @param object|string|null $timezone_name
   *   PHP DateTimeZone object, string or NULL allowed. Defaults to NULL.
   * @param string $format
   *   PHP date() type format for parsing. Doesn't support timezones; if you
   *   have a timezone, send NULL and the default constructor method will
   *   hopefully parse it. $format is recommended in order to use negative or
   *   large years, which php's parser fails on.
   * @return
   *   Returns FALSE on failure.
   */
  public function __construct($time = 'now', $timezone_name = NULL, $format = NULL) {

    // Store the raw time input so it is available for validation.
    $this->input_original = $time;

    $this->input_adjusted = $this->prepareInput($time);

    $this->timezone_object = $this->prepareTimezone($timezone_name);

    $this->format = $format;

    // Handling for Unix timestamps.
    // Create a date object and convert it to the local timezone.
    // Don't try to turn a value like '2010' with a format of 'Y' into a timestamp.
    if (is_numeric($this->input_adjusted) && (empty($this->format) || $this->format == 'U')) {
      $this->constructFromTimestamp($this->input_adjusted, $this->timezone_object);
    }

    // Handling for arrays of date parts.
    // Convert the input value into an ISO date,
    // forcing a full ISO date even if some values are missing.
    elseif (is_array($time)) {
      $this->constructFromArray($this->input_adjusted, $this->timezone_object);
    }

    // The parse function will create a date from a string and an expected
    // format, and set errors on date parts in the format that have no value.
    elseif (!empty($this->format)) {
      $this->constructFromFormat($this->format, $this->input_adjusted, $this->timezone_object);
    }

    // If the input was none of the above, let the parent dateTime attempt
    // to turn this string into a valid date. It might fail and we want to
    // control the error messages.
    elseif (is_string($this->input_adjusted)) {
      try {
        @parent::__construct($this->input_adjusted, $this->timezone_object);
      }
      catch (Exception $e) {
        $errors = $this->getLastErrors();
        if (($errors['warning_count'] + $errors['error_count']) > 0) {
          $this->errors += $errors['errors'];
          return FALSE;
        }
      }
    }

    // If something else, or nothing, was input, we don't have a date.
    else {
      return FALSE;
    }

  }

  /**
   * Create a date object from timestamp input.
   *
   * @param int $time
   *   A unix timestamp.
   * @param object $timezone
   *   A DateTimezone object.
   */
  public function constructFromTimestamp($time, DateTimezone $timezone) {
    parent::__construct('', $timezone);
    $this->setTimestamp($time);
    $this->getErrors();
  }

  /**
   * Create a date object from an array of date parts.
   *
   * @param array $time
   *   A keyed array of date parts and values.
   * @param object $timezone
   *   A DateTimezone object.
   */
  public function constructFromArray($time, DateTimezone $timezone) {
    // arrayErrors finds errors in the input array from the original input.
    $this->errors += $this->arrayErrors($time);
    $this->input_adjusted = $this->toISO($time, TRUE);
    parent::__construct($this->input_adjusted, $timezone);
    $this->getErrors();
  }

  /**
   * Create a date object from an input format.
   *
   * @param string $format
   *   A date format.
   * @param string $time
   *   A date value, in the format described by $format.
   * @param object $timezone
   *   A DateTimezone object.
   */
  public function constructFromFormat($format, $time, DateTimezone $timezone) {
    parent::__construct('', $timezone);

    // We try to create a date from the format and use it if we can.
    // try/catch won't work right here, if the value is invalid
    // it doesn't return an exception.
    if ($date = parent::createFromFormat($format, $time, $timezone)) {
      $this->setTimestamp($date->getTimestamp());
      $this->setTimezone($date->getTimezone());
    }
    $this->getErrors($date);
    return;
  }

  /**
   * Examine getLastErrors() and see what errors to report.
   *
   * @see http://us3.php.net/manual/en/datetime.getlasterrors.php.
   */
  protected function getErrors($from = NULL) {
    if (!empty($from)) {
      $errors = $from->getLastErrors();
    }
    else {
      $errors = $this->getLastErrors();
    }
    $this->errors += $errors['errors'];
    // We're interested in two kinds of errors: anything that DateTime considers an error,
    // and also a warning that the date was invalid. PHP creates a valid date from
    // invalid data (2012-02-30 becomes 2012-03-03, for instance), but we don't want that.
    if (!empty($errors['warnings']) && in_array('The parsed date was invalid', $errors['warnings'])) {
      $this->errors['date'] = self::$invalid_date_message;
    }
  }

  /**
   * Set the default timezone name to use when no other information is available.
   * The system requires that a fallback timezone name be available.
   */
  public static function setDefaultTimezoneName($timezone_name = NULL) {
    $system_timezone = date_default_timezone_get();
    if (!empty($timezone_name)) {
      self::$default_timezone_name = $timezone_name;
    }
    elseif (!empty($system_timezone)) {
      self::$default_timezone_name = $system_timezone;
    }
    else {
      self::$default_timezone_name = 'UTC';
    }
  }

  /**
   * Get the default timezone name.
   */
  public static function getDefaultTimezoneName() {
    if (empty(self::$default_timezone_name)) {
      self::setDefaultTimezoneName('UTC');
    }
    return self::$default_timezone_name;
  }

  /**
   * Prepare the input value before trying to use it.
   *
   * @param mixed $time
   *   An input value, which could be a timestamp, a string, or an array of date parts.
   */
  protected function prepareInput($time) {
    // Make sure dates like 2010-00-00T00:00:00 get converted to
    // 2010-01-01T00:00:00 before creating a date object
    // to avoid unintended changes in the month or day.
      //$temp = $this->getFuzzyDate($time, $format = NULL, 'empty');
      //echo '<br>' . $time .'<br>';
      //print_r($this->granularity);
      //$time = date_make_iso_valid($time);
    return $time;
  }

  /**
   * Prepare the timezone before trying to use it.
   *
   * @param mixed $timezone
   *   Either a timezone name or a timezone object.
   */
  protected function prepareTimezone($timezone) {
    // Allow string timezones.
    if (!empty($timezone) && !is_object($timezone)) {
      $timezone = new DateTimeZone($timezone);
    }

    // Default to the site timezone when not explicitly provided.
    if (empty($timezone)) {
      $timezone = new DateTimeZone($this->getDefaultTimezoneName());
    }
    return $timezone;
  }

  /**
   * Returns all standard date parts in an array.
   *
   * Will return '' for parts in which it lacks granularity.
   *
   * @param object $date
   *   A date object.
   *
   * @return array
   *   An array of formatted date part values, keyed by date parts.
   */
  public static function toArray($date) {
    return array(
      'year' => $date->format('Y'),
      'month' => $date->format('n'),
      'day' => $date->format('j'),
      'hour' => intval($date->format('H')),
      'minute' => intval($date->format('i')),
      'second' => intval($date->format('s')),
    );
  }

  /**
   * Creates an ISO date from an array of values.
   *
   * @param array $array
   *   An array of date values keyed by date part.
   * @param bool $full
   *   (optional) Whether to force a full date by filling in missing values.
   *   Defaults to FALSE.
   *
   * @return string
   *   The date as an ISO string.
   */
  public static function toISO($array, $full = FALSE) {
    // Add empty values to avoid errors. The empty values must create a valid
    // date or we will get date slippage, i.e. a value of 2011-00-00 will get
    // interpreted as November of 2010.
    if ($full) {
      $array += array('year' => 0, 'month' => 1, 'day' => 1, 'hour' => 0, 'minute' => 0, 'second' => 0);
    }
    else {
      $array += array('year' => '', 'month' => '', 'day' => '', 'hour' => '', 'minute' => '', 'second' => '');
    }
    $datetime = '';
    if ($array['year'] !== '') {
      $datetime = self::datePad(intval($array['year']), 4);
      if ($full || $array['month'] !== '') {
        $datetime .= '-' . self::datePad(intval($array['month']));
        if ($full || $array['day'] !== '') {
          $datetime .= '-' . self::datePad(intval($array['day']));
        }
      }
    }
    if ($array['hour'] !== '') {
      $datetime .= $datetime ? 'T' : '';
      $datetime .= self::datePad(intval($array['hour']));
      if ($full || $array['minute'] !== '') {
        $datetime .= ':' . self::datePad(intval($array['minute']));
        if ($full || $array['second'] !== '') {
          $datetime .= ':' . self::datePad(intval($array['second']));
        }
      }
    }
    return $datetime;
  }

  /**
   * Finds possible errors in an array of date part values.
   *
   * The forceValid() function will change an invalid value to a valid one, so
   * we just need to see if the value got altered.
   *
   * @param array $array
   *   An array of date values, keyed by date part.
   *
   * @return array
   *   An array of error messages, keyed by date part.
   */
  protected function arrayErrors($array) {
    $errors = array();
    $now = new DateObject();
    $default_month = !empty($array['month']) ? $array['month'] : $now->format('n');
    $default_year = !empty($array['year']) ? $array['year'] : $now->format('Y');

    $this->granularity = array();
    foreach ($array as $part => $value) {
      // Avoid false errors when a numeric value is input as a string by casting
      // as an integer.
      $value = intval($value);
      if (!empty($value) && $this->forceValid($part, $value, 'now', $default_month, $default_year) != $value) {
        $errors[$part] = 'The ' . $part . ' is invalid';
      }
    }
    return $errors;
  }

  /**
   * Converts a date part into something that will produce a valid date.
   *
   * @param string $part
   *   The date part.
   * @param int $value
   *   The date value for this part.
   * @param string $default
   *   (optional) If the fallback should use the first value of the date part,
   *   or the current value of the date part. Defaults to 'first'.
   * @param int $month
   *   (optional) Used when the date part is less than 'month' to specify the
   *   date. Defaults to NULL.
   * @param int $year
   *   (optional) Used when the date part is less than 'year' to specify the
   *   date. Defaults to NULL.
   *
   * @return int
   *   A valid date value.
   */
  protected function forceValid($part, $value, $default = 'first', $month = NULL, $year = NULL) {
    $now = new DateObject();
    switch ($part) {
      case 'year':
        $date_api_info = config('date_api.info');
        $fallback = $now->format('Y');
        return !is_int($value) || empty($value) || $value < $date_api_info->get('year.min') || $value > $date_api_info->get('year.max') ? $fallback : $value;
        break;
      case 'month':
        $fallback = $default == 'first' ? 1 : $now->format('n');
        return !is_int($value) || empty($value) || $value <= 0 || $value > 12 ? $fallback : $value;
        break;
      case 'day':
        $fallback = $default == 'first' ? 1 : $now->format('j');
        $max_day = isset($year) && isset($month) ? date_days_in_month($year, $month) : 31;
        return !is_int($value) || empty($value) || $value <= 0 || $value > $max_day ? $fallback : $value;
        break;
      case 'hour':
        $fallback = $default == 'first' ? 0 : $now->format('G');
        return !is_int($value) || $value < 0 || $value > 23 ? $fallback : $value;
        break;
      case 'minute':
        $fallback = $default == 'first' ? 0 : $now->format('i');
        return !is_int($value) || $value < 0 || $value > 59 ? $fallback : $value;
        break;
      case 'second':
        $fallback = $default == 'first' ? 0 : $now->format('s');
        return !is_int($value) || $value < 0 || $value > 59 ? $fallback : $value;
        break;
    }
  }

  /**
   * Helper function to left pad date parts with zeros.
   *
   * Provided because this is needed so often with dates.
   *
   * @param int $value
   *   The value to pad.
   * @param int $size
   *   (optional) Size expected, usually 2 or 4. Defaults to 2.
   *
   * @return string
   *   The padded value.
   */
  public static function datePad($value, $size = 2) {
    return sprintf("%0" . $size . "d", $value);
  }

}