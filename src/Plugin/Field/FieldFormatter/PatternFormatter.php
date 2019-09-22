<?php

namespace Drupal\ui_patterns_field_formatters\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ui_patterns\Form\PatternDisplayFormTrait;
use Drupal\ui_patterns\UiPatternsSourceManager;
use Drupal\ui_patterns\UiPatternsManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Plugin implementation of the 'pattern' formatter.
 *
 * @FieldFormatter(
 *   id = "pattern_formatter",
 *   label = @Translation("Pattern"),
 *   field_types = {
 *     "boolean",
 *     "changed",
 *     "comment",
 *     "created",
 *     "datetime",
 *     "decimal",
 *     "email",
 *     "entity_reference",
 *     "file",
 *     "float",
 *     "image",
 *     "integer",
 *     "language",
 *     "link",
 *     "list_float",
 *     "list_integer",
 *     "list_string",
 *     "map",
 *     "password",
 *     "path",
 *     "string",
 *     "string_long",
 *     "telephone",
 *     "text",
 *     "text_long",
 *     "text_with_summary",
 *     "timestamp",
 *     "uri",
 *     "uuid"
 *   },
 * )
 */
class PatternFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  use PatternDisplayFormTrait;

  /**
   * UI Patterns manager.
   *
   * @var \Drupal\ui_patterns\UiPatternsManager
   */
  protected $patternsManager;

  /**
   * UI Patterns source manager.
   *
   * @var \Drupal\ui_patterns\UiPatternsSourceManager
   */
  protected $sourceManager;

  /**
   * A module manager object.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param \Drupal\ui_patterns\UiPatternsManager $patterns_manager
   *   UI Patterns manager.
   * @param \Drupal\ui_patterns\UiPatternsSourceManager $source_manager
   *   UI Patterns source manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, UiPatternsManager $patterns_manager, UiPatternsSourceManager $source_manager, ModuleHandlerInterface $module_handler) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->patternsManager = $patterns_manager;
    $this->sourceManager = $source_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.ui_patterns'),
      $container->get('plugin.manager.ui_patterns_source'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'pattern' => '',
      'variants' => '',
      'pattern_mapping' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $field_storage_definition = $this->fieldDefinition->getFieldStorageDefinition();
    $context = [
      'storageDefinition' => $field_storage_definition,
      'limit' => $field_storage_definition->getPropertyNames(),
    ];
    $configuration = $this->getSettings();
    $pattern = $this->getSetting('pattern');
    $configuration['pattern_variant'] = $this->getSetting('variants')[$pattern];
    $this->buildPatternDisplayForm($form, 'field_properties', $context, $configuration);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $pattern = $this->getSetting('pattern');
    $pattern = $this->patternsManager->getDefinition($pattern);

    $label = $this->t('None');
    if (!empty($this->getSetting('pattern'))) {
      $label = $pattern->getLabel();
    }
    $summary[] = $this->t('Pattern: @pattern', ['@pattern' => $label]);

    if (!empty($this->getSetting('variants'))) {
      $variant = $this->getSetting('variants')[$pattern->id()];
      $variant = $pattern->getVariant($variant)->getLabel();
      $summary[] = $this->t('Variant: @variant', ['@variant' => $variant]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      $pattern = $this->getSetting('pattern');

      // Set pattern fields.
      $fields = [];
      $mapping = $this->getSetting('pattern_mapping');
      $mapping = $mapping[$pattern]['settings'];
      foreach ($mapping as $source => $field) {
        // Get rid of the source tag.
        $source = explode(":", $source)[1];
        $fields[$field['destination']] = (string) $item->get($source)->getValue();
      }

      // Set pattern render array.
      $elements[$delta] = [
        '#type' => 'pattern',
        '#id' => $this->getSetting('pattern'),
        '#fields' => $fields,
      ];

      // Set the variant.
      if (!empty($this->getSetting('variants'))) {
        $variant = $this->getSetting('variants')[$pattern];
        $elements[$delta]['#variant'] = $variant;
      }

      // Set pattern context.
      // TODO: Add context.
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultValue(array $configuration, $field_name, $value) {
    $pattern = $configuration['pattern'];
    if (isset($configuration['pattern_mapping'][$pattern]['settings'][$field_name][$value])) {
      return $configuration['pattern_mapping'][$pattern]['settings'][$field_name][$value];
    }
    return NULL;
  }

}
