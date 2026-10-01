import {dashboardIcons} from '@app/dashboard/dashboard-icons';
import {SettingsNavItem} from '@common/admin/settings/settings-nav-config';
import {message} from '@ui/i18n/message';
import {FileClockIcon} from '@ui/icons/lucide/file-clock';
import {AltRouteIcon} from '@ui/icons/material/AltRoute';
import {ChromeReaderModeIcon} from '@ui/icons/material/ChromeReaderMode';
import {FileCopyIcon} from '@ui/icons/material/FileCopy';
import {ManageAccountsIcon} from '@ui/icons/material/ManageAccounts';
import {SellIcon} from '@ui/icons/material/Sell';
import {SettingsIcon} from '@ui/icons/material/Settings';
import {TextFieldsIcon} from '@ui/icons/material/TextFields';
import {TranslateIcon} from '@ui/icons/material/Translate';

// icons
export const AdminSidebarIcons = {
  '/admin/settings': SettingsIcon,
  '/admin/roles': ManageAccountsIcon,
  '/admin/custom-pages': ChromeReaderModeIcon,
  '/admin/tags': SellIcon,
  '/admin/files': FileCopyIcon,
  '/admin/localizations': TranslateIcon,
  '/admin/logs': FileClockIcon,
  '/admin/attributes': TextFieldsIcon,
  '/admin/triggers': AltRouteIcon,
  '/admin/ai-agents': dashboardIcons.aiAgent,
  '/admin/campaigns': dashboardIcons.campaigns,
  '/admin/team': dashboardIcons.team,
  '/admin/views': dashboardIcons.views,
  '/admin/statuses': dashboardIcons.status,
  '/admin/reports/tickets': dashboardIcons.reports,
  '/admin/hc/arrange': dashboardIcons.library,
  '/admin/customers': dashboardIcons.users,
  '/admin/saved-replies': dashboardIcons.saveReplies,
};

// settings nav config
export const AppSettingsNavConfig: SettingsNavItem[] = [
  {label: message('Tickets'), to: 'tickets', position: 2},
  {label: message('Livechat'), to: 'livechat', position: 3},
  {label: message('AI & Agents'), to: 'ai', position: 4},
  {label: message('Help center'), to: 'hc', position: 5},
  {label: message('Envato'), to: 'envato', position: 6},
  {label: message('Search'), to: 'search', position: 7},
];

// docs urls
const base = 'https://support.vebto.com/hc/articles';
export const AdminDocsUrls = {
  manualUpdate: `${base}/42/43/283`,
  settings: {
    general: `${base}/42/46/269`,
    search: `${base}/42/46/159`,
    tickets: `${base}/42/46/271`,
    liveChat: `${base}/42/71/241`,
    ai: `${base}/42/68/227`,
    themes: `${base}/42/46/270`,
    helpCenter: `${base}/42/73/243`,
    menus: `${base}/42/46/272`,
    envato: `${base}/42/78/273`,
    localization: `${base}/42/46/247`,
    authentication: `${base}/42/46/274`,
    uploading: `${base}/42/74/291`,
    incomingEmail: `${base}/42/72/155`,
    outgoingEmail: `${base}/42/72/245`,
    cache: `${base}/42/46/256/system-settings#cache-provider`,
    queue: `${base}/42/46/256/system-settings#queue-method`,
    websockets: `${base}/42/75/255`,
    logging: `${base}/42/46/256/system-settings#logging`,
    googleAnalytics: `${base}/42/78/277`,
    customCode: `${base}/42/46/278`,
    captcha: `${base}/42/78/276`,
    gdpr: `${base}/42/46/279`,
    seo: `${base}/42/46/280`,
    s3: `${base}/42/74/216`,
    backblaze: `${base}/42/74/217`,
    purchaseCode: `${base}/42/46/293`,
  },
  pages: {
    triggers: `${base}/42/68/153`,
    views: `${base}/42/69/228`,
    statuses: `${base}/42/69/229`,
    attributes: `${base}/42/69/230`,
    helpCenter: `${base}/42/73/242`,
    team: `${base}/42/70/232`,
    groups: `${base}/42/70/234`,
    agentInvites: `${base}/42/70/235`,
    customers: `${base}/42/70/233`,
    roles: `${base}/42/46/258`,
    savedReplies: `${base}/42/69/231`,
    translations: `${base}/42/46/247`,
    files: `${base}/42/74/266`,
    customPages: `${base}/42/46/267`,
    logs: `${base}/42/76/268`,
    aiAgentSettings: `${base}/42/68/286`,
    aiAgentKnowledge: `${base}/42/68/287`,
    flows: `${base}/42/68/288`,
    tools: `${base}/42/68/289`,
  },
} as any;
