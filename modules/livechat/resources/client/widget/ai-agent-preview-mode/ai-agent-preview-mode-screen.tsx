import {aiAgentPreviewMessages} from '@ai/ai-agent/preview/preview-messages';
import {isLastItemInGroup} from '@app/dashboard/conversations/conversation-page/messages/feed/is-last-item-in-group';
import {MessagesInfiniteScrollContainer} from '@app/dashboard/conversations/conversation-page/messages/messages-infinite-scroll-container';
import {FileUploadProvider} from '@common/uploads/uploader/file-upload-provider';
import {WidgetChatFeedContentItem} from '@livechat/widget/conversation-screen/feed/widget-chat-feed-content-item';
import {WidgetChatGreeting} from '@livechat/widget/conversation-screen/feed/widget-chat-greeting';
import {useChatMessageSubmitter} from '@livechat/widget/conversation-screen/requests/use-chat-message-submitter';
import {useWidgetChatMessages} from '@livechat/widget/conversation-screen/requests/use-widget-chat-messages';
import {shouldHideReplyComposer} from '@livechat/widget/conversation-screen/utils/should-hide-reply-composer';
import {WidgetChatTextEditor} from '@livechat/widget/conversation-screen/widget-chat-text-editor';
import {WidgetFlags} from '@livechat/widget/widget-flags';
import {widgetStore} from '@livechat/widget/widget-store';
import {DialogStoreOutlet} from '@ui/overlays/store/dialog-store-outlet';
import {ToastContainer} from '@ui/toast/toast-container';
import clsx from 'clsx';
import {useEffect, useRef} from 'react';
import {Route, Routes, useNavigate, useParams} from 'react-router';

export function AiAgentPreviewModeScreen() {
  const isBootStrapped = useRef(false);
  const navigate = useNavigate();

  useEffect(() => {
    if (!isBootStrapped.current) {
      WidgetFlags.setHeadersForPreviewMode();
      widgetStore().setIsAiAgentPreviewMode(true);
      isBootStrapped.current = true;

      aiAgentPreviewMessages.postLoaded(window.parent);
    }

    return aiAgentPreviewMessages.listen(window, {
      onConversationReset: () => {
        return navigate('/ai-agent-preview-mode', {replace: true});
      },
    });
  }, []);

  return (
    <div className="h-full bg text-main scheme-dark">
      <Routes>
        <Route path="ai-agent-preview-mode" element={<ConversationScreen />} />
        <Route
          path="ai-agent-preview-mode/:conversationId"
          element={<ConversationScreen />}
        />
      </Routes>
    </div>
  );
}

function ConversationScreen() {
  const {conversationId} = useParams();
  const messageQuery = useWidgetChatMessages(conversationId);
  const messages = messageQuery.data?.items ?? [];

  useEffect(() => {
    aiAgentPreviewMessages.postConversationIdChanged(
      window.parent,
      conversationId ?? null,
    );
    return () => {
      aiAgentPreviewMessages.postConversationIdChanged(window.parent, null);
    };
  }, [conversationId]);

  return (
    <div className="flex h-full flex-col">
      <ToastContainer />
      <DialogStoreOutlet />
      <div
        className={clsx(
          'compact-scrollbar flex-auto overflow-y-auto px-20 py-20',
        )}
      >
        <MessagesInfiniteScrollContainer
          className="w-full"
          query={messageQuery}
        >
          <WidgetChatGreeting disablePreChatForm />
          {messages.map((message, index) => {
            return (
              <WidgetChatFeedContentItem
                key={message.uuid}
                message={message}
                {...isLastItemInGroup(index, message, messages)}
              />
            );
          })}
        </MessagesInfiniteScrollContainer>
      </div>
      <div className="mt-20 flex-shrink-0 px-20 pb-16">
        <FileUploadProvider>
          {shouldHideReplyComposer(messages) ? null : (
            <ReplyComposer conversationId={conversationId} />
          )}
        </FileUploadProvider>
      </div>
    </div>
  );
}

type ReplyComposerProps = {
  conversationId: string | number | undefined;
};
function ReplyComposer({conversationId}: ReplyComposerProps) {
  const {createChat, submitMessage, isPending} = useChatMessageSubmitter();

  return (
    <WidgetChatTextEditor
      isPending={isPending}
      onSubmit={data => {
        if (conversationId) {
          submitMessage({message: data, conversationId});
        } else {
          createChat({message: data, startWithGreeting: true});
        }
      }}
    />
  );
}
