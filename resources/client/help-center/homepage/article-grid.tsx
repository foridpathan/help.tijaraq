import {ArticleLink} from '@app/help-center/articles/article-link';
import {CategoryLink} from '@app/help-center/categories/category-link';
import {HcCategoryImage} from '@app/help-center/hc-category-icons';
import {helpCenterQueries} from '@app/help-center/help-center-queries';
import {LandingPageDataCategory} from '@app/help-center/homepage/hc-landing-page-data';
import {useSuspenseQuery} from '@tanstack/react-query';
import {Trans} from '@ui/i18n/trans';
import {ArrowRightAltIcon} from '@ui/icons/material/ArrowRightAlt';
import {KeyboardArrowRightIcon} from '@ui/icons/material/KeyboardArrowRight';
import {useSettings} from '@ui/settings/use-settings';
import clsx from 'clsx';
import {Fragment} from 'react';

export function ArticleGrid() {
  const query = useSuspenseQuery(
    helpCenterQueries.categories.landingPageData(),
  );

  return (
    <div className="hc-home-article-groups">
      {query.data.categories.map(category => (
        <section key={category.id} className="hc-home-article-group">
          {query.data.categories.length > 1 && !category.hide_from_structure ? (
            <ParentCategoryHeader category={category} />
          ) : null}
          <CategoriesGrid categories={category.sections} />
        </section>
      ))}
    </div>
  );
}

type ParentCategoryHeaderProps = {
  category: LandingPageDataCategory;
};
function ParentCategoryHeader({category}: ParentCategoryHeaderProps) {
  if (!category.name) {
    return null;
  }
  return (
    <Fragment>
      <h2
        className={clsx(
          'hc-home-category-heading flex items-center gap-10',
          category.image && 'mb-6',
        )}
      >
        {category.image && (
          <HcCategoryImage src={category.image} className="h-30 w-30 rounded" />
        )}
        <CategoryLink category={category} />
      </h2>
      {category.description && (
        <p className="hc-home-category-description">{category.description}</p>
      )}
    </Fragment>
  );
}

type CategoriesGridProps = {
  categories: LandingPageDataCategory[];
};
function CategoriesGrid({categories}: CategoriesGridProps) {
  const {hcLanding} = useSettings();
  return (
    <div className="hc-home-article-grid">
      {categories.map(category => {
        if (hcLanding?.hide_small_categories && category.articles.length < 2) {
          return null;
        }
        return <ArticleGridItem key={category.id} category={category} />;
      })}
    </div>
  );
}

interface ArticleGridItemProps {
  category: LandingPageDataCategory;
}
function ArticleGridItem({category}: ArticleGridItemProps) {
  return (
    <div className="hc-home-article-card">
      <div className="hc-home-article-card-heading">
        <div className="flex items-center gap-4">
          {category.image && (
            <HcCategoryImage src={category.image} iconSize="w-20 h-20" />
          )}
          <h3>
            <CategoryLink category={category} />
          </h3>
        </div>
        <div className="hc-home-article-card-description">
          {category.description}
        </div>
      </div>

      <div className="hc-home-article-links">
        {category.articles.map(article => (
          <ArticleLink
            key={article.id}
            article={article}
            section={category}
            className="hc-home-article-link group flex items-center gap-8"
          >
            <span className="mr-auto block">{article.title}</span>
            <KeyboardArrowRightIcon
              size="sm"
              className="ease-in-out-energetic -translate-x-5 transform-gpu transition-transform duration-200 group-hover:translate-x-0 group-focus:translate-x-0"
            />
          </ArticleLink>
        ))}
      </div>

      {category.articles.length < category.articles_count && (
        <CategoryLink
          category={category}
          className="hc-home-see-all mt-auto flex items-center gap-4 font-semibold"
        >
          <Trans
            message="See all :count articles"
            values={{count: category.articles_count}}
          />
          <ArrowRightAltIcon />
        </CategoryLink>
      )}
    </div>
  );
}
