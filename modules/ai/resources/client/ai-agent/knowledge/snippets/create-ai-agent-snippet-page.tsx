import {aiAgentQueries} from '@ai/ai-agent/ai-agent-queries';
import {UploadType} from '@app/site-config';
import ArticleEditorPage from '@common/article-editor/article-editor-page';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {StaticPageTitle} from '@common/seo/static-page-title';
import {useNavigate} from '@common/ui/navigation/use-navigate';
import {useRequiredParams} from '@common/ui/navigation/use-required-params';
import {useMutation} from '@tanstack/react-query';
import {Breadcrumb} from '@ui/breadcrumbs/breadcrumb';
import {BreadcrumbItem} from '@ui/breadcrumbs/breadcrumb-item';
import {Button} from '@ui/buttons/button';
import {message} from '@ui/i18n/message';
import {Trans} from '@ui/i18n/trans';
import {toast} from '@ui/toast/toast';
import {Fragment} from 'react';
import {FormProvider, useForm} from 'react-hook-form';

interface Payload {
  title: string;
  body: string;
  aiAgentId: number | string;
}

export function Component() {
  const {aiAgentId} = useRequiredParams(['aiAgentId']);
  const navigate = useNavigate();
  const createSnippet = useCreateSnippet();
  const form = useForm<Payload>();

  const handleSave = () => {
    createSnippet.mutate(
      {
        ...form.getValues(),
        aiAgentId,
      },
      {
        onSuccess: () => navigate('../', {relative: 'path'}),
      },
    );
  };

  const breadCrumb = (
    <Breadcrumb size="xl">
      <BreadcrumbItem to="../knowledge">
        <Trans message="Knowledge" />
      </BreadcrumbItem>
      <BreadcrumbItem to="../knowledge/snippets">
        <Trans message="Snippets" />
      </BreadcrumbItem>
      <BreadcrumbItem>
        <Trans message="New" />
      </BreadcrumbItem>
    </Breadcrumb>
  );

  const saveButton = (
    <Button
      type="submit"
      variant="flat"
      color="primary"
      size="xs"
      disabled={createSnippet.isPending}
      onClick={() => handleSave()}
    >
      <Trans message="Create" />
    </Button>
  );

  return (
    <Fragment>
      <StaticPageTitle>
        <Trans message="New snippet" />
      </StaticPageTitle>
      <FormProvider {...form}>
        <ArticleEditorPage
          imageUploadType={UploadType.articleImages}
          title={breadCrumb}
          saveButton={saveButton}
          onChange={value => {
            form.setValue('body', value, {shouldDirty: true});
          }}
        />
      </FormProvider>
    </Fragment>
  );
}

function useCreateSnippet() {
  return useMutation({
    mutationFn: (payload: Payload) =>
      apiClient.post('lc/ai-agent/snippets', payload).then(r => r.data),
    onError: err => showHttpErrorToast(err),
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: aiAgentQueries.knowledge.invalidateKey,
      });
      toast(message('Snippet created'));
    },
  });
}
