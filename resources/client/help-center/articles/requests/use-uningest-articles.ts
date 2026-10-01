import {aiAgentQueries} from '@ai/ai-agent/ai-agent-queries';
import {helpCenterQueries} from '@app/help-center/help-center-queries';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {useRequiredParams} from '@common/ui/navigation/use-required-params';
import {useMutation} from '@tanstack/react-query';
import {toast} from '@ui/toast/toast';

interface Payload {
  all?: true;
  articleIds?: (number | string)[];
}

export function useUningestArticles() {
  const {aiAgentId} = useRequiredParams(['aiAgentId']);
  return useMutation({
    mutationFn: (payload: Payload) => {
      return apiClient
        .post('lc/ai-agent/articles/uningest', {
          ...payload,
          aiAgentId,
        })
        .then(r => r.data);
    },
    onSuccess: () => {
      toast({message: 'Disabled articles for AI agent'});
      return Promise.allSettled([
        queryClient.invalidateQueries({
          queryKey: aiAgentQueries.knowledge.invalidateKey,
        }),
        queryClient.invalidateQueries({
          queryKey: helpCenterQueries.articles.invalidateKey,
        }),
      ]);
    },
    onError: r => showHttpErrorToast(r),
  });
}
