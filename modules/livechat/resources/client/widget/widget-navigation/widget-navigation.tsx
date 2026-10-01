import {useUnseenConversationsStore} from '@app/dashboard/websockets/websocket-updates-notifier';
import {ChatFilledIcon} from '@livechat/widget/widget-navigation/chat-filled-icon';
import {HomeFilledIcon} from '@livechat/widget/widget-navigation/home-filled-icon';
import {Badge} from '@ui/badge/badge';
import {ButtonBase} from '@ui/buttons/button-base';
import {message} from '@ui/i18n/message';
import {Trans} from '@ui/i18n/trans';
import {ChatIcon} from '@ui/icons/material/Chat';
import {HelpIcon} from '@ui/icons/material/Help';
import {HelpOutlineIcon} from '@ui/icons/material/HelpOutline';
import {HomeIcon} from '@ui/icons/material/Home';
import {useSettings} from '@ui/settings/use-settings';
import clsx from 'clsx';
import {ReactNode, useMemo} from 'react';
import {Link, useLocation} from 'react-router';

export const widgetNavigationTabs: {
  route: string;
  label: ReturnType<typeof message>;
  icon: ReactNode;
  activeIcon: ReactNode;
}[] = [
  {
    route: '/',
    label: message('Home'),
    icon: <HomeIcon />,
    activeIcon: <HomeFilledIcon />,
  },
  {
    route: 'conversations',
    label: message('Conversations'),
    icon: <ChatIcon />,
    activeIcon: <ChatFilledIcon />,
  },
  {
    route: 'hc',
    label: message('Help'),
    icon: <HelpOutlineIcon />,
    activeIcon: <HelpIcon />,
  },
];

export function WidgetNavigation() {
  const {chatWidget} = useSettings();
  const {pathname} = useLocation();

  const tabs = useMemo(() => {
    const enabledTabs = chatWidget?.screens ?? [];
    const sortFn = (x: string) =>
      enabledTabs.includes(x) ? enabledTabs.indexOf(x) : enabledTabs.length;
    return [...widgetNavigationTabs]
      .sort((a, b) => sortFn(a.route) - sortFn(b.route))
      .filter(tab => enabledTabs.includes(tab.route));
  }, [chatWidget?.screens]);

  return (
    <div className="flex flex-shrink-0 border-t shadow-[rgba(0,0,0,0.05)_0px_0px_25px]">
      {tabs.map(tab => {
        const isActive =
          tab.route === '/' && pathname === '/'
            ? true
            : pathname.startsWith(`/${tab.route}`);
        return (
          <ButtonLayout
            key={tab.route || 'home'}
            route={tab.route}
            icon={isActive ? tab.activeIcon : tab.icon}
            isActive={isActive}
          >
            <Trans {...tab.label} />
          </ButtonLayout>
        );
      })}
    </div>
  );
}

interface ButtonLayoutProps {
  icon: ReactNode;
  route: string;
  children: ReactNode;
  isActive: boolean;
}
function ButtonLayout({children, route, icon, isActive}: ButtonLayoutProps) {
  return (
    <ButtonBase
      elementType={Link}
      to={route}
      replace
      display="flex"
      className={clsx(
        'relative min-w-0 flex-1 flex-col items-center gap-6 overflow-hidden bg-elevated py-18 transition-all hover:bg-hover',
        isActive ? 'font-semibold text-primary' : 'text-muted',
      )}
    >
      {icon}
      <div className="overflow-hidden whitespace-nowrap text-sm leading-normal">
        {children}
      </div>
      {route === 'chats' && <ChatsMenuItemBadge />}
    </ButtonBase>
  );
}

function ChatsMenuItemBadge() {
  const unseenChats = useUnseenConversationsStore(s => s.unseenConversations);

  if (!unseenChats.length) return null;

  return <Badge top="top-12" right="right-44" color="bg-danger" />;
}
