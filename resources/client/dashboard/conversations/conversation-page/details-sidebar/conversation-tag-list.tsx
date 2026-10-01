import {ConversationTag} from '@app/dashboard/conversation';
import {useRemoveTagFromConversations} from '@app/dashboard/conversations/conversations-table/conversation-actions/add-tag-to-conversations-button';
import {helpdeskQueries} from '@app/dashboard/helpdesk-queries';
import {queryClient} from '@common/http/query-client';
import {Chip} from '@ui/forms/input-field/chip-field/chip';
import {
  ChipList,
  ChipListProps,
} from '@ui/forms/input-field/chip-field/chip-list';

interface Props extends ChipListProps {
  conversationId: number | string;
  tags: ConversationTag[];
}
export function ConversationTagList({
  conversationId,
  size = 'xs',
  tags,
  ...chipListProps
}: Props) {
  const removeTag = useRemoveTagFromConversations();

  if (!tags.length) {
    return null;
  }

  return (
    <ChipList {...chipListProps} size={size}>
      {tags.map(tag => (
        <Chip
          key={tag.id}
          disabled={removeTag.isPending}
          onRemove={() =>
            removeTag.mutate(
              {
                tagId: tag.id,
                conversationIds: [conversationId],
              },
              {
                onSuccess: () => {
                  queryClient.invalidateQueries({
                    queryKey:
                      helpdeskQueries.conversations.get(conversationId)
                        .queryKey,
                  });
                },
              },
            )
          }
        >
          {tag.name}
        </Chip>
      ))}
    </ChipList>
  );
}
