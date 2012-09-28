<?php
/**
 * @file
 * Definition of DateFormatter.
 */
namespace Drupal\date_api;

use IntlDateFormatter;

/**
 * This class is an extension of the PHP IntlDateFormatter class.
 */
class DateFormatter extends IntlDateFormatter {

  /**
   * @param string $locale 
   * @param int $datetype
   * @param int $timetype
   * @param string $timezone
   * @param int $calendar
   * @param string $pattern
   */
  public function __construct($locale = NULL, $datetype = NULL, $timetype = NULL, $timezone = NULL, $calendar = NULL, $pattern = NULL) {

    // Set some default values;
    $locale = $this->getLocale($locale);
    $datetype = $this->getDateType($datetype);
    $timetype = $this->getTimeType($timetype);
    $timezone = $this->getTimezone($timezone);
    $calendar = $this->getCalendar($calendar);
    parent::_construct($locale, $datetype, $timetype, $timezone, $calendar, $pattern);
  }

  /**
   * Return a locale string.
   * Either the supplied string, or one constructed from system settings.
   */
  public function getLocale($locale) {
    if (empty($locale)) {
      $langcode = language(LANGUAGE_TYPE_INTERFACE)->langcode;
      $country = variable_get('site_default_country');
      $locale = $langcode . '-' . $country;
    }
    return $locale;
  }

  /**
   * Return a datetype setting.
   * Either the supplied setting, or a default value.
   */
  public function getDateType($datetype) {
    if (empty($datetype)) {
      $datetype = parent::MEDIUM;
    }
    return $datetype;
  }

  /**
   * Return a datetype setting.
   * Either the supplied setting, or a default value.
   */
  public function getTimeType($timetype) {
    if (empty($timetype)) {
      $timetype = parent::MEDIUM;
    }
    return $timetype;
  }

  /**
   * Return a timezone setting.
   * Either the supplied setting, or a default value.
   */
  public function getTimezone($timezone) {
    if (empty($timezone)) {
      $timezone = variable_get('date_default_timezone');
    }
    return $timezone;
  }

  /**
   * Return a calendar setting.
   * Either the supplied setting, or a default value.
   */
  public function getCalendar($calendar) {
    if (empty($calendar)) {
      $calendar = parent::GREGORIAN;
    }
    return $calendar;
  }

  /**
   * Convert a format string from DateFormatter format to PHP format.
   */
  public function toPHP($format) {
    $formats = array_flip($this->map());
    return $formats[$format];
  }

  /**
   * Convert a format string from PHP format to DateFormatter format.
   */
  public function fromPHP($format) {
    $formats = $this->map();
    return $formats[$format];
  }

  /**
   * Map format strings used by PHP to format strings used by the
   * intlDateFormatter, for ease in going back and forth.
   *
   * The keys of this array consist of the PHP format strings, and
   * the values are the intlDateFormatter strings.
   */
  public static function map() {
    return array(
      'Y' => 'yyyy',
      'y' => 'yy',
      'F' => 'MMMM',
      'm' => 'MM',
      'M' => 'MMM',
      'n' => 'M',
      't' => '', // no equivalent
      'D' => 'dd',
      'd' => 'EEE',
      'j' => 'd',
      'l' => 'EEEE',
      'N' => 'e',
      'S' => '', // no equivalent
      'w' => '', // no equivalent
      'z' => 'D',
      'W' => 'w',
      'a' => 'a',
      'A' => 'a', // no uppercase
      'B' => '', // no equivalent
      'g' => 'h',
      'G' => 'H',
      'h' => 'hh',
      'H' => 'HH',
      'i' => 'mm',
      's' => 'ss',
      'u' => '', // no equivalent
      'e' => 'zzzz',
      'I' => '', // no equivalent
      'O' => 'ZZZ',
      't' => 'Z',
      'P' => 'ZZZZ',
      'c' => "yyyy-yy-yy'THH:mm:ssZZZ", // Might be a slight difference
      'r' => 'EEE, dd MMM yyyy HH:mm:ss ZZZ', // Might be a slight difference
      'U' => '', // no equivalent
      '/' => ',', // the escape characters.
    );
  }
}
