<?php

namespace MadeByBramble\AiContentWriter;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Dashboard;
use craft\web\UrlManager;
use MadeByBramble\AiContentWriter\models\Settings;
use MadeByBramble\AiContentWriter\services\OpenAiService;
use MadeByBramble\AiContentWriter\services\ModelConfigService;
use MadeByBramble\AiContentWriter\services\ContentGenerationService;
use MadeByBramble\AiContentWriter\services\EntryTypeService;
use MadeByBramble\AiContentWriter\services\FieldMappingService;
use MadeByBramble\AiContentWriter\controllers\ContentController;
use MadeByBramble\AiContentWriter\controllers\SettingsController;
use MadeByBramble\AiContentWriter\widgets\ContentGenerationStatsWidget;
use yii\base\Event;

/**
 * AI Content Writer plugin
 * 
 * This plugin provides AI-powered content generation for Craft CMS entries using OpenAI's language models.
 * Features include entry editor integration, field type detection, content preview, and bulk generation capabilities.
 *
 * @property-read OpenAiService $openAi
 * @property-read ModelConfigService $modelConfig
 * @property-read ContentGenerationService $contentGeneration
 * @property-read EntryTypeService $entryType
 * @property-read FieldMappingService $fieldMapping
 * @property-read Settings $settings
 * 
 * @since 1.0.0
 * @author Made By Bramble - Phill Morgan
 */
class Plugin extends BasePlugin
{
    /**
     * @var string Plugin schema version for database migrations
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool Plugin has settings page in control panel
     */
    public bool $hasCpSettings = true;

    /**
     * @var bool Plugin does not have its own CP section
     */
    public bool $hasCpSection = false;

    /**
     * Configure plugin components and services
     *
     * @return array Component configuration
     * @throws \Exception When component configuration fails
     */
    public static function config(): array
    {
        return [
            'components' => [
                'openAi' => ['class' => OpenAiService::class],
                'modelConfig' => ['class' => ModelConfigService::class],
                'contentGeneration' => ['class' => ContentGenerationService::class],
                'entryType' => ['class' => EntryTypeService::class],
                'fieldMapping' => ['class' => FieldMappingService::class],
            ],
            'controllerMap' => [
                'content' => ContentController::class,
                'settings' => SettingsController::class,
            ],
        ];
    }

    /**
     * Initialize the plugin and register event handlers
     * 
     * @throws \Exception When initialization fails
     */
    public function init(): void
    {
        parent::init();

        $this->name = Craft::t('ai-content-writer', 'AI Content Writer');

        // Defer initialization until Craft is ready to avoid timing issues
        Craft::$app->onInit(function () {
            $this->registerTemplateHooks();
            $this->registerWidgets();
            $this->registerCpUrlRules();
        });
    }

    /**
     * Create and return the settings model instance
     *
     * @return Model|null Settings model instance
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * Render the settings page HTML
     *
     * @return string|null Settings page HTML
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('ai-content-writer/settings', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    /**
     * Register event handlers for entry editor integration
     * 
     * This method registers the EVENT_DEFINE_SIDEBAR_HTML event to add
     * the AI content generation panel to the entry editor sidebar.
     * 
     * Replaces the obsolete cp.entries.edit.details template hook that was
     * removed in Craft CMS 4+.
     */
    private function registerTemplateHooks(): void
    {
        // Register modern event-based entry sidebar integration
        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_SIDEBAR_HTML,
            [$this, 'defineEntrySidebarHtml']
        );
    }

    /**
     * Add custom HTML to entry edit sidebar
     *
     * This method is called when Craft is defining the HTML content for the entry editor sidebar.
     * It adds the AI Content Writer panel if the entry type is enabled and configured properly.
     *
     * @param DefineHtmlEvent $event The event object containing the entry and current HTML
     */
    public function defineEntrySidebarHtml(DefineHtmlEvent $event): void
    {
        /** @var Entry $entry */
        $entry = $event->sender;

        // Check if plugin should appear for this entry type
        $settings = $this->getSettings();
        if (!$this->shouldShowForEntry($entry, $settings)) {
            return;
        }

        // Render the entry panel template
        try {
            $html = Craft::$app->getView()->renderTemplate('ai-content-writer/_entry-panel', [
                'entry' => $entry,
                'settings' => $settings,
            ]);

            // Append to sidebar HTML
            $event->html .= $html;

            // Log successful panel rendering
            Craft::info(
                "AI Content Writer panel rendered for entry '{$entry->title}' (ID: {$entry->id})",
                'ai-content-writer'
            );
        } catch (\Throwable $e) {
            // Log template rendering errors
            Craft::error(
                "Failed to render AI Content Writer panel for entry {$entry->id}: " . $e->getMessage(),
                'ai-content-writer'
            );
        }
    }

    /**
     * Check if the plugin should show for a specific entry
     *
     * @param Entry $entry The entry to check
     * @param Settings $settings Plugin settings
     * @return bool Whether the plugin should appear
     */
    private function shouldShowForEntry(Entry $entry, Settings $settings): bool
    {
        // Check if API key is configured
        if (empty($settings->getParsedApiKey())) {
            return false;
        }

        // Check if this entry type is enabled for content generation
        $entryTypeId = $entry->typeId;
        return !empty($settings->entryTypeSettings[$entryTypeId]['enabled']);
    }

    /**
     * Register widgets for the dashboard
     */
    private function registerWidgets(): void
    {
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = ContentGenerationStatsWidget::class;
            }
        );
    }

    /**
     * Register CP URL rules for AJAX endpoints
     * 
     * This method registers the control panel URL rules needed for the plugin's
     * AJAX endpoints to work properly in Craft CMS 5.
     */
    private function registerCpUrlRules(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['ai-content-writer/content/get-available-fields'] = 'ai-content-writer/content/get-available-fields';
                $event->rules['ai-content-writer/content/generate'] = 'ai-content-writer/content/generate';
                $event->rules['ai-content-writer/content/test-connection'] = 'ai-content-writer/content/test-connection';
                $event->rules['ai-content-writer/content/generate-batch'] = 'ai-content-writer/content/generate-batch';
                $event->rules['ai-content-writer/content/get-field-mapping'] = 'ai-content-writer/content/get-field-mapping';
                $event->rules['ai-content-writer/content/get-stats'] = 'ai-content-writer/content/get-stats';
            }
        );
    }
}
