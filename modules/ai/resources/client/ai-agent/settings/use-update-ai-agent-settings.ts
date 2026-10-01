import {aiAgentQueries} from '@ai/ai-agent/ai-agent-queries';
import {AiAgentSettings} from '@ai/ai-agent/settings/ai-agent-settings';
import {onFormQueryError} from '@common/errors/on-form-query-error';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {useRequiredParams} from '@common/ui/navigation/use-required-params';
import {useMutation} from '@tanstack/react-query';
import {UseFormReturn} from 'react-hook-form';

type Payload = {
  enabled?: boolean;
  config?: Partial<AiAgentSettings>;
};

export function useUpdateAiAgentSettings(
  form?: UseFormReturn<Partial<AiAgentSettings>>,
) {
  const {aiAgentId} = useRequiredParams(['aiAgentId']);
  return useMutation({
    mutationFn: (payload: Payload) => {
      return apiClient.put(`lc/ai-agents/${aiAgentId}`, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: aiAgentQueries.agents.invalidateKey,
      });
    },
    onError: err => {
      if (form) {
        onFormQueryError(err, form);
      } else {
        showHttpErrorToast(err);
      }
    },
  });
}
