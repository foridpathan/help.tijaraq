import {Navigate, Outlet, RouteObject} from 'react-router';

export const helpdeskReportRoutes: RouteObject[] = [
  {
    path: 'reports',
    //lazy: () => import('@app/dashboard/reports/helpdesk-report-page'),
    element: <Outlet />,
    children: [
      {
        index: true,
        element: <Navigate to="tickets" replace />,
      },
      {
        path: 'tickets',
        lazy: () =>
          import('@app/dashboard/reports/conversations-overview-report-page'),
      },
      {
        path: 'teammates',
        lazy: () =>
          import(
            '@app/dashboard/reports/team/teammate-performance-report-page'
          ),
      },
      {
        path: 'tags',
        lazy: () => import('@app/dashboard/reports/tags-report-page'),
      },
      {
        path: 'articles',
        lazy: () => import('@app/dashboard/reports/articles-report-page'),
      },
      {
        path: 'search',
        element: <Navigate to="popular" replace />,
      },
      {
        path: 'search/popular',
        lazy: () =>
          import('@app/dashboard/reports/search-report-page').then(
            ({PopularSearchReportPage}) => ({
              Component: PopularSearchReportPage,
            }),
          ),
      },
      {
        path: 'search/failed',
        lazy: () =>
          import('@app/dashboard/reports/search-report-page').then(
            ({FailedSearchReportPage}) => ({
              Component: FailedSearchReportPage,
            }),
          ),
      },
      {
        path: 'analytics',
        lazy: () =>
          import('@app/dashboard/reports/google-analytics-report-page'),
      },
    ],
  },
];
