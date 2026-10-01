import {ConversationListItemType as ListItem} from '@app/dashboard/conversation';
import {CustomerAvatar} from '@app/dashboard/conversations/avatars/customer-avatar';
import {CustomerName} from '@app/dashboard/conversations/customer-name';
import {useConversationLink} from '@app/dashboard/conversations/utils/use-navigate-to-conversation-page';
import {FormattedRelativeTime} from '@ui/i18n/formatted-relative-time';
import {ConfirmationNumberIcon} from '@ui/icons/material/ConfirmationNumber';
import clsx from 'clsx';
import {ReactNode} from 'react';
import {Link} from 'react-router';

interface Props {
  conversation: ListItem;
  isActive?: boolean;
  className?: string;
  badge?: ReactNode;
  descriptionClassName?: string;
}
export function ConversationsListItem({
  conversation,
  isActive,
  className,
  badge,
  descriptionClassName,
}: Props) {
  const conversationLink = useConversationLink(conversation.id);
  return (
    <Link
      to={conversationLink}
      relative="path"
      className={clsx(
        'block min-h-70 cursor-pointer rounded-panel p-12 text-sm transition-bg-color',
        isActive ? 'bg-primary/8' : 'hover:bg-hover',
        className,
      )}
    >
      <div className="flex gap-8">
        {conversation.user && (
          <CustomerAvatar user={conversation.user} size="sm" />
        )}
        <div className="min-w-0 flex-auto">
          <div className="mb-6 flex min-w-0 items-center gap-8">
            <CustomerName
              user={conversation.user}
              className={clsx(
                'mr-auto min-w-0 overflow-hidden overflow-ellipsis whitespace-nowrap font-semibold',
              )}
            />
            {conversation.type === 'ticket' && (
              <ConfirmationNumberIcon size="xs" className="text-muted" />
            )}
            <time className="block flex-shrink-0 whitespace-nowrap text-xs text-muted">
              <FormattedRelativeTime
                date={conversation.updated_at}
                style="narrow"
                variant="noText"
              />
            </time>
          </div>
          <div className="text-[13px]">
            <div className="flex items-center gap-8">
              <div
                className={clsx(
                  'body line-clamp-2 text-muted',
                  descriptionClassName,
                )}
              >
                {conversation.latest_message?.body}
              </div>
              {badge}
            </div>
          </div>
        </div>
      </div>
    </Link>
  );
}
