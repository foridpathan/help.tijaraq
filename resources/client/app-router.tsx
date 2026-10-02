import {adminRoutes} from '@app/admin/admin-routes';
import {dashboardRoutes} from '@app/dashboard/dashboard-routes';
import {helpCenterRoutes} from '@app/help-center/help-center-routes';
import {authRoutes} from '@common/auth/auth-routes';
import {authGuard} from '@common/auth/guards/auth-route';
import {RootErrorElement, RootRoute} from '@common/core/common-provider';
import {commonRoutes} from '@common/core/common-routes';
import {notificationRoutes} from '@common/notifications/notification-routes';
import {getBootstrapData} from '@ui/bootstrap-data/bootstrap-data-store';
import {FullPageLoader} from '@ui/progress/full-page-loader';
import {createBrowserRouter} from 'react-router';

export const appRouter = createBrowserRouter(
  [
    {
      id: 'root',
      element: <RootRoute />,
      errorElement: <RootErrorElement />,
      hydrateFallbackElement: <FullPageLoader screen />,
      children: [
        {
          path: '/',
          lazy: () => import('@app/help-center/homepage/hc-landing-page'),
        },
        ...authRoutes({
          loginRoute: {
            lazy: () => import('@app/auth/app-login-page'),
          },
          registerRoute: {
            lazy: () => import('@app/auth/app-register-page'),
          },
          accountSettingsRoute: {
            lazy: () => import('@app/auth/app-account-settings-page'),
          },
        }),
        ...notificationRoutes,
        ...helpCenterRoutes,
        ...dashboardRoutes,
        ...adminRoutes,
        ...commonRoutes,
        {
          path: '/agents/join/:inviteId',
          handle: {inviteType: 'agentInvite'},
          lazy: () => import('@app/auth/app-register-page'),
        },
        {
          path: 'api-docs',
          loader: () =>
            authGuard({permission: 'api.access', requireLogin: false}),
          lazy: () => import('@common/swagger/swagger-api-docs-page'),
        },
      ],
    },
  ],
  {
    basename: getBootstrapData().settings.html_base_uri,
  },
);
