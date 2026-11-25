<?php

namespace Drupal\ai_reading_age\Plugin\AiFunctionCall;

use DaveChild\TextStatistics\TextStatistics;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[FunctionCall(
  id: 'text_statistics',
  function_name: 'text_statistics',
  name: 'Text statistics',
  description: 'Get statistics on the readability of some text.',
  context_definitions: [
    'text' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Text'),
      required: TRUE,
      description: new TranslatableMarkup('The text to get statistics for.'),
    ),
  ],
)]
class TextStatisticsTool extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $stats = new TextStatistics();
    $stats->setText($this->getContextValue('text'));

    $avg_grade = round($this->avg(
      $stats->fleschKincaidGradeLevel(),
//      $stats->gunningFogScore(),
//      $stats->colemanLiauIndex(),
    ), 1);

    $this->setOutput("Readability grade: {$avg_grade}");
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

}
