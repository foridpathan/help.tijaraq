import {AdminSettings} from '@common/admin/settings/admin-settings';
import {SettingsWithPreview} from '@common/admin/settings/layout/settings-with-preview';
import {useAdminSettings} from '@common/admin/settings/requests/use-admin-settings';
import {
  HomeScreenBackgroundSettings,
  HomeScreenLinksSettings,
  HomeScreenMessagesSettings,
} from '@livechat/admin/settings/home-screen-settings';
import {WidgetThemeEditor} from '@livechat/admin/settings/widget-style-settings';
import {Accordion, AccordionItem} from '@ui/accordion/accordion';
import {Trans} from '@ui/i18n/trans';
import {useForm} from 'react-hook-form';
import {chatSettingsTabs, useChatSettingsNav} from './use-chat-settings-nav';

const chatWidgetCategories = chatSettingsTabs[0].categories;

export function ChatWidgetSettings() {
  const {data} = useAdminSettings();
  const widget = data.client.chatWidget ?? {};
  const form = useForm<AdminSettings>({
    defaultValues: {
      themes: data.themes.filter(t => t.type === 'chatWidget'),
      client: {
        chatWidget: {
          logo_light: widget.logo_light ?? '',
          logo_dark: widget.logo_dark ?? '',
          showAvatars: widget.showAvatars ?? true,
          background: widget.background ?? {},
          fadeBg: widget.fadeBg ?? true,
          showHcCard: widget.showHcCard ?? true,
          hideHomeArticles: widget.hideHomeArticles ?? false,
          greeting: widget.greeting ?? '',
          greetingAnonymous: widget.greetingAnonymous ?? '',
          introduction: widget.introduction ?? '',
          homeNewChatTitle: widget.homeNewChatTitle ?? '',
          homeNewChatSubtitle: widget.homeNewChatSubtitle ?? '',
          homeShowTickets: widget.homeShowTickets ?? false,
          homeNewTicketTitle: widget.homeNewTicketTitle ?? '',
          homeNewTicketSubtitle: widget.homeNewTicketSubtitle ?? '',
          homeLinks: widget.homeLinks ?? [],
          launcherIcon: widget.launcherIcon ?? '',
          position: widget.position ?? 'right',
          spacing: {
            side: widget.spacing?.side ?? '16',
            bottom: widget.spacing?.bottom ?? '16',
          },
          hide: widget.hide ?? false,
          defaultTheme: widget.defaultTheme ?? 'light',
          inheritThemes: widget.inheritThemes ?? false,
          defaultScreen: widget.defaultScreen ?? '/',
          hideNavigation: widget.hideNavigation ?? false,
          screens: widget.screens ?? [],
          forms: widget.forms ?? {
            preChat: {disabled: false, attributes: []},
            postChat: {disabled: false, attributes: []},
          },

          // chat screen
          defaultMessage: widget.defaultMessage ?? '',
          inputPlaceholder: widget.inputPlaceholder ?? '',
          agentsAwayMessage: widget.agentsAwayMessage ?? '',
          inQueueMessage: widget.inQueueMessage ?? '',
        },
        chatPage: {
          title: data.client.chatPage?.title ?? '',
          subtitle: data.client.chatPage?.subtitle ?? '',
        },
      },
    },
  });

  return (
    <SettingsWithPreview.Form form={form} mergePreviewSettings={false}>
      <Content />
    </SettingsWithPreview.Form>
  );
}

export function Content() {
  const {activeSectionName, activeCategoryName, setActiveCategory} =
    useChatSettingsNav();

  if (activeSectionName === 'background') {
    return <HomeScreenBackgroundSettings />;
  }

  if (activeSectionName === 'messages') {
    return <HomeScreenMessagesSettings />;
  }

  if (activeSectionName === 'links') {
    return <HomeScreenLinksSettings />;
  }

  if (activeSectionName === 'themesEditor') {
    return <WidgetThemeEditor />;
  }

  return (
    <Accordion
      expandedValues={activeCategoryName ? [activeCategoryName] : []}
      onExpandedChange={([name]) => {
        setActiveCategory(name as string);
      }}
      size="lg"
      variant="outline"
    >
      {chatWidgetCategories.map(category => {
        const Component = category.component;
        return (
          <AccordionItem
            key={category.name}
            label={<Trans {...category.label} />}
            value={category.name}
          >
            <Component />
          </AccordionItem>
        );
      })}
    </Accordion>
  );
}
