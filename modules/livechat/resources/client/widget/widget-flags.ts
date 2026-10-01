import {addGlobalHeaderToApiClient} from '@common/http/query-client';
import {getWidgetBootstrapData} from '@livechat/widget/hooks/use-widget-bootstrap-data';

let activeAiAgentId: string | null = null;
let activeKnowledgeScopeTag: string | null = null;

const setHeadersForLiveMode = () => {
  addGlobalHeaderToApiClient('X-Chat-Widget', 'true');

  // ai agent id
  const aiAgentId = getWidgetBootstrapData().aiAgent?.id;
  if (aiAgentId) {
    activeAiAgentId = `${aiAgentId}`;
    addGlobalHeaderToApiClient('X-Widget-Ai-Agent-Id', `${aiAgentId}`);
  } else {
    activeAiAgentId = null;
  }

  // knowledge scope tag
  const activeKnowledgeScopeTag = getWidgetBootstrapData().knowledgeScopeTag;
  if (activeKnowledgeScopeTag) {
    addGlobalHeaderToApiClient(
      'X-Widget-Knowledge-Scope-Tag',
      activeKnowledgeScopeTag,
    );
  }
};

export const WidgetFlags = {
  setHeadersForLiveMode,
  setHeadersForPreviewMode: () => {
    setHeadersForLiveMode();
    addGlobalHeaderToApiClient('X-Ai-Agent-Preview-Mode', 'true');
  },
  getAiAgentId: () => activeAiAgentId,
  getKnowledgeScopeTag: () => activeKnowledgeScopeTag,
  keys: {
    url: {
      aiAgentId: 'xWidgetAiAgentId',
      flowId: 'xWidgetFlowId',
      isMobile: 'xWidgetIsMobile',
    },
    headers: {
      widget: 'X-Chat-Widget',
    },
  },
};
