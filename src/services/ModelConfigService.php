<?php

namespace MadeByBramble\AiContentWriter\services;

use Craft;
use craft\base\Component;
use craft\helpers\FileHelper;
use Symfony\Component\Yaml\Yaml;
use InvalidArgumentException;
use MadeByBramble\AiContentWriter\models\Settings;

/**
 * Model Configuration Service
 * 
 * Manages OpenAI model configurations via YAML files instead of hardcoded arrays.
 * Provides centralized model metadata, API parameters, and UI display settings.
 */
class ModelConfigService extends Component
{
    private array $modelConfigs = [];
    private bool $configsLoaded = false;
    private array $configFileHashes = [];
    
    /**
     * Load all model configurations from YAML files
     */
    private function loadModelConfigs(): void
    {
        if ($this->configsLoaded && !$this->hasConfigurationChanged()) {
            return;
        }
        
        $configPath = __DIR__ . '/../config/models';
        
        if (!is_dir($configPath)) {
            Craft::warning(
                "Model configuration directory not found: {$configPath}",
                'ai-content-writer'
            );
            $this->configsLoaded = true;
            return;
        }
        
        $files = FileHelper::findFiles($configPath, [
            'only' => ['*.yaml', '*.yml']
        ]);
        
        foreach ($files as $file) {
            try {
                $config = Yaml::parseFile($file);
                $modelId = $config['model']['id'] ?? null;
                
                if (!$modelId) {
                    Craft::warning(
                        "Model configuration missing 'model.id' in file: {$file}",
                        'ai-content-writer'
                    );
                    continue;
                }
                
                $this->modelConfigs[$modelId] = $config;
                
                // Store file hash for change detection
                $this->configFileHashes[$file] = hash_file('md5', $file);
                
            } catch (\Exception $e) {
                Craft::error(
                    "Failed to parse model configuration file {$file}: " . $e->getMessage(),
                    'ai-content-writer'
                );
            }
        }
        
        $this->configsLoaded = true;
    }
    
    /**
     * Get configuration for a specific model
     */
    public function getModelConfig(string $modelId): ?array
    {
        $this->loadModelConfigs();
        return $this->modelConfigs[$modelId] ?? null;
    }
    
    /**
     * Get all available vision-capable models
     */
    public function getVisionModels(): array
    {
        $this->loadModelConfigs();
        
        $visionModels = [];
        foreach ($this->modelConfigs as $config) {
            if (($config['capabilities']['vision'] ?? false) === true) {
                if (($config['ui_display']['show_in_dropdown'] ?? true) === true) {
                    $visionModels[] = $config;
                }
            }
        }
        
        // Sort by priority (higher priority first)
        usort($visionModels, function($a, $b) {
            $priorityA = $a['metadata']['priority'] ?? 0;
            $priorityB = $b['metadata']['priority'] ?? 0;
            return $priorityB - $priorityA;
        });
        
        return $visionModels;
    }
    
    /**
     * Get all available models (not just vision-capable)
     */
    public function getAllModels(): array
    {
        $this->loadModelConfigs();
        return array_values($this->modelConfigs);
    }
    
    /**
     * Get API parameters for a model
     * 
     * @param string $modelId The model ID to get parameters for
     * @param Settings|null $settings Plugin settings to override defaults (optional)
     * @return array Model parameters with token limits, temperature, etc.
     */
    public function getModelParameters(string $modelId, ?Settings $settings = null): array
    {
        $config = $this->getModelConfig($modelId);
        if (!$config) {
            throw new InvalidArgumentException("Model configuration not found: {$modelId}");
        }
        
        $params = [
            'token_param' => $config['api_parameters']['token_parameter'],
            'token_value' => $config['api_parameters']['default_token_limit'], // Default from YAML
        ];
        
        // OVERRIDE: Use settings maxTokens if provided and valid
        if ($settings && $settings->maxTokens) {
            // Validate settings value is within acceptable range
            $maxTokens = max(100, min(4000, $settings->maxTokens));
            $originalValue = $params['token_value'];
            $params['token_value'] = $maxTokens;
            
            // Log the override for debugging
            Craft::info(
                "Token limit overridden by settings for model '{$modelId}': {$maxTokens} (YAML default was {$originalValue})",
                'ai-content-writer'
            );
        }
        
        // Ensure token limit doesn't exceed model's maximum context
        if (isset($config['version_info']['max_context_tokens'])) {
            $maxContext = $config['version_info']['max_context_tokens'];
            if ($params['token_value'] > $maxContext) {
                $originalValue = $params['token_value'];
                $params['token_value'] = $maxContext;
                
                Craft::warning(
                    "Token limit {$originalValue} exceeds model {$modelId} max context {$maxContext}, using model maximum",
                    'ai-content-writer'
                );
            }
        }
        
        if (($config['api_parameters']['supports_temperature'] ?? true) === true) {
            $params['temperature'] = $config['api_parameters']['default_temperature'] ?? 0.1;
        }
        
        // Add reasoning_effort parameter for models that support it (like O3)
        if (($config['api_parameters']['supports_reasoning_effort'] ?? false) === true) {
            $params['reasoning_effort'] = $config['api_parameters']['default_reasoning_effort'] ?? 'medium';
        }
        
        return $params;
    }
    
    /**
     * Check if a model supports vision capabilities
     */
    public function supportsVision(string $modelId): bool
    {
        $config = $this->getModelConfig($modelId);
        return ($config['capabilities']['vision'] ?? false) === true;
    }
    
    /**
     * Check if a model supports temperature parameter
     */
    public function supportsTemperature(string $modelId): bool
    {
        $config = $this->getModelConfig($modelId);
        return ($config['api_parameters']['supports_temperature'] ?? true) === true;
    }
    
    /**
     * Check if a model supports reasoning effort parameter
     */
    public function supportsReasoningEffort(string $modelId): bool
    {
        $config = $this->getModelConfig($modelId);
        return ($config['api_parameters']['supports_reasoning_effort'] ?? false) === true;
    }
    
    /**
     * Get friendly name for a model
     */
    public function getFriendlyName(string $modelId): string
    {
        $config = $this->getModelConfig($modelId);
        return $config['model']['friendly_name'] ?? $config['model']['name'] ?? $modelId;
    }
    
    /**
     * Validate model configuration files
     */
    public function validateConfigs(): array
    {
        $this->loadModelConfigs();
        $issues = [];
        
        foreach ($this->modelConfigs as $modelId => $config) {
            // Required field validation
            $requiredFields = [
                'model.id',
                'capabilities.vision',
                'api_parameters.token_parameter'
            ];
            
            foreach ($requiredFields as $field) {
                if (!$this->hasNestedValue($config, $field)) {
                    $issues[] = "Model {$modelId}: Missing required field '{$field}'";
                }
            }
            
            // Parameter validation
            $tokenParam = $config['api_parameters']['token_parameter'] ?? null;
            if (!in_array($tokenParam, ['max_tokens', 'max_completion_tokens'])) {
                $issues[] = "Model {$modelId}: Invalid token_parameter '{$tokenParam}'. Must be 'max_tokens' or 'max_completion_tokens'";
            }
            
            // Validate temperature settings
            if (isset($config['api_parameters']['supports_temperature'])) {
                if (!is_bool($config['api_parameters']['supports_temperature'])) {
                    $issues[] = "Model {$modelId}: 'supports_temperature' must be boolean";
                }
            }
            
            // Validate reasoning effort settings
            if (isset($config['api_parameters']['supports_reasoning_effort'])) {
                if (!is_bool($config['api_parameters']['supports_reasoning_effort'])) {
                    $issues[] = "Model {$modelId}: 'supports_reasoning_effort' must be boolean";
                }
                
                if ($config['api_parameters']['supports_reasoning_effort'] === true) {
                    $reasoningEffort = $config['api_parameters']['default_reasoning_effort'] ?? null;
                    $validEfforts = ['minimal', 'low', 'medium', 'high'];
                    if ($reasoningEffort && !in_array($reasoningEffort, $validEfforts)) {
                        $issues[] = "Model {$modelId}: 'default_reasoning_effort' must be one of: " . implode(', ', $validEfforts);
                    }
                }
            }
            
            // Validate priority is numeric
            if (isset($config['metadata']['priority'])) {
                if (!is_numeric($config['metadata']['priority'])) {
                    $issues[] = "Model {$modelId}: 'priority' must be numeric";
                }
            }
        }
        
        return $issues;
    }
    
    /**
     * Check if a nested array key exists
     */
    private function hasNestedValue(array $array, string $key): bool
    {
        $keys = explode('.', $key);
        $current = $array;
        
        foreach ($keys as $k) {
            if (!is_array($current) || !array_key_exists($k, $current)) {
                return false;
            }
            $current = $current[$k];
        }
        
        return true;
    }
    
    /**
     * Get model count for logging/debugging
     */
    public function getModelCount(): int
    {
        $this->loadModelConfigs();
        return count($this->modelConfigs);
    }
    
    /**
     * Check if configuration files have changed since last load
     */
    private function hasConfigurationChanged(): bool
    {
        $configPath = __DIR__ . '/../config/models';
        
        if (!is_dir($configPath)) {
            return false;
        }
        
        $files = FileHelper::findFiles($configPath, [
            'only' => ['*.yaml', '*.yml']
        ]);
        
        // Check if any files have changed
        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }
            
            $currentHash = hash_file('md5', $file);
            $storedHash = $this->configFileHashes[$file] ?? null;
            
            if ($storedHash !== $currentHash) {
                Craft::info(
                    "Configuration change detected in file: {$file}",
                    'ai-content-writer'
                );
                return true;
            }
        }
        
        // Check if files were added or removed
        if (count($files) !== count($this->configFileHashes)) {
            Craft::info(
                "Configuration file count changed: " . count($files) . " files found, " . count($this->configFileHashes) . " previously loaded",
                'ai-content-writer'
            );
            return true;
        }
        
        return false;
    }
    
    /**
     * Reload configurations (useful for testing)
     */
    public function reload(): void
    {
        $this->modelConfigs = [];
        $this->configFileHashes = [];
        $this->configsLoaded = false;
        $this->loadModelConfigs();
    }
}