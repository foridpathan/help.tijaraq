import {ConversationSummaryPanel} from '@ai/conversation-summary-panel/conversation-summary-panel';
import {AttributeRenderer} from '@app/attributes/rendering/attribute-renderer';
import {EditConversationAttributesDialog} from '@app/attributes/rendering/edit-conversation-attributes-dialog';
import {FullConversationResponse} from '@app/dashboard/conversation';
import {ConversationDetailsSkeleton} from '@app/dashboard/conversations/conversation-page/details-sidebar/conversation-details-skeleton';
import {ConversationGeneralDetails} from '@app/dashboard/conversations/conversation-page/details-sidebar/conversation-general-details';
import {
  DetailsList,
  DetailsListItem,
} from '@app/dashboard/conversations/conversation-page/details-sidebar/details-list';
import {PageVisitsPanel} from '@app/dashboard/conversations/conversation-page/details-sidebar/page-visists-panel';
import {RecentConversationsPanel} from '@app/dashboard/conversations/conversation-page/details-sidebar/recent-conversations-panel';
import {TechnologyPanel} from '@app/dashboard/conversations/conversation-page/details-sidebar/technology-panel';
import {useAgentInboxLayout} from '@app/dashboard/conversations/conversation-page/use-agent-inbox-layout';
import {ConversationPreviewDialog} from '@app/dashboard/conversations/conversation-preview-dialog';
import {InboxSectionHeader} from '@app/dashboard/dashboard-layout/inbox-section-header';
import {useIsModuleInstalledAndSetup} from '@app/use-is-module-installed';
import {ConversationPagePurchaseList} from '@envato/envato-purchase-list/conversation-page-purchase-list';
import {
  Accordion,
  AccordionItem,
  AccordionItemProps,
} from '@ui/accordion/accordion';
import {Button} from '@ui/buttons/button';
import {IconButton} from '@ui/buttons/icon-button';
import {FormattedRelativeTime} from '@ui/i18n/formatted-relative-time';
import {Trans} from '@ui/i18n/trans';
import {MessagesSquareIcon} from '@ui/icons/lucide/messages-square-icon';
import {ConfirmationNumberIcon} from '@ui/icons/material/ConfirmationNumber';
import {ToggleRightSidebarIcon} from '@ui/icons/toggle-right-sidebar-icon';
import {DialogTrigger} from '@ui/overlays/dialog/dialog-trigger';
import {openDialog} from '@ui/overlays/store/dialog-store';
import {useLocalStorage} from '@ui/utils/hooks/local-storage';
import {AnimatePresence} from 'framer-motion';
import {Fragment} from 'react';

interface Props {
  data?: FullConversationResponse;
}
export function ConversationDetailsSidebar({data}: Props) {
  const {toggleRightSidebar} = useAgentInboxLayout();
  return (
    <div className="dashboard-rounded-panel flex w-full flex-col lg:ml-8">
      <InboxSectionHeader>
        <Trans message="Details" />
        <IconButton
          className="ml-auto"
          size="xs"
          onClick={() => toggleRightSidebar()}
        >
          <ToggleRightSidebarIcon />
        </IconButton>
      </InboxSectionHeader>
      <div className="compact-scrollbar flex-auto overflow-y-auto stable-scrollbar">
        <ConversationDetails data={data} />
      </div>
    </div>
  );
}

function ConversationDetails({data}: Props) {
  const isAiSetup = useIsModuleInstalledAndSetup('ai');
  const isEnvatoSetup = useIsModuleInstalledAndSetup('envato');
  const isLivechatSetup = useIsModuleInstalledAndSetup('livechat');
  const [expandedItems, setExpendedItems] = useLocalStorage(
    'dash.chat.info',
    [0, 1, 2],
  );

  return (
    <AnimatePresence initial={false} mode="wait">
      {!data?.user ? (
        <ConversationDetailsSkeleton isLoading={false} />
      ) : (
        <Fragment>
          <ConversationGeneralDetails data={data} key="identity-panel" />
          <Accordion
            expandedValues={expandedItems ?? []}
            onExpandedChange={values => setExpendedItems(values as number[])}
            mode="multiple"
            variant="minimal"
            className="border-t"
          >
            {isEnvatoSetup && data.envatoPurchaseCodes.length > 0 && (
              <SidebarAccordionItem label={<Trans message="Envato" />}>
                <ConversationPagePurchaseList data={data} />
              </SidebarAccordionItem>
            )}
            <SidebarAccordionItem
              label={<Trans message="Conversation attributes" />}
            >
              <ConversationAttributesPanel data={data} />
            </SidebarAccordionItem>
            <SidebarAccordionItem
              label={<Trans message="Recent conversations" />}
            >
              <RecentConversationsPanel
                customerId={data.user.id}
                excludeId={data.conversation.id}
                onSelected={conversation => {
                  openDialog(ConversationPreviewDialog, {
                    conversationId: conversation.id,
                  });
                }}
              />
            </SidebarAccordionItem>
            {data.session && (
              <SidebarAccordionItem label={<Trans message="Technology" />}>
                <TechnologyPanel session={data.session} />
              </SidebarAccordionItem>
            )}
            {isLivechatSetup && (
              <SidebarAccordionItem label={<Trans message="Visited pages" />}>
                <PageVisitsPanel
                  userId={data.user.id}
                  initialData={data.visits}
                />
              </SidebarAccordionItem>
            )}
            {isAiSetup && (
              <SidebarAccordionItem label={<Trans message="Summary" />}>
                <ConversationSummaryPanel initialData={data.summary} />
              </SidebarAccordionItem>
            )}
          </Accordion>
        </Fragment>
      )}
    </AnimatePresence>
  );
}

interface ConversationAttributesPanelProps {
  data: FullConversationResponse;
}
function ConversationAttributesPanel({data}: ConversationAttributesPanelProps) {
  return (
    <Fragment>
      <DetailsList className="mb-16">
        <DetailsListItem label={<Trans message="Type" />}>
          {data.conversation.type === 'chat' ? (
            <div className="flex items-center gap-4">
              <MessagesSquareIcon size="xs" />
              <Trans message="Chat" />
            </div>
          ) : (
            <div className="flex items-center gap-4">
              <ConfirmationNumberIcon size="xs" />
              <Trans message="Ticket" />
            </div>
          )}
        </DetailsListItem>
        <DetailsListItem label={<Trans message="ID" />}>
          {data.conversation.id}
        </DetailsListItem>
        <DetailsListItem label={<Trans message="Started" />}>
          <FormattedRelativeTime date={data.conversation.created_at} />
        </DetailsListItem>
        <DetailsListItem label={<Trans message="Last activity" />}>
          <FormattedRelativeTime date={data.conversation.updated_at} />
        </DetailsListItem>
        {data.conversation.channel ? (
          <DetailsListItem label={<Trans message="Channel" />}>
            <ConversationChannel channel={data.conversation.channel} />
          </DetailsListItem>
        ) : null}
        {data.attributes.map(item => (
          <DetailsListItem key={item.id} label={<Trans message={item.name} />}>
            <AttributeRenderer attribute={item} className="text-sm" />
          </DetailsListItem>
        ))}
      </DetailsList>
      {!!data.attributes.length && (
        <DialogTrigger type="modal">
          <Button variant="outline" size="xs">
            <Trans message="Edit" />
          </Button>
          <EditConversationAttributesDialog
            attributes={data.attributes}
            conversation={data.conversation}
          />
        </DialogTrigger>
      )}
    </Fragment>
  );
}

function SidebarAccordionItem(props: AccordionItemProps) {
  return (
    <AccordionItem
      {...props}
      buttonPadding="py-12 pl-24 pr-2r"
      bodyPadding="px-24 pb-16 pt-4"
      labelClassName="font-semibold"
      className="border-b"
    >
      {props.children}
    </AccordionItem>
  );
}

function ConversationChannel({
  channel,
}: {
  channel: FullConversationResponse['conversation']['channel'];
}) {
  switch (channel) {
    case 'email':
      return <Trans message="Email" />;
    case 'widget':
      return <Trans message="Widget" />;
    case 'website':
      return <Trans message="Website" />;
  }
}
