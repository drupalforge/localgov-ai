<?php

namespace Drupal\ai_reading_age\Plugin\AiContentSuggestions;

use Drupal\ai_agents\PluginInterfaces\AiAgentInterface;
use Drupal\ai_agents\PluginManager\AiAgentManager;
use Drupal\ai_agents\Task\Task;
use Drupal\ai_content_suggestions\AiContentSuggestionsFormAlter;
use Drupal\ai_content_suggestions\AiContentSuggestionsPluginBase;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the ai_content_suggestions.
 *
 * @AiContentSuggestions(
 *   id = "text_statistics",
 *   label = @Translation("Readability"),
 *   description = @Translation("Assess and improve the readability of the content."),
 *   operation_type = "chat"
 * )
 */
class ReadabilitySuggestions extends AiContentSuggestionsPluginBase {

  private AiAgentManager $agentManager;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->agentManager = $container->get('plugin.manager.ai_agents');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, array $fields) {
    $element = [
      '#type' => 'details',
      '#title' => $this->label(),
      '#group' => 'advanced',
      '#tree' => TRUE,
    ];
    $element['target_fields'][0] = [
      '#type' => 'select',
      '#options' => $fields,
      '#default_value' => isset($fields['body']) ? 'body' : NULL,
    ];
    $element['submit'] = [
      '#type' => 'button',
      '#value' => $this->t('Calculate readability'),
      '#plugin' => $this->getPluginId(),
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [AiContentSuggestionsFormAlter::class, 'getPluginResponse'],
        'wrapper' => $this->getAjaxId(),
      ],
      '#action' => 'calculate',
    ];
    $element['response'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $this->getAjaxId(),
      ],
      'improve' => [
        '#type' => 'button',
        '#value' => $this->t('Improve readability'),
        '#plugin' => $this->getPluginId(),
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [AiContentSuggestionsFormAlter::class, 'getPluginResponse'],
          'wrapper' => $this->getAjaxId(),
        ],
        '#action' => 'improve',
        '#printed' => TRUE,
        '#weight' => 50,
      ],
      '#weight' => 50,
    ];
    $form[$this->getPluginId()] = $element;
  }

  /**
   * {@inheritdoc}
   */
  public function updateFormWithResponse(array &$form, FormStateInterface $form_state): void {
    $value = $this->getTargetFieldValue($form_state);
    if (!$value) {
      $form[$this->getPluginId()]['response']['error'] = [
        '#markup' => $this->t('Please select a non-empty field.'),
      ];
      return;
    }

    $form[$this->getPluginId()]['response']['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#attributes' => ['class' => ['form-item__description']],
      '#value' => $this->t('US grade 3 is aged 8-9 and is the target. See @link for descriptions of the alorithms used.', [
        '@link' => Link::fromTextAndUrl(
          'Readable.com',
          Url::fromUri(
            'https://readable.com/readability/readability-formulas/',
            ['attributes' => ['target' => '_blank']],
          ),
        )->toString(),
      ]),
    ];

    $results = $this->getReadabilityStats($value);
    $avg_grade = end($results)[1];

    $form[$this->getPluginId()]['response']['results'] = [
      '#theme' => 'item_list',
      '#items' => array_map(
        fn (array $result) => new FormattableMarkup('@label: @score', [
          '@label' => $result[0],
          '@score' => $result[1],
        ]),
        $results,
      ),
    ];

    if ($avg_grade < 4) {
      $form[$this->getPluginId()]['response']['error'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Readability is already meeting the target, no action required.'),
      ];
      return;
    }


    if ($form_state->getTriggeringElement()['#action'] !== 'improve') {
      $form[$this->getPluginId()]['response']['improve']['#printed'] = FALSE;
      return;
    }

    $form[$this->getPluginId()]['response']['results']['#title'] = $this->t('Original results');

    $default_provider = $this->providerPluginManager->getDefaultProviderForOperationType('chat_with_tools');
    $provider = $this->providerPluginManager->createInstance($default_provider['provider_id']);
    $agent = $this->agentManager->createInstance('improve_readability');
    $agent->setAiProvider($provider);
    $agent->setModelName($default_provider['model_id']);
    $agent->setTask(new Task($value));
    if ($agent->determineSolvability() === AiAgentInterface::JOB_SOLVABLE) {
      $message = $agent->solve();
    }
    else {
      $message = 'Unable to resolve in the allowed iterations.';
    }
    $form[$this->getPluginId()]['response']['result'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => nl2br($message),
      '#weight' => 60,
    ];

    /** @var \Drupal\ai_reading_age\Plugin\AiFunctionCall\TextStatisticsTool[] $tool_calls */
    $tool_calls = $agent->getToolResults();
    $last_call = end($tool_calls);
    $final_copy = $last_call->getContextValue('text');
    $results = $this->getReadabilityStats($final_copy);

    $form[$this->getPluginId()]['response']['new_results'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('New results'),
      '#items' => array_map(
        fn (array $result) => new FormattableMarkup('@label: @score', [
          '@label' => $result[0],
          '@score' => $result[1],
        ]),
        $results,
      ),
      '#weight' => 70,
    ];

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand(NULL, $form[$this->getPluginId()]['response']));

    $field_names = $this->getFormFieldValue('target_fields', $form_state);
    $field_name = reset($field_names);
    $source_field = $form[$field_name];
    unset($source_field['#group']);

    if (isset($source_field['widget'][0])) {
      $source_element = &$source_field['widget'][0];
    }
    else {
      $source_element = &$source_field['widget'];
    }
    if (isset($source_element['value'])) {
      $source_element['value']['#value'] = $final_copy;
    }
    else {
      $source_element['#value'] = $final_copy;
    }

    $response->addCommand(new ReplaceCommand('[data-drupal-selector="' . $source_field['#attributes']['data-drupal-selector'] . '"]', $source_field));
    $form[$this->getPluginId()]['response'] = $response;
  }

  /**
   * Get an average for an array of numbers.
   *
   * @param array<int|float> $nums
   *   The numbers to average.
   *
   * @return float
   *   The average.
   */
  private function avg(int|float ...$nums): float {
    return array_sum($nums) / count($nums);
  }

  /**
   * @param \DaveChild\TextStatistics\TextStatistics $stats
   *
   * @return array[]
   */
  private function getReadabilityStats(string $text): array {
    $stats = new \DaveChild\TextStatistics\TextStatistics();
    $stats->setText($text);

    $results = [
      [$this->t('Flesch-Kincaid'), $stats->fleschKincaidGradeLevel()],
//      [$this->t('Gunning Fog'), $stats->gunningFogScore()],
//      [$this->t('Coleman Liau'), $stats->colemanLiauIndex()],
    ];

//    $results[] = [
//      $this->t('Average'),
//      round(
//        $this->avg(...array_map(
//          fn(array $result) => $result[1],
//          $results,
//        )),
//        1,
//      ),
//    ];

    return $results;
  }

}
