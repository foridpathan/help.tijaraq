import {MessageAuthorName} from '@app/dashboard/conversations/conversation-page/messages/message-author-name';
import {MessageAvatar} from '@app/dashboard/conversations/conversation-page/messages/message-avatar';
import {UnseenMessagesBadge} from '@app/dashboard/websockets/unseen-messages-badge';
import {BulletSeparatedItems} from '@common/ui/other/bullet-seprated-items';
import {useWidgetChatMessages} from '@livechat/widget/conversation-screen/requests/use-widget-chat-messages';
import {HomeScreenCardLayout} from '@livechat/widget/home/home-screen-card-layout';
import {widgetQueries} from '@livechat/widget/widget-queries';
import {useQuery} from '@tanstack/react-query';
import {FormattedRelativeTime} from '@ui/i18n/formatted-relative-time';
import {Trans} from '@ui/i18n/trans';
import {getCurrentDateTime} from '@ui/i18n/use-current-date-time';
import {useTrans} from '@ui/i18n/use-trans';
import {KeyboardArrowRightIcon} from '@ui/icons/material/KeyboardArrowRight';
import {useSettings} from '@ui/settings/use-settings';
import {stripTags} from '@ui/utils/string/strip-tags';
import memoized from 'nano-memoize';
import {Link} from 'react-router';

const memoStripTags = memoized(stripTags);

interface Props {
  chatId: number;
}
export function ResumeChatCard({chatId}: Props) {
  const chatQuery = useQuery(widgetQueries.conversations.get(chatId));
  const messagesQuery = useWidgetChatMessages(chatId);
  const {chatWidget} = useSettings();
  const {trans} = useTrans();

  if (!chatQuery.data?.conversation || !chatQuery.data?.items) {
    return null;
  }

  const {conversation} = chatQuery.data;

  const lastMsg = messagesQuery.data?.items.at(-1);
  if (!lastMsg) return null;

  const lastMsgText =
    lastMsg?.type === 'message'
      ? memoStripTags(lastMsg.body)
      : trans({message: chatWidget?.defaultMessage ?? ''});
  const lastMsgDate =
    lastMsg?.created_at ?? getCurrentDateTime().toAbsoluteString();

  return (
    <HomeScreenCardLayout>
      <div className="bg-elevated px-20 py-16 transition-button hover:bg-hover">
        <Link to={`/conversations/${conversation.id}`} className="block">
          <div className="mb-8 flex items-center justify-between gap-8">
            <div className="font-semibold">
              <Trans message="Recent message" />
            </div>
            <UnseenMessagesBadge
              conversationId={conversation.id}
              className="ml-auto"
            />
          </div>
          <div className="flex items-center gap-8">
            <MessageAvatar message={lastMsg} size="lg" agentWithIndicator />
            <div className="min-w-0 flex-auto overflow-hidden">
              <BulletSeparatedItems className="text-xs text-muted">
                <MessageAuthorName message={lastMsg} />
                <div>
                  <FormattedRelativeTime date={lastMsgDate} style="narrow" />
                </div>
              </BulletSeparatedItems>
              <div
                className="line-clamp-2 text-sm"
                dangerouslySetInnerHTML={{__html: lastMsgText}}
              />
            </div>
            <KeyboardArrowRightIcon className="text-primary" size="sm" />
          </div>
        </Link>
      </div>
    </HomeScreenCardLayout>
  );
}
