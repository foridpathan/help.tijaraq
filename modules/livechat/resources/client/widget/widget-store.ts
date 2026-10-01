import {Campaign} from '@livechat/dashboard/campaigns/campaign';
import {getWidgetSessionId} from '@livechat/widget/hooks/get-widget-session-id';
import {getWidgetBootstrapData} from '@livechat/widget/hooks/use-widget-bootstrap-data';
import {getBootstrapData} from '@ui/bootstrap-data/bootstrap-data-store';
import {create} from 'zustand';

const widgetWidth = 400;
const spaceForShadow = 16;

export const widgetSizes = {
  open: {width: `${widgetWidth + spaceForShadow * 2}px`, height: '800px'},
  closed: {width: '94px', height: '94px'},
  articleMaximized: {width: '700px', height: '100%'},
};

interface WidgetState {
  isMobile: boolean;
  setIsMobile: (isMobile: boolean) => void;
  isAiAgentPreviewMode: boolean;
  setIsAiAgentPreviewMode: (isAiAgentPreviewMode: boolean) => void;
  activeConversationId: number | null;
  setActiveConversationId: (conversationId: number | null) => void;
  widgetState: 'open' | 'closed' | 'articleMaximized';
  widgetClosedAt: number | null;
  interactedWithWidget: boolean;
  setWidgetState: (
    state: WidgetState['widgetState'],
    isInitial?: boolean,
  ) => void;
  showCampaign: (campaign: Campaign) => void;
  hideCampaign: () => void;
  activeCampaign: Campaign | null;
}

let closeTimeout: ReturnType<typeof setTimeout> | null = null;

export const useWidgetStore = create<WidgetState>()((set, get) => ({
  isMobile: getWidgetBootstrapData().isMobile ?? false,
  setIsMobile: isMobile => set({isMobile}),
  isAiAgentPreviewMode: false,
  setIsAiAgentPreviewMode: isAiAgentPreviewMode =>
    set({isAiAgentPreviewMode: isAiAgentPreviewMode}),
  activeConversationId: null,
  setActiveConversationId: chatId => set({activeConversationId: chatId}),
  widgetState: 'closed',
  activeCampaign: null,
  widgetClosedAt: null,
  interactedWithWidget: false,
  setWidgetState: (state, isInitial) => {
    const isClosing = state === 'closed';
    const prevState = get().widgetState;
    if (prevState === state && !isInitial) return;

    set({activeCampaign: null, interactedWithWidget: !isInitial});

    // when closing, first set size state to initiate close animation,
    // wait for animation to complete and then resize iframe
    if (isClosing) {
      if (closeTimeout) {
        clearTimeout(closeTimeout);
      }
      set({
        widgetState: state,
        widgetClosedAt: !isInitial ? performance.now() : null,
      });

      const notifyOfSizeChange = () => {
        notifyIframeOfSizeChange({
          width: widgetSizes[state].width,
          height: widgetSizes[state].height,
          widgetState: state,
        });
      };

      if (isInitial) {
        notifyOfSizeChange();
      } else {
        closeTimeout = setTimeout(() => notifyOfSizeChange(), 300);
      }

      // when opening, resize iframe first, wait a tick and then set size state
    } else {
      if (closeTimeout) clearTimeout(closeTimeout);
      const shouldTransition =
        prevState === 'articleMaximized' || state === 'articleMaximized';
      notifyIframeOfSizeChange(
        {
          width: widgetSizes[state].width,
          height: widgetSizes[state].height,
          widgetState: state,
        },
        shouldTransition,
      );
      setTimeout(() => {
        set({widgetState: state, activeCampaign: null, widgetClosedAt: null});
      });
    }
  },
  showCampaign: campaign => {
    if (get().activeCampaign) return;
    set({activeCampaign: campaign});
    notifyIframeOfSizeChange({
      // width + 16px padding on each side
      width: `${campaign.width + 32}px`,
      // height + 80px toggle height + 16px padding + 28px close button
      height: `${campaign.height + 80 + 16 + 28}px`,
      widgetState: get().widgetState,
    });
    logCampaignImpression(campaign, false);
  },
  hideCampaign: () => {
    set({activeCampaign: null});
    notifyIframeOfSizeChange({
      width: widgetSizes[get().widgetState].width,
      height: widgetSizes[get().widgetState].height,
      widgetState: get().widgetState,
    });
  },
}));

export const widgetStore = (): WidgetState => {
  return useWidgetStore.getState();
};

function notifyIframeOfSizeChange(
  data: {
    width: string;
    height: string;
    widgetState: WidgetState['widgetState'];
  },
  shouldTransition?: boolean,
) {
  window.parent.postMessage(
    {
      source: 'livechat-widget',
      type: 'resize',
      width: data.width,
      height: data.height,
      widgetState: data.widgetState,
      shouldTransition,
    },
    '*',
  );
}

export function logCampaignImpression(
  campaign: Campaign,
  isInteraction: boolean,
) {
  const {base_url} = getBootstrapData().settings;
  if (navigator.sendBeacon) {
    navigator.sendBeacon(
      `${base_url}/api/v1/lc/widget/campaigns/${campaign.id}/imp?_token=${
        getBootstrapData().csrf_token
      }`,
      JSON.stringify({
        isInteraction,
        sessionId: getWidgetSessionId(),
      }),
    );
  }
}
