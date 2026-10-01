import {aiAgentQueries} from '@ai/ai-agent/ai-agent-queries';
import {useRequiredParams} from '@common/ui/navigation/use-required-params';
import {useSuspenseQuery} from '@tanstack/react-query';
import {AiAgentDocument} from './documents/ai-agent-document';
import {AiAgentSnippet} from './snippets/ai-agent-snippet';
import {AiAgentWebsite} from './websites/requests/ai-agent-website';

interface KnowledgeGroup<T> {
  items: T[];
  ingesting: boolean;
  more: {
    count: number;
    ingesting: boolean;
  };
}

export interface Knowledge {
  ingesting: boolean;
  websites: KnowledgeGroup<AiAgentWebsite>;
  documents: KnowledgeGroup<AiAgentDocument>;
  articles: KnowledgeGroup<{
    id: number;
    title: string;
    scan_pending: boolean;
    updated_at: string;
  }>;
  snippets: KnowledgeGroup<AiAgentSnippet>;
}

export function useKnowledge() {
  const {aiAgentId} = useRequiredParams(['aiAgentId']);
  return useSuspenseQuery(aiAgentQueries.knowledge.index(aiAgentId));
}
