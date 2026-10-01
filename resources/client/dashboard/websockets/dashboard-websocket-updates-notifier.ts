import {CompactAgent} from '@app/dashboard/types/agent';
import {
  ConversationSoundName,
  playConversationSound,
} from '@app/dashboard/websockets/play-conversation-sound';
import {WebsocketUpdatesNotifier} from '@app/dashboard/websockets/websocket-updates-notifier';
import {auth} from '@common/auth/use-auth';

class DashboardWebsocketUpdatesNotifier extends WebsocketUpdatesNotifier {
  protected groupIds: number[] = [];

  init(agent: CompactAgent) {
    this.groupIds = agent.groups.map(g => g.id);
    this.initNotifier();
  }

  protected playSound(sound: ConversationSoundName) {
    playConversationSound(sound, 'dashboard');
  }

  protected conversationBelongsToUser(conversation: {
    assignee_id?: number;
    group_id: number;
  }) {
    return (
      conversation.assignee_id === auth.user?.id ||
      (!conversation.assignee_id &&
        this.groupIds.includes(conversation.group_id))
    );
  }

  protected appIsVisible(): boolean {
    return document.visibilityState === 'visible';
  }

  protected addCountToTitle(count: number) {
    if (!this.isCountInTitle()) {
      const prefix = `(${count}) `;
      document.title = prefix + document.title;
    }
  }

  protected isCountInTitle() {
    return /^\(\d+\)\s/.test(document.title);
  }

  protected removeCountFromTitle() {
    document.title = document.title.replace(/^\(\d+\)\s/, '');
  }
}

export const dashboardChatUpdatesNotifier =
  new DashboardWebsocketUpdatesNotifier();
