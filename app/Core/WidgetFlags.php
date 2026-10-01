<?php

namespace App\Core;

class WidgetFlags
{
    public static function knowledgeScopeTag(): string|null
    {
        return request()->header('X-Widget-Knowledge-Scope-Tag') ?:
            request('xWidgetKnowledgeScopeTag');
    }

    public static function aiAgentId(): int|null
    {
        $id =
            request()->header('X-Widget-Ai-Agent-Id') ?:
            request('xWidgetAiAgentId');
        return $id ? (int) $id : null;
    }

    public static function flowId(): int|null
    {
        $id = request('xWidgetFlowId');
        return $id ? (int) $id : null;
    }

    public static function conversationId(): int|null
    {
        $id = request('xWidgetConversationId');
        return $id ? (int) $id : null;
    }

    public static function isAiAgentPreviewMode(): bool
    {
        return request()->header('X-Ai-Agent-Preview-Mode') === 'true';
    }

    public static function isLivechatWidget(): bool
    {
        return request()->header('X-Chat-Widget') === 'true' ||
            request()->get('_xChatWidget') === 'true' ||
            str_starts_with(request()->path(), 'lc/widget');
    }

    public static function isMobile(): bool
    {
        return request()->header('X-Widget-Is-Mobile') === 'true' ||
            request()->get('xWidgetIsMobile') === 'true';
    }
}
