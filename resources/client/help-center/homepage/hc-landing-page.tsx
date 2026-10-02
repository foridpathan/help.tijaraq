import {ArticleGrid} from '@app/help-center/homepage/article-grid';
import {CategoryGrid} from '@app/help-center/homepage/category-grid';
import {ColorfulHeader} from '@app/help-center/homepage/colorful-header';
import {MultiProductArticleGrid} from '@app/help-center/homepage/multi-product-article-grid';
import {SimpleHeader} from '@app/help-center/homepage/simple-header';
import {AuthRoute} from '@common/auth/guards/auth-route';
import {Footer} from '@common/ui/footer/footer';
import {Trans} from '@ui/i18n/trans';
import {useSettings} from '@ui/settings/use-settings';
import './hc-modern-home.css';

export function Component() {
  const {hcLanding, branding} = useSettings();

  return (
    <AuthRoute requireLogin={false} permission="articles.view">
      <div className="hc-modern-home isolate">
        {hcLanding?.header?.variant === 'simple' ? (
          <SimpleHeader />
        ) : (
          <ColorfulHeader />
        )}
        <div className="hc-home-body">
          <main className="hc-home-main">
            <div className="hc-home-section-intro">
              <span>
                <Trans message="EXPLORE THE KNOWLEDGE BASE" />
              </span>
              <h2>
                <Trans message="Find the answers you need" />
              </h2>
              <p>
                <Trans message="Browse helpful guides and articles by topic." />
              </p>
            </div>
            <Content />
          </main>
        </div>
        {hcLanding?.show_footer && (
          <div className="hc-home-footer">
            <div className="hc-home-footer-inner">
              <div className="hc-home-footer-intro">
                <strong>{branding.site_name}</strong>
                <span>
                  {hcLanding?.header?.subtitle ? (
                    <Trans message={hcLanding.header.subtitle} />
                  ) : (
                    <Trans message="Helpful answers, all in one place." />
                  )}
                </span>
              </div>
              <Footer />
            </div>
          </div>
        )}
      </div>
    </AuthRoute>
  );
}

function Content() {
  const {hcLanding} = useSettings();

  if (hcLanding?.content?.variant === 'categoryGrid') {
    return <CategoryGrid />;
  }

  if (hcLanding?.content?.variant === 'multiProduct') {
    return <MultiProductArticleGrid />;
  }

  return <ArticleGrid />;
}
