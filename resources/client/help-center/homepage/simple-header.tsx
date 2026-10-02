import {SearchField} from '@app/help-center/homepage/colorful-header';
import {LandingPagePattern} from '@app/help-center/homepage/landing-page-pattern';
import {useLandingPageHeaderBackground} from '@app/help-center/homepage/use-landing-page-header-background';
import {Navbar} from '@common/ui/navigation/navbar/navbar';
import {Trans} from '@ui/i18n/trans';
import {message} from '@ui/i18n/message';
import {useTrans} from '@ui/i18n/use-trans';
import {useSettings} from '@ui/settings/use-settings';

export function SimpleHeader() {
  const {hcLanding} = useSettings();
  const config = hcLanding?.header;
  const cssProps = useLandingPageHeaderBackground();
  const {trans} = useTrans();
  return (
    <div className="hc-home-hero hc-home-hero--simple" style={cssProps}>
      <Navbar
        menuPosition="header"
        color="transparent"
        darkModeColor="transparent"
        textColor="text-main"
        logoColor="matchMode"
        wrapInContainer
        className="hc-home-navbar relative z-10"
      />
      <div className="hc-home-hero-content">
        {!cssProps && <LandingPagePattern blur />}
        <div className="relative z-10">
          <span className="hc-home-kicker">
            <Trans message="HELP CENTER" />
          </span>
          {config?.title && (
            <h1>
              <Trans message={config?.title} />
            </h1>
          )}
          {config?.subtitle && (
            <p className="hc-home-subtitle">
              <Trans message={config?.subtitle} />
            </p>
          )}
          <SearchField
            placeholder={
              config?.placeholder
                ? trans(message(config.placeholder))
                : undefined
            }
          />
        </div>
      </div>
    </div>
  );
}
