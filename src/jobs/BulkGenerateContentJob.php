<?php

namespace MadeByBramble\AiContentWriter\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\Entry;
use MadeByBramble\AiContentWriter\Plugin;

/**
 * Bulk Generate Content Job
 * 
 * Background job for generating content for multiple entries or fields.
 * Processes items in batches to avoid memory issues and provide progress updates.
 */
class BulkGenerateContentJob extends BaseJob
{
    /**
     * @var array Array of generation tasks: [['entryId' => int, 'fieldHandle' => string, 'prompt' => string], ...]
     */
    public array $generationTasks;
    
    /**
     * @var int Current task index for progress tracking
     */
    public int $currentIndex = 0;
    
    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $totalTasks = count($this->generationTasks);
        
        if ($totalTasks === 0) {
            return;
        }
        
        Craft::info(
            "Starting bulk content generation job with {$totalTasks} tasks",
            'ai-content-writer'
        );
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        
        foreach ($this->generationTasks as $index => $task) {
            $this->currentIndex = $index;
            
            // Update progress
            $this->setProgress(
                $queue,
                ($index / $totalTasks),
                Craft::t('ai-content-writer', 'Processing {current} of {total}', [
                    'current' => $index + 1,
                    'total' => $totalTasks
                ])
            );
            
            try {
                // Find the entry
                $entry = Entry::find()->id($task['entryId'])->one();
                if (!$entry) {
                    throw new \Exception("Entry not found: {$task['entryId']}");
                }
                
                // Generate content
                $content = Plugin::getInstance()->contentGeneration->generateForEntry(
                    $entry,
                    $task['fieldHandle'],
                    $task['prompt']
                );
                
                // Set the field value and save entry
                $entry->setFieldValue($task['fieldHandle'], $content);
                $success = Craft::$app->elements->saveElement($entry);
                
                if (!$success) {
                    $entryErrors = implode(', ', $entry->getFirstErrors());
                    throw new \Exception("Failed to save entry {$task['entryId']}: {$entryErrors}");
                }
                
                $successCount++;
                
                Craft::info(
                    "Bulk generation completed for entry {$task['entryId']}, field '{$task['fieldHandle']}'",
                    'ai-content-writer'
                );
                
            } catch (\Throwable $e) {
                $errorCount++;
                $errorMessage = "Entry {$task['entryId']}, field '{$task['fieldHandle']}': {$e->getMessage()}";
                $errors[] = $errorMessage;
                
                Craft::error(
                    "Bulk generation failed: {$errorMessage}",
                    'ai-content-writer'
                );
            }
        }
        
        // Final progress update
        $this->setProgress($queue, 1, Craft::t('ai-content-writer', 'Completed'));
        
        $message = "Bulk content generation completed: {$successCount} successful, {$errorCount} failed";
        if ($errorCount > 0) {
            $message .= ". Errors: " . implode('; ', $errors);
        }
        
        Craft::info($message, 'ai-content-writer');
        
        if ($errorCount > 0) {
            throw new \Exception($message);
        }
    }
    
    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        $totalTasks = count($this->generationTasks);
        return Craft::t('ai-content-writer', 'Bulk generating content for {count} items', [
            'count' => $totalTasks
        ]);
    }
}