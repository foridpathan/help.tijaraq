import {AnimatedChatMessage} from '@app/dashboard/conversations/conversation-page/messages/animated-chat-message';
import {ConversationContentItem} from '@app/dashboard/conversations/conversation-page/messages/conversation-message';
import {useEventText} from '@app/dashboard/conversations/conversation-page/messages/events/use-event-text';
import {DashboardFeedMessage} from '@app/dashboard/conversations/conversation-page/messages/feed/dashboard-feed-message';
import {FeedBubble} from '@app/dashboard/conversations/conversation-page/messages/feed/feed-bubble';
import {FeedMessageLayout} from '@app/dashboard/conversations/conversation-page/messages/feed/feed-message-layout';
import {MessageAvatar} from '@app/dashboard/conversations/conversation-page/messages/message-avatar';
import {MessageDate} from '@app/dashboard/conversations/conversation-page/messages/message-date';
import {SubmittedFormData} from '@app/dashboard/conversations/conversation-page/messages/submitted-form-data';
import {FormattedDate} from '@ui/i18n/formatted-date';
import {Trans} from '@ui/i18n/trans';

interface Props {
  message: ConversationContentItem;
  isLastInGroup?: boolean;
}
export function FeedContentItem({message, isLastInGroup}: Props) {
  switch (message.type) {
    case 'event':
    case 'collectDetailsForm':
    case 'cards':
      return <EventItem message={message} />;
    case 'submittedFormData':
      return (
        <AnimatedChatMessage uuid={message.uuid}>
          <FeedMessageLayout
            align="left"
            maxWidth="max-w-[min(86%,548px)]"
            avatar={<MessageAvatar message={message} />}
            footer={<MessageDate date={message.created_at} />}
            className="mb-12"
          >
            <FeedBubble color="chip" allowBreak>
              <SubmittedFormData message={message} />
            </FeedBubble>
          </FeedMessageLayout>
        </AnimatedChatMessage>
      );
    default:
      return (
        <AnimatedChatMessage uuid={message.uuid}>
          <DashboardFeedMessage
            message={message}
            isLastInGroup={isLastInGroup}
          />
        </AnimatedChatMessage>
      );
  }
}

interface EventItemProps {
  message: ConversationContentItem;
}
function EventItem({message}: EventItemProps) {
  const eventText = useEventText({message, variant: 'agent'});

  if (eventText === null) return null;

  return (
    <AnimatedChatMessage
      uuid={message.uuid}
      className="my-24 mr-40 flex justify-end gap-3 text-xs text-muted"
    >
      <Trans {...eventText} /> -
      <time>
        <FormattedDate date={message.created_at} preset="time" />
      </time>
    </AnimatedChatMessage>
  );
}
