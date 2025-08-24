<?php

namespace MadeByBramble\AiContentWriter\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\Entry;
use MadeByBramble\AiContentWriter\Plugin;

/**
 * Generate Content Job
 * 
 * Background job for generating content for a single entry field.
 * Used to avoid timeouts and provide better user experience.
 */
class GenerateContentJob extends BaseJob
{
    /**
     * @var int Entry ID to generate content for
     */
    public int $entryId;
    
    /**
     * @var string Field handle to populate with generated content
     */
    public string $fieldHandle;
    
    /**
     * @var string User prompt for content generation
     */
    public string $prompt;
    
    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        // Find the entry
        $entry = Entry::find()->id($this->entryId)->one();
        if (!$entry) {
            throw new \Exception("Entry not found: {$this->entryId}");
        }
        
        Craft::info(
            "Starting content generation job for entry {$this->entryId}, field '{$this->fieldHandle}'",
            'ai-content-writer'
        );
        
        // Generate content
        $content = Plugin::getInstance()->contentGeneration->generateForEntry(
            $entry, 
            $this->fieldHandle, 
            $this->prompt
        );
        
        // Set the field value and save entry
        $entry->setFieldValue($this->fieldHandle, $content);
        $success = Craft::$app->elements->saveElement($entry);
        
        if (!$success) {
            $errors = implode(', ', $entry->getFirstErrors());
            throw new \Exception("Failed to save entry: {$errors}");
        }
        
        Craft::info(
            "Content generation job completed successfully for entry {$this->entryId}",
            'ai-content-writer'
        );
    }
    
    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('ai-content-writer', 'Generating content for entry {entryId}', [
            'entryId' => $this->entryId
        ]);
    }
}