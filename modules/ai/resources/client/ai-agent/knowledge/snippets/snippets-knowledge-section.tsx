import {useUningestSnippets} from '@ai/ai-agent/knowledge/snippets/use-uningest-snippets';
import {useKnowledge} from '@ai/ai-agent/knowledge/use-knowledge';
import {Button} from '@ui/buttons/button';
import {IconButton} from '@ui/buttons/icon-button';
import {Item} from '@ui/forms/listbox/item';
import {FormattedRelativeTime} from '@ui/i18n/formatted-relative-time';
import {Trans} from '@ui/i18n/trans';
import {SquareScissorsIcon} from '@ui/icons/lucide/square-scissors';
import {ArticleIcon} from '@ui/icons/material/Article';
import {ContentCopyIcon} from '@ui/icons/material/ContentCopy';
import {MoreVertIcon} from '@ui/icons/material/MoreVert';
import {Menu, MenuTrigger} from '@ui/menu/menu-trigger';
import {Fragment} from 'react';
import {Link} from 'react-router';
import {KnowledgePageSectionLayout} from '../knowledge-page-section-layout';
import {KnowledgeSectionItem} from '../knowledge-section-item';
import {AiAgentSnippet} from './ai-agent-snippet';
import {useIngestSnippets} from './use-ingest-snippets';

export function SnippetsKnowledgeSection() {
  const {data} = useKnowledge();
  return (
    <KnowledgePageSectionLayout
      icon={<SquareScissorsIcon size="md" />}
      title={
        <Link to="../knowledge/snippets" className="hover:underline">
          <Trans message="Snippets" />
        </Link>
      }
      description={
        <Trans message="Add custom FAQs, answers or other snippets." />
      }
      action={
        <div>
          <SnippetActions />
        </div>
      }
    >
      {data?.snippets.items.map(snippet => (
        <SnippetRow key={snippet.id} snippet={snippet} />
      ))}
      <MoreSnippetsRow />
    </KnowledgePageSectionLayout>
  );
}

function SnippetActions() {
  const {data} = useKnowledge();
  const ingestSnippets = useIngestSnippets();
  const uningestSnippets = useUningestSnippets();

  const isPending =
    ingestSnippets.isPending ||
    uningestSnippets.isPending ||
    data?.snippets.ingesting;

  return (
    <div className="flex items-center gap-8">
      <Button
        elementType={Link}
        to="../knowledge/snippets"
        size="xs"
        variant="outline"
      >
        <Trans message="Manage snippets" />
      </Button>
      <MenuTrigger>
        <IconButton size="xs" variant="outline" disabled={isPending}>
          <MoreVertIcon />
        </IconButton>
        <Menu>
          <Item
            value="enable"
            onSelected={() => ingestSnippets.mutate({all: true})}
          >
            <Trans message="Enable all snippets for AI agent" />
          </Item>
          <Item
            value="disable"
            onSelected={() => uningestSnippets.mutate({all: true})}
          >
            <Trans message="Disable all snippets for AI agent" />
          </Item>
        </Menu>
      </MenuTrigger>
    </div>
  );
}

function MoreSnippetsRow() {
  const {data} = useKnowledge();
  if (!data?.snippets.more.count) return null;

  const link = '../knowledge/snippets';
  return (
    <KnowledgeSectionItem
      scanPending={data.snippets.more.ingesting}
      to={link}
      name={
        <Trans
          message="And :count more snippets"
          values={{count: data.snippets.more.count}}
        />
      }
      icon={<ContentCopyIcon size="sm" />}
    />
  );
}

interface SnippetRowProps {
  snippet: AiAgentSnippet;
}
function SnippetRow({snippet}: SnippetRowProps) {
  return (
    <KnowledgeSectionItem
      name={snippet.title}
      to={`../knowledge/snippets/${snippet.id}/edit`}
      icon={<ArticleIcon />}
      scanPending={snippet.scan_pending}
      description={
        <Fragment>
          {snippet.scan_pending ? (
            <Trans message="Scanning..." />
          ) : (
            <Trans
              message="Updated: :date"
              values={{
                date: <FormattedRelativeTime date={snippet.updated_at} />,
              }}
            />
          )}
        </Fragment>
      }
    />
  );
}
