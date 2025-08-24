<?php

namespace MadeByBramble\AiContentWriter\services;

use Craft;
use craft\elements\Entry;
use craft\models\EntryType;
use MadeByBramble\AiContentWriter\Plugin;
use yii\base\Component;

/**
 * Entry Type Service
 * 
 * Handles entry type configurations and field mappings for content generation.
 * Provides methods to determine which entry types and fields are available for AI content generation.
 */
class EntryTypeService extends Component
{
    /**
     * Get available fields for content generation on a specific entry type
     *
     * @param EntryType $entryType Entry type to get fields for
     * @return array Available field information
     */
    public function getAvailableFields(EntryType $entryType): array
    {
        $fields = [];
        $layout = $entryType->getFieldLayout();
        
        if (!$layout) {
            return $fields;
        }
        
        $settings = Plugin::getInstance()->getSettings();
        
        // Add title field support if entry type has title field
        if ($entryType->hasTitleField) {
            $titleFieldClass = 'craft\\fieldlayoutelements\\entries\\EntryTitleField';
            if ($this->isFieldTypeSupported($titleFieldClass)) {
                $fields[] = [
                    'handle' => 'title',
                    'name' => 'Title',
                    'type' => $titleFieldClass,
                    'id' => 'title',
                    'instructions' => 'Entry title field',
                    'required' => true,
                    'isBuiltIn' => true
                ];
            }
        }
        
        // Process custom fields with fixed detection logic
        foreach ($layout->getCustomFields() as $field) {
            $fieldClass = get_class($field);
            
            if ($this->isFieldTypeSupported($fieldClass)) {
                $fields[] = [
                    'handle' => $field->handle,
                    'name' => $field->name,
                    'type' => $fieldClass,
                    'id' => $field->id,
                    'instructions' => $field->instructions,
                    'required' => $field->required,
                    'isBuiltIn' => false
                ];
            }
        }
        
        return $fields;
    }

    /**
     * Check if content generation is enabled for a specific entry type
     *
     * @param int $entryTypeId Entry type ID to check
     * @return bool Whether generation is enabled
     */
    public function isGenerationEnabled(int $entryTypeId): bool
    {
        $settings = Plugin::getInstance()->getSettings();
        return $settings->entryTypeSettings[$entryTypeId]['enabled'] ?? false;
    }

    /**
     * Get default fields configured for a specific entry type
     *
     * @param int $entryTypeId Entry type ID
     * @return array Array of field handles
     */
    public function getDefaultFields(int $entryTypeId): array
    {
        $settings = Plugin::getInstance()->getSettings();
        return $settings->entryTypeSettings[$entryTypeId]['defaultFields'] ?? [];
    }

    /**
     * Get all entry types with their generation status
     *
     * @return array Entry types with generation status
     */
    public function getAllEntryTypesWithStatus(): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $entryTypes = [];

        $sections = Craft::$app->getEntries()->getAllSections();
        
        foreach ($sections as $section) {
            foreach ($section->getEntryTypes() as $entryType) {
                $entryTypes[] = [
                    'id' => $entryType->id,
                    'name' => $entryType->name,
                    'handle' => $entryType->handle,
                    'section' => $section->name,
                    'sectionHandle' => $section->handle,
                    'enabled' => $this->isGenerationEnabled($entryType->id),
                    'defaultFields' => $this->getDefaultFields($entryType->id),
                    'availableFields' => $this->getAvailableFields($entryType)
                ];
            }
        }

        return $entryTypes;
    }

    /**
     * Update generation settings for an entry type
     *
     * @param int $entryTypeId Entry type ID
     * @param bool $enabled Whether generation is enabled
     * @param array $defaultFields Array of default field handles
     * @return bool Success status
     */
    public function updateGenerationSettings(int $entryTypeId, bool $enabled, array $defaultFields = []): bool
    {
        try {
            $settings = Plugin::getInstance()->getSettings();
            
            $settings->entryTypeSettings[$entryTypeId] = [
                'enabled' => $enabled,
                'defaultFields' => $defaultFields
            ];

            // Save the updated settings
            Craft::$app->getPlugins()->savePluginSettings(Plugin::getInstance(), $settings->getAttributes());
            
            // Log settings changes only in debug mode
            if (Craft::$app->getConfig()->general->devMode) {
                Craft::info(
                    "Updated generation settings for entry type {$entryTypeId}: enabled={$enabled}, defaultFields=" . implode(',', $defaultFields),
                    'ai-content-writer'
                );
            }

            return true;
        } catch (\Exception $e) {
            Craft::error(
                "Failed to update generation settings for entry type {$entryTypeId}: " . $e->getMessage(),
                'ai-content-writer'
            );
            return false;
        }
    }

    /**
     * Get supported field types for content generation
     *
     * @return array Supported field types with their display names
     */
    public function getSupportedFieldTypes(): array
    {
        return [
            'craft\\fields\\PlainText' => 'Plain Text',
            'craft\\fields\\Table' => 'Table',
            'craft\\fields\\Matrix' => 'Matrix',
            'craft\\redactor\\Field' => 'Redactor',
            'craft\\ckeditor\\Field' => 'CKEditor'
        ];
    }

    /**
     * Check if a field type is supported for content generation
     *
     * Handles the backslash normalization issue between settings storage
     * and field class detection, plus type coercion from string to boolean.
     *
     * @param string $fieldClass Field class name
     * @return bool Whether the field type is supported
     */
    public function isFieldTypeSupported(string $fieldClass): bool
    {
        $settings = Plugin::getInstance()->getSettings();
        $fieldTypeSupport = $settings->fieldTypeSupport;
        
        // Normalize field class key for settings lookup (double backslashes)
        $normalizedFieldClass = str_replace('\\', '\\\\', $fieldClass);
        
        // Check both normalized key and original key for backward compatibility
        if (isset($fieldTypeSupport[$normalizedFieldClass])) {
            return (bool) $fieldTypeSupport[$normalizedFieldClass];
        } elseif (isset($fieldTypeSupport[$fieldClass])) {
            return (bool) $fieldTypeSupport[$fieldClass];
        }
        
        return false;
    }

    /**
     * Get entry types that have content generation enabled
     *
     * @return array Enabled entry types
     */
    public function getEnabledEntryTypes(): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $enabledTypes = [];

        $sections = Craft::$app->getEntries()->getAllSections();
        
        foreach ($sections as $section) {
            foreach ($section->getEntryTypes() as $entryType) {
                if ($this->isGenerationEnabled($entryType->id)) {
                    $enabledTypes[] = [
                        'id' => $entryType->id,
                        'name' => $entryType->name,
                        'handle' => $entryType->handle,
                        'section' => $section->name,
                        'sectionHandle' => $section->handle,
                        'defaultFields' => $this->getDefaultFields($entryType->id),
                        'availableFields' => $this->getAvailableFields($entryType)
                    ];
                }
            }
        }

        return $enabledTypes;
    }

    /**
     * Validate field handles for an entry type
     *
     * @param EntryType $entryType Entry type to validate against
     * @param array $fieldHandles Field handles to validate
     * @return array Valid field handles
     */
    public function validateFieldHandles(EntryType $entryType, array $fieldHandles): array
    {
        $availableFields = $this->getAvailableFields($entryType);
        $availableHandles = array_column($availableFields, 'handle');
        
        return array_intersect($fieldHandles, $availableHandles);
    }

    /**
     * Get entry type configuration summary for admin display
     *
     * @return array Summary statistics
     */
    public function getConfigurationSummary(): array
    {
        $allEntryTypes = $this->getAllEntryTypesWithStatus();
        $enabledCount = count(array_filter($allEntryTypes, function($type) {
            return $type['enabled'];
        }));
        
        return [
            'totalEntryTypes' => count($allEntryTypes),
            'enabledEntryTypes' => $enabledCount,
            'disabledEntryTypes' => count($allEntryTypes) - $enabledCount,
            'supportedFieldTypes' => count($this->getSupportedFieldTypes())
        ];
    }

}