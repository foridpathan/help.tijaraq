import {DatatableSearchParams} from '@common/datatable/filters/utils/validate-datatable-search';
import {castObjectValuesToString} from '@ui/utils/objects/cast-object-values-to-string';

export type ArticleDatatableSearchParams = {
  aiAgentId?: string;
} & DatatableSearchParams;

export const validateArticleDatatableSearch = (
  search: Record<string, unknown>,
  aiAgentId?: string,
): ArticleDatatableSearchParams => {
  return castObjectValuesToString({
    page: search.page || '1',
    perPage: search.perPage || '15',
    orderBy: search.orderBy || '',
    orderDir: search.orderDir || '',
    query: search.query || '',
    filters: search.filters || '',
    with: search.with || '',
    withCount: search.withCount || '',
    paginate: search.paginate || 'preferLengthAware',
    aiAgentId: aiAgentId || '',
  });
};
