<?php

namespace Drupal\conreg_badges\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\simple_conreg\SimpleConregConfig;
use Drupal\simple_conreg\SimpleConregOptions;
use Drupal\simple_conreg\SimpleConregStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class BadgeNamesForm extends FormBase {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Construct the badge names form.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   */
  public function __construct(AccountInterface $account) {
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load the service required to construct this class.
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'conreg_badge_names';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form,
                            FormStateInterface $form_state,
                            int $eid = 1,
                            bool $export = FALSE,
                            string $fields = NULL,
                            string $update = NULL): Response | Array {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    // Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();

    if ($export) {
      return $this->exportBadges($eid, $fields, $update);
    }

    $form = [
      '#attached' => [
        'library' => ['simple_conreg/conreg_tables'],
      ],
      '#prefix' => '<div id="memberform">',
      '#suffix' => '</div>',
    ];

    $form['fields'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Include fields'),
    ];

    $showMemberNo = ($form_values['showMemberNo'] ?? TRUE);
    $exportFields = $showMemberNo ? 'M' : '';
    $form['fields']['showMemberNo'] = $this->checkBox($this->t('Show member number'), TRUE);

    $showMemberName = ($form_values['showMemberName'] ?? TRUE);
    $exportFields .= $showMemberName ? 'N' : '';
    $form['fields']['showMemberName'] = $this->checkBox($this->t('Show member name'), TRUE);

    $showBadgeName = ($form_values['showBadgeName'] ?? TRUE);
    $exportFields .= $showBadgeName ? 'B' : '';
    $form['fields']['showBadgeName'] = $this->checkBox($this->t('Show badge name'), TRUE);

    $showBadgeTypes = ($form_values['showBadgeTypes'] ?? TRUE);
    $exportFields .= $showBadgeTypes ? 'T' : '';
    $form['fields']['showBadgeTypes'] = $this->checkBox($this->t('Show badge types'), TRUE);

    if ($this->account->hasPermission('view membership badges member type')) {
      $showMemberTypes = ($form_values['showMemberTypes'] ?? TRUE);
      $exportFields .= $showMemberTypes ? 'Y' : '';
      $form['fields']['showMemberTypes'] = $this->checkBox($this->t('Show member types'), TRUE);
    }
    else {
      $showMemberTypes = FALSE;
    }

    $showDays = ($form_values['showDays'] ?? TRUE);
    $exportFields .= $showDays ? 'D' : '';
    $form['fields']['showDays'] = $this->checkBox($this->t('Show days'), TRUE);

    $form['filter'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Filters'),
    ];

    if (isset($form_values['updated'])) {
      $update = empty($form_values['updated']) ? 0 : (new DrupalDateTime($form_values['updated']))->getTimestamp();
    }
    $form['filter']['updated'] = [
      '#type' => 'date',
      '#title' => $this->t('Updated since'),
      '#ajax' => [
        'wrapper' => 'memberform',
        'callback' => [$this, 'updateDisplayCallback'],
        'event' => 'change',
      ],
    ];

    $form['export'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Export'),
    ];

    $exportUrl = Url::fromRoute(
      'conreg_badges_list_export',
      [
        'eid' => $eid,
        'fields' => $exportFields,
        'update' => $update,
      ],
      ['absolute' => TRUE]
    );
    $exportLink = Link::fromTextAndUrl($this->t('Export Badge Names'), $exportUrl);

    $form['export']['link'] = [
      '#type' => 'markup',
      '#prefix' => '<div>',
      '#suffix' => '</div>',
      '#markup' => $exportLink->toString(),
    ];

    $form['message'] = [
      '#markup' => $this->t('Here is a list of all paid convention members...'),
      '#prefix' => '<div id="Heading">',
      '#suffix' => '</div>',
    ];

    $badgeNameRows = $this->getBadgeNameRows($eid, $showMemberNo, $showMemberName, $showBadgeName, $showBadgeTypes, $showMemberTypes, $showDays, $update);

    $headers = [];
    foreach ($badgeNameRows->headers as $field => $label) {
      $headers[$field] = ['data' => $label, 'field' => $field];
    }

    $form['table'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $badgeNameRows->rows,
      '#empty' => $this->t('No entries available.'),
      '#sticky' => TRUE,
    ];
    // Don't cache this page.
    $form['#cache']['max-age'] = 0;

    return $form;
  }

  /**
   * Return a Drupal form checkbox element.
   *
   * @param string $title
   *   The title of the checkbox.
   * @param bool $default
   *   The default value.
   *
   * @return array
   *   The form field array.
   */
  private function checkBox(string $title, bool $default): array {
    return [
      '#type' => 'checkbox',
      '#title' => $title,
      '#default_value' => $default,
      '#ajax' => [
        'wrapper' => 'memberform',
        'callback' => [$this, 'updateDisplayCallback'],
        'event' => 'change',
      ],
    ];
  }

  /**
   * Format a value for a CSV export file.
   *
   * @param string $value
   *   The value to format for a CSV export.
   *
   * @return string
   *   The formatted value.
   */
  private function csvField(string $value): string {
    if (str_contains($value, '"')) {
      $value = str_replace('"', '""', $value);
    }
    if (str_contains($value, '"') || str_contains($value, ',')) {
      $value = '"' . $value . '"';
    }
    return $value;
  }

  /**
   * Export the badge list as a CSV file.
   *
   * @param int $eid
   *   The event ID.
   * @param string $fields
   *   String containing letters indicating the fields to include.
   * @param string $update
   *   The date to list badges since.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   HTTP response containing headers and file output.
   */
  private function exportBadges(int $eid,
                                string $fields,
                                string $update): Response {
    $badgeNameRows = $this->getBadgeNameRows($eid,
    // 'M' for Member No.
      empty($fields) || str_contains($fields, 'M'),
    // 'N' for Name.
      empty($fields) || str_contains($fields, 'N'),
    // 'B' for Badge Name.
      empty($fields) || str_contains($fields, 'B'),
    // 'T' for Badge Type.
      empty($fields) || str_contains($fields, 'T'),
    // 'D' for Days.
      empty($fields) || str_contains($fields, 'D'),
      $update
    );
    $output = '';
    $separator = '';
    foreach ($badgeNameRows->headers as $label) {
      $output .= $separator . $this->csvField($label);
      $separator = ',';
    }
    foreach ($badgeNameRows->rows as $row) {
      $output .= "\n";
      $separator = '';
      foreach ($row as $value) {

        $output .= $separator . $this->csvField($value);
        $separator = ',';
      }
    }
    $response = new Response($output);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename=badge_names.csv');
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');
    return $response;
  }

  /**
   * Function to return header row and badge name rows.
   *
   * @param int $eid
   *   The event ID.
   * @param bool $showMemberNo
   *   Show member no field if true.
   * @param bool $showMemberName
   *   Show member name field if true.
   * @param bool $showBadgeName
   *   Show badge name field if true.
   * @param bool $showBadgeTypes
   *   Show badge type field if true.
   * @param bool $showMemberTypes
   *   Show member type field if true.
   * @param bool $showDays
   *   Show days member joined for field if true.
   * @param string $updated
   *   Date to get updates since.
   *
   * @return object
   *   Object containing header and rows arrays.
   */
  private function getBadgeNameRows(int $eid,
                                    bool $showMemberNo = TRUE,
                                    bool $showMemberName = TRUE,
                                    bool $showBadgeName = TRUE,
                                    bool $showBadgeTypes = TRUE,
                                    bool $showMemberTypes = FALSE,
                                    bool $showDays = TRUE,
                                    string $updated = NULL): object {
    $config = SimpleConregConfig::getConfig($eid);
    $badgeTypes = SimpleConregOptions::badgeTypes($eid, $config);
    $memberTypes = SimpleConregOptions::memberTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $digits = $config->get('member_no_digits');

    $headers = [];
    if ($showMemberNo) {
      $headers['member_no'] = $this->t('Member no');
    }
    if ($showMemberName) {
      $headers['first_name'] = $this->t('First name');
      $headers['last_name'] = $this->t('Last name');
    }
    if ($showBadgeName) {
      $headers['badge_name'] = $this->t('Badge name');
    }
    if ($showBadgeTypes) {
      $headers['badge_type'] = $this->t('Badge type');
    }
    if ($showMemberTypes && $this->account->hasPermission('view membership badges member type')) {
      $headers['member_type'] = $this->t('Member type');
    }
    if ($showDays) {
      $headers['days'] = $this->t('Days');
    }

    $rows = [];
    $options = [];
    if (!is_null($updated)) {
      $options['update_since'] = $updated;
    }
    foreach (SimpleConregStorage::adminMemberBadges($eid, 0, $options) as $entry) {
      $row = [];
      if ($showMemberNo) {
        $row['member_no'] =
          empty($entry['member_no']) ?
          "" :
          $entry['badge_type'] . sprintf("%0" . $digits . "d", $entry['member_no']);
      }
      if ($showMemberName) {
        $row['first_name'] = $entry['first_name'];
        $row['last_name'] = $entry['last_name'];
      }
      if ($showBadgeName) {
        $row['badge_name'] = $entry['badge_name'];
      }
      if ($showBadgeTypes) {
        $row['badge_type'] = $badgeTypes[$entry['badge_type']] ?? $entry['badge_type'];
      }
      if ($showMemberTypes && $this->account->hasPermission('view membership badges member type')) {
        $row['member_type'] = $memberTypes->types[$entry['member_type']]->name ?? $entry['member_type'];
      }
      if ($showDays) {
        $dayDescs = [];
        if (!empty($entry['days'])) {
          foreach (explode('|', $entry['days']) as $day) {
            $dayDescs[] = $days[$day] ?? $day;
          }
        }
        $row['days'] = implode(', ', $dayDescs);
      }
      $rows[] = $row;
    }

    return (object) ['headers' => $headers, 'rows' => $rows];
  }

  /**
   * Callback function for "display" drop down.
   */
  public function updateDisplayCallback(array $form, FormStateInterface $form_state) {
    // Form rebuilt with required number of members before callback. Return new
    // form.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
