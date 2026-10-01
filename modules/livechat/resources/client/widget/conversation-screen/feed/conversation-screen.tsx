import {FullConversationResponse} from '@app/dashboard/conversation';
import {AgentAvatarWithIndicator} from '@app/dashboard/conversations/avatars/agent-avatar';
import {AiAgentAvatar} from '@app/dashboard/conversations/avatars/ai-agent-avatar';
import {isLastItemInGroup} from '@app/dashboard/conversations/conversation-page/messages/feed/is-last-item-in-group';
import {MessagesInfiniteScrollContainer} from '@app/dashboard/conversations/conversation-page/messages/messages-infinite-scroll-container';
import {statusCategory} from '@app/dashboard/statuses/status-category';
import {TicketDetails} from '@app/help-center/tickets-portal/ticket-page/ticket-details';
import {useSettingsPreviewMode} from '@common/admin/settings/preview/use-settings-preview-mode';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {WidgetPostChatForm} from '@livechat/widget/chat/forms/widget-post-chat-form';
import {WidgetChatFeedContentItem} from '@livechat/widget/conversation-screen/feed/widget-chat-feed-content-item';
import {WidgetChatFeedLayout} from '@livechat/widget/conversation-screen/feed/widget-chat-feed-layout';
import {WidgetChatGreeting} from '@livechat/widget/conversation-screen/feed/widget-chat-greeting';
import {NoAgentsAvailableMessage} from '@livechat/widget/conversation-screen/no-agents-available-message';
import {FullWidgetConversationResponse} from '@livechat/widget/conversation-screen/requests/full-widget-conversation-response';
import {useChatMessageSubmitter} from '@livechat/widget/conversation-screen/requests/use-chat-message-submitter';
import {useWidgetConversationScreenData} from '@livechat/widget/conversation-screen/requests/use-widget-chat-screen-data';
import {shouldHideReplyComposer} from '@livechat/widget/conversation-screen/utils/should-hide-reply-composer';
import {WidgetChatTextEditor} from '@livechat/widget/conversation-screen/widget-chat-text-editor';
import {useWidgetBootstrapData} from '@livechat/widget/hooks/use-widget-bootstrap-data';
import {useWidgetLogoSrc} from '@livechat/widget/hooks/use-widget-logo-src';
import {widgetQueries} from '@livechat/widget/widget-queries';
import {WidgetScreenHeader} from '@livechat/widget/widget-screen-header';
import {widgetStore} from '@livechat/widget/widget-store';
import {useMutation} from '@tanstack/react-query';
import {Avatar} from '@ui/avatar/avatar';
import {Button} from '@ui/buttons/button';
import {IconButton} from '@ui/buttons/icon-button';
import {Item} from '@ui/forms/listbox/item';
import {Switch} from '@ui/forms/toggle/switch';
import {message} from '@ui/i18n/message';
import {Trans} from '@ui/i18n/trans';
import {CheckCircleIcon} from '@ui/icons/material/CheckCircle';
import {CloseIcon} from '@ui/icons/material/Close';
import {ConfirmationNumberIcon} from '@ui/icons/material/ConfirmationNumber';
import {DownloadIcon} from '@ui/icons/material/Download';
import {KeyboardArrowLeftIcon} from '@ui/icons/material/KeyboardArrowLeft';
import {MoreHorizIcon} from '@ui/icons/material/MoreHoriz';
import {ScheduleIcon} from '@ui/icons/material/Schedule';
import {VolumeOffIcon} from '@ui/icons/material/VolumeOff';
import {VolumeUpIcon} from '@ui/icons/material/VolumeUp';
import {Menu, MenuTrigger} from '@ui/menu/menu-trigger';
import {ConfirmationDialog} from '@ui/overlays/dialog/confirmation-dialog';
import {Dialog} from '@ui/overlays/dialog/dialog';
import {DialogBody} from '@ui/overlays/dialog/dialog-body';
import {useDialogContext} from '@ui/overlays/dialog/dialog-context';
import {DialogHeader} from '@ui/overlays/dialog/dialog-header';
import {DialogTrigger} from '@ui/overlays/dialog/dialog-trigger';
import {openDialog} from '@ui/overlays/store/dialog-store';
import {FullPageLoader} from '@ui/progress/full-page-loader';
import {useSettings} from '@ui/settings/use-settings';
import {toast} from '@ui/toast/toast';
import {downloadFileFromUrl} from '@ui/utils/files/download-file-from-url';
import {useLocalStorage} from '@ui/utils/hooks/local-storage';
import {Link, useLocation, useNavigate} from 'react-router';

export function ConversationScreen() {
  const {state} = useLocation();

  const {conversationQuery, messagesQuery, postChatFormIsVisible} =
    useWidgetConversationScreenData();
  const conversation = conversationQuery.data?.conversation;

  let textEditor = null;
  if (
    !shouldHideReplyComposer(messagesQuery.data?.items) &&
    !postChatFormIsVisible
  ) {
    textEditor = <WidgetChatScreenTextEditor chatId={conversation?.id} />;
  }

  const content = conversationQuery.isLoading ? (
    <FullPageLoader />
  ) : (
    <WidgetChatFeedLayout
      fixedHeader={
        conversationQuery.data &&
        conversationQuery.data.conversation.type === 'ticket' && (
          <TicketDetailsButton data={conversationQuery.data} />
        )
      }
      header={<NoAgentsAvailableMessage data={conversationQuery.data} />}
      feed={
        <MessagesInfiniteScrollContainer query={messagesQuery}>
          <WidgetChatGreeting />
          {messagesQuery.data?.items.map((message, index) => (
            <WidgetChatFeedContentItem
              key={message.uuid}
              message={message}
              {...isLastItemInGroup(index, message, messagesQuery.data.items)}
            />
          ))}
          {postChatFormIsVisible && conversation && (
            <WidgetPostChatForm chatId={conversation.id} />
          )}
        </MessagesInfiniteScrollContainer>
      }
      editor={
        <div>
          {conversationQuery.data?.conversation.type === 'ticket' && (
            <TicketStatusMessage data={conversationQuery.data} />
          )}
          {textEditor}
        </div>
      }
    />
  );

  return (
    <div className="relative flex min-h-0 flex-auto flex-col">
      <WidgetScreenHeader
        className="mb-20"
        start={
          <IconButton elementType={Link} to={state?.prevPath ?? '/'}>
            <KeyboardArrowLeftIcon />
          </IconButton>
        }
        label={<ScreenHeader conversation={conversation} />}
        end={<MoreActionsButton conversation={conversation} />}
      />
      {content}
    </div>
  );
}

interface MoreActionsButtonProps {
  conversation: FullWidgetConversationResponse['conversation'] | undefined;
}
function MoreActionsButton({conversation}: MoreActionsButtonProps) {
  const {base_url} = useSettings();
  const downloadTranscript = () => {
    if (conversation) {
      downloadFileFromUrl(
        `${base_url}/api/v1/lc/widget/chats/${conversation.id}/download-transcript`,
      );
    }
  };

  const [soundsDisabled, setSoundsDisabled] = useLocalStorage(
    'widget-chatSoundsDisabled',
    false,
  );

  return (
    <MenuTrigger>
      <IconButton>
        <MoreHorizIcon />
      </IconButton>
      <Menu>
        <Item
          value="closeConversation"
          startIcon={<CloseIcon />}
          onSelected={() => openDialog(CloseConversationDialog, {conversation})}
        >
          <Trans message="Close conversation" />
        </Item>
        {conversation && conversation.type !== 'ticket' && (
          <Item
            value="downloadTranscript"
            startIcon={<DownloadIcon />}
            onSelected={() => downloadTranscript()}
          >
            <Trans message="Download transcript" />
          </Item>
        )}
        <Item
          value="sounds"
          startIcon={soundsDisabled ? <VolumeOffIcon /> : <VolumeUpIcon />}
          onSelected={() => {
            setSoundsDisabled(!soundsDisabled);
          }}
        >
          <div className="flex items-center justify-between gap-4">
            <Trans message="Sounds" />
            <Switch checked={!soundsDisabled} readOnly />
          </div>
        </Item>
      </Menu>
    </MenuTrigger>
  );
}

interface ScreenHeaderProps {
  conversation?: FullWidgetConversationResponse['conversation'];
}
function ScreenHeader({conversation}: ScreenHeaderProps) {
  const logoSrc = useWidgetLogoSrc();
  const {branding} = useSettings();
  const {newChatGreeting, aiAgent} = useWidgetBootstrapData();

  let avatar = logoSrc ? (
    <Avatar src={logoSrc} circle={false} size="w-30 h-30" />
  ) : null;
  let label = branding.site_name;

  if (conversation?.assignee) {
    avatar = (
      <AgentAvatarWithIndicator
        showAwayIcon
        user={conversation.assignee}
        size="w-30 h-30"
      />
    );
    label = conversation.assignee.name;
  } else if (
    conversation?.assigned_to === 'bot' ||
    newChatGreeting?.parts[0]?.author === 'bot'
  ) {
    avatar = <AiAgentAvatar size="w-30 h-30" />;
    label = aiAgent?.name || 'AI assistant';
  }

  return (
    <div className="ml-auto mr-auto flex w-max items-center gap-10">
      {avatar}
      <div className="overflow-hidden text-ellipsis whitespace-nowrap">
        {label}
      </div>
    </div>
  );
}

function TicketStatusMessage({data}: TicketDetailsButtonProps) {
  const conversation = data.conversation;
  const icon =
    conversation.status_category > statusCategory.closed ? (
      <ScheduleIcon size="xs" />
    ) : (
      <CheckCircleIcon size="xs" />
    );
  return (
    <div className="mb-8 flex items-center gap-4 text-xs text-muted justify-center-safe">
      {icon}
      {conversation.status}
    </div>
  );
}

interface TicketDetailsButtonProps {
  data: FullWidgetConversationResponse;
}
function TicketDetailsButton({data}: TicketDetailsButtonProps) {
  return (
    <div className="flex items-center justify-center">
      <DialogTrigger
        type="modal"
        mobileType="modal"
        position="absolute"
        underlayTransparent={true}
        underlayBlurred={false}
      >
        <Button
          variant="outline"
          color="primary"
          size="sm"
          startIcon={<ConfirmationNumberIcon />}
          className="max-w-full"
          shadow="shadow dark:shadow-none"
          justify="justify-center-safe"
        >
          <span className="overflow-hidden overflow-ellipsis">
            {data.conversation.subject ? (
              data.conversation.subject
            ) : (
              <Trans message="Ticket details" />
            )}
          </span>
        </Button>
        <Dialog>
          <DialogHeader>
            <Trans message="Ticket details" />
          </DialogHeader>
          <DialogBody>
            <TicketDetails data={data} className="px-20 py-10" />
          </DialogBody>
        </Dialog>
      </DialogTrigger>
    </div>
  );
}

function WidgetChatScreenTextEditor({
  chatId,
}: {
  chatId: number | string | undefined;
}) {
  const {createChat, submitMessage, isPending} = useChatMessageSubmitter();
  const {chatWidget} = useSettings();
  const {settingsEditorParams} = useSettingsPreviewMode();
  const preChatForm = chatWidget?.forms?.preChat;

  if (settingsEditorParams.form === 'preChat') return null;

  // hide editor if pre-chat form is visible
  if (!chatId && !preChatForm?.disabled && !!preChatForm?.attributes.length) {
    return null;
  }

  return (
    <WidgetChatTextEditor
      isPending={isPending}
      onSubmit={data => {
        if (chatId) {
          submitMessage({message: data, conversationId: chatId});
        } else {
          createChat({message: data, startWithGreeting: true});
        }
      }}
    />
  );
}

type CloseConversationDialogProps = {
  conversation: FullConversationResponse['conversation'];
};
function CloseConversationDialog({conversation}: CloseConversationDialogProps) {
  const {close} = useDialogContext();
  const navigate = useNavigate();
  const markAsSolved = useMutation({
    mutationFn: () =>
      apiClient.post(
        `helpdesk/customer/conversations/${conversation.id}/mark-as-solved`,
      ),
    onSuccess: () => {
      toast(message('Conversation closed'));
      queryClient.invalidateQueries({
        queryKey: widgetQueries.conversations.invalidateKey,
      });
      if (widgetStore().activeConversationId === conversation.id) {
        widgetStore().setActiveConversationId(null);
      }
      close();
      navigate(`/`);
    },
    onError: err => showHttpErrorToast(err),
  });
  return (
    <ConfirmationDialog
      title={
        conversation?.type === 'ticket' ? (
          <Trans message="Close ticket" />
        ) : (
          <Trans message="Close chat" />
        )
      }
      body={
        <Trans message="Are you sure you want to close this conversation?" />
      }
      confirm={<Trans message="Close" />}
      isLoading={markAsSolved.isPending}
      onConfirm={() => {
        markAsSolved.mutate();
      }}
    />
  );
}
