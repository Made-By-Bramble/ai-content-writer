<?php

namespace MadeByBramble\AiContentWriter\widgets;

use Craft;
use craft\base\Widget;
use MadeByBramble\AiContentWriter\Plugin;

/**
 * Content Generation Stats Widget
 * 
 * Displays statistics about content generation usage on the dashboard.
 */
class ContentGenerationStatsWidget extends Widget
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('ai-content-writer', 'AI Content Writer Stats');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): ?string
    {
        return 'edit';
    }

    /**
     * @inheritdoc
     */
    public static function maxColspan(): ?int
    {
        return 1;
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml(): ?string
    {
        try {
            $entryTypeService = Plugin::getInstance()->entryType;
            $stats = $entryTypeService->getConfigurationSummary();
            
            return Craft::$app->getView()->renderTemplate('ai-content-writer/_widgets/stats', [
                'stats' => $stats,
                'widget' => $this
            ]);
        } catch (\Throwable $e) {
            Craft::error(
                'Failed to render content generation stats widget: ' . $e->getMessage(),
                'ai-content-writer'
            );
            
            return '<p class="light">' . Craft::t('ai-content-writer', 'Unable to load statistics') . '</p>';
        }
    }
}