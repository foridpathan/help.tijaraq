import {FullConversationResponse} from '@app/dashboard/conversation';
import {ConversationSubject} from '@app/dashboard/conversations/conversation-page/toolbar/conversation-subject';
import {MoreOptionsButton} from '@app/dashboard/conversations/conversation-page/toolbar/more-options-button';
import {StatusButton} from '@app/dashboard/conversations/conversation-page/toolbar/status-button';
import {useAgentInboxLayout} from '@app/dashboard/conversations/conversation-page/use-agent-inbox-layout';
import {
  ConversationTagManagerDialog,
  TagManagerItem,
  useAddTagToConversations,
  useRemoveTagFromConversations,
} from '@app/dashboard/conversations/conversations-table/conversation-actions/add-tag-to-conversations-button';
import {InboxSectionHeader} from '@app/dashboard/dashboard-layout/inbox-section-header';
import {helpdeskQueries} from '@app/dashboard/helpdesk-queries';
import {queryClient} from '@common/http/query-client';
import {DashboardLayoutContext} from '@common/ui/dashboard-layout/dashboard-layout-context';
import {IconButton} from '@ui/buttons/icon-button';
import {ArrowBackIcon} from '@ui/icons/material/ArrowBack';
import {SellIcon} from '@ui/icons/material/Sell';
import {ToggleLeftSidebarIcon} from '@ui/icons/toggle-left-sidebar-icon';
import {ToggleRightSidebarIcon} from '@ui/icons/toggle-right-sidebar-icon';
import {DialogTrigger} from '@ui/overlays/dialog/dialog-trigger';
import {useKeybind} from '@ui/utils/keybinds/use-keybind';
import {useContext, useState} from 'react';
import {Link, useSearchParams} from 'react-router';

interface Props {
  data: FullConversationResponse;
}
export function ConversationPageToolbar({data}: Props) {
  return (
    <InboxSectionHeader gap="gap-4">
      <ToggleConversationListButton />
      <div className="text-overflow-ellipsis mr-24 min-w-0 overflow-hidden">
        <ConversationSubject data={data} />
      </div>
      <MoreOptionsButton data={data} />
      <ManageTagsButton data={data} />
      <StatusButton data={data} />
      <ToggleRightSidebarButton />
    </InboxSectionHeader>
  );
}

export function ToggleConversationListButton() {
  const {toggleConversationList} = useAgentInboxLayout();
  const {isMobileMode} = useContext(DashboardLayoutContext);
  const [searchParams] = useSearchParams();
  const viewId = searchParams.get('viewId') ?? 'all';

  if (isMobileMode) {
    return (
      <IconButton
        size="sm"
        elementType={Link}
        to={`/dashboard/conversations?viewId=${viewId}`}
      >
        <ArrowBackIcon />
      </IconButton>
    );
  }

  return (
    <IconButton size="xs" onClick={() => toggleConversationList()}>
      <ToggleLeftSidebarIcon />
    </IconButton>
  );
}

export function ToggleRightSidebarButton() {
  const {toggleRightSidebar, rightSidebarOpen: rightSidenavOpen} =
    useAgentInboxLayout();
  if (rightSidenavOpen) return null;
  return (
    <IconButton onClick={() => toggleRightSidebar()} size="xs">
      <ToggleRightSidebarIcon />
    </IconButton>
  );
}

interface ManageTagsButtonProps {
  data: FullConversationResponse;
}
export function ManageTagsButton({data}: ManageTagsButtonProps) {
  const [isOpen, setIsOpen] = useState(false);
  const attachedTags = data.tags.map(t => t.id);
  const addTag = useAddTagToConversations();
  const removeTag = useRemoveTagFromConversations();

  const invalidateConversation = () => {
    queryClient.invalidateQueries({
      queryKey: helpdeskQueries.conversations.get(data.conversation.id)
        .queryKey,
    });
  };

  useKeybind('window', 't', () => setIsOpen(true));

  return (
    <DialogTrigger
      type="popover"
      isOpen={isOpen}
      onOpenChange={setIsOpen}
      onClose={(tag: TagManagerItem) => {
        if (tag) {
          if (attachedTags.includes(tag.id)) {
            removeTag.mutate(
              {
                tagId: tag.id,
                conversationIds: [data.conversation.id],
              },
              {onSuccess: invalidateConversation},
            );
          } else {
            addTag.mutate(
              {
                tagId: tag.id,
                newTagName: tag.newTagName,
                conversationIds: [data.conversation.id],
              },
              {onSuccess: invalidateConversation},
            );
          }
        }
      }}
    >
      <IconButton className="mr-8" size="xs" iconSize="sm">
        <SellIcon />
      </IconButton>
      <ConversationTagManagerDialog attachedTags={attachedTags} />
    </DialogTrigger>
  );
}
