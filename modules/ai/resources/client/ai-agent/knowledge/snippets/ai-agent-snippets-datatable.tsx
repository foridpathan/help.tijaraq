import {aiAgentQueries} from '@ai/ai-agent/ai-agent-queries';
import {AiAgentSnippet} from '@ai/ai-agent/knowledge/snippets/ai-agent-snippet';
import {useIngestSnippets} from '@ai/ai-agent/knowledge/snippets/use-ingest-snippets';
import {useUningestSnippets} from '@ai/ai-agent/knowledge/snippets/use-uningest-snippets';
import {tagsFilter} from '@app/help-center/articles/article-datatable/article-datatable-filters';
import onlineArticlesImg from '@app/help-center/articles/article-datatable/online-articles.svg';
import {SyncTagsButton} from '@app/help-center/sync-tags-button';
import {GlobalLoadingProgress} from '@common/core/global-loading-progress';
import {ColumnConfig} from '@common/datatable/column-config';
import {DataTableAddItemButton} from '@common/datatable/data-table-add-item-button';
import {DataTableHeader} from '@common/datatable/data-table-header';
import {DataTablePaginationFooter} from '@common/datatable/data-table-pagination-footer';
import {
  BackendFilter,
  FilterControlType,
  FilterOperator,
} from '@common/datatable/filters/backend-filter';
import {createdAtFilter} from '@common/datatable/filters/timestamp-filters';
import {useDatatableSearchParams} from '@common/datatable/filters/utils/use-datatable-search-params';
import {validateDatatableSearch} from '@common/datatable/filters/utils/validate-datatable-search';
import {DataTableEmptyStateMessage} from '@common/datatable/page/data-table-emty-state-message';
import {DatatableFilters} from '@common/datatable/page/datatable-filters';
import {
  DatatablePageHeaderBar,
  DatatablePageScrollContainer,
  DatatablePageWithHeaderBody,
  DatatablePageWithHeaderLayout,
} from '@common/datatable/page/datatable-page-with-header-layout';
import {DeleteSelectedItemsAction} from '@common/datatable/page/delete-selected-items-action';
import {useDatatableQuery} from '@common/datatable/requests/use-datatable-query';
import {queryClient} from '@common/http/query-client';
import {StaticPageTitle} from '@common/seo/static-page-title';
import {useRequiredParams} from '@common/ui/navigation/use-required-params';
import {Table} from '@common/ui/tables/table';
import {Breadcrumb} from '@ui/breadcrumbs/breadcrumb';
import {BreadcrumbItem} from '@ui/breadcrumbs/breadcrumb-item';
import {Button} from '@ui/buttons/button';
import {IconButton} from '@ui/buttons/icon-button';
import {Chip} from '@ui/forms/input-field/chip-field/chip';
import {ChipList} from '@ui/forms/input-field/chip-field/chip-list';
import {Item} from '@ui/forms/listbox/item';
import {FormattedDate} from '@ui/i18n/formatted-date';
import {message} from '@ui/i18n/message';
import {Trans} from '@ui/i18n/trans';
import {CheckIcon} from '@ui/icons/material/Check';
import {CloseIcon} from '@ui/icons/material/Close';
import {EditIcon} from '@ui/icons/material/Edit';
import {KeyboardArrowDownIcon} from '@ui/icons/material/KeyboardArrowDown';
import {Menu, MenuTrigger} from '@ui/menu/menu-trigger';
import {useState} from 'react';
import {Link} from 'react-router';

const columns: ColumnConfig<AiAgentSnippet>[] = [
  {
    key: 'name',
    width: 'flex-3 min-w-200',
    visibleInMode: 'all',
    header: () => <Trans message="Title" />,
    body: snippet => (
      <div className="flex items-center gap-6">
        {!!snippet.tags.length && (
          <ChipList size="xs">
            {snippet.tags.slice(0, 3).map(tag => (
              <Chip key={tag}>{tag}</Chip>
            ))}
          </ChipList>
        )}
        <Link
          to={`../knowledge/snippets/${snippet.id}/edit`}
          className="hover:underline"
        >
          {snippet.title}
        </Link>
      </div>
    ),
  },
  {
    key: 'fully_scanned',
    allowsSorting: true,
    width: 'w-144 flex-shrink-0',
    header: () => <Trans message="Status" />,
    body: snippet => {
      if (snippet.scan_pending) {
        return (
          <Chip color="danger" size="xs">
            <Trans message="Scan pending" />
          </Chip>
        );
      }
      return (
        <Chip color="positive" size="xs">
          <Trans message="Scanned" />
        </Chip>
      );
    },
  },
  {
    key: 'used_by_ai_agent',
    allowsSorting: true,
    width: 'w-100 flex-shrink-0',
    header: () => <Trans message="AI agent" />,
    body: snippet =>
      snippet.used_by_ai_agent ? (
        <CheckIcon size="md" className="text-positive" />
      ) : (
        <CloseIcon size="md" className="text-muted" />
      ),
  },
  {
    key: 'created_at',
    allowsSorting: true,
    width: 'w-144',
    header: () => <Trans message="Scan date" />,
    body: snippet => (
      <time>
        <FormattedDate date={snippet.created_at} />
      </time>
    ),
  },
  {
    key: 'actions',
    header: () => <Trans message="Actions" />,
    width: 'w-42 flex-shrink-0',
    hideHeader: true,
    align: 'end',
    visibleInMode: 'all',
    body: snippet => (
      <div className="text-muted">
        <IconButton
          size="md"
          elementType={Link}
          to={`../knowledge/snippets/${snippet.id}/edit`}
          disabled={snippet.scan_pending}
        >
          <EditIcon />
        </IconButton>
      </div>
    ),
  },
];

const filters: BackendFilter[] = [
  {
    key: 'scan_pending',
    label: message('Fully scanned'),
    description: message('Whether snippet is fully scanned'),
    defaultOperator: FilterOperator.eq,
    control: {
      type: FilterControlType.Select,
      defaultValue: '0',
      options: [
        {value: true, label: 'No', key: '1'},
        {value: false, label: 'Yes', key: '0'},
      ],
    },
  },
  {
    key: 'used_by_ai_agent',
    label: message('AI Agent status'),
    defaultOperator: FilterOperator.eq,
    control: {
      type: FilterControlType.Select,
      options: [
        {key: '01', value: true, label: message('Used by AI agent')},
        {key: '02', value: false, label: message('Not used by AI agent')},
      ],
      defaultValue: '01',
    },
  },
  tagsFilter(),
  createdAtFilter({
    label: message('Creation date'),
    description: message('Date snippet was created'),
  }),
];

export function Component() {
  const {aiAgentId} = useRequiredParams(['aiAgentId']);
  const [selectedIds, setSelectedIds] = useState<(number | string)[]>([]);
  const {
    searchParams,
    sortDescriptor,
    mergeIntoSearchParams,
    setSearchQuery,
    isFiltering,
  } = useDatatableSearchParams(validateDatatableSearch);

  const query = useDatatableQuery(
    aiAgentQueries.snippets.index(aiAgentId, searchParams),
  );

  return (
    <DatatablePageWithHeaderLayout>
      <GlobalLoadingProgress query={query} />
      <StaticPageTitle>
        <Trans message="Snippets" />
      </StaticPageTitle>
      <DatatablePageHeaderBar showSidebarToggleButton>
        <Breadcrumb size="xl">
          <BreadcrumbItem to="../knowledge">
            <Trans message="Knowledge" />
          </BreadcrumbItem>
          <BreadcrumbItem to="../knowledge/snippets">
            <Trans message="Snippets" />
          </BreadcrumbItem>
        </Breadcrumb>
      </DatatablePageHeaderBar>
      <DatatablePageWithHeaderBody>
        <DataTableHeader
          searchValue={searchParams.query}
          onSearchChange={setSearchQuery}
          filters={filters}
          actions={<Actions />}
          selectedItems={selectedIds}
          selectedActions={
            <SelectedActions
              selectedIds={selectedIds}
              onActionDone={() => {
                queryClient.invalidateQueries({
                  queryKey: aiAgentQueries.snippets.invalidateKey,
                });
                setSelectedIds([]);
              }}
            />
          }
        />
        <DatatableFilters filters={filters} />
        <DatatablePageScrollContainer>
          <Table
            columns={columns}
            data={query.items}
            enableSelection
            selectedRows={selectedIds}
            onSelectionChange={setSelectedIds}
            sortDescriptor={sortDescriptor}
            onSortChange={mergeIntoSearchParams}
            cellHeight="h-64"
          />
          {query.isEmpty ? (
            <DataTableEmptyStateMessage
              isFiltering={isFiltering}
              image={onlineArticlesImg}
              title={<Trans message="No snippets have been created yet" />}
              filteringTitle={<Trans message="No matching snippets" />}
            />
          ) : null}
          <DataTablePaginationFooter
            query={query}
            onPageChange={page => mergeIntoSearchParams({page})}
            onPerPageChange={perPage => mergeIntoSearchParams({perPage})}
          />
        </DatatablePageScrollContainer>
      </DatatablePageWithHeaderBody>
    </DatatablePageWithHeaderLayout>
  );
}

function Actions() {
  return (
    <DataTableAddItemButton elementType={Link} to="new">
      <Trans message="Add snippet" />
    </DataTableAddItemButton>
  );
}

type SelectedActionsProps = {
  selectedIds: (number | string)[];
  onActionDone: () => void;
};
function SelectedActions({selectedIds, onActionDone}: SelectedActionsProps) {
  return (
    <div className="flex gap-8">
      <ChangeStateButton
        selectedIds={selectedIds}
        onActionDone={onActionDone}
      />
      <SyncTagsButton
        taggableType="aiAgentSnippet"
        selectedIds={selectedIds}
        onTagsSynced={() => onActionDone()}
      />
      <DeleteSelectedItemsAction />
    </div>
  );
}
function ChangeStateButton({selectedIds, onActionDone}: SelectedActionsProps) {
  const ingestSnippets = useIngestSnippets();
  const uningestSnippets = useUningestSnippets();
  return (
    <MenuTrigger>
      <Button
        variant="outline"
        endIcon={<KeyboardArrowDownIcon />}
        disabled={ingestSnippets.isPending || uningestSnippets.isPending}
      >
        <Trans message="Change AI agent state" />
      </Button>
      <Menu>
        <Item
          value="enable"
          startIcon={<CheckIcon size="sm" />}
          onSelected={() =>
            ingestSnippets.mutate(
              {snippetIds: selectedIds},
              {onSuccess: () => onActionDone()},
            )
          }
        >
          <Trans message="Enable for AI agent" />
        </Item>
        <Item
          value="disable"
          startIcon={<CloseIcon size="sm" />}
          onSelected={() =>
            uningestSnippets.mutate(
              {snippetIds: selectedIds},
              {onSuccess: () => onActionDone()},
            )
          }
        >
          <Trans message="Disable for AI agent" />
        </Item>
      </Menu>
    </MenuTrigger>
  );
}
