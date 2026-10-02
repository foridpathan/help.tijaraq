import {helpdeskQueries} from '@app/dashboard/helpdesk-queries';
import {helpCenterQueries} from '@app/help-center/help-center-queries';
import {authGuard, AuthRoute} from '@common/auth/guards/auth-route';
import {auth} from '@common/auth/use-auth';
import {queryClient} from '@common/http/query-client';
import {getBootstrapData} from '@ui/bootstrap-data/bootstrap-data-store';
import {searchParamsFromUrl} from '@ui/utils/urls/search-params-from-url';
import {Params, replace, RouteObject} from 'react-router';

const articlePageLoader = async (params: Params) => {
  const isLegacyThreePart =
    params.articlePart && /^\d+$/.test(params.articlePart);
  const articleId =
    params.articleId || (isLegacyThreePart ? params.articlePart : undefined);
  return await queryClient.ensureQueryData(
    !articleId
      ? helpCenterQueries.articles.getForArticleSlugPage({
          articleSlug: params.articleSlug || params.articlePart!,
          categorySlug: params.categoryPart,
          sectionSlug: params.sectionPart,
        })
      : helpCenterQueries.articles.getForArticlePage({
          articleId,
          categoryId: params.categoryId || params.categoryPart,
          sectionId: params.sectionId || params.sectionPart,
        }),
  );
};

const categoryPageLoader = async (params: Params) => {
  const categorySlug =
    params.categorySlug ||
    (!/^\d+$/.test(params.categoryPart || '')
      ? params.categoryPart
      : undefined);
  return await queryClient.ensureQueryData(
    categorySlug
      ? helpCenterQueries.categories.getBySlug(categorySlug, params.sectionPart)
      : helpCenterQueries.categories.get(
          params.sectionId || params.categoryId || params.categoryPart!,
        ),
  );
};

export const helpCenterRoutes: RouteObject[] = [
  {
    path: 'hc',
    element: <AuthRoute requireLogin={false} permission="articles.view" />,
    children: [
      {
        index: true,
        lazy: () => import('@app/help-center/homepage/hc-landing-page'),
        loader: async () =>
          queryClient.ensureQueryData(
            helpCenterQueries.categories.landingPageData(),
          ),
      },
      {
        path: 'articles/:articleSlug',
        lazy: () =>
          import('@app/help-center/articles/article-page/article-page'),
        loader: async ({params}) => articlePageLoader(params),
      },
      {
        path: 'articles/:articleId/:articleSlug',
        lazy: () =>
          import('@app/help-center/articles/article-page/article-page'),
        loader: async ({params}) => articlePageLoader(params),
      },
      {
        path: 'articles/:categoryPart/:sectionPart/:articlePart',
        lazy: () =>
          import('@app/help-center/articles/article-page/article-page'),
        loader: async ({params}) => articlePageLoader(params),
      },
      {
        path: 'articles/:categoryId/:sectionId/:articleId/:articleSlug',
        lazy: () =>
          import('@app/help-center/articles/article-page/article-page'),
        loader: async ({params}) => articlePageLoader(params),
      },
      {
        path: 'categories/:categorySlug',
        lazy: () => import('@app/help-center/categories/category-page'),
        loader: async ({params}) => categoryPageLoader(params),
      },
      {
        path: 'categories/:categoryId/:sectionId/:slug',
        lazy: () => import('@app/help-center/categories/category-page'),
        loader: async ({params}) => categoryPageLoader(params),
      },
      {
        path: 'categories/:categoryPart/:sectionPart',
        lazy: () => import('@app/help-center/categories/category-page'),
        loader: async ({params}) => categoryPageLoader(params),
      },
      {
        path: 'search/:query',
        lazy: () => import('@app/help-center/search/hc-search-page'),
        loader: ({params}) =>
          queryClient.ensureQueryData(
            helpCenterQueries.articles.search({
              query: params.query,
              perPage: '30',
            }),
          ),
      },

      {
        path: 'tickets',
        lazy: () =>
          import('@app/help-center/tickets-portal/ticklets-table/tickets-table-page'),
        loader: async ({request}) => {
          const redirect = authGuard();
          if (redirect) return redirect;
          return Promise.all([
            await queryClient.ensureQueryData(
              helpCenterQueries.customerConversations.index(
                searchParamsFromUrl(request.url),
              ),
            ),
            await queryClient.ensureQueryData(
              helpdeskQueries.statuses.dropdownList('user'),
            ),
          ]);
        },
      },
      {
        path: 'tickets/new',
        lazy: () =>
          import('@app/help-center/tickets-portal/new-ticket-page/new-ticket-page'),
        loader: async () => {
          if (
            (!auth.isLoggedIn &&
              !getBootstrapData().settings.tickets?.guest_tickets) ||
            !auth.hasPermission('conversations.create')
          ) {
            return replace('/login');
          }
          return await queryClient.ensureQueryData(
            helpCenterQueries.customerConversations.newTicketPageData(),
          );
        },
      },
      {
        path: 'tickets/:conversationId',
        loader: async ({params}) => {
          const redirect = authGuard();
          if (redirect) return redirect;
          return Promise.all([
            queryClient.ensureQueryData(
              helpCenterQueries.customerConversations.get(
                params.conversationId!,
              ),
            ),
            queryClient.ensureInfiniteQueryData(
              helpdeskQueries.conversations.messages(params.conversationId!),
            ),
          ]);
        },
        lazy: () =>
          import('@app/help-center/tickets-portal/ticket-page/ticket-page'),
      },
    ],
  },
];
