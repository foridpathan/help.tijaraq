import {ConversationListItemType} from '@app/dashboard/conversation';
import {get} from '@app/dashboard/helpdesk-queries';
import {HcLandingPageData} from '@app/help-center/homepage/hc-landing-page-data';
import {
  getNextPageParam,
  PaginatedBackendResponse,
} from '@common/http/backend-response/pagination-response';
import {queryClient} from '@common/http/query-client';
import {FullWidgetConversationResponse} from '@livechat/widget/conversation-screen//requests/full-widget-conversation-response';
import {HomeScreenArticleListData} from '@livechat/widget/home/home-screen-article-list-data';
import {WidgetCustomer} from '@livechat/widget/user/widget-customer';
import {
  InfiniteData,
  infiniteQueryOptions,
  queryOptions,
} from '@tanstack/react-query';

export const widgetQueries = {
  customers: {
    invalidateKey: ['users', 'customers'],
    get: () =>
      queryOptions<{user: WidgetCustomer}>({
        queryKey: ['users', 'customers', 'widget'],
        queryFn: () => get(`lc/widget/customer`),
      }),
  },
  conversations: {
    invalidateKey: ['conversations'],
    index: () =>
      infiniteQueryOptions({
        queryKey: ['conversations', 'widget'],
        queryFn: ({pageParam}) =>
          get<PaginatedBackendResponse<ConversationListItemType>>(
            `lc/widget/conversations`,
            pageParam ? {cursor: pageParam} : undefined,
          ),
        initialPageParam: null,
        getNextPageParam: getNextPageParam,
      }),
    get: (conversationId: string | number | undefined) =>
      queryOptions({
        enabled: !!conversationId,
        queryKey: ['conversations', 'widget', `${conversationId}`],
        queryFn: async () => {
          const response = await get<FullWidgetConversationResponse>(
            `lc/widget/conversations/${conversationId}`,
          );
          setMessagesQueryData(response);
          return response;
        },
      }),
    messages: (conversationId: string | number) =>
      infiniteQueryOptions({
        // ai agent streamed response might get overwritten without this
        staleTime: Infinity,
        queryKey: ['conversations', 'widget', `${conversationId}`, 'messages'],
        queryFn: ({pageParam}) => {
          return get<{pagination: FullWidgetConversationResponse['items']}>(
            `lc/widget/chats/${conversationId}/messages`,
            pageParam ? {cursor: pageParam} : undefined,
          );
        },
        initialPageParam: null,
        getNextPageParam: getNextPageParam,
      }),
  },
  articles: {
    hcData: () => {
      return queryOptions<HcLandingPageData>({
        queryKey: ['articles', 'widget', 'hcData'],
        queryFn: () => get(`lc/widget/help-center-data`),
      });
    },
    homeArticleList: () => {
      return queryOptions<HomeScreenArticleListData>({
        queryKey: ['articles', 'widget', 'homeArticleList'],
        queryFn: () => get(`lc/widget/home-article-list`),
      });
    },
  },
};

export function setWidgetConversationQueryData(
  conversationid: number | string,
  data: FullWidgetConversationResponse,
) {
  queryClient.setQueryData<FullWidgetConversationResponse>(
    widgetQueries.conversations.get(conversationid).queryKey,
    data,
  );
  setMessagesQueryData(data);
}

function setMessagesQueryData(response: FullWidgetConversationResponse) {
  queryClient.setQueryData<
    InfiniteData<{
      pagination: FullWidgetConversationResponse['items'];
    }>
  >(
    widgetQueries.conversations.messages(response.conversation.id).queryKey,
    prev => {
      if (prev) {
        prev.pages[0] = {pagination: response.items};
        return {
          pages: prev.pages,
          pageParams: prev.pageParams,
        };
      } else {
        return {
          pages: [{pagination: response.items}],
          pageParams: [response.items.prev_cursor ?? null],
        };
      }
    },
  );
}
