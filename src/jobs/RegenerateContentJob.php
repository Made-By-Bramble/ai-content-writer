<?php

namespace MadeByBramble\AiContentWriter\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\elements\Entry;
use MadeByBramble\AiContentWriter\Plugin;

/**
 * Regenerate Content Job
 * 
 * Background job for regenerating content for entries that already have content.
 * This replaces existing content with newly generated content based on the same or updated prompts.
 */
class RegenerateContentJob extends BaseJob
{
    /**
     * @var int Entry ID to regenerate content for
     */
    public int $entryId;
    
    /**
     * @var string Field handle to regenerate content for
     */
    public string $fieldHandle;
    
    /**
     * @var string New prompt for content generation
     */
    public string $prompt;
    
    /**
     * @var bool Whether to backup the original content before regenerating
     */
    public bool $backupOriginal = true;
    
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
            "Starting content regeneration job for entry {$this->entryId}, field '{$this->fieldHandle}'",
            'ai-content-writer'
        );
        
        // Backup original content if requested
        $originalContent = null;
        if ($this->backupOriginal) {
            $originalContent = $entry->getFieldValue($this->fieldHandle);
            
            if (!empty($originalContent)) {
                Craft::info(
                    "Backing up original content for entry {$this->entryId}, field '{$this->fieldHandle}' (length: " . strlen($originalContent) . ")",
                    'ai-content-writer'
                );
            }
        }
        
        try {
            // Generate new content
            $newContent = Plugin::getInstance()->contentGeneration->generateForEntry(
                $entry, 
                $this->fieldHandle, 
                $this->prompt
            );
            
            // Set the new field value and save entry
            $entry->setFieldValue($this->fieldHandle, $newContent);
            $success = Craft::$app->elements->saveElement($entry);
            
            if (!$success) {
                $errors = implode(', ', $entry->getFirstErrors());
                
                // If backup exists and save failed, attempt to restore original content
                if ($this->backupOriginal && $originalContent !== null) {
                    $entry->setFieldValue($this->fieldHandle, $originalContent);
                    $restoreSuccess = Craft::$app->elements->saveElement($entry);
                    
                    if ($restoreSuccess) {
                        throw new \Exception("Failed to save entry with new content, restored original content: {$errors}");
                    } else {
                        throw new \Exception("Failed to save entry with new content AND failed to restore original content: {$errors}");
                    }
                } else {
                    throw new \Exception("Failed to save entry: {$errors}");
                }
            }
            
            Craft::info(
                "Content regeneration job completed successfully for entry {$this->entryId}. New content length: " . strlen($newContent),
                'ai-content-writer'
            );
            
        } catch (\Throwable $e) {
            // If content generation failed and we have a backup, attempt to restore
            if ($this->backupOriginal && $originalContent !== null) {
                try {
                    $entry->setFieldValue($this->fieldHandle, $originalContent);
                    Craft::$app->elements->saveElement($entry);
                    
                    Craft::error(
                        "Content regeneration failed for entry {$this->entryId}, restored original content: " . $e->getMessage(),
                        'ai-content-writer'
                    );
                } catch (\Throwable $restoreException) {
                    Craft::error(
                        "Content regeneration failed AND failed to restore original content for entry {$this->entryId}: " . $e->getMessage() . " | Restore error: " . $restoreException->getMessage(),
                        'ai-content-writer'
                    );
                }
            }
            
            throw $e;
        }
    }
    
    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('ai-content-writer', 'Regenerating content for entry {entryId}', [
            'entryId' => $this->entryId
        ]);
    }
}