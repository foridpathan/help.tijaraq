import {useNavigate} from '@common/ui/navigation/use-navigate';
import {Campaign} from '@livechat/dashboard/campaigns/campaign';
import {
  CampaignActionHandlerCallback,
  CampaignActionName,
  CampaignContentItem,
} from '@livechat/dashboard/campaigns/campaign-editor/content-items/campaign-content-item';
import {useCreateWidgetChat} from '@livechat/widget/conversation-screen/requests/use-create-widget-chat';
import {
  logCampaignImpression,
  widgetStore,
} from '@livechat/widget/widget-store';
import {useSettings} from '@ui/settings/use-settings';
import {toast} from '@ui/toast/toast';
import {nanoid} from 'nanoid';
import {useCallback} from 'react';

export function useWidgetCampaignActionHandler() {
  const navigate = useNavigate();
  const {sendMessageHandler, isPending} = useSendMessageHandler();
  const handler: CampaignActionHandlerCallback = useCallback(
    (
      campaign: Campaign,
      item: CampaignContentItem,
      action: CampaignActionName,
      actionValue: string | undefined,
    ) => {
      logCampaignImpression(campaign, true);

      if (action === 'dismiss') {
        widgetStore().hideCampaign();
      }

      if (!actionValue) return;

      if (action === 'openUrl') {
        window.open(actionValue, '_blank');
      } else if (action === 'copyToClipboard') {
        navigator.clipboard.writeText(actionValue);
        toast('Copied to clipboard!');
      } else if (action === 'openEmbed') {
        navigate('/embed', {state: {embedUrl: actionValue}});
        widgetStore().setWidgetState('open');
      } else if (action === 'sendMessage') {
        sendMessageHandler(actionValue);
      }
    },
    [sendMessageHandler, navigate],
  );

  return {
    handleCampaignAction: handler,
    isPending,
  };
}

function useSendMessageHandler() {
  const settings = useSettings();
  const preChatFormEnabled =
    settings.chatWidget?.forms?.preChat &&
    !settings.chatWidget.forms.preChat.disabled;
  const navigate = useNavigate();
  const createChat = useCreateWidgetChat();

  const handler = useCallback(
    (actionValue: string) => {
      if (!preChatFormEnabled) {
        createChat.mutate(
          {
            message: {
              uuid: nanoid(),
              body: actionValue,
              attachments: [],
            },
          },
          {
            onSuccess: () => {
              widgetStore().setWidgetState('open');
            },
          },
        );
      } else {
        navigate(`/conversations/new`, {
          replace: true,
          state: {messageBody: actionValue},
        });
        widgetStore().setWidgetState('open');
      }
    },
    [createChat, navigate, preChatFormEnabled],
  );

  return {
    sendMessageHandler: handler,
    isPending: createChat.isPending,
  };
}
