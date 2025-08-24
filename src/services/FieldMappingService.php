<?php

namespace MadeByBramble\AiContentWriter\services;

use Craft;
use craft\base\Field;
use craft\elements\Entry;
use MadeByBramble\AiContentWriter\Plugin;
use yii\base\Component;

/**
 * Field Mapping Service
 * 
 * Handles field type detection and content insertion logic.
 * Provides methods to map content to appropriate field types and formats.
 */
class FieldMappingService extends Component
{
    /**
     * Get field information including type and insertion capabilities
     *
     * @param Field $field Field instance to analyze
     * @return array Field information for content insertion
     */
    public function getFieldInfo(Field $field): array
    {
        $fieldClass = get_class($field);
        $settings = Plugin::getInstance()->getSettings();
        
        return [
            'handle' => $field->handle,
            'name' => $field->name,
            'type' => $fieldClass,
            'id' => $field->id,
            'supported' => $settings->fieldTypeSupport[$fieldClass] ?? false,
            'format' => $this->getFieldFormat($fieldClass),
            'insertionMethod' => $this->getInsertionMethod($fieldClass),
            'instructions' => $field->instructions,
            'required' => $field->required
        ];
    }

    /**
     * Determine the content format for a field type
     *
     * @param string $fieldClass Field class name
     * @return string Content format (plain, html, markdown)
     */
    public function getFieldFormat(string $fieldClass): string
    {
        return match($fieldClass) {
            'craft\\redactor\\Field' => 'html',
            'craft\\ckeditor\\Field' => 'html',
            'craft\\fields\\PlainText' => 'plain',
            'craft\\fields\\Table' => 'plain',
            'craft\\fields\\Matrix' => 'html',
            'craft\\fieldlayoutelements\\entries\\EntryTitleField' => 'plain',
            default => 'plain'
        };
    }

    /**
     * Determine the insertion method for a field type
     *
     * @param string $fieldClass Field class name
     * @return string Insertion method (direct, api, special)
     */
    public function getInsertionMethod(string $fieldClass): string
    {
        return match($fieldClass) {
            'craft\\fields\\PlainText' => 'direct',
            'craft\\redactor\\Field' => 'api',
            'craft\\ckeditor\\Field' => 'api', 
            'craft\\fields\\Table' => 'special',
            'craft\\fields\\Matrix' => 'special',
            'craft\\fieldlayoutelements\\entries\\EntryTitleField' => 'direct',
            default => 'direct'
        };
    }

    /**
     * Get JavaScript selector for field insertion
     *
     * @param string $fieldHandle Field handle
     * @param string $fieldClass Field class name
     * @return array Selector information for JavaScript insertion
     */
    public function getFieldSelector(string $fieldHandle, string $fieldClass): array
    {
        // Special handling for title field
        if ($fieldHandle === 'title') {
            return [
                'container' => '#title-field',
                'input' => 'input[type="text"]#title',
                'method' => 'direct'
            ];
        }
        
        $baseSelector = "[data-attribute=\"{$fieldHandle}\"]";
        
        switch ($fieldClass) {
            case 'craft\\fields\\PlainText':
                return [
                    'container' => $baseSelector,
                    'input' => 'textarea, input[type="text"]',
                    'method' => 'direct'
                ];
                
            case 'craft\\redactor\\Field':
                return [
                    'container' => $baseSelector,
                    'input' => '.redactor-in',
                    'method' => 'redactor'
                ];
                
            case 'craft\\ckeditor\\Field':
                return [
                    'container' => $baseSelector,
                    'input' => '.ck-editor__editable',
                    'method' => 'ckeditor'
                ];
                
            case 'craft\\fields\\Table':
                return [
                    'container' => $baseSelector,
                    'input' => '.editable-table tbody',
                    'method' => 'table'
                ];
                
            case 'craft\\fields\\Matrix':
                return [
                    'container' => $baseSelector,
                    'input' => '.matrix-blocks',
                    'method' => 'matrix'
                ];
                
            case 'craft\\fieldlayoutelements\\entries\\EntryTitleField':
                return [
                    'container' => '#title-field',
                    'input' => 'input[type="text"]#title',
                    'method' => 'direct'
                ];
                
            default:
                return [
                    'container' => $baseSelector,
                    'input' => 'textarea, input[type="text"]',
                    'method' => 'direct'
                ];
        }
    }

    /**
     * Process content for specific field type requirements
     *
     * @param string $content Generated content
     * @param string $fieldClass Field class name
     * @param Field|null $field Optional field instance for additional context
     * @return string Processed content ready for insertion
     */
    public function processContentForField(string $content, string $fieldClass, ?Field $field = null): string
    {
        switch ($fieldClass) {
            case 'craft\\redactor\\Field':
            case 'craft\\ckeditor\\Field':
                return $this->processForRichText($content);
                
            case 'craft\\fields\\PlainText':
                return $this->processForPlainText($content);
                
            case 'craft\\fields\\Table':
                return $this->processForTable($content, $field);
                
            case 'craft\\fields\\Matrix':
                return $this->processForMatrix($content, $field);
                
            default:
                return $this->processForPlainText($content);
        }
    }

    /**
     * Process content for rich text fields (Redactor, CKEditor)
     *
     * @param string $content Content to process
     * @return string HTML formatted content
     */
    private function processForRichText(string $content): string
    {
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
     * @param string $content Content to process
     * @return string Plain text content
     */
    private function processForPlainText(string $content): string
    {
        // Strip HTML and normalize whitespace
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        return trim($content);
    }

    /**
     * Process content for table fields
     *
     * @param string $content Content to process
     * @param Field|null $field Table field instance
     * @return string Content formatted for table insertion
     */
    private function processForTable(string $content, ?Field $field = null): string
    {
        // For now, return as plain text
        // Future enhancement: Parse content into table structure
        return $this->processForPlainText($content);
    }

    /**
     * Process content for Matrix fields
     *
     * @param string $content Content to process
     * @param Field|null $field Matrix field instance
     * @return string Content formatted for Matrix insertion
     */
    private function processForMatrix(string $content, ?Field $field = null): string
    {
        // Process as rich text for Matrix blocks
        return $this->processForRichText($content);
    }

    /**
     * Get JavaScript code for field insertion
     *
     * @param string $fieldHandle Field handle
     * @param string $fieldClass Field class name
     * @return string JavaScript code for content insertion
     */
    public function getInsertionJavaScript(string $fieldHandle, string $fieldClass): string
    {
        $selector = $this->getFieldSelector($fieldHandle, $fieldClass);
        
        switch ($selector['method']) {
            case 'direct':
                return $this->getDirectInsertionJS($selector);
                
            case 'redactor':
                return $this->getRedactorInsertionJS($selector);
                
            case 'ckeditor':
                return $this->getCKEditorInsertionJS($selector);
                
            case 'table':
                return $this->getTableInsertionJS($selector);
                
            case 'matrix':
                return $this->getMatrixInsertionJS($selector);
                
            default:
                return $this->getDirectInsertionJS($selector);
        }
    }

    /**
     * Get JavaScript for direct input insertion
     */
    private function getDirectInsertionJS(array $selector): string
    {
        return "
            const fieldContainer = document.querySelector('{$selector['container']}');
            if (fieldContainer) {
                const input = fieldContainer.querySelector('{$selector['input']}');
                if (input) {
                    input.value = generatedContent;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    return true;
                }
            }
            return false;
        ";
    }

    /**
     * Get JavaScript for Redactor insertion
     */
    private function getRedactorInsertionJS(array $selector): string
    {
        return "
            const fieldContainer = document.querySelector('{$selector['container']}');
            if (fieldContainer) {
                const redactorFrame = fieldContainer.querySelector('{$selector['input']}');
                if (redactorFrame && typeof $ !== 'undefined') {
                    const redactorInstance = $(redactorFrame).data('redactor');
                    if (redactorInstance) {
                        redactorInstance.source.setCode(generatedContent);
                        return true;
                    }
                }
            }
            return false;
        ";
    }

    /**
     * Get JavaScript for CKEditor insertion
     */
    private function getCKEditorInsertionJS(array $selector): string
    {
        return "
            const fieldContainer = document.querySelector('{$selector['container']}');
            if (fieldContainer) {
                const ckeditor = fieldContainer.querySelector('{$selector['input']}');
                if (ckeditor && ckeditor.ckeditorInstance) {
                    ckeditor.ckeditorInstance.setData(generatedContent);
                    return true;
                }
            }
            return false;
        ";
    }

    /**
     * Get JavaScript for Table insertion
     */
    private function getTableInsertionJS(array $selector): string
    {
        return "
            const fieldContainer = document.querySelector('{$selector['container']}');
            if (fieldContainer) {
                const tableBody = fieldContainer.querySelector('{$selector['input']}');
                if (tableBody) {
                    // Show notification instead of alert
                    if (window.Craft && Craft.cp) {
                        Craft.cp.displayNotice('Table content generated. Please manually structure the content into table rows and columns.');
                    }
                    return true;
                }
            }
            return false;
        ";
    }

    /**
     * Get JavaScript for Matrix insertion
     */
    private function getMatrixInsertionJS(array $selector): string
    {
        return "
            const fieldContainer = document.querySelector('{$selector['container']}');
            if (fieldContainer) {
                // For Matrix fields, we'll need to work with existing blocks
                // This is a complex operation that would require more specific implementation
                if (window.Craft && Craft.cp) {
                    Craft.cp.displayNotice('Matrix content generated. Please manually add the content to your Matrix blocks.');
                }
                return true;
            }
            return false;
        ";
    }

    /**
     * Validate that a field can receive generated content
     *
     * @param Field $field Field to validate
     * @return bool Whether content can be inserted
     */
    public function canInsertContent(Field $field): bool
    {
        $settings = Plugin::getInstance()->getSettings();
        $fieldClass = get_class($field);
        
        return $settings->fieldTypeSupport[$fieldClass] ?? false;
    }

    /**
     * Get field mapping statistics
     *
     * @return array Mapping statistics
     */
    public function getMappingStatistics(): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $supportedTypes = array_keys(array_filter($settings->fieldTypeSupport));
        
        return [
            'supportedFieldTypes' => count($supportedTypes),
            'supportedTypesList' => $supportedTypes,
            'insertionMethods' => [
                'direct' => ['craft\\fields\\PlainText'],
                'api' => ['craft\\redactor\\Field', 'craft\\ckeditor\\Field'],
                'special' => ['craft\\fields\\Table', 'craft\\fields\\Matrix']
            ]
        ];
    }
}