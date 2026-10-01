import {useCompactAgents} from '@app/dashboard/agents/use-compact-agents';
import {cssPropsFromBgConfig} from '@common/background-selector/css-props-from-bg-config';
import {CustomMenuItem} from '@common/menus/custom-menu';
import {HomeScreenCardLayout} from '@livechat/widget/home/home-screen-card-layout';
import {HomeScreenHcHard} from '@livechat/widget/home/home-screen-hc-hard';
import {ResumeChatCard} from '@livechat/widget/home/resume-chat-card';
import {useWidgetLogoSrc} from '@livechat/widget/hooks/use-widget-logo-src';
import {MobileCloseButton} from '@livechat/widget/mobile-close-button';
import {useWidgetCustomer} from '@livechat/widget/user/use-widget-customer';
import {useWidgetStore} from '@livechat/widget/widget-store';
import {Avatar} from '@ui/avatar/avatar';
import {AvatarGroup} from '@ui/avatar/avatar-group';
import {Trans} from '@ui/i18n/trans';
import {TicketPlusIcon} from '@ui/icons/lucide/ticket-plus-icon';
import {OpenInNewIcon} from '@ui/icons/material/OpenInNew';
import {SendIcon} from '@ui/icons/material/Send';
import {useSettings} from '@ui/settings/use-settings';
import {useIsDarkMode} from '@ui/themes/use-is-dark-mode';
import clsx from 'clsx';
import {useMemo} from 'react';
import {Link} from 'react-router';

export function HomeScreen() {
  const isMobile = useWidgetStore(s => s.isMobile);
  const {chatWidget} = useSettings();
  const activeChatId = useWidgetStore(s => s.activeConversationId);
  return (
    <div
      className={clsx(
        'compact-scrollbar h-full min-h-0 overflow-y-auto overflow-x-hidden',
        !isMobile && 'rounded-t-panel',
      )}
    >
      <div className="relative isolate">
        <Background />
        <div className="relative z-20">
          <Greeting />
          <div className="space-y-16 px-20 pb-20">
            {activeChatId ? (
              <ResumeChatCard chatId={activeChatId} />
            ) : (
              <NewChatCard />
            )}
            {chatWidget?.homeShowTickets && <NewTicketCard />}
            <CustomLinksList />
            {chatWidget?.showHcCard && <HomeScreenHcHard />}
          </div>
        </div>
      </div>
    </div>
  );
}

function Background() {
  const {chatWidget} = useSettings();
  const isDarkMode = useIsDarkMode();
  const cssProps = useMemo(() => {
    return cssPropsFromBgConfig(chatWidget?.background);
  }, [chatWidget]);

  if (isDarkMode) {
    return null;
  }

  return (
    <div className="absolute left-0 right-0 top-0 z-10 h-[320px]">
      <div className="absolute h-full w-full" style={cssProps} />
      {chatWidget?.fadeBg && (
        <div className="widget-header-fade-gradient absolute h-full w-full" />
      )}
    </div>
  );
}

function TopBar() {
  const isMobile = useWidgetStore(s => s.isMobile);
  const {agents} = useCompactAgents();
  const {chatWidget} = useSettings();
  const logoSrc = useWidgetLogoSrc();
  return (
    <div className="mb-100 flex items-center gap-12">
      {logoSrc && (
        <img
          className="mr-auto max-h-36 max-w-full object-cover"
          src={logoSrc}
          alt="logo"
        />
      )}
      {chatWidget?.showAvatars && (
        <AvatarGroup showMore={false} className="ml-auto">
          {agents.slice(0, 4).map(agent => (
            <Avatar
              key={agent.id}
              src={agent.image}
              label={agent.name}
              fallback="initials"
            />
          ))}
        </AvatarGroup>
      )}
      <MobileCloseButton />
    </div>
  );
}

function Greeting() {
  const {chatWidget} = useSettings();
  const customer = useWidgetCustomer();
  const greeting =
    customer?.name && chatWidget?.greeting
      ? chatWidget.greeting
      : chatWidget?.greetingAnonymous;

  return (
    <div className="px-40 py-30">
      <TopBar />
      <div
        className="leanding break-words text-[32px] font-bold leading-10"
        style={{
          color: !chatWidget?.fadeBg
            ? chatWidget?.background?.color
            : undefined,
        }}
      >
        {greeting && (
          <h1>
            <Trans
              message={greeting}
              values={{
                ...customer?.attributes,
                name: customer?.name,
                email: customer?.email,
              }}
            />
          </h1>
        )}
        {chatWidget?.introduction && (
          <h2>
            <Trans message={chatWidget.introduction} />
          </h2>
        )}
      </div>
    </div>
  );
}

function NewChatCard() {
  const {chatWidget} = useSettings();
  return (
    <HomeScreenCardLayout>
      <div className="bg-elevated px-20 py-16 transition-button hover:bg-hover">
        <Link
          to="/conversations/new"
          className="flex items-center justify-between gap-8"
        >
          <div>
            {chatWidget?.homeNewChatTitle && (
              <div className="font-semibold">
                <Trans message={chatWidget.homeNewChatTitle} />
              </div>
            )}
            {chatWidget?.homeNewChatSubtitle && (
              <div>
                <Trans message={chatWidget.homeNewChatSubtitle} />
              </div>
            )}
          </div>
          <SendIcon className="text-primary" />
        </Link>
      </div>
    </HomeScreenCardLayout>
  );
}

function NewTicketCard() {
  const {chatWidget} = useSettings();
  return (
    <HomeScreenCardLayout>
      <div className="px-20 py-16 transition-button hover:bg-hover">
        <Link
          to="/tickets/new"
          className="flex items-center justify-between gap-8"
        >
          <div>
            {chatWidget?.homeNewTicketTitle && (
              <div className="font-semibold">
                <Trans message={chatWidget.homeNewTicketTitle} />
              </div>
            )}
            {chatWidget?.homeNewTicketSubtitle && (
              <div>
                <Trans message={chatWidget.homeNewTicketSubtitle} />
              </div>
            )}
          </div>
          <TicketPlusIcon className="text-primary" size="sm" />
        </Link>
      </div>
    </HomeScreenCardLayout>
  );
}

function CustomLinksList() {
  const {chatWidget} = useSettings();
  return (
    <div className="space-y-16 text-sm">
      {chatWidget?.homeLinks?.map(link => (
        <HomeScreenCardLayout
          key={link.id}
          className="flex cursor-pointer items-center justify-between gap-8 px-20 py-16 hover:bg-hover"
        >
          <CustomMenuItem item={link} />
          <OpenInNewIcon className="text-muted" size="sm" />
        </HomeScreenCardLayout>
      ))}
    </div>
  );
}
