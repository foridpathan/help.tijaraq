import {aiAgentQueries} from '@ai/ai-agent/ai-agent-queries';
import {PreviewSidebar} from '@ai/ai-agent/preview/preview-sidebar';
import {CantAssistPanel} from '@ai/ai-agent/settings/cant-assist-panel';
import {GreetingPanel} from '@ai/ai-agent/settings/greeting-panel';
import {IdentityPanel} from '@ai/ai-agent/settings/identity-panel';
import {PersonalityPanel} from '@ai/ai-agent/settings/personality-panel';
import {TransferPanel} from '@ai/ai-agent/settings/transfer-panel';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {Accordion} from '@common/ui/library/accordion/accordion';
import {Button} from '@common/ui/library/buttons/button';
import {Trans} from '@common/ui/library/i18n/trans';
import {ArrowForwardIcon} from '@common/ui/library/icons/material/ArrowForward';
import {useRequiredParams} from '@common/ui/navigation/use-required-params';
import {useMutation, useSuspenseQuery} from '@tanstack/react-query';
import {message} from '@ui/i18n/message';
import {ConfirmationDialog} from '@ui/overlays/dialog/confirmation-dialog';
import {useDialogContext} from '@ui/overlays/dialog/dialog-context';
import {DialogTrigger} from '@ui/overlays/dialog/dialog-trigger';
import {toast} from '@ui/toast/toast';
import {useState} from 'react';
import {Link, useNavigate} from 'react-router';
import {Fragment} from 'react/jsx-runtime';
import {AiAgentPageHeader} from '../ai-agent-page-header';

export function Component() {
  const [previewVisible, setPreviewVisible] = useState(false);
  const {aiAgentId} = useRequiredParams(['aiAgentId']);
  // re-render on selected agent change, so forms are reset to new values
  return (
    <Fragment key={aiAgentId}>
      <div className="dashboard-grid-content dashboard-rounded-panel flex h-full flex-col">
        <AiAgentPageHeader
          previewVisible={previewVisible}
          onTogglePreview={() => setPreviewVisible(!previewVisible)}
        />
        <div className="flex-auto overflow-y-auto p-24">
          <div>
            <Accordion variant="outline" gap="space-y-24">
              <IdentityPanel />
              <PersonalityPanel />
              <GreetingPanel />
              <CantAssistPanel />
              <TransferPanel />
            </Accordion>
            <div className="mt-24 flex items-center justify-between gap-24 pl-8">
              <Button
                elementType={Link}
                to="/admin/settings/ai"
                variant="link"
                color="primary"
                startIcon={<ArrowForwardIcon />}
              >
                <Trans message="Additional AI settings" />
              </Button>
              <DialogTrigger type="modal">
                <Button variant="text" size="xs">
                  <Trans message="Delete AI Agent" />
                </Button>
                <DeleteAiAgentDialog />
              </DialogTrigger>
            </div>
          </div>
        </div>
      </div>
      {previewVisible && (
        <PreviewSidebar onClose={() => setPreviewVisible(false)} />
      )}
    </Fragment>
  );
}

function DeleteAiAgentDialog() {
  const {data} = useSuspenseQuery(aiAgentQueries.agents.index());
  const {close} = useDialogContext();
  const navigate = useNavigate();
  const {aiAgentId} = useRequiredParams(['aiAgentId']);
  const deleteAiAgent = useMutation({
    mutationFn: () => apiClient.delete(`lc/ai-agents/${aiAgentId}`),
    onSuccess: () => {
      const nextAgentId = data.aiAgents.find(
        agent => `${agent.id}` !== aiAgentId,
      )?.id;
      if (nextAgentId) {
        navigate(`../../${nextAgentId}`, {replace: true});
      }
      queryClient.invalidateQueries({
        queryKey: aiAgentQueries.invalidateKey,
      });
      toast(message('AI Agent deleted'));
      close();
    },
    onError: err => showHttpErrorToast(err),
  });
  return (
    <ConfirmationDialog
      isDanger
      title={<Trans message="Delete AI Agent" />}
      body={<Trans message="Are you sure you want to delete this AI agent?" />}
      onConfirm={() => {
        deleteAiAgent.mutate();
      }}
      confirm={<Trans message="Delete" />}
      isLoading={deleteAiAgent.isPending}
    />
  );
}
