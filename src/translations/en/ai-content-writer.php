<?php
/**
 * AI Content Writer plugin for Craft CMS
 *
 * AI Content Writer English Translation
 *
 * @author    Made By Bramble
 * @package   AiContentWriter
 * @since     1.0.0
 */

return [
    // Plugin name
    'AI Content Writer' => 'AI Content Writer',

    // General messages
    'AI-powered content generation for Craft CMS entries using OpenAI' => 'AI-powered content generation for Craft CMS entries using OpenAI',

    // Entry panel
    'Target Field' => 'Target Field',
    'Select a field...' => 'Select a field...',
    'Prompt' => 'Prompt',
    'Describe the content you want to generate...' => 'Describe the content you want to generate...',
    'Generate Content' => 'Generate Content',
    'Generating...' => 'Generating...',
    'Insert into Field' => 'Insert into Field',
    'Generated Content' => 'Generated Content',

    // Settings page
    'OpenAI API Key' => 'OpenAI API Key',
    'Enter your OpenAI API key. You can use environment variables like `$OPENAI_API_KEY`.' => 'Enter your OpenAI API key. You can use environment variables like `$OPENAI_API_KEY`.',
    'Test your OpenAI API connection to ensure it\'s working properly.' => 'Test your OpenAI API connection to ensure it\'s working properly.',
    'Test Connection' => 'Test Connection',
    'Testing...' => 'Testing...',
    'OpenAI Model' => 'OpenAI Model',
    'Select the OpenAI model to use for content generation.' => 'Select the OpenAI model to use for content generation.',
    'Select a model...' => 'Select a model...',
    'Max Tokens' => 'Max Tokens',
    'Maximum tokens for content generation (100-4000). This setting overrides model defaults. Higher values allow longer content but increase API costs. Model defaults are now aligned with this setting (2000 tokens).' => 'Maximum tokens for content generation (100-4000). This setting overrides model defaults. Higher values allow longer content but increase API costs. Model defaults are now aligned with this setting (2000 tokens).',
    'Important:' => 'Important:',
    'Token limits cannot exceed individual model maximums. The system will automatically cap your setting to each model\'s maximum context size.' => 'Token limits cannot exceed individual model maximums. The system will automatically cap your setting to each model\'s maximum context size.',
    'Custom System Prompt' => 'Custom System Prompt',
    'Override the default system prompt used for content generation. Leave empty to use the default prompt.' => 'Override the default system prompt used for content generation. Leave empty to use the default prompt.',
    'Leave empty to use default prompt...' => 'Leave empty to use default prompt...',

    // Field type support
    'Field Type Support' => 'Field Type Support',
    'Configure which field types can receive AI-generated content.' => 'Configure which field types can receive AI-generated content.',
    'Plain Text Fields' => 'Plain Text Fields',
    'Table Fields' => 'Table Fields',
    'Redactor Fields' => 'Redactor Fields',
    'CKEditor Fields' => 'CKEditor Fields',

    // Entry type configuration
    'Entry Type Configuration' => 'Entry Type Configuration',
    'Configure content generation settings for each entry type.' => 'Configure content generation settings for each entry type.',
    'Entry Type' => 'Entry Type',
    'Section' => 'Section',
    'Enable Generation' => 'Enable Generation',
    'Available Fields' => 'Available Fields',
    'compatible fields' => 'compatible fields',
    'No compatible fields' => 'No compatible fields',
    'No entry types found. Please create at least one entry type to use this plugin.' => 'No entry types found. Please create at least one entry type to use this plugin.',

    // Advanced settings
    'Advanced Settings' => 'Advanced Settings',
    'Max Retries' => 'Max Retries',
    'Maximum number of retry attempts for failed API requests (1-10).' => 'Maximum number of retry attempts for failed API requests (1-10).',
    'API Timeout (seconds)' => 'API Timeout (seconds)',
    'Timeout in seconds for OpenAI API requests (10-120). Longer timeouts recommended for content generation.' => 'Timeout in seconds for OpenAI API requests (10-120). Longer timeouts recommended for content generation.',

    // Error messages
    'Please select a field and enter a prompt' => 'Please select a field and enter a prompt',
    'Field information not available' => 'Field information not available',
    'Content generated successfully' => 'Content generated successfully',
    'Generation failed' => 'Generation failed',
    'No content to insert' => 'No content to insert',
    'Content inserted into field' => 'Content inserted into field',
    'Could not insert content into field' => 'Could not insert content into field',
    'Please enter an API key first' => 'Please enter an API key first',
    'Connection test failed: ' => 'Connection test failed: ',
    'Testing connection...' => 'Testing connection...',
    'Max tokens must be between 100 and 4000' => 'Max tokens must be between 100 and 4000',
    'Max retries must be between 1 and 10' => 'Max retries must be between 1 and 10',
    'API timeout must be between 10 and 120 seconds' => 'API timeout must be between 10 and 120 seconds',

    // Field insertion messages
    'Table content generated. Please manually structure the content into table rows and columns.' => 'Table content generated. Please manually structure the content into table rows and columns.',
];