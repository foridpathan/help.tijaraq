import {aiAgentQueries} from '@ai/ai-agent/ai-agent-queries';
import {TogglePreviewButton} from '@ai/ai-agent/preview/toggle-preview-button';
import {useUpdateAiAgentSettings} from '@ai/ai-agent/settings/use-update-ai-agent-settings';
import {useSelectedAiAgent} from '@ai/ai-agent/use-selected-ai-agent';
import {AdminDocsUrls} from '@app/admin/admin-config';
import {DatatablePageHeaderBar} from '@common/datatable/page/datatable-page-with-header-layout';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {useUrlBackedTabs} from '@common/http/use-url-backed-tabs';
import {StaticPageTitle} from '@common/seo/static-page-title';
import {Button} from '@common/ui/library/buttons/button';
import {Trans} from '@common/ui/library/i18n/trans';
import {MediaPauseIcon} from '@common/ui/library/icons/media/media-pause';
import {MediaPlayIcon} from '@common/ui/library/icons/media/media-play';
import {Tab} from '@common/ui/library/tabs/tab';
import {TabList} from '@common/ui/library/tabs/tab-list';
import {Tabs} from '@common/ui/library/tabs/tabs';
import {useRequiredParams} from '@common/ui/navigation/use-required-params';
import {useMutation, useSuspenseQuery} from '@tanstack/react-query';
import {Form} from '@ui/forms/form';
import {FormTextField} from '@ui/forms/input-field/text-field/text-field';
import {Item} from '@ui/forms/listbox/item';
import {message} from '@ui/i18n/message';
import {BookOpenIcon} from '@ui/icons/lucide/book-open';
import {AddIcon} from '@ui/icons/material/Add';
import {ArrowDropDownIcon} from '@ui/icons/material/ArrowDropDown';
import {Menu, MenuTrigger} from '@ui/menu/menu-trigger';
import {Dialog} from '@ui/overlays/dialog/dialog';
import {DialogBody} from '@ui/overlays/dialog/dialog-body';
import {useDialogContext} from '@ui/overlays/dialog/dialog-context';
import {DialogFooter} from '@ui/overlays/dialog/dialog-footer';
import {DialogHeader} from '@ui/overlays/dialog/dialog-header';
import {DialogTrigger} from '@ui/overlays/dialog/dialog-trigger';
import {toast} from '@ui/toast/toast';
import {useIsMobileMediaQuery} from '@ui/utils/hooks/is-mobile-media-query';
import {Fragment, useState} from 'react';
import {useForm} from 'react-hook-form';
import {Link, useNavigate} from 'react-router';

const tabConfig = [
  {uri: 'settings', label: {message: 'Settings'}},
  {uri: 'knowledge', label: {message: 'Knowledge'}},
  {uri: 'flows', label: {message: 'Flows'}},
  {uri: 'tools', label: {message: 'Tools'}},
];

function HeaderTabs() {
  const [activeTab, setActiveTab] = useUrlBackedTabs(tabConfig);
  return (
    <Tabs selectedTab={activeTab} onTabChange={setActiveTab}>
      <TabList className="px-24">
        {tabConfig.map(tab => (
          <Tab key={tab.uri} elementType={Link} to={`../${tab.uri}`}>
            <Trans {...tab.label} />
          </Tab>
        ))}
      </TabList>
    </Tabs>
  );
}

type AiAgentPageHeaderProps = {
  previewVisible?: boolean;
  onTogglePreview?: () => void;
};
export function AiAgentPageHeader({
  previewVisible,
  onTogglePreview,
}: AiAgentPageHeaderProps) {
  return (
    <div>
      <StaticPageTitle>
        <Trans message="AI Agents" />
      </StaticPageTitle>
      <DatatablePageHeaderBar
        showSidebarToggleButton
        border="border-none"
        rightContent={
          <AiAgentPageHeaderActions
            previewVisible={previewVisible}
            onTogglePreview={onTogglePreview}
          />
        }
        leftContent={<AiAgentSelector />}
      >
        <Trans message="AI Agents" />
      </DatatablePageHeaderBar>
      <HeaderTabs />
    </div>
  );
}

function AiAgentSelector() {
  const [createAiAgentDialogOpen, setCreateAiAgentDialogOpen] = useState(false);
  const {aiAgentId} = useRequiredParams(['aiAgentId']);
  const navigate = useNavigate();
  const {data} = useSuspenseQuery(aiAgentQueries.agents.index());
  const selectedAgent =
    data.aiAgents.find(agent => `${agent.id}` === aiAgentId) ||
    data.aiAgents[0];

  return (
    <>
      <MenuTrigger selectedValue={aiAgentId} selectionMode="single">
        <Button variant="outline" endIcon={<ArrowDropDownIcon />} size="xs">
          {selectedAgent.config.name}
        </Button>
        <Menu>
          {data.aiAgents.map(agent => (
            <Item
              key={agent.id}
              value={`${agent.id}`}
              onSelected={() => navigate(`../../${agent.id}`, {replace: true})}
            >
              {agent.config.name}
            </Item>
          ))}
          <Item
            value="create"
            onClick={() => setCreateAiAgentDialogOpen(true)}
            startIcon={<AddIcon size="sm" />}
          >
            <Trans message="Create new AI agent" />
          </Item>
        </Menu>
      </MenuTrigger>
      <DialogTrigger
        type="modal"
        isOpen={createAiAgentDialogOpen}
        onOpenChange={setCreateAiAgentDialogOpen}
      >
        <CreateAiAgentDialog />
      </DialogTrigger>
    </>
  );
}

function CreateAiAgentDialog() {
  const navigate = useNavigate();
  const form = useForm<{name: string}>();
  const createAiAgent = useMutation({
    mutationFn: (payload: {name: string}) =>
      apiClient
        .post<{aiAgent: {id: number}}>('lc/ai-agents', payload)
        .then(r => r.data),
    onSuccess: r => {
      queryClient.invalidateQueries({
        queryKey: aiAgentQueries.invalidateKey,
      });
      navigate(`../../${r.aiAgent.id}`, {replace: true});
      toast(message('AI Agent created'));
      close();
    },
    onError: err => showHttpErrorToast(err),
  });
  const {formId, close} = useDialogContext();
  return (
    <Dialog>
      <DialogHeader>
        <Trans message="Create AI Agent" />
      </DialogHeader>
      <DialogBody>
        <Form
          id={formId}
          form={form}
          onSubmit={values => {
            createAiAgent.mutate(values);
          }}
        >
          <FormTextField
            required
            name="name"
            autoFocus
            label={<Trans message="Name" />}
          />
        </Form>
      </DialogBody>
      <DialogFooter>
        <Button type="button" onClick={() => close()}>
          <Trans message="Cancel" />
        </Button>
        <Button
          type="submit"
          form={formId}
          disabled={createAiAgent.isPending}
          variant="flat"
          color="primary"
        >
          <Trans message="Create" />
        </Button>
      </DialogFooter>
    </Dialog>
  );
}

function AiAgentPageHeaderActions({
  previewVisible,
  onTogglePreview,
}: AiAgentPageHeaderProps) {
  return (
    <Fragment>
      <LearnButton />
      {!!onTogglePreview && (
        <TogglePreviewButton
          onTogglePreview={onTogglePreview}
          previewIsVisible={previewVisible ?? false}
        />
      )}
      <ToggleButton />
    </Fragment>
  );
}

const learnItems = [
  {
    url: AdminDocsUrls.settings.ai,
    label: message('Choosing AI Provider'),
  },
  {
    url: AdminDocsUrls.pages.aiAgentSettings,
    label: message('AI Agent Settings'),
  },
  {
    url: AdminDocsUrls.pages.aiAgentKnowledge,
    label: message('AI Agent Knowledge'),
  },
  {
    url: AdminDocsUrls.pages.flows,
    label: message('Building AI Agent Flows'),
  },
  {
    url: AdminDocsUrls.pages.tools,
    label: message('Using Tools With AI Agent'),
  },
];

function LearnButton() {
  return (
    <MenuTrigger>
      <Button
        variant="outline"
        startIcon={<BookOpenIcon />}
        endIcon={<ArrowDropDownIcon />}
        size="xs"
      >
        <Trans message="Learn" />
      </Button>
      <Menu>
        {learnItems.map(item => (
          <Item
            key={item.url}
            value={item.url}
            to={item.url}
            target="_blank"
            startIcon={<BookOpenIcon size="xs" />}
          >
            <Trans {...item.label} />
          </Item>
        ))}
      </Menu>
    </MenuTrigger>
  );
}

function ToggleButton() {
  const isMobile = useIsMobileMediaQuery();
  const updateSettings = useUpdateAiAgentSettings();
  const selectedAiAgent = useSelectedAiAgent();

  if (selectedAiAgent.enabled) {
    return (
      <Button
        variant="flat"
        color="chip"
        size="xs"
        startIcon={<MediaPauseIcon />}
        disabled={updateSettings.isPending}
        onClick={() => updateSettings.mutate({enabled: false})}
        className={isMobile ? 'min-w-90' : 'min-w-140'}
      >
        {isMobile ? (
          <Trans message="Pause" />
        ) : (
          <Trans message="Pause AI Agent" />
        )}
      </Button>
    );
  } else {
    return (
      <Button
        variant="flat"
        color="positive"
        size="xs"
        startIcon={<MediaPlayIcon />}
        disabled={updateSettings.isPending}
        onClick={() => updateSettings.mutate({enabled: true})}
        className={isMobile ? 'min-w-90' : 'min-w-140'}
      >
        {isMobile ? (
          <Trans message="Enable" />
        ) : (
          <Trans message="Enable AI Agent" />
        )}
      </Button>
    );
  }
}
