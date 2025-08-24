<?php

namespace MadeByBramble\AiContentWriter\models;

use craft\base\Model;
use craft\helpers\App;

/**
 * Plugin settings model
 * 
 * This model handles the configuration settings for the AI Content Writer plugin,
 * including API credentials, entry type settings, and content generation options.
 */
class Settings extends Model
{
    /**
     * @var string OpenAI API key for accessing the API
     *             Can be set directly or use environment variable syntax like $OPENAI_API_KEY
     *             Required for all plugin functionality - obtain from https://platform.openai.com/
     */
    public string $openAiApiKey = '';

    /**
     * @var string OpenAI model to use for content generation
     *            Defaults to GPT-5 which provides excellent text generation capabilities
     *            Can be changed to other models as they become available
     *            Model selection affects API costs and response quality
     */
    public string $openAiModel = 'gpt-5';

    /**
     * @var array Per-entry type configuration settings that control content generation behavior
     *            Structure: [entryTypeId => ['enabled' => bool, 'defaultFields' => []]]
     *            - 'enabled': Whether content generation is enabled for this entry type
     *            - 'defaultFields': Array of field handles that can receive generated content
     *            Example: [1 => ['enabled' => true, 'defaultFields' => ['title', 'summary']]]
     */
    public array $entryTypeSettings = [];

    /**
     * @var array Pre-configured prompt templates for different content types
     *            Structure: [templateName => 'prompt text']
     *            Provides quick access to common content generation patterns
     *            Example: ['blog_intro' => 'Write an engaging introduction for a blog post about...']
     */
    public array $defaultPromptTemplates = [];

    /**
     * @var array Configuration for which field types can receive generated content
     *            Structure: [fieldClassName => enabled]
     *            Controls which field types appear in the field selector
     */
    public array $fieldTypeSupport = [
        'craft\\fields\\PlainText' => true,
        'craft\\fields\\Table' => true,
        'craft\\fields\\Matrix' => true,
        'craft\\redactor\\Field' => true,
        'craft\\ckeditor\\Field' => true,
        'craft\\fieldlayoutelements\\entries\\EntryTitleField' => true,
    ];

    /**
     * @var int Maximum number of retries for failed API requests
     *           Range: 1-10 (enforced by validation)
     *           Higher values increase reliability but may delay job completion
     *           Recommended: 3 for balanced reliability and performance
     */
    public int $maxRetries = 3;

    /**
     * @var int Timeout in seconds for individual OpenAI API requests
     *           Range: 10-120 seconds (enforced by validation)
     *           Longer timeout for content generation compared to alt text
     */
    public int $apiTimeout = 60;

    /**
     * @var string Custom system prompt override for content generation (optional)
     *            When empty, uses the default content generation prompt
     *            Advanced users can customize to match specific brand voice or requirements
     */
    public string $promptOverride = '';

    /**
     * @var int Maximum tokens for content generation
     *           Controls the length of generated content
     *           Higher values allow for longer content but increase API costs
     */
    public int $maxTokens = 2000;

    /**
     * Define validation rules for the settings model
     *
     * Validates configuration values to ensure proper plugin operation
     * and prevent invalid settings that could cause API errors or performance issues.
     *
     * @return array Array of validation rules with constraints and requirements
     */
    public function defineRules(): array
    {
        return [
            // API key validation - required for functionality
            [['openAiApiKey'], 'string'],
            [['openAiApiKey'], 'required', 'message' => 'OpenAI API key is required'],
            
            // Model selection - must be valid OpenAI model (allow empty for initial setup)
            [['openAiModel'], 'string'],
            
            // Custom prompt validation - optional but must be string if provided  
            [['promptOverride'], 'string'],
            
            // Retry limits prevent infinite loops and excessive API usage
            [['maxRetries'], 'integer', 'min' => 1, 'max' => 10],
            
            // Timeout bounds prevent hanging requests and premature failures
            [['apiTimeout'], 'integer', 'min' => 10, 'max' => 120],
            
            // Token limits prevent excessive API costs
            [['maxTokens'], 'integer', 'min' => 100, 'max' => 4000],
            
            // Settings arrays - structure validated in application logic
            [['entryTypeSettings', 'defaultPromptTemplates', 'fieldTypeSupport'], 'safe'],
        ];
    }

    /**
     * Get the parsed OpenAI API key (supports environment variables)
     *
     * Resolves environment variable references in the API key setting.
     * Example: $OPENAI_API_KEY will be replaced with the actual environment variable value.
     *
     * @return string Parsed API key with environment variable support
     */
    public function getParsedApiKey(): string
    {
        return App::parseEnv($this->openAiApiKey);
    }

    /**
     * Get the parsed OpenAI model (supports environment variables)
     *
     * Resolves environment variable references in the model setting.
     * Useful for different models in different environments.
     *
     * @return string Parsed model with environment variable support  
     */
    public function getParsedModel(): string
    {
        return App::parseEnv($this->openAiModel);
    }

    /**
     * Get default system prompt for content generation
     * 
     * This prompt is designed to generate high-quality, contextual content
     *
     * @return string Default system prompt
     */
    public function getDefaultSystemPrompt(): string
    {
        return 'You are a professional content writer creating high-quality content for a CMS. 

Rules:
1. Generate only the requested content, no explanations or metadata
2. Match the tone and style appropriate for the content type and context
3. Keep content focused and relevant to the prompt
4. Use proper grammar, spelling, and formatting
5. For HTML fields, include appropriate markup when beneficial
6. Consider SEO best practices when applicable
7. Write in a clear, engaging style appropriate for the target audience

Generate the requested content based on the following prompt:';
    }

    /**
     * Get the system prompt to use (custom or default)
     *
     * @return string System prompt to use for OpenAI requests
     */
    public function getSystemPrompt(): string
    {
        return !empty($this->promptOverride) ? $this->promptOverride : $this->getDefaultSystemPrompt();
    }

    /**
     * Check if content generation is enabled for a specific entry type
     *
     * @param int $entryTypeId Entry type ID to check
     * @return bool Whether generation is enabled
     */
    public function isGenerationEnabledForEntryType(int $entryTypeId): bool
    {
        return $this->entryTypeSettings[$entryTypeId]['enabled'] ?? false;
    }

    /**
     * Get default fields for a specific entry type
     *
     * @param int $entryTypeId Entry type ID
     * @return array Array of field handles
     */
    public function getDefaultFieldsForEntryType(int $entryTypeId): array
    {
        return $this->entryTypeSettings[$entryTypeId]['defaultFields'] ?? [];
    }

    /**
     * Get effective max tokens considering model limitations
     *
     * @param string $modelId The model to check limits for
     * @return int Effective max tokens value
     */
    public function getEffectiveMaxTokens(string $modelId = ''): int
    {
        $maxTokens = $this->maxTokens;
        
        // If model is specified, check its limits
        if (!empty($modelId)) {
            try {
                $modelConfigService = \MadeByBramble\AiContentWriter\Plugin::getInstance()->modelConfig;
                $config = $modelConfigService->getModelConfig($modelId);
                
                if ($config && isset($config['version_info']['max_context_tokens'])) {
                    $modelMax = $config['version_info']['max_context_tokens'];
                    $maxTokens = min($maxTokens, $modelMax);
                }
            } catch (\Exception $e) {
                // If model config fails, use settings value
                \Craft::warning(
                    "Could not get model limits for {$modelId}: " . $e->getMessage(),
                    'ai-content-writer'
                );
            }
        }
        
        return $maxTokens;
    }
}