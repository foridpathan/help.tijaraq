import {getArticleLink} from '@app/help-center/articles/article-link';
import {
  CategoryLink,
  getCategoryLink,
} from '@app/help-center/categories/category-link';
import {CategoryPageData} from '@app/help-center/categories/category-page-data';
import {helpCenterQueries} from '@app/help-center/help-center-queries';
import {HcSearchBar} from '@app/help-center/search/hc-search-bar';
import {Footer} from '@common/ui/footer/footer';
import {Navbar} from '@common/ui/navigation/navbar/navbar';
import {useNavigate} from '@common/ui/navigation/use-navigate';
import {useSuspenseQuery} from '@tanstack/react-query';
import {Breadcrumb} from '@ui/breadcrumbs/breadcrumb';
import {BreadcrumbItem} from '@ui/breadcrumbs/breadcrumb-item';
import {Trans} from '@ui/i18n/trans';
import {ChevronRightIcon} from '@ui/icons/material/ChevronRight';
import {useSettings} from '@ui/settings/use-settings';
import {slugifyString} from '@ui/utils/string/slugify-string';
import '@app/help-center/hc-content-header.css';
import clsx from 'clsx';
import {useEffect, useRef} from 'react';
import {Link, useParams} from 'react-router';

export function Component() {
  const alreadyScrolled = useRef(false);
  const {hcLanding} = useSettings();
  const {categoryId, sectionId, categorySlug, categoryPart, sectionPart} =
    useParams();
  const activeCategorySlug =
    categorySlug ||
    (!/^\d+$/.test(categoryPart || '') ? categoryPart : undefined);
  const sectionSlug = activeCategorySlug ? sectionPart : undefined;

  const query = useSuspenseQuery(
    activeCategorySlug
      ? helpCenterQueries.categories.getBySlug(activeCategorySlug, sectionSlug)
      : helpCenterQueries.categories.get(
          sectionId || categoryId || categoryPart!,
        ),
  );
  const category = query.data.category;
  const visibleSections = category.is_section
    ? query.data.categoryNav.filter(section => section.id === category.id)
    : query.data.categoryNav;
  const visibleArticleCount = visibleSections.reduce(
    (total, section) => total + section.articles.length,
    0,
  );

  useEffect(() => {
    if ((sectionId || sectionSlug) && !alreadyScrolled.current) {
      const element = document.getElementById(`section-${category.id}`);
      if (element) {
        element.scrollIntoView({
          behavior: 'smooth',
          block: 'center',
        });
        alreadyScrolled.current = true;
      }
    }
  }, [sectionId, sectionSlug, category.id]);

  return (
    <div>
      <Navbar
        color="bg"
        menuPosition="header"
        className="hc-content-navbar sticky top-0 z-10 flex-shrink-0"
        size="md"
      >
        <HcSearchBar
          categoryId={category.is_section ? category.parent_id : category?.id}
        />
      </Navbar>
      <div className="hc-category-page">
        <PageBreadcrumb category={category} />
        <header className="hc-category-hero">
          <div className="hc-category-hero-copy">
            <span className="hc-content-eyebrow">
              <Trans message={category.is_section ? 'SECTION' : 'CATEGORY'} />
            </span>
            <h1>{category.name}</h1>
            {category.description && <p>{category.description}</p>}
            <div className="hc-category-hero-meta">
              {!category.is_section && (
                <>
                  <span><Trans message=":count sections" values={{count: visibleSections.length}} /></span>
                  <span aria-hidden="true">·</span>
                </>
              )}
              <span>
                <Trans
                  message=":count articles"
                  values={{count: visibleArticleCount}}
                />
              </span>
            </div>
          </div>
          {category.image && <img src={category.image} alt="" className="hc-category-hero-image" />}
        </header>
        <div className="hc-category-layout">
          <aside className="hc-category-aside">
            <span className="hc-content-eyebrow"><Trans message="IN THIS CATEGORY" /></span>
            <nav aria-label="Sections">
              {query.data.categoryNav.map(section => (
                <a key={section.id} href={`#section-${section.id}`}>
                  <span>{section.name}</span>
                  <span>{section.articles.length}</span>
                </a>
              ))}
            </nav>
          </aside>
          <main className="hc-category-sections">
            <div className="hc-category-list-heading">
              <span className="hc-content-eyebrow"><Trans message="BROWSE GUIDES" /></span>
              <h2><Trans message={category.is_section ? 'Explore articles' : 'Explore sections'} /></h2>
            </div>
            {visibleSections.map((section, index) => (
              <section
                key={section.id}
                id={`section-${section.id}`}
                className={clsx(
                  'hc-category-section',
                  (sectionId === `${section.id}` || sectionSlug === slugifyString(section.name)) && 'hc-category-section--active',
                )}
              >
                <div className="hc-category-section-heading">
                  <span className="hc-category-section-index">{String(index + 1).padStart(2, '0')}</span>
                  <div>
                    <span className="hc-content-eyebrow"><Trans message="SECTION" /></span>
                    <h3><CategoryLink category={section} /></h3>
                    <p><Trans message=":count articles" values={{count: section.articles.length}} /></p>
                  </div>
                </div>
                <div className="hc-category-article-list">
                  {section.articles.map(article => (
                    <Link key={article.id} to={getArticleLink(article, {section})} className="hc-category-article-link">
                      <span>{article.title}</span>
                      <ChevronRightIcon size="sm" />
                    </Link>
                  ))}
                </div>
              </section>
            ))}
          </main>
        </div>
      </div>
      {hcLanding?.show_footer && <Footer className="px-40" />}
    </div>
  );
}

interface PageBreadcrumbProps {
  category: CategoryPageData['category'];
}
function PageBreadcrumb({category}: PageBreadcrumbProps) {
  const navigate = useNavigate();
  const categories: {id: number; name: string}[] = [category];
  if (
    category.is_section &&
    category.parent
  ) {
    categories.unshift(category.parent);
  }

  return (
    <Breadcrumb size="sm" className="-ml-6">
      <BreadcrumbItem onSelected={() => navigate(`/hc`)}>
        <Trans message="Help center" />
      </BreadcrumbItem>
      {categories.map(category => (
        <BreadcrumbItem
          key={category.id}
          onSelected={() => navigate(getCategoryLink(category))}
        >
          <Trans message={category.name} />
        </BreadcrumbItem>
      ))}
    </Breadcrumb>
  );
}
