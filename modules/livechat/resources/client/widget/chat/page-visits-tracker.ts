import {PageVisit} from '@app/dashboard/conversations/conversation-page/details-sidebar/page-visists-panel';
import {maybeShowCampaigns} from '@livechat/widget/campaigns/conditions/maybe-show-campaigns';
import {getWidgetSessionId} from '@livechat/widget/hooks/get-widget-session-id';
import {WidgetFlags} from '@livechat/widget/widget-flags';
import {getBootstrapData} from '@ui/bootstrap-data/bootstrap-data-store';
import {getCurrentDateTime} from '@ui/i18n/use-current-date-time';

interface NavigationMessageData {
  source: 'livechat-loader';
  type: 'navigate';
  title: string;
  url: string;
  referer: string;
  time: number;
  initialLoadTime: number;
}

let currentVisit: PageVisit | null = null;
let pendingVisitTimeout: ReturnType<typeof setTimeout> | null = null;

const allVisitsThisSession: {url: string; time: number}[] = [];

export function trackUserPageVisits() {
  window.addEventListener(
    'message',
    async (e: MessageEvent<NavigationMessageData>) => {
      if (e.data.source === 'livechat-loader' && e.data.type === 'navigate') {
        allVisitsThisSession.push({
          url: e.data.url,
          time: e.data.time,
        });

        if (pendingVisitTimeout) {
          clearTimeout(pendingVisitTimeout);
        }

        if (currentVisit) {
          changeVisitStatus(currentVisit, 'ended');
          currentVisit = null;
        }

        // don't create a visit if user was on page for less than 5 sec,
        // to avoid creating visits for accidental clicks
        pendingVisitTimeout = setTimeout(async () => {
          currentVisit = await createNewVisit(e.data);
        }, 5000);

        maybeShowCampaigns({
          currentUrl: e.data.url,
          currentUrlLoadedAt: e.data.time,
          sessionStartedAt: e.data.initialLoadTime,
          sessionVisits: allVisitsThisSession,
        });
      }
    },
  );

  document.addEventListener('visibilitychange', () => {
    if (!currentVisit) return;
    if (document.visibilityState === 'hidden') {
      changeVisitStatus(currentVisit, 'ended');
    } else {
      changeVisitStatus(currentVisit, 'active');
    }
  });
}

async function createNewVisit({
  url,
  title,
  referer,
}: NavigationMessageData): Promise<PageVisit> {
  const response = await fetch(
    `${getBootstrapData().settings.base_url}/api/v1/lc/widget/visits`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getBootstrapData().csrf_token,
        [WidgetFlags.keys.headers.widget]: 'true',
      },
      body: JSON.stringify({
        url,
        title,
        referer,
        session_id: getWidgetSessionId(),
        started_at: getCurrentDateTime().toAbsoluteString(),
      }),
    },
  );
  return (await response.json()).visit;
}

function changeVisitStatus(visit: PageVisit, status: 'active' | 'ended') {
  const {base_url} = getBootstrapData().settings;
  if (navigator.sendBeacon) {
    navigator.sendBeacon(
      `${base_url}/api/v1/lc/widget/visits/${visit.id}/change-status?_token=${
        getBootstrapData().csrf_token
      }&_xChatWidget=true`,
      JSON.stringify({status}),
    );
  }
}
