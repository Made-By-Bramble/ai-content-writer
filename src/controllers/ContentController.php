<?php

namespace MadeByBramble\AiContentWriter\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use craft\web\Response;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use MadeByBramble\AiContentWriter\Plugin;

/**
 * Content Controller
 * 
 * Handles AJAX requests for content generation from the entry editor panel.
 * Provides endpoints for generating content, testing connections, and managing field mappings.
 */
class ContentController extends Controller
{
    /**
     * @var array|bool|int Allow anonymous access to specific actions
     */
    protected array|bool|int $allowAnonymous = false;

    /**
     * Generate content for a specific entry field
     *
     * @return Response JSON response with generated content or error
     * @throws BadRequestHttpException If required parameters are missing
     * @throws NotFoundHttpException If entry is not found
     */
    public function actionGenerate(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        // Enhanced parameter collection for new entry support
        $entryId = $request->getRequiredBodyParam('entryId');
        $fieldHandle = $request->getRequiredBodyParam('fieldHandle');
        $prompt = $request->getRequiredBodyParam('prompt');
        $typeId = $request->getBodyParam('typeId'); // Optional parameter for new entries
        $sectionId = $request->getBodyParam('sectionId'); // Fallback parameter

        $entry = null;
        $entryType = null;

        // Try to find existing entry first
        if ($entryId && is_numeric($entryId)) {
            $entry = Entry::find()->id($entryId)->one();
        }

        // Determine entry type through multiple methods
        if ($entry) {
            // Existing entry - get type from entry
            $entryType = $entry->getType();
            Craft::info("Found existing entry {$entryId}, type: {$entryType->handle}", 'ai-content-writer');
        } elseif ($typeId && is_numeric($typeId)) {
            // New entry - get type by ID
            $entryType = Craft::$app->getEntries()->getEntryTypeById($typeId);
            Craft::info("New entry detected for generation, using typeId {$typeId}, type: " . ($entryType ? $entryType->handle : 'not found'), 'ai-content-writer');
        } elseif ($sectionId && is_numeric($sectionId)) {
            // Fallback - get first entry type from section
            $section = Craft::$app->getEntries()->getSectionById($sectionId);
            if ($section) {
                $entryTypes = $section->getEntryTypes();
                $entryType = !empty($entryTypes) ? $entryTypes[0] : null;
                Craft::info("Using first entry type from section {$sectionId}: " . ($entryType ? $entryType->handle : 'none found'), 'ai-content-writer');
            }
        }

        if (!$entryType) {
            return $this->asJson([
                'success' => false,
                'error' => 'Could not determine entry type for content generation.'
            ]);
        }

        // Check permissions (use entry if available, otherwise skip detailed section check for new entries)
        if ($entry) {
            $sectionUid = $entry->section->uid;
            if (!Craft::$app->getUser()->checkPermission('editEntries:' . $sectionUid)) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'Insufficient permissions to edit entries in this section'
                ]);
            }
        } else {
            // For new entries, we'll do a basic permission check
            // The user must have permission to create entries in at least one section
            $canCreateEntries = false;
            foreach (Craft::$app->getEntries()->getEditableSections() as $section) {
                if (Craft::$app->getUser()->checkPermission('createEntries:' . $section->uid)) {
                    $canCreateEntries = true;
                    break;
                }
            }
            
            if (!$canCreateEntries) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'Insufficient permissions to create entries'
                ]);
            }
        }

        // Validate prompt is not empty
        if (empty(trim($prompt))) {
            return $this->asJson([
                'success' => false,
                'error' => 'Prompt cannot be empty'
            ]);
        }

        try {
            $contentGenerationService = Plugin::getInstance()->contentGeneration;

            // For new entries, create a temporary entry with the correct type
            if (!$entry) {
                // Find a section that uses this entry type
                $sectionId = null;
                foreach (Craft::$app->getEntries()->getAllSections() as $section) {
                    foreach ($section->getEntryTypes() as $sectionEntryType) {
                        if ($sectionEntryType->id === $entryType->id) {
                            $sectionId = $section->id;
                            break 2; // Break out of both loops
                        }
                    }
                }
                
                if (!$sectionId) {
                    return $this->asJson([
                        'success' => false,
                        'error' => 'Could not find a section that uses this entry type.'
                    ]);
                }
                
                // Create temporary entry for validation and generation
                $entry = new Entry([
                    'typeId' => $entryType->id,
                    'sectionId' => $sectionId,
                    'title' => 'Temporary Entry for Content Generation'
                ]);
            }

            // Validate that content generation is allowed for this field
            // For new entries, validate directly with entry type instead of relying on entry->getType()
            $settings = Plugin::getInstance()->getSettings();
            
            // Check if entry type supports generation
            if (!$settings->isGenerationEnabledForEntryType($entryType->id)) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'Content generation is not enabled for this entry type'
                ]);
            }

            // Check if field exists in the entry type's field layout
            $fieldLayout = $entryType->getFieldLayout();
            $field = null;
            $fieldClass = null;
            
            // Handle built-in fields like 'title'
            if ($fieldHandle === 'title') {
                // Title is always available if the entry type has a title field
                if ($entryType->hasTitleField) {
                    $fieldClass = 'craft\\fieldlayoutelements\\entries\\EntryTitleField';
                    // Don't set $field since title field doesn't have a field object
                } else {
                    return $this->asJson([
                        'success' => false,
                        'error' => 'This entry type does not have a title field enabled'
                    ]);
                }
            } else {
                // Look for custom field
                $field = $fieldLayout->getFieldByHandle($fieldHandle);
                if ($field) {
                    $fieldClass = get_class($field);
                }
            }
            
            // Debug: log available fields if field not found
            if (!$field && $fieldHandle !== 'title') {
                $availableFields = [];
                
                // Add title if available
                if ($entryType->hasTitleField) {
                    $availableFields[] = 'title';
                }
                
                // Add custom fields
                foreach ($fieldLayout->getCustomFields() as $availableField) {
                    $availableFields[] = $availableField->handle;
                }
                
                Craft::error(
                    "Field '{$fieldHandle}' not found in entry type '{$entryType->handle}'. Available fields: " . implode(', ', $availableFields),
                    'ai-content-writer'
                );
                
                return $this->asJson([
                    'success' => false,
                    'error' => "Field '{$fieldHandle}' does not exist in this entry type. Available fields: " . implode(', ', $availableFields),
                    'debug' => [
                        'requestedField' => $fieldHandle,
                        'entryType' => $entryType->handle,
                        'availableFields' => $availableFields
                    ]
                ]);
            }

            // Check if field type is supported
            // Normalize field class by escaping backslashes to match settings format
            $normalizedFieldClass = addslashes($fieldClass);
            
            if (!($settings->fieldTypeSupport[$normalizedFieldClass] ?? false)) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'Field type is not supported for content generation',
                    'debug' => [
                        'fieldClass' => $fieldClass,
                        'normalizedFieldClass' => $normalizedFieldClass,
                        'supportedTypes' => array_keys(array_filter($settings->fieldTypeSupport))
                    ]
                ]);
            }

            // Log the generation attempt
            if ($entry->id) {
                Craft::info(
                    "Content generation requested for entry '{$entry->title}' (ID: {$entry->id}), field '{$fieldHandle}'",
                    'ai-content-writer'
                );
            } else {
                Craft::info(
                    "Content generation requested for new entry, type '{$entryType->handle}', field '{$fieldHandle}'",
                    'ai-content-writer'
                );
            }

            // Generate content
            $content = $contentGenerationService->generateForEntry($entry, $fieldHandle, $prompt);

            // Log successful generation  
            $entryRef = $entry->id ? "entry {$entry->id}" : "new entry type '{$entryType->handle}'";
            Craft::info(
                "Content generated successfully for {$entryRef}, field '{$fieldHandle}', length: " . strlen($content),
                'ai-content-writer'
            );

            return $this->asJson([
                'success' => true,
                'content' => $content,
                'fieldHandle' => $fieldHandle,
                'contentLength' => strlen($content)
            ]);
        } catch (\Throwable $e) {
            // Log the error
            $entryRef = $entry ? "entry {$entry->id}" : "new entry type '{$entryType->handle}'";
            Craft::error(
                "Content generation failed for {$entryRef}, field '{$fieldHandle}': " . $e->getMessage(),
                'ai-content-writer'
            );

            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage(),
                'debug' => [
                    'entryId' => $entryId ?? null,
                    'typeId' => $typeId ?? null,
                    'sectionId' => $sectionId ?? null,
                    'isNewEntry' => !$entry
                ]
            ]);
        }
    }

    /**
     * Generate content for multiple fields in batch
     *
     * @return Response JSON response with batch results
     */
    public function actionGenerateBatch(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();

        // Validate required parameters
        $entryId = $request->getRequiredBodyParam('entryId');
        $fieldPrompts = $request->getRequiredBodyParam('fieldPrompts'); // [fieldHandle => prompt]

        if (!is_array($fieldPrompts) || empty($fieldPrompts)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Field prompts must be a non-empty array'
            ]);
        }

        // Find the entry
        $entry = Entry::find()->id($entryId)->one();

        if (!$entry) {
            throw new NotFoundHttpException('Entry not found');
        }

        // Check permissions
        if (!Craft::$app->getUser()->checkPermission('editEntries:' . $entry->section->uid)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Insufficient permissions to edit this entry'
            ]);
        }

        try {
            $contentGenerationService = Plugin::getInstance()->contentGeneration;

            // Generate content for all fields
            $results = $contentGenerationService->generateBatch($entry, $fieldPrompts);

            // Log batch generation
            $successCount = count(array_filter($results, function ($result) {
                return $result['success'];
            }));

            Craft::info(
                "Batch content generation completed for entry {$entry->id}: {$successCount}/" . count($results) . " successful",
                'ai-content-writer'
            );

            return $this->asJson([
                'success' => true,
                'results' => $results,
                'summary' => [
                    'total' => count($results),
                    'successful' => $successCount,
                    'failed' => count($results) - $successCount
                ]
            ]);
        } catch (\Throwable $e) {
            Craft::error(
                "Batch content generation failed for entry {$entry->id}: " . $e->getMessage(),
                'ai-content-writer'
            );

            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get available fields for an entry
     *
     * @return Response JSON response with available fields
     */
    public function actionGetAvailableFields(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        // Enhanced parameter collection for new entry support
        $entryId = $this->request->post("entryId");
        $typeId = $this->request->post("typeId"); // New parameter
        $sectionId = $this->request->post("sectionId"); // Fallback parameter

        $entry = null;
        $entryType = null;

        // Try to find existing entry first
        if ($entryId && is_numeric($entryId)) {
            $entry = Entry::find()->id($entryId)->one();
        }

        // Determine entry type through multiple methods
        if ($entry) {
            // Existing entry - get type from entry
            $entryType = $entry->getType();
            Craft::info("Found existing entry {$entryId}, type: {$entryType->handle}", 'ai-content-writer');
        } elseif ($typeId && is_numeric($typeId)) {
            // New entry - get type by ID
            $entryType = Craft::$app->getEntries()->getEntryTypeById($typeId);
            Craft::info("New entry detected, using typeId {$typeId}, type: " . ($entryType ? $entryType->handle : 'not found'), 'ai-content-writer');
        } elseif ($sectionId && is_numeric($sectionId)) {
            // Fallback - get first entry type from section
            $section = Craft::$app->getEntries()->getSectionById($sectionId);
            if ($section) {
                $entryTypes = $section->getEntryTypes();
                $entryType = !empty($entryTypes) ? $entryTypes[0] : null;
                Craft::info("Using first entry type from section {$sectionId}: " . ($entryType ? $entryType->handle : 'none found'), 'ai-content-writer');
            }
        }

        if (!$entryType) {
            return $this->asJson([
                'success' => false,
                'error' => 'Could not determine entry type. Please ensure entry type information is available.'
            ]);
        }

        try {
            $entryTypeService = Plugin::getInstance()->entryType;
            $fields = $entryTypeService->getAvailableFields($entryType);

            return $this->asJson([
                'success' => true,
                'fields' => $fields,
                'entryType' => [
                    'id' => $entryType->id,
                    'name' => $entryType->name,
                    'handle' => $entryType->handle
                ],
                'entryInfo' => [
                    'isNewEntry' => !$entry->id,
                    'entryId' => $entry->id ?? null,
                    'sectionId' => $entry->sectionId ?? null,
                    'sectionName' => $entry->section->name ?? null
                ]
            ]);

        } catch (\Throwable $e) {
            Craft::error(
                "Failed to get available fields for entry type " . ($entryType ? $entryType->handle : 'unknown') . ": " . $e->getMessage(),
                'ai-content-writer'
            );

            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage(),
                'debug' => [
                    'entryId' => $entryId ?? null,
                    'typeId' => $typeId ?? null,
                    'sectionId' => $sectionId ?? null
                ]
            ]);
        }
    }

    /**
     * Test OpenAI connection
     *
     * @return Response JSON response with connection test result
     */
    public function actionTestConnection(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        // Check admin permissions for settings testing
        $this->requirePermission('utility:system-report');

        try {
            $openAiService = Plugin::getInstance()->openAi;
            $result = $openAiService->testConnection();

            Craft::info(
                "OpenAI connection test: " . ($result['success'] ? 'successful' : 'failed') . " - " . $result['message'],
                'ai-content-writer'
            );

            return $this->asJson($result);
        } catch (\Throwable $e) {
            Craft::error(
                "OpenAI connection test error: " . $e->getMessage(),
                'ai-content-writer'
            );

            return $this->asJson([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get field mapping information for JavaScript insertion
     *
     * @return Response JSON response with field mapping data
     */
    public function actionGetFieldMapping(): Response
    {
        $this->requireGetRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $fieldHandle = $request->getRequiredQueryParam('fieldHandle');
        $fieldClass = $request->getRequiredQueryParam('fieldClass');

        try {
            $fieldMappingService = Plugin::getInstance()->fieldMapping;

            $mapping = [
                'selector' => $fieldMappingService->getFieldSelector($fieldHandle, $fieldClass),
                'format' => $fieldMappingService->getFieldFormat($fieldClass),
                'insertionMethod' => $fieldMappingService->getInsertionMethod($fieldClass),
                'javascript' => $fieldMappingService->getInsertionJavaScript($fieldHandle, $fieldClass)
            ];

            return $this->asJson([
                'success' => true,
                'mapping' => $mapping
            ]);
        } catch (\Throwable $e) {
            Craft::error(
                "Failed to get field mapping for field '{$fieldHandle}': " . $e->getMessage(),
                'ai-content-writer'
            );

            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get plugin statistics
     *
     * @return Response JSON response with plugin statistics
     */
    public function actionGetStats(): Response
    {
        $this->requireGetRequest();
        $this->requireAcceptsJson();

        // Check admin permissions
        $this->requirePermission('utility:system-report');

        try {
            $entryTypeService = Plugin::getInstance()->entryType;
            $fieldMappingService = Plugin::getInstance()->fieldMapping;

            $stats = [
                'entryTypes' => $entryTypeService->getConfigurationSummary(),
                'fieldMapping' => $fieldMappingService->getMappingStatistics(),
                'modelConfig' => [
                    'modelCount' => Plugin::getInstance()->modelConfig->getModelCount()
                ]
            ];

            return $this->asJson([
                'success' => true,
                'stats' => $stats
            ]);
        } catch (\Throwable $e) {
            Craft::error(
                "Failed to get plugin statistics: " . $e->getMessage(),
                'ai-content-writer'
            );

            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
