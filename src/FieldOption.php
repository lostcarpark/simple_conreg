<?php

namespace Drupal\simple_conreg;

/**
 * @file
 * Contains \Drupal\simple_conreg\FieldOption.
 */

/**
 * Class to represent a field options.
 */
class FieldOption {

  /**
   * Option ID.
   *
   * @var int
   */
  public int $optionId;
  /**
   * Option group ID.
   *
   * @var int
   */
  public int $groupId;
  /**
   * The option group object.
   *
   * @var FieldOptionGroup
   */
  public FieldOptionGroup $group;
  /**
   * Option title.
   *
   * @var string
   */
  public string $title;
  /**
   * Title for detail textbox.
   *
   * @var string
   */
  public string $detailTitle;
  /**
   * True if detail is mandatory.
   *
   * @var bool
   */
  public bool $detailRequired;
  /**
   * Weight of option (doesn't do much at the moment).
   *
   * @var int
   */
  public int $weight;
  /**
   * List of member classes the option belongs in.
   *
   * @var array
   */
  public array $inMemberClasses;
  /**
   * If true, the option must be selected to complete registration.
   *
   * @var bool
   */
  public bool $mustSelect;
  /**
   * If true, option not included in email confirmation to staff.
   *
   * @var bool
   */
  public bool $private;
  /**
   * Optional email address to be notified when member joins with option.
   *
   * @var string
   */
  public string $informEmail;

  /**
   * Constructs a new FieldOption object.
   */
  public function __construct() {
    $this->inMemberClasses = [];
  }

  /**
   * Take line from configuration settings and parse into option information.
   *
   * @param string $optionLine
   *   Line containing option from configuration.
   *
   * @return bool
   *   True if valid option parsed.
   */
  public function parseOption(string $optionLine): bool {
    if (preg_match("/^\d+\|\d+\|[^|]+\|[^|]*\|\d*\|\d*\|([^|,]+,)*[^|,]?/", $optionLine)) {
      $optionFields = array_pad(explode('|', trim($optionLine)), 10, '');
      [
        $this->optionId,
        $this->groupId,
        $this->title,
        $this->detailTitle,
        $this->detailRequired,
        $this->weight,
        $belongsIn,
        $this->mustSelect,
        $this->private,
        $this->informEmail,
      ] = $optionFields;
      $this->inMemberClasses = [];
      foreach (explode(',', trim($belongsIn)) as $inClass) {
        $this->inMemberClasses[] = $inClass;
      }
      return TRUE;
    }
    \Drupal::logger('simple_conreg')->notice(t('Unexpected membership option line: @line', ['@line' => $optionLine]));
    return FALSE;
  }

  /**
   * Set the option group for this option.
   *
   * @param FieldOptionGroup $optionGroup
   *   The option group to add to the option.
   */
  public function setGroup(FieldOptionGroup &$optionGroup): void {
    $this->group = &$optionGroup;
  }

  /**
   * Create an option from the configuration line.
   *
   * @param string $optionLine
   *   Line containing option values.
   */
  public static function newOption($optionLine) {
    if (!empty($optionLine)) {
      $option = new FieldOption();
      if ($option->parseOption($optionLine)) {
        return $option;
      }
    }
    return FALSE;
  }

}
