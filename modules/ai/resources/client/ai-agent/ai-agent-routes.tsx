import {aiAgentQueries} from '@ai/ai-agent/ai-agent-queries';
import {getSelectedAiAgentId} from '@ai/ai-agent/use-selected-ai-agent';
import {helpdeskQueries} from '@app/dashboard/helpdesk-queries';
import {helpCenterQueries} from '@app/help-center/help-center-queries';
import {shouldRevalidateDatatableLoader} from '@common/datatable/filters/utils/should-revalidate-datatable-loader';
import {queryClient} from '@common/http/query-client';
import {message} from '@ui/i18n/message';
import {searchParamsFromUrl} from '@ui/utils/urls/search-params-from-url';
import {Outlet, redirect, RouteObject} from 'react-router';
import {Fragment} from 'react/jsx-runtime';

export const aiAgentRoutes: RouteObject[] = [
  {
    path: 'ai-agents',
    element: <Outlet />,
    loader: async () =>
      queryClient.ensureQueryData(aiAgentQueries.agents.index()),
    middleware: [
      async ({params}, next) => {
        // wait for aiAgents to be fetched in loader
        await next();

        // if no aiAgentId in route params, redirect to selected agent
        if (!params.aiAgentId) {
          const selectedAgentId = getSelectedAiAgentId();
          const data = queryClient.getQueryData(
            aiAgentQueries.agents.index().queryKey,
          );
          if (data) {
            const selectedAgent =
              data.aiAgents.find(agent => `${agent.id}` === selectedAgentId) ??
              data.aiAgents[0];
            throw redirect(`/dashboard/ai-agents/${selectedAgent.id}`);
          }
        }
      },
    ],
    children: [
      {
        path: ':aiAgentId',
        children: [
          {
            index: true,
            loader: () => redirect('settings'),
            element: <Fragment />,
          },
          {
            path: 'settings',
            handle: {customDashboardLayout: true},
            lazy: () => import('./settings/settings-page'),
          },
          {
            path: 'knowledge',
            handle: {customDashboardLayout: true},
            lazy: () => import('./knowledge/knowledge-page'),
            loader: ({params}) =>
              queryClient.ensureQueryData(
                aiAgentQueries.knowledge.index(params.aiAgentId!),
              ),
          },

          // flows
          {
            path: 'flows',
            handle: {customDashboardLayout: true},
            shouldRevalidate: shouldRevalidateDatatableLoader,
            lazy: () => import('./flows/pages/flows-index-page'),
            loader: ({request, params}) =>
              queryClient.ensureQueryData(
                aiAgentQueries.flows.index(
                  params.aiAgentId!,
                  searchParamsFromUrl(request.url),
                ),
              ),
          },
          {
            path: 'flows/:flowId/edit',
            handle: {customDashboardLayout: true},
            lazy: () => import('./flows/pages/update-flow-page'),
            loader: ({params}) => {
              // no need to wait for this to load
              queryClient.ensureQueryData(
                aiAgentQueries.flows.list(params.aiAgentId!),
              );
              queryClient.ensureQueryData(
                aiAgentQueries.tools.list(params.aiAgentId!),
              );
              queryClient.ensureQueryData(
                helpdeskQueries.attributes.normalizedList({
                  for: 'agent',
                }),
              );
              return queryClient.ensureQueryData(
                aiAgentQueries.flows.get(params.flowId!),
              );
            },
          },

          // tools
          {
            path: 'tools',
            handle: {customDashboardLayout: true},
            shouldRevalidate: shouldRevalidateDatatableLoader,
            lazy: () => import('./tools/tools-index-page/tools-index-page'),
            loader: ({request, params}) =>
              queryClient.ensureQueryData(
                aiAgentQueries.tools.index(
                  params.aiAgentId!,
                  searchParamsFromUrl(request.url),
                ),
              ),
          },
          {
            path: 'tools/new',
            lazy: () => import('./tools/editor/tool-editor-page'),
          },
          {
            path: 'tools/:toolId/edit',
            lazy: () => import('./tools/editor/tool-editor-page'),
            loader: ({params}) =>
              queryClient.ensureQueryData(
                aiAgentQueries.tools.get(params.toolId!, 'editor'),
              ),
          },

          // websites
          {
            path: 'knowledge/websites',
            shouldRevalidate: shouldRevalidateDatatableLoader,
            lazy: () => import('./knowledge/websites/websites-datatable'),
            loader: ({request, params}) =>
              queryClient.ensureQueryData(
                aiAgentQueries.websites.index(
                  params.aiAgentId!,
                  searchParamsFromUrl(request.url),
                ),
              ),
          },
          {
            path: 'knowledge/websites/:websiteId/pages',
            shouldRevalidate: shouldRevalidateDatatableLoader,
            lazy: () => import('./knowledge/websites/website-pages-datatable'),
            loader: ({request, params}) =>
              queryClient.ensureQueryData(
                aiAgentQueries.webpages.index(
                  params.websiteId!,
                  searchParamsFromUrl(request.url),
                ),
              ),
          },
          {
            path: 'knowledge/websites/:websiteId/pages/:webpageId',
            lazy: () => import('./knowledge/websites/webpage-preview'),
            loader: ({params}) =>
              queryClient.ensureQueryData(
                aiAgentQueries.webpages.get(
                  params.websiteId!,
                  params.webpageId!,
                ),
              ),
          },

          // documents
          {
            path: 'knowledge/documents',
            shouldRevalidate: shouldRevalidateDatatableLoader,
            lazy: () => import('./knowledge/documents/documents-datatable'),
            loader: ({request, params}) =>
              queryClient.ensureQueryData(
                aiAgentQueries.documents.index(
                  params.aiAgentId!,
                  searchParamsFromUrl(request.url),
                ),
              ),
          },
          {
            path: 'knowledge/documents/:documentId',
            lazy: () => import('./knowledge/documents/document-preview-page'),
            loader: ({params}) =>
              queryClient.ensureQueryData(
                aiAgentQueries.documents.get(params.documentId!),
              ),
          },

          // articles (wrap in parent so relative links work properly in create/update pages)
          {
            path: 'knowledge',
            handle: {breadcrumbRoot: message('Knowledge')},
            children: [
              {
                path: 'articles',
                shouldRevalidate: shouldRevalidateDatatableLoader,
                lazy: () =>
                  import('@app/help-center/articles/article-datatable/article-datatable-page'),
                loader: ({request, params}) =>
                  queryClient.ensureQueryData(
                    helpCenterQueries.articles.index(
                      searchParamsFromUrl(request.url),
                      params.aiAgentId,
                    ),
                  ),
              },
              {
                path: 'articles/new',
                handle: {customDashboardLayout: true},
                lazy: () =>
                  import('@app/help-center/articles/article-editor/create-article-page'),
                loader: () =>
                  queryClient.ensureQueryData(
                    helpCenterQueries.categories.normalizedList(),
                  ),
              },
              {
                path: 'articles/:articleId/edit',
                handle: {customDashboardLayout: true},
                lazy: () =>
                  import('@app/help-center/articles/article-editor/update-article-page'),
                loader: ({params}) =>
                  Promise.allSettled([
                    queryClient.ensureQueryData(
                      helpCenterQueries.articles.getForUpdateArticlePage({
                        articleId: params.articleId!,
                      }),
                    ),
                    queryClient.ensureQueryData(
                      helpCenterQueries.categories.normalizedList(),
                    ),
                  ]),
              },
            ],
          },

          // snippets
          {
            path: 'knowledge/snippets',
            shouldRevalidate: shouldRevalidateDatatableLoader,
            lazy: () =>
              import('./knowledge/snippets/ai-agent-snippets-datatable'),
            loader: ({request, params}) =>
              queryClient.ensureQueryData(
                aiAgentQueries.snippets.index(
                  params.aiAgentId!,
                  searchParamsFromUrl(request.url),
                ),
              ),
          },
          {
            path: 'knowledge/snippets/new',
            lazy: () =>
              import('./knowledge/snippets/create-ai-agent-snippet-page'),
            handle: {customDashboardLayout: true},
          },
          {
            path: 'knowledge/snippets/:snippetId/edit',
            lazy: () =>
              import('./knowledge/snippets/update-ai-agent-snippet-page'),
            handle: {customDashboardLayout: true},
            loader: ({params}) =>
              queryClient.ensureQueryData(
                aiAgentQueries.snippets.get(params.snippetId!),
              ),
          },
        ],
      },
    ],
  },
];
