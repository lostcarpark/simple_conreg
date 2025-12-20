<?php

declare(strict_types=1);

namespace Drupal\simple_conreg\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\simple_conreg\SimpleConregOptions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a price and availability block.
 */
#[Block(
  id: 'simple_conreg_price_availability',
  admin_label: new TranslatableMarkup('Price and Availability'),
  category: new TranslatableMarkup('ConReg'),
)]
final class PriceAvailabilityBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the plugin instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'eid' => 1,
      'price_text' => $this->t('Describe prices and availability here...'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['eid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event ID'),
      '#default_value' => $this->configuration['eid'],
    ];
    $form['price_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Price and availability text'),
      '#description' => $this->t('Describe the price and availability of member types. Replacable tokens: [price:type], [quantity:type], [remaining:type].'),
      '#default_value' => $this->configuration['price_text'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['eid'] = (int) $form_state->getValue('eid');
    $this->configuration['price_text'] = $form_state->getValue('price_text');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $eid = $this->configuration['eid'];
    $config = $this->configFactory->get('simple_conreg.settings.' . $eid);
    $types = SimpleConregOptions::memberTypes($eid, $config);
    $price_text = $this->configuration['price_text'];
    foreach($types->types as $type_id => $type_vals) {
      $search = [
        "[price:$type_id]",
        "[quantity:$type_id]",
        "[remaining:$type_id]",
      ];
      $replace = [
        $type_vals->price,
        $type_vals->number_allowed,
        ($type_vals->remaining ?? 0) > 0 ? $this->t('%remaining left', ['%remaining' => $type_vals->remaining]) : $this->t('Sold out!'),
      ];
      $price_text = str_replace($search, $replace, $price_text);
    }
    $build['content'] = [
      '#cache' => [
        'tags' => ['event:' . $eid . ':type'],
        'tags' => ['event:' . $eid . ':remaining'],
      ],
      '#markup' => $price_text,
    ];
    return $build;
  }

}
