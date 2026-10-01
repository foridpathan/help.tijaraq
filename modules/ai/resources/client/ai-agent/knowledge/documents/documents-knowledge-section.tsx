import {aiAgentQueries} from '@ai/ai-agent/ai-agent-queries';
import {AiAgentDocument} from '@ai/ai-agent/knowledge/documents/ai-agent-document';
import {IngestDocumentsDialog} from '@ai/ai-agent/knowledge/documents/ingest-documents-dialog';
import {KnowledgePageSectionLayout} from '@ai/ai-agent/knowledge/knowledge-page-section-layout';
import {KnowledgeSectionItem} from '@ai/ai-agent/knowledge/knowledge-section-item';
import {DeleteKnowledgeItemDialog} from '@ai/ai-agent/knowledge/preview/knowledge-preview-layout';
import {useKnowledge} from '@ai/ai-agent/knowledge/use-knowledge';
import {apiClient, queryClient} from '@common/http/query-client';
import {showHttpErrorToast} from '@common/http/show-http-error-toast';
import {FileTypeIcon} from '@common/uploads/components/file-type-icon/file-type-icon';
import {FileUploadProvider} from '@common/uploads/uploader/file-upload-provider';
import {useMutation} from '@tanstack/react-query';
import {Button} from '@ui/buttons/button';
import {IconButton} from '@ui/buttons/icon-button';
import {Item} from '@ui/forms/listbox/item';
import {FormattedRelativeTime} from '@ui/i18n/formatted-relative-time';
import {message} from '@ui/i18n/message';
import {Trans} from '@ui/i18n/trans';
import {ArrowOutwardIcon} from '@ui/icons/arrow-outward';
import {FileInputIcon} from '@ui/icons/lucide/file-input';
import {AddIcon} from '@ui/icons/material/Add';
import {ArrowDropDownIcon} from '@ui/icons/material/ArrowDropDown';
import {ContentCopyIcon} from '@ui/icons/material/ContentCopy';
import {DeleteIcon} from '@ui/icons/material/Delete';
import {VisibilityIcon} from '@ui/icons/material/Visibility';
import {Menu, MenuTrigger} from '@ui/menu/menu-trigger';
import {DialogTrigger} from '@ui/overlays/dialog/dialog-trigger';
import {toast} from '@ui/toast/toast';
import {Fragment, useState} from 'react';
import {Link, useNavigate} from 'react-router';

export function DocumentsKnowledgeSection() {
  const {data} = useKnowledge();
  return (
    <KnowledgePageSectionLayout
      icon={<FileInputIcon size="md" />}
      title={
        <Link to="../knowledge/documents" className="hover:underline">
          <Trans message="Documents" />
        </Link>
      }
      description={
        <Trans message="Import content from documents that are not available publicly" />
      }
      action={
        <>
          <DialogTrigger type="modal">
            <Button startIcon={<AddIcon />} variant="outline" size="xs">
              <Trans message="Upload documents" />
            </Button>
            <FileUploadProvider>
              <IngestDocumentsDialog />
            </FileUploadProvider>
          </DialogTrigger>
          <IconButton
            size="xs"
            variant="outline"
            className="ml-6"
            elementType={Link}
            to="../knowledge/documents"
          >
            <ArrowOutwardIcon />
          </IconButton>
        </>
      }
    >
      {data?.documents.items.map(document => (
        <DocumentRow key={document.id} document={document} />
      ))}
      <MoreDocumentsRow />
    </KnowledgePageSectionLayout>
  );
}

function MoreDocumentsRow() {
  const {data} = useKnowledge();
  if (!data?.documents.more.count) return null;

  const link = '../knowledge/documents';
  return (
    <KnowledgeSectionItem
      scanPending={data.documents.more.ingesting}
      to={link}
      name={
        <Trans
          message="And :count more documents"
          values={{count: data.documents.more.count}}
        />
      }
      icon={<ContentCopyIcon size="sm" />}
      actions={
        <Button
          variant="outline"
          size="xs"
          className="min-w-96"
          elementType={Link}
          to={link}
        >
          <Trans message="View all" />
        </Button>
      }
    />
  );
}

interface DocumentRowProps {
  document: AiAgentDocument;
}
function DocumentRow({document}: DocumentRowProps) {
  if (!document.file_entry) return null;
  return (
    <KnowledgeSectionItem
      name={document.file_entry.name}
      to={`../knowledge/documents/${document.id}`}
      icon={
        <FileTypeIcon
          type={document.file_entry.type}
          mime={document.file_entry.mime}
          color="text-main"
        />
      }
      scanPending={document.scan_pending}
      scanFailed={document.scan_failed}
      description={
        <Fragment>
          {document.scan_pending ? (
            <Trans message="Scanning..." />
          ) : document.scan_failed ? (
            <span className="text-danger">
              <Trans message="Could not extract content from this document" />
            </span>
          ) : (
            <Trans
              message="Imported: :date"
              values={{
                date: <FormattedRelativeTime date={document.updated_at} />,
              }}
            />
          )}
        </Fragment>
      }
      actions={<RowOptionsTrigger document={document} />}
    />
  );
}

interface RowOptionsTriggerProps {
  document: AiAgentDocument;
}
function RowOptionsTrigger({document}: RowOptionsTriggerProps) {
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const navigate = useNavigate();
  return (
    <Fragment>
      <MenuTrigger>
        <Button variant="outline" size="xs" endIcon={<ArrowDropDownIcon />}>
          <Trans message="Manage" />
        </Button>
        <Menu>
          {!(document.scan_failed && !document.scan_pending) && (
            <Item
              value="view"
              startIcon={<VisibilityIcon size="sm" />}
              onSelected={() => {
                navigate(`../knowledge/documents/${document.id}`);
              }}
            >
              <Trans message="View content" />
            </Item>
          )}
          <Item
            value="delete"
            startIcon={<DeleteIcon size="sm" />}
            className="text-danger"
            onSelected={() => setDeleteDialogOpen(true)}
          >
            <Trans message="Delete document" />
          </Item>
        </Menu>
      </MenuTrigger>
      {deleteDialogOpen && (
        <DialogTrigger
          type="modal"
          isOpen={deleteDialogOpen}
          onOpenChange={setDeleteDialogOpen}
        >
          <DeleteDocumentDialog documentId={document.id} />
        </DialogTrigger>
      )}
    </Fragment>
  );
}

interface DeleteDocumentDialogProps {
  documentId: string | number;
}
function DeleteDocumentDialog({documentId}: DeleteDocumentDialogProps) {
  const deleteDocuments = useDeleteDocuments();
  return (
    <DeleteKnowledgeItemDialog
      onDelete={() => {
        return deleteDocuments.mutateAsync({
          documentIds: [documentId],
        });
      }}
      isPending={deleteDocuments.isPending}
    />
  );
}

export function useDeleteDocuments() {
  return useMutation({
    mutationFn: async ({documentIds}: {documentIds: (string | number)[]}) => {
      return apiClient.delete(`lc/ai-agent/documents/${documentIds}`);
    },
    onSuccess: () => {
      toast(message('Document deleted'));
      return queryClient.invalidateQueries({
        queryKey: aiAgentQueries.knowledge.invalidateKey,
      });
    },
    onError: err => showHttpErrorToast(err),
  });
}
