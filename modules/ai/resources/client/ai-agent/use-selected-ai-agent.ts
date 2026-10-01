import {aiAgentQueries} from '@ai/ai-agent/ai-agent-queries';
import {queryClient} from '@common/http/query-client';
import {useRequiredParams} from '@common/ui/navigation/use-required-params';
import {useSuspenseQuery} from '@tanstack/react-query';
import {getFromLocalStorage} from '@ui/utils/hooks/local-storage';

const localStorageKey = 'ai-agent-settings-selected-agent-id';

export function getSelectedAiAgentId(): string {
  return getFromLocalStorage(localStorageKey) ?? getFirstAgentId();
}

export function useSelectedAiAgent() {
  const {aiAgentId} = useRequiredParams(['aiAgentId']);
  const {data} = useSuspenseQuery(aiAgentQueries.agents.index());
  return (
    data.aiAgents.find(agent => `${agent.id}` === aiAgentId) || data.aiAgents[0]
  );
}

function getFirstAgentId(): string {
  return `${queryClient.getQueryData(aiAgentQueries.agents.index().queryKey)!.aiAgents[0].id}`;
}
