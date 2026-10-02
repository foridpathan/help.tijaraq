import {NotFoundPage} from '@common/ui/not-found-page/not-found-page';
import {redirectDocument, RouteObject} from 'react-router';

export const commonRoutes: RouteObject[] = [
  {
    path: 'contact',
    lazy: () => import('@common/contact/contact-us-page'),
  },
  {
    path: 'pages/privacy-policy',
    loader: () => redirectDocument('https://tijaraq.com/privacy-policy/'),
  },
  {
    path: 'pages/terms-of-service',
    loader: () => redirectDocument('https://tijaraq.com/terms-of-service/'),
  },
  {
    path: 'pages/about-us',
    loader: () => redirectDocument('https://tijaraq.com/about-us/'),
  },
  {
    path: 'pages/:pageSlug',
    lazy: () => import('@common/custom-page/custom-page-layout'),
  },
  {
    path: 'pages/:pageId/:pageSlug',
    lazy: () => import('@common/custom-page/custom-page-layout'),
  },
  {
    path: '404',
    element: <NotFoundPage />,
  },
];
