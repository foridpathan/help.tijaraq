import {useNavigate} from '@common/ui/navigation/use-navigate';
import {ConversationScreen} from '@livechat/widget/conversation-screen/feed/conversation-screen';
import {ConversationsListScreen} from '@livechat/widget/conversations-list-screen';
import {EmbedScreen} from '@livechat/widget/embed-screen';
import {CategoryListScreen} from '@livechat/widget/help/category-list-screen';
import {CategoryScreen} from '@livechat/widget/help/category-screen';
import {HelpScreen} from '@livechat/widget/help/help-screen';
import {SectionScreen} from '@livechat/widget/help/section-screen';
import {WidgetArticleScreen} from '@livechat/widget/help/widget-article-screen';
import {HomeScreen} from '@livechat/widget/home/home-screen';
import {useIsWidgetInline} from '@livechat/widget/hooks/use-is-widget-inline';
import {useWidgetPosition} from '@livechat/widget/hooks/use-widget-position';
import {NewTicketScreen} from '@livechat/widget/new-ticket-screen';
import {WidgetNavigation} from '@livechat/widget/widget-navigation/widget-navigation';
import {useWidgetStore} from '@livechat/widget/widget-store';
import {PopoverAnimation} from '@ui/overlays/popover-animation';
import {DialogStoreOutlet} from '@ui/overlays/store/dialog-store-outlet';
import {setRootEl} from '@ui/root-el';
import {useSettings} from '@ui/settings/use-settings';
import {ToastContainer} from '@ui/toast/toast-container';
import clsx from 'clsx';
import {AnimatePresence, m} from 'framer-motion';
import {Fragment, useLayoutEffect, useRef} from 'react';
import {Outlet, Route, Routes} from 'react-router';

export default function LivechatPopup() {
  const ref = useRef<HTMLDivElement>(null!);
  const {isInline} = useIsWidgetInline();
  const {chatWidget} = useSettings();
  const navigate = useNavigate();
  const defaultScreen = chatWidget?.defaultScreen ?? '/';
  const alreadySetDefaultScreen = useRef(false);
  const defaultScreenIsHomepage = defaultScreen === '/' || defaultScreen === '';
  const {paddingSide} = useWidgetPosition();
  const isMobile = useWidgetStore(s => s.isMobile);

  useLayoutEffect(() => {
    setRootEl(ref.current);
  }, []);

  useLayoutEffect(() => {
    if (!defaultScreenIsHomepage && !alreadySetDefaultScreen.current) {
      navigate(defaultScreen, {replace: true});
      alreadySetDefaultScreen.current = true;
    }
  }, [defaultScreen, defaultScreenIsHomepage, navigate]);

  // prevent home screen from rendering if default screen is not home screen
  if (!defaultScreenIsHomepage && !alreadySetDefaultScreen.current) {
    return null;
  }

  return (
    <m.div
      key="livechat-popup"
      {...(isInline ? {} : PopoverAnimation)}
      style={{
        paddingLeft: isMobile ? '0px' : paddingSide,
        paddingRight: isMobile ? '0px' : paddingSide,
        paddingTop: isMobile ? '0px' : paddingSide,
      }}
      className={clsx('ml-auto min-h-0 w-full flex-auto', !isMobile && 'pb-16')}
    >
      <div
        className={clsx(
          'livechat-popup-inner relative flex h-full min-h-0 w-full flex-col overflow-hidden bg text',
          !isMobile && 'rounded-panel shadow-widget-popup',
        )}
        ref={ref}
      >
        <ToastContainer toastPosition="absolute" toastPlacement="top-center" />
        <DialogStoreOutlet />
        <Routes>
          <Route
            path=""
            element={
              <Fragment>
                <AnimatePresence initial={false}>
                  <Outlet />
                </AnimatePresence>
                {!chatWidget?.hideNavigation && <WidgetNavigation />}
              </Fragment>
            }
          >
            <Route index element={<HomeScreen />} />
            <Route path="conversations" element={<ConversationsListScreen />} />
            <Route path="hc" element={<HelpScreen />}>
              <Route index element={<CategoryListScreen />} />
              <Route
                path="categories/:categoryId"
                element={<CategoryScreen />}
              />
              <Route
                path="categories/:categoryId/:sectionId"
                element={<SectionScreen />}
              />
            </Route>
          </Route>
          <Route path="tickets/new" element={<NewTicketScreen />} />
          <Route path="conversations/new" element={<ConversationScreen />} />
          <Route
            path="conversations/:conversationId"
            element={<ConversationScreen />}
          />
          <Route
            path="hc/articles/:categoryId/:sectionId/:articleId/:articleSlug"
            element={<WidgetArticleScreen />}
          />
          <Route path="embed" element={<EmbedScreen />} />
        </Routes>
      </div>
    </m.div>
  );
}
