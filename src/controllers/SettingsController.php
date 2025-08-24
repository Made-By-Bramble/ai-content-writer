<?php

namespace MadeByBramble\AiContentWriter\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use MadeByBramble\AiContentWriter\Plugin;

/**
 * Settings Controller
 * 
 * Handles AJAX requests for settings management including entry type configuration,
 * model loading, and other administrative functions.
 */
class SettingsController extends Controller
{
    /**
     * @var array|bool|int Allow anonymous access to specific actions
     */
    protected array|bool|int $allowAnonymous = false;

    /**
     * Save entry type settings via AJAX
     *
     * @return Response JSON response with save result
     */
    public function actionSaveEntryTypeSettings(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        // Check admin permissions
        $this->requirePermission('utility:system-report');
        
        $request = Craft::$app->getRequest();
        
        $entryTypeId = $request->getRequiredBodyParam('entryTypeId');
        $enabled = (bool)$request->getBodyParam('enabled', false);
        $defaultFields = $request->getBodyParam('defaultFields', []);
        
        if (!is_numeric($entryTypeId)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Invalid entry type ID provided'
            ]);
        }
        
        try {
            $entryTypeService = Plugin::getInstance()->entryType;
            $success = $entryTypeService->updateGenerationSettings(
                (int)$entryTypeId,
                $enabled,
                $defaultFields
            );
            
            if ($success) {
                return $this->asJson([
                    'success' => true,
                    'message' => 'Entry type settings saved successfully'
                ]);
            } else {
                return $this->asJson([
                    'success' => false,
                    'error' => 'Failed to save entry type settings'
                ]);
            }
            
        } catch (\Throwable $e) {
            Craft::error(
                "Failed to save entry type settings for type {$entryTypeId}: " . $e->getMessage(),
                'ai-content-writer'
            );
            
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get list of available OpenAI models from configuration
     *
     * @return Response JSON response with model list
     */
    public function actionGetModelList(): Response
    {
        $this->requireAcceptsJson();
        
        // Check admin permissions
        $this->requirePermission('utility:system-report');
        
        try {
            $modelConfigService = Plugin::getInstance()->modelConfig;
            $models = $modelConfigService->getAllModels();
            
            // Format models for dropdown selection
            $formattedModels = [];
            foreach ($models as $model) {
                // Only include models that should be shown in dropdown
                $showInDropdown = $model['ui_display']['show_in_dropdown'] ?? true;
                if (!$showInDropdown) {
                    continue;
                }
                
                $formattedModels[] = [
                    'value' => $model['model']['id'],
                    'label' => $model['model']['friendly_name'] ?? $model['model']['name'] ?? $model['model']['id'],
                    'description' => $model['metadata']['description'] ?? '',
                    'priority' => $model['metadata']['priority'] ?? 0,
                    'capabilities' => $model['capabilities'] ?? [],
                    'badge' => $model['ui_display']['badge'] ?? null,
                    'recommended' => $model['ui_display']['recommended'] ?? false
                ];
            }
            
            // Sort by priority (higher priority first)
            usort($formattedModels, function($a, $b) {
                return $b['priority'] - $a['priority'];
            });
            
            return $this->asJson([
                'success' => true,
                'models' => $formattedModels,
                'count' => count($formattedModels)
            ]);
            
        } catch (\Throwable $e) {
            Craft::error(
                "Failed to get model list: " . $e->getMessage(),
                'ai-content-writer'
            );
            
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get detailed information about a specific model
     *
     * @return Response JSON response with model details
     */
    public function actionGetModelInfo(): Response
    {
        $this->requireAcceptsJson();
        
        // Check admin permissions
        $this->requirePermission('utility:system-report');
        
        $request = Craft::$app->getRequest();
        $modelId = $request->getRequiredQueryParam('modelId');
        
        try {
            $modelConfigService = Plugin::getInstance()->modelConfig;
            $modelConfig = $modelConfigService->getModelConfig($modelId);
            
            if (!$modelConfig) {
                return $this->asJson([
                    'success' => false,
                    'error' => "Model configuration not found: {$modelId}"
                ]);
            }
            
            return $this->asJson([
                'success' => true,
                'model' => [
                    'id' => $modelConfig['model']['id'],
                    'name' => $modelConfig['model']['name'] ?? $modelId,
                    'friendlyName' => $modelConfig['model']['friendly_name'] ?? null,
                    'description' => $modelConfig['metadata']['description'] ?? '',
                    'capabilities' => $modelConfig['capabilities'] ?? [],
                    'parameters' => [
                        'tokenParameter' => $modelConfig['api_parameters']['token_parameter'],
                        'defaultTokenLimit' => $modelConfig['api_parameters']['default_token_limit'],
                        'supportsTemperature' => $modelConfig['api_parameters']['supports_temperature'] ?? true,
                        'defaultTemperature' => $modelConfig['api_parameters']['default_temperature'] ?? 0.1,
                        'supportsReasoningEffort' => $modelConfig['api_parameters']['supports_reasoning_effort'] ?? false,
                        'defaultReasoningEffort' => $modelConfig['api_parameters']['default_reasoning_effort'] ?? null
                    ],
                    'uiDisplay' => $modelConfig['ui_display'] ?? []
                ]
            ]);
            
        } catch (\Throwable $e) {
            Craft::error(
                "Failed to get model info for {$modelId}: " . $e->getMessage(),
                'ai-content-writer'
            );
            
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update field type support settings
     *
     * @return Response JSON response with update result
     */
    public function actionUpdateFieldTypeSupport(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        // Check admin permissions
        $this->requirePermission('utility:system-report');
        
        $request = Craft::$app->getRequest();
        $fieldTypeSupport = $request->getBodyParam('fieldTypeSupport', []);
        
        try {
            $plugin = Plugin::getInstance();
            $settings = $plugin->getSettings();
            
            // Update field type support
            $settings->fieldTypeSupport = $fieldTypeSupport;
            
            // Save the updated settings
            $success = Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->getAttributes());
            
            if ($success) {
                // Log settings changes only in debug mode to avoid exposing sensitive data
                if (Craft::$app->getConfig()->general->devMode) {
                    Craft::info(
                        "Field type support settings updated: " . json_encode($fieldTypeSupport),
                        'ai-content-writer'
                    );
                }
                
                return $this->asJson([
                    'success' => true,
                    'message' => 'Field type support settings updated successfully'
                ]);
            } else {
                return $this->asJson([
                    'success' => false,
                    'error' => 'Failed to save field type support settings'
                ]);
            }
            
        } catch (\Throwable $e) {
            Craft::error(
                "Failed to update field type support settings: " . $e->getMessage(),
                'ai-content-writer'
            );
            
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get configuration summary for admin display
     *
     * @return Response JSON response with configuration summary
     */
    public function actionGetConfigurationSummary(): Response
    {
        $this->requireAcceptsJson();
        
        // Check admin permissions
        $this->requirePermission('utility:system-report');
        
        try {
            $entryTypeService = Plugin::getInstance()->entryType;
            $fieldMappingService = Plugin::getInstance()->fieldMapping;
            $modelConfigService = Plugin::getInstance()->modelConfig;
            
            $summary = [
                'entryTypes' => $entryTypeService->getConfigurationSummary(),
                'fieldMapping' => $fieldMappingService->getMappingStatistics(),
                'models' => [
                    'totalModels' => $modelConfigService->getModelCount(),
                    'availableModels' => count($modelConfigService->getAllModels())
                ]
            ];
            
            return $this->asJson([
                'success' => true,
                'summary' => $summary
            ]);
            
        } catch (\Throwable $e) {
            Craft::error(
                "Failed to get configuration summary: " . $e->getMessage(),
                'ai-content-writer'
            );
            
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
}