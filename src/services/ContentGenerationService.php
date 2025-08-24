<?php

namespace MadeByBramble\AiContentWriter\services;

use Craft;
use craft\elements\Entry;
use MadeByBramble\AiContentWriter\Plugin;
use yii\base\Component;

/**
 * Content Generation Service
 * 
 * Main service for handling content generation logic including entry context building,
 * field format detection, and content processing based on field types.
 */
class ContentGenerationService extends Component
{
    /**
     * Generate content for a specific entry field
     *
     * @param Entry $entry The entry to generate content for
     * @param string $fieldHandle Handle of the field to populate
     * @param string $prompt User prompt for content generation
     * @return string Generated content formatted for the field type
     * @throws \Exception If content generation fails
     */
    public function generateForEntry(Entry $entry, string $fieldHandle, string $prompt): string
    {
        // Handle built-in fields like 'title'
        $field = null;
        $fieldFormat = 'plain';
        $existingContent = null;
        
        if ($fieldHandle === 'title') {
            // Title field - no field object, but always plain text format
            $fieldFormat = 'plain';
            $existingContent = $entry->title ?? '';
        } else {
            // Get custom field instance
            $field = $entry->getFieldLayout()->getFieldByHandle($fieldHandle);
            
            if (!$field) {
                throw new \Exception("Field '{$fieldHandle}' not found in entry layout");
            }
            
            $fieldFormat = $this->getFieldFormat($field);
            $existingContent = $entry->getFieldValue($fieldHandle);
        }

        // Build context for generation
        $context = [
            'entryType' => $entry->getType()->handle,
            'section' => $entry->getSection()->handle,
            'field' => $fieldHandle,
            'format' => $fieldFormat,
            'existingContent' => $existingContent
        ];

        // Add entry metadata for context
        if ($entry->title) {
            $context['entryTitle'] = $entry->title;
        }

        // Log the generation attempt (debug mode only)
        if (Craft::$app->getConfig()->general->devMode) {
            Craft::info(
                "Generating content for entry '{$entry->title}' (ID: {$entry->id}), field '{$fieldHandle}', format: {$context['format']}",
                'ai-content-writer'
            );
        }

        // Generate content via OpenAI
        $content = Plugin::getInstance()->openAi->generateContent($prompt, $context);

        // Process based on field type
        if ($fieldHandle === 'title') {
            // Title fields are always plain text
            return $this->processPlainTextContent($content);
        } else {
            return $this->processForFieldType($content, $field);
        }
    }

    /**
     * Determine the format type for a field
     *
     * @param \craft\base\Field $field Field instance
     * @return string Format type (plain, html, markdown)
     */
    private function getFieldFormat($field): string
    {
        return match(get_class($field)) {
            'craft\\redactor\\Field' => 'html',
            'craft\\ckeditor\\Field' => 'html',
            'craft\\fields\\PlainText' => 'plain',
            'craft\\fields\\Table' => 'plain',
            default => 'plain'
        };
    }

    /**
     * Process generated content based on field type
     *
     * @param string $content Generated content
     * @param \craft\base\Field $field Field instance
     * @return string Processed content ready for field insertion
     */
    private function processForFieldType(string $content, $field): string
    {
        $fieldClass = get_class($field);

        switch ($fieldClass) {
            case 'craft\\redactor\\Field':
            case 'craft\\ckeditor\\Field':
                // Rich text editors - ensure proper HTML formatting
                return $this->processHtmlContent($content);

            case 'craft\\fields\\PlainText':
                // Plain text - strip HTML and normalize whitespace
                return $this->processPlainTextContent($content);

            case 'craft\\fields\\Table':
                // Table field - process as structured content
                return $this->processTableContent($content, $field);


            default:
                // Default to plain text processing
                if (Craft::$app->getConfig()->general->devMode) {
                    Craft::info(
                        "Unknown field type '{$fieldClass}', defaulting to plain text processing",
                        'ai-content-writer'
                    );
                }
                return $this->processPlainTextContent($content);
        }
    }

    /**
     * Process content for HTML fields (Redactor, CKEditor)
     *
     * @param string $content Generated content
     * @return string Processed HTML content
     */
    private function processHtmlContent(string $content): string
    {
        // Ensure proper HTML structure
        $content = trim($content);
        
        // If content doesn't contain HTML tags, wrap in paragraph
        if (!empty($content) && strpos($content, '<') === false) {
            $content = '<p>' . nl2br(htmlspecialchars($content)) . '</p>';
        }

        return $content;
    }

    /**
     * Process content for plain text fields
     *
     * @param string $content Generated content
     * @return string Processed plain text content
     */
    private function processPlainTextContent(string $content): string
    {
        // Strip HTML tags and normalize whitespace
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        return trim($content);
    }

    /**
     * Process content for table fields
     *
     * @param string $content Generated content
     * @param \craft\base\Field $field Table field instance
     * @return string Processed content for table field
     */
    private function processTableContent(string $content, $field): string
    {
        // For table fields, we'll return the content as plain text
        // The user can manually structure it into table rows/columns
        // Future enhancement: Parse content into table structure automatically
        return $this->processPlainTextContent($content);
    }

    /**
     * Validate that content generation is allowed for the given entry and field
     *
     * @param Entry $entry Entry to validate
     * @param string $fieldHandle Field handle to validate
     * @return bool Whether generation is allowed
     */
    public function canGenerateForField(Entry $entry, string $fieldHandle): bool
    {
        $settings = Plugin::getInstance()->getSettings();
        
        // Check if entry type supports generation
        $entryTypeId = $entry->getType()->id;
        if (!$settings->isGenerationEnabledForEntryType($entryTypeId)) {
            return false;
        }

        // Check if field exists
        $field = $entry->getFieldLayout()->getFieldByHandle($fieldHandle);
        if (!$field) {
            return false;
        }

        // Check if field type is supported
        $fieldClass = get_class($field);
        return $settings->fieldTypeSupport[$fieldClass] ?? false;
    }

    /**
     * Get available fields for content generation on an entry
     *
     * @param Entry $entry Entry to get fields for
     * @return array Available field information
     */
    public function getAvailableFields(Entry $entry): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $entryType = $entry->getType();
        $fields = [];

        // Check if generation is enabled for this entry type
        if (!$settings->isGenerationEnabledForEntryType($entryType->id)) {
            return $fields;
        }

        $layout = $entryType->getFieldLayout();
        if (!$layout) {
            return $fields;
        }

        foreach ($layout->getCustomFields() as $field) {
            $fieldClass = get_class($field);
            if ($settings->fieldTypeSupport[$fieldClass] ?? false) {
                $fields[] = [
                    'handle' => $field->handle,
                    'name' => $field->name,
                    'type' => $fieldClass,
                    'id' => $field->id,
                    'format' => $this->getFieldFormat($field)
                ];
            }
        }

        return $fields;
    }

}