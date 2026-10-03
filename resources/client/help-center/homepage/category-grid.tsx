import {getArticleLink} from '@app/help-center/articles/article-link';
import {
  CategoryLink,
  getCategoryLink,
} from '@app/help-center/categories/category-link';
import {HcCategoryImage} from '@app/help-center/hc-category-icons';
import {helpCenterQueries} from '@app/help-center/help-center-queries';
import {LandingPageDataCategory} from '@app/help-center/homepage/hc-landing-page-data';
import {useSuspenseQuery} from '@tanstack/react-query';
import {Trans} from '@ui/i18n/trans';
import {KeyboardArrowRightIcon} from '@ui/icons/material/KeyboardArrowRight';
import {Link} from 'react-router';

export function CategoryGrid() {
  const query = useSuspenseQuery(
    helpCenterQueries.categories.landingPageData(),
  );

  return (
    <div className="hc-home-category-groups">
      {query.data.categories.map((category, index) => (
        <section key={category.id} className="hc-home-category-group">
          <ParentCategoryHeader category={category} index={index + 1} />
          <CategoriesGrid categories={category.sections} />
        </section>
      ))}
      <PopularArticles />
    </div>
  );
}

type ParentCategoryHeaderProps = {
  category: LandingPageDataCategory;
  index: number;
};
function ParentCategoryHeader({category, index}: ParentCategoryHeaderProps) {
  if (!category.name) {
    return null;
  }
  return (
    <div className="hc-home-group-header">
      <div className="hc-home-group-identity">
        <span className="hc-home-group-index">{String(index).padStart(2, '0')}</span>
        {category.image && (
          <HcCategoryImage src={category.image} className="hc-home-group-image h-40 w-40 rounded" />
        )}
        <div>
          <span className="hc-home-group-label"><Trans message="CATEGORY" /></span>
          <h2 className="hc-home-category-heading">
            <CategoryLink category={category} />
          </h2>
          {category.description && (
            <p className="hc-home-category-description">{category.description}</p>
          )}
        </div>
      </div>
      <CategoryLink category={category} className="hc-home-group-link">
        <Trans message="Explore category" /> <KeyboardArrowRightIcon size="sm" />
      </CategoryLink>
    </div>
  );
}

type CategoriesGridProps = {
  categories: LandingPageDataCategory[];
};
function CategoriesGrid({categories}: CategoriesGridProps) {
  return (
    <div className="hc-home-category-grid">
      {categories.map(category => (
        <CategoryGridItem key={category.id} category={category} />
      ))}
    </div>
  );
}

interface CategoryGridItemProps {
  category: LandingPageDataCategory;
}
function CategoryGridItem({category}: CategoryGridItemProps) {
  return (
    <Link to={getCategoryLink(category)} className="hc-home-category-card">
      <span className="hc-home-card-eyebrow"><Trans message="SECTION" /></span>
      <div className="hc-home-category-card-top">
        {category.image && (
          <HcCategoryImage
            src={category.image}
            iconSize="w-28 h-28"
            className="hc-home-category-icon h-60 w-60 p-4"
          />
        )}
        <div>
          <h3>{category.name}</h3>
          <div className="text-sm text-muted">
            <Trans
              message=":count articles"
              values={{count: category.articles_count}}
            />
          </div>
        </div>
      </div>
      {category.description ? (
        <div className="hc-home-category-card-description">
          {category.description}
        </div>
      ) : null}
    </Link>
  );
}

function PopularArticles() {
  const query = useSuspenseQuery(
    helpCenterQueries.categories.landingPageData(),
  );

  if (!query.data.articles?.length) {
    return null;
  }

  return (
    <section className="hc-home-popular">
      <h2 className="hc-home-category-heading">
        <Trans message="Popular articles" />
      </h2>
      <div className="hc-home-popular-list">
        {query.data.articles?.map(article => (
          <Link
            key={article.id}
            className="hc-home-popular-link"
            to={getArticleLink(article)}
          >
            <div>
              <div className="text-base font-semibold">{article.title}</div>
              <p className="mt-2 text-sm text-muted">{article.body}</p>
            </div>
            <KeyboardArrowRightIcon size="sm" className="ml-auto text-muted" />
          </Link>
        ))}
      </div>
    </section>
  );
}
