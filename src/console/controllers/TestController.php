<?php

namespace MadeByBramble\AiContentWriter\console\controllers;

use craft\console\Controller;
use MadeByBramble\AiContentWriter\Plugin;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Test controller for AI Content Writer plugin
 *
 * Tests content generation across all configured OpenAI models.
 *
 * Usage:
 *   php craft ai-content-writer/test
 *   php craft ai-content-writer/test --model=gpt-5.2
 */
class TestController extends Controller
{
    /**
     * @var string Specific model to test (optional)
     */
    public string $model = '';

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'model';
        return $options;
    }

    /**
     * Test content generation across all configured models (default action)
     *
     * Makes real API calls to verify each model works correctly.
     * Use --model=gpt-4o to test a specific model only.
     */
    public function actionIndex(): int
    {
        $this->stdout("\n=== AI Content Writer - Model Test ===\n\n");

        $modelConfig = Plugin::getInstance()->modelConfig;
        $openAi = Plugin::getInstance()->openAi;

        // Validate configs first
        $issues = $modelConfig->validateConfigs();
        if (!empty($issues)) {
            $this->stderr("Configuration errors found:\n", Console::FG_RED);
            foreach ($issues as $issue) {
                $this->stderr("  - {$issue}\n");
            }
            return ExitCode::CONFIG;
        }

        // Test prompt
        $prompt = 'Write exactly one sentence describing what makes a good website.';

        // Get all configured models, sorted by priority
        $allModels = $modelConfig->getAllModels();
        usort($allModels, fn($a, $b) => ($b['metadata']['priority'] ?? 0) - ($a['metadata']['priority'] ?? 0));
        $modelsToTest = array_map(fn($m) => $m['model']['id'], $allModels);

        // If specific model requested, only test that one
        if (!empty($this->model)) {
            $config = $modelConfig->getModelConfig($this->model);
            if (!$config) {
                $this->stderr("Model '{$this->model}' not found.\n", Console::FG_RED);
                $this->stdout("Available models: " . implode(', ', $modelsToTest) . "\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $modelsToTest = [$this->model];
        }

        $this->stdout("Testing " . count($modelsToTest) . " model(s)...\n\n");

        $results = [];
        $passed = 0;
        $failed = 0;

        foreach ($modelsToTest as $modelId) {
            $this->stdout("  {$modelId}... ");

            $result = $openAi->generateWithModel($modelId, $prompt, ['format' => 'plain']);
            $results[$modelId] = $result;

            if ($result['success']) {
                $passed++;
                $this->stdout("✓", Console::FG_GREEN);
                $this->stdout(" {$result['duration']}s, {$result['usage']['total_tokens']} tokens\n");

                // Show truncated content
                $content = $result['content'];
                if (strlen($content) > 80) {
                    $content = substr($content, 0, 80) . '...';
                }
                $this->stdout("    \"{$content}\"\n", Console::FG_CYAN);
            } else {
                $failed++;
                $this->stdout("✗", Console::FG_RED);
                $this->stdout(" {$result['duration']}s - {$result['error']}\n");
            }
        }

        // Summary
        $this->stdout("\n");
        if ($failed === 0) {
            $this->stdout("✓ All {$passed} models passed\n", Console::FG_GREEN);
        } else {
            $this->stdout("Results: {$passed} passed, {$failed} failed\n", Console::FG_YELLOW);
        }

        // Average response time
        $durations = array_filter(array_map(fn($r) => $r['success'] ? $r['duration'] : null, $results));
        if (!empty($durations)) {
            $avg = round(array_sum($durations) / count($durations), 2);
            $this->stdout("Avg response: {$avg}s\n");
        }

        return $failed === 0 ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }
}
