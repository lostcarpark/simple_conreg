<?php

/**
 * @file
 * Contains \Drupal\conreg_badges\Form\BadgeNamesForm
 */

namespace Drupal\conreg_badges\Form;

use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\devel;
use Drupal\simple_conreg\SimpleConregConfig;
use Drupal\simple_conreg\SimpleConregOptions;
use Drupal\simple_conreg\SimpleConregStorage;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class BadgeNamesForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormID()
  {
    return 'simple_conreg_badge_names';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1, $export = false, $fields = null, $update = null)
  {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);

    //Get any existing form values for use in AJAX validation.
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

    $showMemberNo = (isset($form_values['showMemberNo']) ? $form_values['showMemberNo'] : TRUE);
    $exportFields = $showMemberNo ? 'M' : '';
    $form['fields']['showMemberNo'] = $this->checkBox($this->t('Show member number'), TRUE);

    $showMemberName = (isset($form_values['showMemberName']) ? $form_values['showMemberName'] : TRUE);
    $exportFields .= $showMemberName ? 'N' : '';
    $form['fields']['showMemberName'] = $this->checkBox($this->t('Show member name'), TRUE);

    $showBadgeName = (isset($form_values['showBadgeName']) ? $form_values['showBadgeName'] : TRUE);
    $exportFields .= $showBadgeName ? 'B' : '';
    $form['fields']['showBadgeName'] = $this->checkBox($this->t('Show badge name'), TRUE);

    $showBadgeTypes = (isset($form_values['showBadgeTypes']) ? $form_values['showBadgeTypes'] : TRUE);
    $exportFields .= $showBadgeTypes ? 'T' : '';
    $form['fields']['showBadgeTypes'] = $this->checkBox($this->t('Show badge types'), TRUE);

    $showDays = (isset($form_values['showDays']) ? $form_values['showDays'] : TRUE);
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
      // '#default_value' => DrupalDateTime::createFromTimestamp($member->join_date),
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

    $exportUrl = Url::fromRoute('conreg_badges_list_export', ['eid' => $eid, 'fields' => $exportFields, 'update' => $update], ['absolute' => TRUE]);
    $exportLink = Link::fromTextAndUrl($this->t('Export Badge Names'), $exportUrl);

    $form['export']['link'] = array(
      '#type' => 'markup',
      '#prefix' => '<div>',
      '#suffix' => '</div>',
      '#markup' => $exportLink->toString(),
    );

    // Export button - currently export is a link, but may use button in future.
    // $form['exportButton'] = array(
    //   '#type' => 'submit',
    //   '#value' => $this->t('Export Badge Names'),
    //   '#submit' => array([$this, 'exportButtonSubmit']),
    // );


    $form['message'] = [
      '#markup' => $this->t('Here is a list of all paid convention members...'),
      '#prefix' => '<div id="Heading">',
      '#suffix' => '</div>',
    ];

    $badgeNameRows = $this->getBadgeNameRows($eid, $showMemberNo, $showMemberName, $showBadgeName, $showBadgeTypes, $showDays, $update);

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

  private function checkBox($title, $default)
  {
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

  private function csvField($value)
  {
    if (str_contains($value, '"'))
      $value = str_replace('"', '""', $value);
    if (str_contains($value, '"') || str_contains($value, ','))
      $value = '"' . $value . '"';
    return $value;
  }

  private function exportBadges($eid, $fields, $update)
  {
    $badgeNameRows = $this->getBadgeNameRows($eid,
      empty($fields) || str_contains($fields, 'M'), // 'M' for Member No.
      empty($fields) || str_contains($fields, 'N'), // 'N' for Name.
      empty($fields) || str_contains($fields, 'B'), // 'B' for Badge Name.
      empty($fields) || str_contains($fields, 'T'), // 'T' for Badge Type.
      empty($fields) || str_contains($fields, 'D'), // 'D' for Days.
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

  /*
   * Function to return two arrays, one containing the header labels, and the second containing the badge name rows.
   */
  private function getBadgeNameRows($eid, $showMemberNo = TRUE, $showMemberName = TRUE, $showBadgeName = TRUE, $showBadgeTypes = TRUE, $showDays = TRUE, $updated = null)
  {
    $config = SimpleConregConfig::getConfig($eid);
    $badgeTypes = SimpleConregOptions::badgeTypes($eid, $config);
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
        $row['badge_type'] = isset($badgeTypes[$entry['badge_type']]) ? $badgeTypes[$entry['badge_type']] : $entry['badge_type'];
      }
      if ($showDays) {
        $dayDescs = [];
        if (!empty($entry['days'])) {
          foreach (explode('|', $entry['days']) as $day) {
            $dayDescs[] = isset($days[$day]) ? $days[$day] : $day;
          }
        }
        $row['days'] = implode(', ', $dayDescs);
      }
      $rows[] = $row;
    }

    return (object)['headers' => $headers, 'rows' => $rows];
  }

  // Callback function for "display" drop down.
  public function updateDisplayCallback(array $form, FormStateInterface $form_state)
  {
    // Form rebuilt with required number of members before callback. Return new form.
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
  }

  public function exportButtonSubmit(array &$form, FormStateInterface $form_state)
  {
    $content = "Hello World.";
    $file_size = strlen($content);
    header('Content-Description: File Transfer');
    header('Content-Type: text/plain'); //Im assuming it is audio file you can have your own logic to assign content type dynamically for your file types
    header('Content-Disposition: attachment; filename="badge_names.csv"'); //Im assuming it is audio mp3 file you can have your own logic to  assign file extension dynamically for your files 
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . $file_size);
    flush();
    echo($content);
  }

}
