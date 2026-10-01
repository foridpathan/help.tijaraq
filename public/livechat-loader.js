(function (scriptSrc) {
  let iframe;

  // set to true when actual livechat app inside iframe is fully loaded
  let setIsBootstrapped;
  let isBootstrapped = new Promise(resolve => (setIsBootstrapped = resolve));

  // set to true when iframe is loaded
  let setIframeIsReady;
  let isIframeReady = new Promise(resolve => (setIframeIsReady = resolve));

  // time when iframe was loaded
  let loadedAt = performance.now();

  // callbacks to be called when livechat is destroyed
  let destroyCallbacks = [];

  // media query for toggling mobile mode
  const mobileMatchMedia = window.matchMedia('(max-width: 768px)');

  // widget config sent from parent via bootstrap message
  let widgetConfig;

  // max height for the iframe, there should be a gap to prevent
  // widget from scaling to full screen size on desktop
  const maxHeight = 'calc(100dvh - 40px)';

  // widget size sent from parent via resize message
  const widgetSize = {
    width: '0px',
    height: '0px',
    widgetState: 'closed',
  };

  const shouldFullScreenIframe = () => {
    return widgetSize.widgetState !== 'closed' && mobileMatchMedia.matches;
  };

  window.BeChat = {
    onLoaded: callback => {
      isBootstrapped.then(() => callback());
    },
    showLauncher: async () => {
      await isIframeReady;
      iframe.style.display = 'block';
    },
    hideLauncher: async () => {
      await isIframeReady;
      iframe.style.display = 'none';
    },
    setTheme: async themeId => {
      await isBootstrapped;
      iframe.contentWindow.postMessage(
        {
          source: 'livechat-loader',
          type: 'setTheme',
          themeId,
        },
        '*',
      );
    },
    setUserData: async userData => {
      await isBootstrapped;
      iframe.contentWindow.postMessage(
        {
          source: 'livechat-loader',
          type: 'setUserData',
          userData,
        },
        '*',
      );
    },
    destroy: async () => {
      iframe?.remove();
      iframe = null;
      isBootstrapped = new Promise(resolve => (setIsBootstrapped = resolve));
      setIframeIsReady = new Promise(resolve => (setIframeIsReady = resolve));
      loadedAt = null;
      destroyCallbacks.forEach(callback => callback());
      destroyCallbacks = [];
    },
  };

  // navigation
  const notifyOfNavigation = function () {
    iframe.contentWindow.postMessage(
      {
        source: 'livechat-loader',
        type: 'navigate',
        url: window.location.href,
        title: document.title,
        referrer: document.referrer,
        time: performance.now(),
        initialLoadTime: loadedAt,
      },
      '*',
    );
  };

  // unseen chats count in title
  function isCountInTitle() {
    return /^\(\d+\)\s/.test(document.title);
  }
  function addCountToTitle(number) {
    if (!isCountInTitle()) {
      const prefix = `(${number}) `;
      document.title = prefix + document.title;
    }
  }
  function removeCountFromTitle() {
    document.title = document.title.replace(/^\(\d+\)\s/, '');
  }

  // launcher and popup will have 16px padding to show shadow,
  // so we need to adjust user specified spacing, if it's more then 16px
  const setSpacing = function () {
    // on mobile, widget is full screen
    if (shouldFullScreenIframe()) {
      iframe.style.top = '0';
      iframe.style.bottom = '0';
      iframe.style.right = '0';
      iframe.style.left = '0';
      iframe.style.width = '100vw';
      iframe.style.height = '100vh';
      iframe.style.maxHeight = 'none';
      return;
    }

    const position = widgetConfig?.position ?? 'right';

    let bottom = widgetConfig?.spacing.bottom
      ? parseInt(widgetConfig.spacing.bottom)
      : 0;
    let side = widgetConfig?.spacing.side
      ? parseInt(widgetConfig.spacing.side)
      : 0;
    bottom = Math.max(bottom - 16, 0);
    side = Math.max(side - 16, 0);

    iframe.style.right = position === 'right' ? `${side}px` : '';
    iframe.style.left = position === 'left' ? `${side}px` : '';
    iframe.style.bottom = `${bottom}px`;
    iframe.style.removeProperty('top');
    iframe.style.maxHeight = maxHeight;
    iframe.style.width = widgetSize.width;
    iframe.style.height = widgetSize.height;
  };

  const executeLoader = () => {
    iframe = document.createElement('iframe');
    iframe.id = 'be-chat-iframe';

    let domain = window.BeChatSettings?.widgetDomain
      ? window.BeChatSettings?.widgetDomain
      : scriptSrc;

    if (!domain) {
      throw new Error('Could not resolve livechat domain');
    }

    const iframeUrl = new URL(domain);

    // include original pathname, in case bedesk is hosted on nested path e.g. site.com/bedesk
    let pathname = iframeUrl.pathname
      .replace('/livechat-loader.js', '')
      .replace(/^\/|\/$/g, '');
    iframeUrl.pathname = `${pathname}/lc/widget`;

    if (window.BeChatSettings?.user) {
      iframeUrl.searchParams.set(
        'user',
        btoa(JSON.stringify(window.BeChatSettings.user)),
      );
    }

    if (window.BeChatSettings?.aiAgentId) {
      iframeUrl.searchParams.set(
        'xWidgetAiAgentId',
        window.BeChatSettings.aiAgentId,
      );
    }

    if (window.BeChatSettings?.knowledgeScopeTag) {
      iframeUrl.searchParams.set(
        'xWidgetKnowledgeScopeTag',
        window.BeChatSettings.knowledgeScopeTag,
      );
    }

    const pageSearch = new URL(document.location.toString()).searchParams;
    if (pageSearch.get('beConversationId')) {
      iframeUrl.searchParams.set(
        'xWidgetConversationId',
        pageSearch.get('beConversationId'),
      );
    }

    if (mobileMatchMedia.matches) {
      iframeUrl.searchParams.set('xWidgetIsMobile', 'true');
    }

    iframe.src = iframeUrl.toString();
    iframe.style = `display: none; position: fixed; bottom: 0; right: 0; border: none; overflow: hidden; width: 0;height: 0; max-height: ${maxHeight}; z-index: 2147483639; outline: none; max-width: 100%; background-color: rgba(0,0,0,0); background-image: none; color-scheme: only light`;
    document.body.appendChild(iframe);
    setIframeIsReady();

    const messageListener = e => {
      if (e.data.source !== 'livechat-widget') return;

      // resize
      if (e.data.type === 'resize') {
        if (e.data.shouldTransition) {
          iframe.style.transition = 'width 0.125s, height 0.125s';
        } else {
          iframe.style.transition = 'none';
        }

        widgetSize.width = e.data.width;
        widgetSize.height = e.data.height;
        widgetSize.widgetState = e.data.widgetState;

        setSpacing();
      }

      // unseen chats count
      if (e.data.type === 'unseenChats') {
        if (e.data.action === 'addCountToTitle') {
          addCountToTitle(e.data.count);
        } else {
          removeCountFromTitle();
        }
      }

      // bootstrap
      if (e.data.type === 'bootstrap') {
        widgetConfig = e.data.widgetConfig;
        setSpacing();

        if (!e.data.widgetConfig?.hide) {
          iframe.style.display = 'block';
        }

        if (typeof window.BeChatReady === 'function') {
          window.BeChatReady(window.BeChat);
        }
        setIsBootstrapped();

        notifyOfNavigation();
      }
    };

    window.addEventListener('message', messageListener);
    destroyCallbacks.push(() => {
      window.removeEventListener('message', messageListener);
    });

    if (window.navigation) {
      const navigateListener = () => {
        setTimeout(() => {
          notifyOfNavigation();
        });
      };
      window.navigation.addEventListener('navigate', navigateListener);
      destroyCallbacks.push(() => {
        window.navigation.removeEventListener('navigate', navigateListener);
      });
    } else {
      const originalPushState = history.pushState;
      history.pushState = function (e) {
        originalPushState.apply(history, arguments);
        setTimeout(() => {
          notifyOfNavigation();
        });
      };
      destroyCallbacks.push(() => {
        history.pushState = originalPushState;
      });
    }

    // notify iframe if parent window is mobile or not
    const mqOnChange = () => {
      setSpacing();

      iframe.contentWindow.postMessage(
        {
          source: 'livechat-loader',
          type: 'isMobile',
          isMobile: mobileMatchMedia.matches,
        },
        '*',
      );
    };
    mobileMatchMedia.addEventListener('change', mqOnChange);
    destroyCallbacks.push(() => {
      mobileMatchMedia.removeEventListener('change', mqOnChange);
    });

    loadedAt = performance.now();
  };

  if (document.readyState !== 'complete') {
    document.onreadystatechange = () => {
      if (document.readyState === 'complete') {
        executeLoader();
      }
    };
  } else {
    executeLoader();
  }
})(document.currentScript.src);
