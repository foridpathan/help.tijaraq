import {helpdeskChannel} from '@app/dashboard/helpdesk-channel';
import {ConversationSoundName} from '@app/dashboard/websockets/play-conversation-sound';
import {WebsocketConversationEvent} from '@app/dashboard/websockets/websocket-conversation-event';
import {create} from 'zustand';
import {statusCategory} from '../statuses/status-category';

export const useUnseenConversationsStore = create<{
  unseenConversations: number[];
  conversationsWithUnseenMessages: number[];
  setData: (
    unseenConversations: number[],
    conversationsWithUnseenMessages: number[],
  ) => void;
}>()(set => ({
  unseenConversations: [],
  conversationsWithUnseenMessages: [],
  setData: (unseenConversations, conversationsWithUnseenMessages) => {
    set({
      unseenConversations,
      conversationsWithUnseenMessages,
    });
  },
}));

export abstract class WebsocketUpdatesNotifier {
  protected intervalId: ReturnType<typeof setInterval> | null = null;

  protected unseenConversations: Record<
    number,
    {unseen: boolean; unseenMessages: boolean}
  > = {};
  protected addUnseenConversation(d: {
    id: number;
    unseen: boolean;
    unseenMessages: boolean;
  }) {
    if (d.unseen || d.unseenMessages) {
      this.unseenConversations[d.id] = d;
    } else {
      this.removeUnseenConversation(d.id);
    }
  }
  protected removeUnseenConversation(id: number) {
    delete this.unseenConversations[id];
  }

  protected abstract conversationBelongsToUser(conversation: {
    assignee_id?: number;
    group_id: number;
    user_id: number;
  }): boolean;
  protected abstract addCountToTitle(count: number): void;
  protected abstract isCountInTitle(): boolean;
  protected abstract removeCountFromTitle(): void;
  protected abstract appIsVisible(): boolean;
  protected abstract playSound(sound: ConversationSoundName): void;

  protected pageStatus = {
    isInboxOpen: false,
    activeConversationId: null as number | null,
  };

  setPageStatus(status: WebsocketUpdatesNotifier['pageStatus']) {
    this.pageStatus = {...this.pageStatus, ...status};
    this.onConversationPageOpenOrDocumentVisibilityChange();
  }

  // mark conversation as seen if we are on inbox page or specified conversation page is open
  protected shouldMarkConversationAsSeen(conversation: {id: number}) {
    return (
      this.appIsVisible() &&
      (this.pageStatus.isInboxOpen ||
        this.pageStatus.activeConversationId === conversation.id)
    );
  }

  // mark messages as seen only if we are on that specific conversation's page and app is visible
  protected shouldMarkMessagesAsSeen(conversation: {id: number}): boolean {
    return (
      this.appIsVisible() &&
      conversation.id === this.pageStatus.activeConversationId
    );
  }

  // play sound if app is not visible or if there's a new
  // conversation or a new message for conversation that is currently not active
  protected shouldPlaySound(conversationId: number) {
    return (
      !this.appIsVisible() ||
      this.pageStatus.activeConversationId !== conversationId
    );
  }

  protected onConversationPageOpenOrDocumentVisibilityChange() {
    if (document.visibilityState === 'hidden') return;

    if (this.pageStatus.isInboxOpen) {
      this.markConversationsAsSeen();
    }

    // if we are on specific conversation page, mark messages of that conversation as seen
    if (this.pageStatus.activeConversationId) {
      this.markMessagesAsSeen([this.pageStatus.activeConversationId]);
    }
  }

  markConversationsAsSeen(conversationIds?: number[]) {
    const conversationsToMark =
      conversationIds || Object.keys(this.unseenConversations).map(Number);

    conversationsToMark.forEach(id => {
      this.unseenConversations[id].unseen = false;
    });

    this.syncWithStore();
    this.maybeStopTitleInterval();
  }

  markMessagesAsSeen(conversationIds?: number[]) {
    const conversationsToMark =
      conversationIds || Object.keys(this.unseenConversations).map(Number);

    conversationsToMark.forEach(id => {
      if (this.unseenConversations[id]) {
        this.unseenConversations[id].unseenMessages = false;
      }
    });

    this.syncWithStore();
    this.maybeStopTitleInterval();
  }

  // this is called whenever conversation create, update or newMessage event is received via websockets
  handleEvent(e: WebsocketConversationEvent) {
    if (
      e.event === helpdeskChannel.events.conversations.created ||
      e.event === helpdeskChannel.events.conversations.updated
    ) {
      e.conversations.forEach(conversation => {
        // if conversation does not belong to user, remove it and continue.
        // might happen if conversation is assigned to agent and then unassigned.
        if (
          !this.conversationBelongsToUser(conversation) ||
          conversation.status_category <= statusCategory.closed
        ) {
          this.removeUnseenConversation(conversation.id);
          return;
        }

        // this is a new conversation, mark as unseen and notify via sound
        if (!this.unseenConversations[conversation.id]) {
          this.addUnseenConversation({
            id: conversation.id,
            unseen: !this.shouldMarkConversationAsSeen(conversation),
            unseenMessages: !this.shouldMarkMessagesAsSeen(conversation),
          });

          if (this.shouldPlaySound(conversation.id)) {
            if (!conversation.assignee_id) {
              this.playSound('queuedVisitor');
            } else if (conversation.assignee_id) {
              this.playSound('incomingChat');
            }
          }
        }
      });
    }

    if (e.event === helpdeskChannel.events.conversations.newMessage) {
      const seenAllMessages = this.shouldMarkMessagesAsSeen(e.conversations[0]);
      if (!seenAllMessages) {
        // needed to show dot in conversation list, when new message arrives while not in inbox
        this.addUnseenConversation({
          id: e.conversations[0].id,
          unseen: !this.shouldMarkConversationAsSeen(e.conversations[0]),
          unseenMessages: !this.shouldMarkMessagesAsSeen(e.conversations[0]),
        });

        // play new message sound only if browser tab is not focused or not on conversation page
        this.playSound('message');
      }
    }

    this.syncWithStore();
    this.syncTitleInterval();
  }

  // stop interval if no unseen conversations/messages, start new interval, or update count in current interval
  protected syncTitleInterval() {
    this.maybeStopTitleInterval();
    // if app is visible, only count unseen conversations, otherwise count unseen messages as well
    const unseenCount = Object.entries(this.unseenConversations).filter(
      ([, {unseen, unseenMessages}]) =>
        unseen || (!this.appIsVisible() && unseenMessages),
    ).length;
    if (unseenCount > 0) {
      this.maybeStopTitleInterval({force: true});
      this.intervalId = setInterval(() => {
        if (!this.isCountInTitle()) {
          this.addCountToTitle(unseenCount);
        } else {
          this.removeCountFromTitle();
        }
      }, 1000);
    }
  }

  protected syncWithStore() {
    useUnseenConversationsStore.getState().setData(
      Object.entries(this.unseenConversations)
        .filter(([, {unseen}]) => unseen)
        .map(([id]) => Number(id)),
      Object.entries(this.unseenConversations)
        .filter(([, {unseenMessages}]) => unseenMessages)
        .map(([id]) => Number(id)),
    );
  }

  protected maybeStopTitleInterval({force}: {force?: boolean} = {}) {
    // only run title interval if conversation itself is unseen and not just its messages
    if (
      force ||
      Object.entries(this.unseenConversations).every(([, {unseen}]) => !unseen)
    ) {
      if (this.intervalId) {
        clearInterval(this.intervalId);
        this.intervalId = null;
      }
      this.removeCountFromTitle();
    }
  }

  isInitialized = false;
  protected initNotifier() {
    document.addEventListener('visibilitychange', () => {
      this.onConversationPageOpenOrDocumentVisibilityChange();
    });

    this.isInitialized = true;
  }
}
