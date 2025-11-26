<?php

namespace Drupal\conreg_lookup\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\simple_conreg\SimpleConregOptions;
use Drupal\simple_conreg\SimpleConregEventStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple form to add an entry, with all the interesting fields.
 */
class LookupMemberForm extends FormBase {

  /**
   * The renderer object.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Storage for private data.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $privateTempStoreFactory;

  /**
   * Constructor for member lookup form.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer object.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $privateTempStoreFactory
   *   The store for private data.
   */
  public function __construct(RendererInterface $renderer, Connection $database, PrivateTempStoreFactory $privateTempStoreFactory) {
    $this->renderer = $renderer;
    $this->database = $database;
    $this->privateTempStoreFactory = $privateTempStoreFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
      // Load the service required to construct this class.
      $container->get('renderer'),
      $container->get('database'),
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_conreg_admin_members';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $eid = 1) {
    // Store Event ID in form state.
    $form_state->set('eid', $eid);
    $event = SimpleConregEventStorage::load(['eid' => $eid]);

    // Get any existing form values for use in AJAX validation.
    $form_values = $form_state->getValues();

    $config = $this->config('simple_conreg.settings.' . $eid);
    $badgeTypes = SimpleConregOptions::badgeTypes($eid, $config);
    $days = SimpleConregOptions::days($eid, $config);
    $digits = $config->get('member_no_digits');

    $tempstore = $this->privateTempStoreFactory->get('simple_conreg');
    // Use form value if submittd, if not check for previous search.
    $search = $form_values['search'] ?? $tempstore->get('lookup_search') ?? '';
    $tempstore->set('lookup_search', $search);

    $form = [
      '#attached' => [
        'library' => ['simple_conreg/conreg_tables'],
      ],
      '#title' => $this->t('@event_name Member Lookup', ['@event_name' => $event['event_name']]),
      '#prefix' => '<div id="memberform">',
      '#suffix' => '</div>',
    ];

    $headers = [
      'member_no' => [
        'data' => $this->t('Member No'),
        'field' => 'm.member_no',
      ],
      'first_name' => [
        'data' => $this->t('First name'),
        'field' => 'm.first_name',
      ],
      'last_name' => [
        'data' => $this->t('Last name'),
        'field' => 'm.last_name',
      ],
      'email' => [
        'data' => $this->t('Email'),
        'field' => 'm.email',
      ],
      'phone' => [
        'data' => $this->t('Phone'),
        'field' => 'm.phone',
      ],
      'badge_name' => [
        'data' => $this->t('Badge name'),
        'field' => 'm.badge_name',
      ],
      'days' => [
        'data' => $this->t('Days'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'badge_type' => [
        'data' => $this->t('Badge type'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'registered_by' => [
        'data' => $this->t('Registered By'),
        'field' => 'm.registered_by',
      ],
    ];

    $form['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom search term'),
      '#default_value' => trim($search),
    ];

    $form['search_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Search'),
      '#attributes' => ['id' => "searchBtn"],
      '#validate' => [],
      '#submit' => ['::search'],
      '#ajax' => [
        'wrapper' => 'memberform',
        'callback' => [$this, 'updateDisplayCallback'],
      ],
    ];

    if (strlen($search) < 3) {
      $form['message'] = [
        '#markup' => $this->t('Please enter at least 3 characters in search box.'),
        '#prefix' => '<div id="memberform">',
        '#suffix' => '</div>',
      ];
      return $form;
    }

    $form['table'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#attributes' => ['id' => 'simple-conreg-admin-member-list'],
      '#empty' => $this->t('No entries available.'),
      '#sticky' => TRUE,
    ];

    $entries = $this->adminMemberLookupLoad($eid, $search);

    foreach ($entries as $entry) {
      $mid = $entry['mid'];
      // Sanitize each entry.
      $row = [];
      if (empty($entry["member_no"])) {
        $member_no = "";
      }
      else {
        $member_no = trim($entry['badge_type']) . sprintf("%0" . $digits . "d", $entry['member_no']);
      }
      $row["member_no"] = [
        '#markup' => $member_no,
      ];
      $row['first_name'] = [
        '#markup' => Html::escape($entry['first_name']),
      ];
      $row['last_name'] = [
        '#markup' => Html::escape($entry['last_name']),
      ];
      $row['email'] = [
        '#markup' => Html::escape($entry['email']),
      ];
      $row['phone'] = [
        '#markup' => Html::escape($entry['phone']),
      ];
      $row['badge_name'] = [
        '#markup' => Html::escape($entry['badge_name']),
      ];
      if (!empty($entry['days'])) {
        $dayDescs = [];
        foreach (explode('|', $entry['days']) as $day) {
          $dayDescs[] = $days[$day] ?? $day;
        }
        $memberDays = implode(', ', $dayDescs);
      }
      else {
        $memberDays = '';
      }
      $row['days'] = [
        '#markup' => Html::escape($memberDays),
      ];
      $badgeType = trim($entry['badge_type']);
      $row['badge_type'] = [
        '#markup' => Html::escape($badgeTypes[$badgeType] ?? $badgeType),
      ];
      $row['registered_by'] = [
        '#markup' => Html::escape($entry['registered_by'] ?? ''),
      ];
      $form['table'][$mid] = $row;
    }

    return $form;
  }

  /**
   * Callback function for "member type" and "add-on" drop-downs.
   */
  public function updateApprovedCallback(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $ajax_response = new AjaxResponse();
    if (preg_match("/table\[(\d+)\]\[is_approved\]/", $triggering_element['#name'], $matches)) {
      $mid = $matches[1];
      $form['table'][$mid]["member_div"]["member_no"]['#value'] = $triggering_element['#value'];
      $ajax_response->addCommand(new HtmlCommand('#member_no_' . $mid, $this->renderer->render($form['table'][$mid]["member_div"]["member_no"]['#value'])));
      // $ajax_response->addCommand(new AlertCommand($row." = ".));
    }
    return $ajax_response;
  }

  /**
   * Callback function for "member type" and "add-on" drop-downs.
   */
  public function updateTestCallback(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $ajax_response = new AjaxResponse();
    $ajax_response->addCommand(new AlertCommand($triggering_element['#name'] . " = " . $triggering_element['#value']));
    return $ajax_response;
  }

  /**
   * Callback function for "display" drop down.
   */
  public function updateDisplayCallback(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Callback for searching.
   */
  public function search(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    Cache::invalidateTags(['simple-conreg-member-list']);
  }

  /**
   * Ajax callback for loading table.
   */
  private function adminMemberLookupLoad($eid, $search) {
    $select = $this->database->select('conreg_members', 'm');
    $select->leftJoin('conreg_members', 'l', 'l.mid = m.lead_mid');
    // Select these specific fields for the output.
    $select->addField('m', 'mid');
    $select->addField('m', 'first_name');
    $select->addField('m', 'last_name');
    $select->addField('m', 'email');
    $select->addField('m', 'badge_name');
    $select->addField('m', 'days');
    $select->addField('m', 'badge_type');
    $select->addField('m', 'member_no');
    $select->addField('m', 'phone');
    $select->addExpression("concat(l.first_name, ' ', l.last_name)", 'registered_by');
    // Add selection criteria.
    $words = explode(' ', trim($search));
    foreach ($words as $word) {
      if ($word != '') {
        // Escape search word to prevent dangerous characters.
        $esc_word = '%' . $this->database->escapeLike($word) . '%';
        $likes = $select->orConditionGroup()
          ->condition('m.member_no', $esc_word, 'LIKE')
          ->condition('m.first_name', $esc_word, 'LIKE')
          ->condition('m.last_name', $esc_word, 'LIKE')
          ->condition('m.badge_name', $esc_word, 'LIKE')
          ->condition('m.email', $esc_word, 'LIKE');
        $select->condition($likes);
      }
    }
    $select->condition("m.is_deleted", FALSE);
    $select->condition("m.is_paid", TRUE);
    $select->orderby("member_no");

    $entries = $select->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return $entries;
  }

}
