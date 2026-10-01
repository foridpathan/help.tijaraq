import {FullConversationResponse} from '@app/dashboard/conversation';
import {useAfterReplyAction} from '@app/dashboard/conversations/agent-reply-composer/after-reply-action';
import {useAgentReplyComposerStore} from '@app/dashboard/conversations/agent-reply-composer/agent-reply-composer-store';
import {addAnimatingMessage} from '@app/dashboard/conversations/conversation-page/messages/animating-messages';
import {helpdeskQueries} from '@app/dashboard/helpdesk-queries';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {useMutation} from '@tanstack/react-query';
import {message} from '@ui/i18n/message';
import {toast} from '@ui/toast/toast';
import {nanoid} from 'nanoid';

export const submitAgentReplyMutationKey = ['submit-agent-reply'];

export function useSubmitAgentReply(
  conversation: FullConversationResponse['conversation'],
) {
  const {perform} = useAfterReplyAction(conversation);
  const getData = useAgentReplyComposerStore(s => s.getData);
  const deleteDraft = useAgentReplyComposerStore(s => s.deleteDraft);
  const setMessageType = useAgentReplyComposerStore(s => s.setMessageType);

  const getPayload = () => {
    const data = getData();
    const uuid = nanoid();
    addAnimatingMessage(uuid);
    return {
      status_id: data.selectedStatus,
      type: data.messageType,
      attachments: data.draft.attachments.map(a => a.id),
      tags: data.draft.tags.map(t => t.id),
      body: data.draft.body,
      author: 'agent' as const,
      uuid,
    };
  };

  return useMutation({
    mutationKey: submitAgentReplyMutationKey,
    mutationFn: () => {
      return apiClient
        .post(
          `helpdesk/agent/conversations/${conversation.id}/messages`,
          getPayload(),
        )
        .then(r => r.data);
    },
    onError: err => showHttpErrorToast(err),
    onSuccess: async response => {
      deleteDraft();
      setMessageType('message');

      // no need to wait for these to finish
      queryClient.invalidateQueries({
        queryKey: helpdeskQueries.views.invalidateKey,
      });

      await Promise.allSettled([
        response.message.type !== 'note' ? perform() : Promise.resolve(),
        queryClient.invalidateQueries({
          queryKey: helpdeskQueries.conversations.invalidateKey,
        }),
      ]);

      // don't put toast of reply composer
      toast(message('Reply submitted'), {position: 'top-center'});
    },
  });
}
