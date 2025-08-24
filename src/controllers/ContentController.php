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

        $entryId = $request->getRequiredBodyParam('entryId');
        $fieldHandle = $request->getRequiredBodyParam('fieldHandle');
        $prompt = $request->getRequiredBodyParam('prompt');
        $typeId = $request->getBodyParam('typeId') ?: $request->getBodyParam('entryTypeId');
        $sectionId = $request->getBodyParam('sectionId');

        $entry = null;
        $entryType = null;

        // Find existing entry
        if ($entryId && is_numeric($entryId)) {
            $entry = Entry::find()->id($entryId)->with(['section'])->one();
        }

        // Determine entry type
        if ($entry) {
            $entryType = $entry->getType();
            $sectionId = $entry->sectionId;
        } elseif ($typeId && is_numeric($typeId)) {
            $entryType = Craft::$app->getEntries()->getEntryTypeById($typeId);
        } elseif ($sectionId && is_numeric($sectionId)) {
            $section = Craft::$app->getEntries()->getSectionById($sectionId);
            if ($section) {
                $entryTypes = $section->getEntryTypes();
                $entryType = !empty($entryTypes) ? $entryTypes[0] : null;
            }
        }

        if (!$entryType) {
            return $this->asJson([
                'success' => false,
                'error' => 'Could not determine entry type for content generation.'
            ]);
        }

        // Check permissions
        if ($entry && $entry->id && $entry->section) {
            $sectionUid = $entry->section->uid;
            if (!Craft::$app->getUser()->checkPermission('editEntries:' . $sectionUid)) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'Insufficient permissions to edit entries in this section'
                ]);
            }
        } else if ($entry && $entry->id && !$entry->section) {
            // Matrix block entries don't have sections, skip section permission check
        } else {
            // Check basic creation permissions for new entries
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

            $settings = Plugin::getInstance()->getSettings();
            
            // Validate entry type supports generation
            if (!$settings->isGenerationEnabledForEntryType($entryType->id)) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'Content generation is not enabled for this entry type'
                ]);
            }

            // Validate field exists in entry type layout
            $fieldLayout = $entryType->getFieldLayout();
            $field = null;
            $fieldClass = null;
            
            if ($fieldHandle === 'title') {
                if ($entryType->hasTitleField) {
                    $fieldClass = 'craft\\fieldlayoutelements\\entries\\EntryTitleField';
                } else {
                    return $this->asJson([
                        'success' => false,
                        'error' => 'This entry type does not have a title field enabled'
                    ]);
                }
            } else {
                $field = $fieldLayout->getFieldByHandle($fieldHandle);
                if ($field) {
                    $fieldClass = get_class($field);
                }
            }
            
            if (!$field && $fieldHandle !== 'title') {
                return $this->asJson([
                    'success' => false,
                    'error' => "Field '{$fieldHandle}' is not available for this entry type"
                ]);
            }

            // Validate field type is supported
            $normalizedFieldClass = addslashes($fieldClass);
            
            if (!($settings->fieldTypeSupport[$normalizedFieldClass] ?? false)) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'Field type is not supported for content generation'
                ]);
            }


            // Generate content
            $content = $contentGenerationService->generateForEntry($entry, $fieldHandle, $prompt);


            return $this->asJson([
                'success' => true,
                'content' => $content,
                'fieldHandle' => $fieldHandle,
                'contentLength' => strlen($content)
            ]);
        } catch (\Throwable $e) {
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

        $request = Craft::$app->getRequest();
        $entryId = $request->getBodyParam('entryId');
        $typeId = $request->getBodyParam('typeId') ?: $request->getBodyParam('entryTypeId');
        $sectionId = $request->getBodyParam('sectionId');

        $entry = null;
        $entryType = null;

        // Find existing entry
        if ($entryId && is_numeric($entryId)) {
            $entry = Entry::find()->id($entryId)->one();
        }

        // Determine entry type
        if ($entry) {
            $entryType = $entry->getType();
            $sectionId = $entry->sectionId;
        } elseif ($typeId && is_numeric($typeId)) {
            $entryType = Craft::$app->getEntries()->getEntryTypeById($typeId);
        } elseif ($sectionId && is_numeric($sectionId)) {
            $section = Craft::$app->getEntries()->getSectionById($sectionId);
            if ($section) {
                $entryTypes = $section->getEntryTypes();
                $entryType = !empty($entryTypes) ? $entryTypes[0] : null;
            }
        }

        if (!$entryType) {
            return $this->asJson([
                'success' => false,
                'error' => 'Could not determine entry type. Please ensure entry type information is available.'
            ]);
        }

        // For new entries, create a temporary entry with the correct type for consistency
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
            
            if ($sectionId) {
                // Create temporary entry for consistent response data
                $entry = new Entry([
                    'typeId' => $entryType->id,
                    'sectionId' => $sectionId,
                    'title' => 'Temporary Entry for Field Discovery'
                ]);
            }
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
                    'isNewEntry' => !$entry || !$entry->id,
                    'entryId' => $entry ? $entry->id : null,
                    'sectionId' => $entry ? $entry->sectionId : null,
                    'sectionName' => $entry && $entry->section ? $entry->section->name : null
                ]
            ]);

        } catch (\Throwable $e) {
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
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

            return $this->asJson($result);
        } catch (\Throwable $e) {
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
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}
