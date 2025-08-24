<?php

namespace MadeByBramble\AiContentWriter\services;

use Craft;
use OpenAI;
use OpenAI\Client;
use MadeByBramble\AiContentWriter\Plugin;
use yii\base\Component;
use yii\base\InvalidConfigException;
use Throwable;

/**
 * OpenAI integration service
 * 
 * This service handles all interactions with the OpenAI API for generating
 * content from text prompts. It includes comprehensive error handling, retry logic,
 * and proper response processing for various content types.
 */
class OpenAiService extends Component
{
    /**
     * @var Client|null OpenAI client instance (cached for performance)
     */
    private ?Client $client = null;

    /**
     * Get configured OpenAI client with proper timeout and API key validation
     *
     * @return Client Configured OpenAI client
     * @throws InvalidConfigException If API key is not configured or invalid
     */
    private function getClient(): Client
    {
        if ($this->client === null) {
            $settings = Plugin::getInstance()->getSettings();
            $apiKey = $settings->getParsedApiKey();

            if (empty($apiKey)) {
                throw new InvalidConfigException('OpenAI API key is not configured');
            }

            // Create client with custom HTTP configuration for proper timeout handling
            $this->client = OpenAI::factory()
                ->withApiKey($apiKey)
                ->withHttpClient(new \GuzzleHttp\Client([
                    'timeout' => $settings->apiTimeout,
                ]))
                ->make();
        }

        return $this->client;
    }

    /**
     * Generate content using OpenAI API
     * 
     * This method handles the complete workflow of building the prompt,
     * calling the OpenAI API, and processing the response to generate
     * appropriate content based on the provided context.
     *
     * @param string $prompt The user prompt for content generation
     * @param array $context Context information for content generation
     * @return string Generated content
     * @throws Throwable If generation fails after all retries
     */
    public function generateContent(string $prompt, array $context = []): string
    {
        $settings = Plugin::getInstance()->getSettings();

        // Build the messages for the API request
        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemPrompt($context)
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ];

        // Log the request for debugging (debug mode only)
        if (Craft::$app->getConfig()->general->devMode) {
            Craft::info(
                "Processing content generation request with prompt: " . substr($prompt, 0, 100) . (strlen($prompt) > 100 ? '...' : ''),
                'ai-content-writer'
            );
        }

        // Call OpenAI API with retry logic for resilience
        $content = $this->callOpenAiWithRetry($messages, $settings->maxRetries);

        // Format the content based on the requested format
        return $this->formatContent($content, $context['format'] ?? 'plain');
    }

    /**
     * Build system prompt based on context
     *
     * @param array $context Context information
     * @return string System prompt
     */
    private function buildSystemPrompt(array $context): string
    {
        $settings = Plugin::getInstance()->getSettings();
        $basePrompt = $settings->getSystemPrompt();

        // Add context-specific instructions
        $contextInstructions = [];

        if (isset($context['entryType'])) {
            $contextInstructions[] = "This content is for a {$context['entryType']} entry type.";
        }

        if (isset($context['field'])) {
            $contextInstructions[] = "The content will be inserted into the '{$context['field']}' field.";
        }

        if (isset($context['format'])) {
            switch ($context['format']) {
                case 'html':
                    $contextInstructions[] = "Format the content as clean HTML with appropriate tags.";
                    break;
                case 'markdown':
                    $contextInstructions[] = "Format the content as Markdown.";
                    break;
                default:
                    $contextInstructions[] = "Format the content as plain text.";
                    break;
            }
        }

        if (isset($context['existingContent']) && !empty($context['existingContent'])) {
            $contextInstructions[] = "Consider the existing content context when generating new content.";
        }

        if (!empty($contextInstructions)) {
            $basePrompt .= "\n\nAdditional context:\n" . implode("\n", $contextInstructions);
        }

        return $basePrompt;
    }

    /**
     * Format content based on the requested format
     *
     * @param string $content Raw content from OpenAI
     * @param string $format Requested format (plain, html, markdown)
     * @return string Formatted content
     */
    private function formatContent(string $content, string $format): string
    {
        // Basic content cleaning
        $content = trim($content);

        // Remove quotes that may wrap the response
        $content = trim($content, '"\'');

        // Format-specific processing
        switch ($format) {
            case 'html':
                // Ensure proper HTML structure if needed
                if (!empty($content) && strpos($content, '<') === false) {
                    // If content doesn't contain HTML tags, wrap in paragraph
                    $content = '<p>' . nl2br(htmlspecialchars($content)) . '</p>';
                }
                break;
            
            case 'markdown':
                // Content is already in markdown format from OpenAI
                break;
            
            default: // plain text
                // Strip any HTML tags that might have been generated
                $content = strip_tags($content);
                break;
        }

        return $content;
    }

    /**
     * Call OpenAI API with exponential backoff retry logic
     * 
     * Implements robust retry mechanism to handle temporary API failures,
     * rate limiting, and network issues with exponential backoff.
     *
     * @param array $messages Message array for the API request
     * @param int $maxRetries Maximum number of retry attempts
     * @return string Generated content from OpenAI
     * @throws Throwable If all retry attempts fail
     */
    private function callOpenAiWithRetry(array $messages, int $maxRetries): string
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                if (Craft::$app->getConfig()->general->devMode) {
                    Craft::info(
                        "OpenAI API attempt {$attempt}/{$maxRetries}",
                        'ai-content-writer'
                    );
                }

                // Build parameters based on model requirements
                $model = $this->getConfiguredModel();
                $modelParams = $this->getModelParameters($model);
                
                $parameters = [
                    'model' => $model,
                    'messages' => $messages,
                ];
                
                // Add token limit parameter based on model requirements
                $parameters[$modelParams['token_param']] = $modelParams['token_value'];
                
                // Add temperature parameter only if model supports it
                if (isset($modelParams['temperature'])) {
                    $parameters['temperature'] = $modelParams['temperature'];
                }
                
                // Add reasoning_effort parameter only if model supports it
                if (isset($modelParams['reasoning_effort'])) {
                    $parameters['reasoning_effort'] = $modelParams['reasoning_effort'];
                }
                
                // Enhanced parameter logging with settings source (debug mode only)
                if (Craft::$app->getConfig()->general->devMode) {
                    $settings = Plugin::getInstance()->getSettings();
                    $paramInfo = "Using {$modelParams['token_param']}={$modelParams['token_value']}";
                    if (isset($modelParams['temperature'])) {
                        $paramInfo .= " and temperature={$modelParams['temperature']}";
                    } else {
                        $paramInfo .= " (using default temperature)";
                    }
                    if (isset($modelParams['reasoning_effort'])) {
                        $paramInfo .= " and reasoning_effort={$modelParams['reasoning_effort']}";
                    }
                    
                    // Indicate source of token limit
                    if ($settings && $settings->maxTokens) {
                        $paramInfo .= " (from settings override)";
                    } else {
                        $paramInfo .= " (from YAML default)";
                    }
                    
                    Craft::info(
                        "Parameters for model '{$model}': {$paramInfo}",
                        'ai-content-writer'
                    );
                }
                
                // Enhanced request logging (debug mode only)
                if (Craft::$app->getConfig()->general->devMode) {
                    Craft::info('OpenAI Request: ' . json_encode([
                        'model' => $model,
                        'parameters' => array_merge($parameters, [
                            // Don't log the full messages content for brevity, just structure info
                            'messages' => [
                                'count' => count($parameters['messages']),
                                'system_prompt_length' => strlen($parameters['messages'][0]['content'] ?? ''),
                                'user_prompt_length' => strlen($parameters['messages'][1]['content'] ?? '')
                            ]
                        ]),
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries
                    ]), 'ai-content-writer');
                }
                
                // Validate token parameters before making the call
                $tokenParam = $modelParams['token_param'];
                $tokenValue = $modelParams['token_value'];
                
                if ($tokenValue <= 0) {
                    throw new \InvalidArgumentException("Invalid token limit: {$tokenValue}");
                }
                
                if ($tokenValue > 4000) {
                    Craft::warning(
                        "Token limit {$tokenValue} is very high and may cause API errors or high costs",
                        'ai-content-writer'
                    );
                }
                
                $response = $this->getClient()->chat()->create($parameters);

                $content = $response->choices[0]->message->content ?? '';
                
                // Enhanced response logging (debug mode only)
                if (Craft::$app->getConfig()->general->devMode) {
                    Craft::info('OpenAI Response: ' . json_encode([
                        'content_length' => strlen($content),
                        'finish_reason' => $response->choices[0]->finishReason ?? 'unknown',
                        'usage' => [
                            'prompt_tokens' => $response->usage->promptTokens ?? null,
                            'completion_tokens' => $response->usage->completionTokens ?? null,
                            'total_tokens' => $response->usage->totalTokens ?? null
                        ],
                        'model_used' => $response->model ?? $model,
                        'choices_count' => count($response->choices),
                        'attempt' => $attempt
                    ]), 'ai-content-writer');
                }
                
                // Log the response for debugging empty responses
                if (empty($content)) {
                    $finishReason = $response->choices[0]->finishReason ?? 'unknown';
                    if ($finishReason === 'length') {
                        Craft::error(
                            "OpenAI response truncated due to token limit. Increase token_value in getModelParameters(). Usage: " . json_encode($response->usage ?? null),
                            'ai-content-writer'
                        );
                    } else {
                        Craft::warning(
                            "OpenAI returned empty content. Finish reason: {$finishReason}. Response: " . json_encode([
                                'choices_count' => count($response->choices),
                                'first_choice' => $response->choices[0] ?? null,
                                'usage' => $response->usage ?? null
                            ]),
                            'ai-content-writer'
                        );
                    }
                }

                return $content;
            } catch (Throwable $e) {
                $lastException = $e;
                
                // Attempt parameter adjustment for compatibility
                $shouldRetry = false;
                $retryParameters = $parameters;
                
                // Handle token parameter errors
                if (str_contains($e->getMessage(), 'max_tokens') || str_contains($e->getMessage(), 'max_completion_tokens')) {
                    $shouldRetry = true;
                    // Switch to alternative token parameter
                    $altTokenParam = isset($parameters['max_tokens']) ? 'max_completion_tokens' : 'max_tokens';
                    $tokenValue = $parameters[$modelParams['token_param']];
                    unset($retryParameters[$modelParams['token_param']]);
                    $retryParameters[$altTokenParam] = $tokenValue;
                    
                    if (Craft::$app->getConfig()->general->devMode) {
                        Craft::info(
                            "Retrying with alternative token parameter: {$altTokenParam}",
                            'ai-content-writer'
                        );
                    }
                }
                
                // Handle temperature parameter errors
                if (str_contains($e->getMessage(), 'temperature') && isset($parameters['temperature'])) {
                    $shouldRetry = true;
                    // Remove temperature parameter to use model default
                    unset($retryParameters['temperature']);
                    
                    if (Craft::$app->getConfig()->general->devMode) {
                        Craft::info(
                            "Retrying without temperature parameter (using model default)",
                            'ai-content-writer'
                        );
                    }
                }
                
                // Attempt retry with adjusted parameters
                if ($shouldRetry) {
                    try {
                        $response = $this->getClient()->chat()->create($retryParameters);
                        $content = $response->choices[0]->message->content ?? '';
                        
                        // Enhanced retry response logging (debug mode only)
                        if (Craft::$app->getConfig()->general->devMode) {
                            Craft::info('OpenAI Retry Response: ' . json_encode([
                                'content_length' => strlen($content),
                                'finish_reason' => $response->choices[0]->finishReason ?? 'unknown',
                                'usage' => [
                                    'prompt_tokens' => $response->usage->promptTokens ?? null,
                                    'completion_tokens' => $response->usage->completionTokens ?? null,
                                    'total_tokens' => $response->usage->totalTokens ?? null
                                ],
                                'model_used' => $response->model ?? $model,
                                'attempt' => $attempt,
                                'retry_parameters' => array_keys(array_diff_key($retryParameters, $parameters))
                            ]), 'ai-content-writer');
                        }
                        
                        // Log retry response for debugging
                        if (empty($content)) {
                            $finishReason = $response->choices[0]->finishReason ?? 'unknown';
                            if ($finishReason === 'length') {
                                Craft::error(
                                    "OpenAI retry response truncated due to token limit. Increase token_value in getModelParameters(). Usage: " . json_encode($response->usage ?? null),
                                    'ai-content-writer'
                                );
                            } else {
                                Craft::warning(
                                    "OpenAI retry returned empty content. Finish reason: {$finishReason}. Response: " . json_encode([
                                        'choices_count' => count($response->choices),
                                        'first_choice' => $response->choices[0] ?? null,
                                        'usage' => $response->usage ?? null
                                    ]),
                                    'ai-content-writer'
                                );
                            }
                        }
                        
                        return $content;
                        
                    } catch (Throwable $retryException) {
                        // Continue with standard error handling after retry failure
                        Craft::error(
                            "Retry with alternative parameters also failed: " . $retryException->getMessage(),
                            'ai-content-writer'
                        );
                    }
                }
                
                Craft::error(
                    "OpenAI API attempt {$attempt} failed: " . $e->getMessage(),
                    'ai-content-writer'
                );

                // Wait before retrying with exponential backoff (1s, 2s, 4s...)
                if ($attempt < $maxRetries) {
                    sleep(pow(2, $attempt - 1));
                }
            }
        }

        // If all retries failed, throw the last exception for proper error handling
        throw $lastException ?? new \Exception('Unknown error occurred');
    }

    /**
     * Get the configured OpenAI model from settings
     *
     * @return string The configured model, with fallback to GPT-4o
     */
    private function getConfiguredModel(): string
    {
        $settings = Plugin::getInstance()->getSettings();
        $configuredModel = $settings->getParsedModel();
        
        // Fallback to GPT-4o if not configured or empty
        if (empty($configuredModel)) {
            return 'gpt-4o';
        }
        
        return $configuredModel;
    }

    /**
     * Get model-specific parameters for OpenAI API
     *
     * Loads model-specific parameters from YAML configuration files.
     * Enables flexible model configuration management.
     *
     * @param string $model Model name to get parameters for
     * @return array Array containing:
     *   - token_param: string (parameter name for token limit)
     *   - token_value: int (token limit value)
     *   - temperature: float|null (temperature value, null if model doesn't support custom temperature)
     */
    private function getModelParameters(string $model): array
    {
        $modelConfigService = Plugin::getInstance()->modelConfig;
        $settings = Plugin::getInstance()->getSettings(); // Get current settings
        return $modelConfigService->getModelParameters($model, $settings);
    }

    /**
     * Test the OpenAI connection and API key validity
     * 
     * This method provides a way to validate the API configuration
     * without processing actual content, useful for settings validation.
     *
     * @return array [success => bool, message => string] Test result
     */
    public function testConnection(): array
    {
        try {
            $client = $this->getClient();
            
            // Make a simple API call to test the connection and API key
            $response = $client->models()->list();
            
            if ($response->object === 'list' && !empty($response->data)) {
                return [
                    'success' => true,
                    'message' => 'Successfully connected to OpenAI API'
                ];
            }

            return [
                'success' => false,
                'message' => 'Unexpected response from OpenAI API'
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ];
        }
    }
}