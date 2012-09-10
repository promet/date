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
 * It extends the PHP DateTime class with more flexible initialization
 * parameters, allowing a date to be created from a timestamp, a string
 * with an unknown format, a string with a known format, or an array of
 * date parts. It also adds an errors array to the date object.
 * Finally, this class changes the default PHP behavior for handling
 * invalid date values like '2011-00-00'. PHP would convert that value
 * to '2010-11-30' and report a warning but not an error. This class
 * returns an error in that situation.
 *
 * As with the base class, we often return a date object even if it has
 * errors. It has an errors array attached to it that explains what the
 * errors are. The calling script can decide what to do about any errors
 * reported.
 *
 * Translation of error messages is not handled in this class, but
 * should be managed by the scripts that invoke it, which can be done
 * using the values in the $errors array.
 *
 */
class DateObject extends DateTime {

  // Static values used in massaging this date.
  public static $date_parts = array('year', 'month', 'day', 'hour', 'minute', 'second');
  public static $default_format = 'Y-m-d H:i:s';
  public static $default_timezone_name = '';
  public static $invalid_date_message = 'The date is invalid.';
  public static $missing_date_message = 'No date was input.';

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
   * @param mixed $time
   *   A date/time string, unix timestamp, or array. Defaults to 'now'.
   * @param mixed $timezone_name
   *   PHP DateTimeZone object, string or NULL allowed. Defaults to NULL.
   * @param string $format
   *   PHP date() type format for parsing. $format is recommended in order
   *   to use things like negative years, which php's parser fails on, or
   *   any other specialized input with a known format.
   *
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
    // Don't try to turn a value like '2010' with a format of 'Y'
    // into a timestamp.
    if (is_numeric($this->input_adjusted) && (empty($this->format) || $this->format == 'U')) {
      $this->constructFromTimestamp($this->input_adjusted, $this->timezone_object);
    }

    // Handling for arrays of date parts.
    // Convert the input value into an ISO date,
    // forcing a full ISO date even if some values are missing.
    elseif (is_array($time)) {
      $this->constructFromArray($this->input_adjusted, $this->timezone_object);
    }

    // The parse function will create a date from a string and an
    // expected format, and set errors on date parts in the format that
    // have no value.
    elseif (!empty($this->format)) {
      $this->constructFromFormat($this->format, $this->input_adjusted, $this->timezone_object);
    }

    // If the input was none of the above, let the parent dateTime attempt
    // to turn this string into a valid date. It might fail and we want to
    // catch the error messages.
    elseif (is_string($this->input_adjusted)) {
      try {
        @parent::__construct($this->input_adjusted, $this->timezone_object);
      }
      catch (Exception $e) {
        $this->errors += $e;
      }
      $this->getErrors();
    }

    // If something else, or nothing, was input, we don't have a date.
    else {
      $this->errors += self::$missing_date_message;
    }

    // Clean up the error messages.
    $this->errors = array_unique($this->errors);

    if (!empty($this->errors)) {
      return FALSE;
    }

    return TRUE;
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
    parent::__construct('', $timezone);

    $time = $this->prepareArray($time, TRUE);
    $this->input_adjusted = $this->toISO($time);
    if ($this->verifyArray($time)) {
      parent::__construct($this->input_adjusted, $timezone);
    }
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
    $this->getErrors();
  }

  /**
   * Examine getLastErrors() and see what errors to report.
   *
   * We're interested in two kinds of errors: anything that DateTime
   * considers an error, and also a warning that the date was invalid.
   * PHP creates a valid date from invalid data with only a warning,
   * 2011-02-30 becomes 2011-03-03, for instance, but we don't want that.
   *
   * @see http://us3.php.net/manual/en/datetime.getlasterrors.php
   */
  public function getErrors() {
    $errors = $this->getLastErrors();
    if (!empty($errors['errors'])) {
      $this->errors += $errors['errors'];
    }
    if (!empty($errors['warnings']) && in_array('The parsed date was invalid', $errors['warnings'])) {
      $this->errors[] = self::$invalid_date_message;
    }
  }

  /**
   * Set the default timezone name to use when no other information is
   * available. The system requires that a fallback timezone name be
   * available.
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
   *   An input value, which could be a timestamp, a string,
   *   or an array of date parts.
   */
  protected function prepareInput($time) {
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
            'year'   => $date->format('Y'),
            'month'  => $date->format('n'),
            'day'    => $date->format('j'),
            'hour'   => intval($date->format('H')),
            'minute' => intval($date->format('i')),
            'second' => intval($date->format('s')),
           );
  }

  /**
   * Creates an ISO date from an array of values.
   *
   * @param array $array
   *   An array of date values keyed by date part.
   * @param bool $force_valid
   *   (optional) Whether to force a full date by filling in missing
   *   values. Defaults to FALSE.
   *
   * @return string
   *   The date as an ISO string.
   */
  public static function toISO($array, $force_valid = FALSE) {
    $array = self::prepareArray($array, $force_valid);
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
   * Creates a complete array from a possibly incomplete array of date parts.
   *
   * @param array $array
   *   An array of date values keyed by date part.
   * @param bool $force_valid
   *   (optional) Whether to force a valid date by filling in missing
   *   values with valid values. Defaults to FALSE.
   *
   * @return array
   *   A complete array of date parts.
   */
  public function prepareArray($array, $force_valid = FALSE) {
    if ($force_valid) {
      $array += array('year' => 0, 'month' => 1, 'day' => 1, 'hour' => 0, 'minute' => 0, 'second' => 0);
    }
    else {
      $array += array('year' => '', 'month' => '', 'day' => '', 'hour' => '', 'minute' => '', 'second' => '');
    }
    return $array;
  }

  /**
   * Check that an array of date parts has a year, month, and day,
   * and that those values create a valid date.
   *
   * @param array $array
   *   An array of date values keyed by date part.
   *
   * @return bool
   *   TRUE if the date parts contain a valid date, otherwise FALSE.
   */
  public function verifyArray($array) {
    if (array_key_exists('year', $array) && array_key_exists('month', $array) && array_key_exists('day', $array)) {
      if (checkdate($array['month'], $array['day'], $array['year'])) {
        return TRUE;
      }
    }
    $this->errors[] = self::$invalid_date_message;
    return FALSE;
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